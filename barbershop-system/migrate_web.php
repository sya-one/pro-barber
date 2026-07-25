<?php
/**
 * Web Migration Runner for cPanel
 * Access via: https://yourdomain.com/migrate_web.php
 * SECURITY: Requires admin authentication
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

// Require admin authentication
if (!isAdmin()) {
    http_response_code(403);
    die('Forbidden - Admin access required');
}

$db = (new Database())->getConnection();

// Create migrations tracking table
$db->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) UNIQUE NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

function isMigrationExecuted($db, $migration_name) {
    $stmt = $db->prepare("SELECT id FROM migrations WHERE migration_name = ? LIMIT 1");
    $stmt->execute([$migration_name]);
    return $stmt->fetch() !== false;
}

function markMigrationExecuted($db, $migration_name) {
    $stmt = $db->prepare("INSERT IGNORE INTO migrations (migration_name) VALUES (?)");
    $stmt->execute([$migration_name]);
}

$results = [];
$executed = 0;
$skipped = 0;
$errors = 0;

$migrations = [
    'add_barcode_to_products' => function($db) {
        $db->exec("ALTER TABLE products ADD COLUMN barcode VARCHAR(50) DEFAULT NULL AFTER product_code");
    },
    'add_cost_price_to_products' => function($db) {
        $db->exec("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0.00 AFTER price");
    },
    'add_commission_type_to_barbers' => function($db) {
        $db->exec("ALTER TABLE barbers ADD COLUMN commission_type ENUM('percentage','fixed','tiered') DEFAULT 'percentage' AFTER commission_rate");
    },
    'add_payment_status_to_sales' => function($db) {
        $db->exec("ALTER TABLE sales ADD COLUMN payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending' AFTER payment_method");
        $db->exec("ALTER TABLE sales ADD COLUMN paystack_reference VARCHAR(100) DEFAULT NULL AFTER payment_status");
    },
    'add_status_to_payments' => function($db) {
        $db->exec("ALTER TABLE payments ADD COLUMN status ENUM('pending','paid','failed','refunded') DEFAULT 'paid' AFTER transaction_code");
    },
    'add_branch_id_to_bookings' => function($db) {
        $db->exec("ALTER TABLE bookings ADD COLUMN branch_id INT(11) DEFAULT 1 AFTER booking_type");
    },
    'add_branch_id_to_sales' => function($db) {
        $db->exec("ALTER TABLE sales ADD COLUMN branch_id INT(11) DEFAULT 1 AFTER invoice_number");
    },
    'add_paid_by_to_commissions' => function($db) {
        $db->exec("ALTER TABLE commissions ADD COLUMN paid_by INT(11) DEFAULT NULL AFTER paid_at");
        $db->exec("ALTER TABLE commissions ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
    },
    'add_entity_to_activity_logs' => function($db) {
        $db->exec("ALTER TABLE activity_logs ADD COLUMN entity VARCHAR(100) DEFAULT NULL AFTER action");
        $db->exec("ALTER TABLE activity_logs ADD COLUMN entity_id INT(11) DEFAULT NULL AFTER entity");
        $db->exec("ALTER TABLE activity_logs ADD COLUMN old_value TEXT DEFAULT NULL AFTER entity_id");
        $db->exec("ALTER TABLE activity_logs ADD COLUMN new_value TEXT DEFAULT NULL AFTER old_value");
        $db->exec("ALTER TABLE activity_logs ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER new_value");
    },
    'create_branches_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS branches (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            address TEXT,
            city VARCHAR(100),
            province VARCHAR(100),
            postal_code VARCHAR(20),
            country VARCHAR(100) DEFAULT 'South Africa',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        $db->exec("INSERT IGNORE INTO branches (id, name, address, city, province, postal_code, is_active) 
                   VALUES (1, 'The Professional Barbershop', '16 Blaine St, KwaDukuza Central', 'KwaDukuza', 'KwaDukuza', '4449', 1)");
    },
    'create_suppliers_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS suppliers (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            address TEXT,
            contact_person VARCHAR(100) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_expense_categories_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS expense_categories (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_expenses_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS expenses (
            id INT(11) NOT NULL AUTO_INCREMENT,
            category VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            date DATE NOT NULL,
            description TEXT,
            receipt_image VARCHAR(255) DEFAULT NULL,
            created_by INT(11) NOT NULL,
            branch_id INT(11) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            KEY branch_id (branch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_purchase_orders_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT(11) NOT NULL AUTO_INCREMENT,
            supplier_id INT(11) NOT NULL,
            po_number VARCHAR(50) NOT NULL,
            status ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
            total_amount DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT,
            created_by INT(11) NOT NULL,
            branch_id INT(11) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            updated_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY po_number (po_number),
            KEY supplier_id (supplier_id),
            KEY branch_id (branch_id),
            CONSTRAINT purchase_orders_ibfk_1 FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_po_items_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS po_items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            purchase_order_id INT(11) NOT NULL,
            product_id INT(11) NOT NULL,
            quantity INT(11) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            received_quantity INT(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY purchase_order_id (purchase_order_id),
            KEY product_id (product_id),
            CONSTRAINT po_items_ibfk_1 FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
            CONSTRAINT po_items_ibfk_2 FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_stock_adjustments_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS stock_adjustments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            product_id INT(11) NOT NULL,
            type ENUM('increase','decrease') NOT NULL,
            quantity INT(11) NOT NULL,
            reason VARCHAR(255),
            created_by INT(11) NOT NULL,
            branch_id INT(11) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY branch_id (branch_id),
            CONSTRAINT stock_adjustments_ibfk_1 FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_cash_ups_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS cash_ups (
            id INT(11) NOT NULL AUTO_INCREMENT,
            cashier_id INT(11) NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            cash_sales DECIMAL(10,2) DEFAULT 0.00,
            card_sales DECIMAL(10,2) DEFAULT 0.00,
            eft_sales DECIMAL(10,2) DEFAULT 0.00,
            paystack_sales DECIMAL(10,2) DEFAULT 0.00,
            yaco_sales DECIMAL(10,2) DEFAULT 0.00,
            online_payments DECIMAL(10,2) DEFAULT 0.00,
            refunds DECIMAL(10,2) DEFAULT 0.00,
            total_expected DECIMAL(10,2) DEFAULT 0.00,
            actual_counted DECIMAL(10,2) DEFAULT 0.00,
            variance DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT,
            status ENUM('open','submitted','approved','closed') NOT NULL DEFAULT 'open',
            branch_id INT(11) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            updated_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY cashier_id (cashier_id),
            KEY branch_id (branch_id),
            CONSTRAINT cash_ups_ibfk_1 FOREIGN KEY (cashier_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_refunds_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS refunds (
            id INT(11) NOT NULL AUTO_INCREMENT,
            sale_id INT(11) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            reason TEXT,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            requested_by INT(11) NOT NULL,
            approved_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            updated_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY sale_id (sale_id),
            KEY requested_by (requested_by),
            KEY approved_by (approved_by),
            CONSTRAINT refunds_ibfk_1 FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
            CONSTRAINT refunds_ibfk_2 FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT refunds_ibfk_3 FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'create_commissions_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS commissions (
            id INT(11) NOT NULL AUTO_INCREMENT,
            barber_id INT(11) NOT NULL,
            sale_id INT(11) NOT NULL,
            booking_id INT(11) DEFAULT NULL,
            amount DECIMAL(10,2) NOT NULL,
            rate_percent DECIMAL(5,2) NOT NULL,
            status ENUM('earned','paid') NOT NULL DEFAULT 'earned',
            paid_at TIMESTAMP NULL DEFAULT NULL,
            paid_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            updated_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY barber_id (barber_id),
            KEY sale_id (sale_id),
            CONSTRAINT commissions_ibfk_1 FOREIGN KEY (barber_id) REFERENCES barbers (id) ON DELETE CASCADE,
            CONSTRAINT commissions_ibfk_2 FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    'add_paystack_settings' => function($db) {
        $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
            ('paystack_public_key', ''),
            ('paystack_secret_key', ''),
            ('paystack_payment_url', 'https://api.paystack.co'),
            ('paystack_use_test_mode', '1')");
    },
    'add_loyalty_settings' => function($db) {
        $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
            ('loyalty_rate', '0.1'),
            ('loyalty_tier_bronze', '0-999'),
            ('loyalty_tier_silver', '1000-2499'),
            ('loyalty_tier_gold', '2500-4999'),
            ('loyalty_tier_vip', '5000-999999')");
    },
    'update_gallery_table_schema' => function($db) {
        // Add missing columns for new schema while preserving existing data
        $stmt = $db->prepare("SHOW COLUMNS FROM gallery LIKE 'status'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE gallery ADD COLUMN status ENUM('active','inactive') DEFAULT 'active' AFTER category");
        }
        $stmt = $db->prepare("SHOW COLUMNS FROM gallery LIKE 'uploaded_by'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE gallery ADD COLUMN uploaded_by INT(11) DEFAULT NULL AFTER sort_order");
        }
        $stmt = $db->prepare("SHOW COLUMNS FROM gallery LIKE 'title'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE gallery ADD COLUMN title VARCHAR(150) AFTER id");
        } else {
            $db->exec("UPDATE gallery SET title = caption WHERE title IS NULL OR title = ''");
        }
        $db->exec("UPDATE gallery SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END WHERE status IS NULL OR status NOT IN ('active', 'inactive')");
    },
    'create_gallery_table' => function($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT,
            image_path VARCHAR(255) NOT NULL,
            category VARCHAR(50),
            status ENUM('active','inactive') DEFAULT 'active',
            sort_order INT DEFAULT 0,
            uploaded_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status (status),
            KEY sort_order (sort_order),
            CONSTRAINT gallery_ibfk_1 FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
];

// Run migrations
foreach ($migrations as $name => $callback) {
    // Check if already executed via tracking table
    if (isMigrationExecuted($db, $name)) {
        $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Already executed'];
        $skipped++;
        continue;
    }
    
    try {
        $callback($db);
        markMigrationExecuted($db, $name);
        $results[] = ['name' => $name, 'status' => 'executed'];
        $executed++;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false || 
            strpos($msg, 'already exists') !== false ||
            strpos($msg, 'Unknown column') !== false) {
            markMigrationExecuted($db, $name);
            $results[] = ['name' => $name, 'status' => 'skipped', 'message' => 'Already exists'];
            $skipped++;
        } else {
            $results[] = ['name' => $name, 'status' => 'error', 'message' => $msg];
            $errors++;
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Database Migration</h2>
        
        <div class="card bg-dark text-white p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Migration Results</h5>
                <button class="btn btn-success" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Refresh
                </button>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="card bg-success text-white text-center p-3">
                        <h5><?= $executed ?></h5>
                        <small>Executed</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-warning text-white text-center p-3">
                        <h5><?= $skipped ?></h5>
                        <small>Skipped</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-danger text-white text-center p-3">
                        <h5><?= $errors ?></h5>
                        <small>Errors</small>
                    </div>
                </div>
            </div>

            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Module</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr>
                        <td>
                            <span class="badge bg-<?= 
                                $r['status'] == 'executed' ? 'success' : 
                                ($r['status'] == 'skipped' ? 'warning' : 'danger') 
                            ?>"><?= ucfirst($r['status']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['message'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info">
            <strong>Note:</strong> Run this script after deploying new code to apply database changes.
            <br>
            For production, run via CLI: <code>php migrate.php</code>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>