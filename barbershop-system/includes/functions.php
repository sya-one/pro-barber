<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/whatsapp.php';

function getDb() {
    static $db = null;
    if ($db === null) {
        $db = (new Database())->getConnection();
    }
    return $db;
}

/**
 * Fetches settings once per request and caches them in memory.
 */
function getSettings() {
    static $settings = null;
    if ($settings === null) {
        $settings = getDb()->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $settings;
}

function logActivity($user_id, $action, $details = null) {
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
}

function formatCurrency($amount) {
    return 'R ' . number_format($amount, 2);
}

function generateBookingCode() {
    return 'pro-bk-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Generates a unique invoice number in the format INV-YYYY-####.
 * The sequence resets annually.
 */
function generateInvoiceNumber($db) {
    $year = date('Y');
    $stmt = $db->prepare("SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["INV-$year-%"]);
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = intval(substr($last, -4)) + 1;
    } else {
        $num = 1;
    }
    return 'INV-' . $year . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function isAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isBarber() {
    return ($_SESSION['role'] ?? '') === 'barber';
}

function isReceptionist() {
    return ($_SESSION['role'] ?? '') === 'receptionist';
}

function notifyRole($db, $role, $message) {
    $users = $db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1");
    $users->execute([$role]);
    while ($u = $users->fetch(PDO::FETCH_COLUMN)) {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$u, $message]);
    }
}

/**
 * Add (or deduct) loyalty points for a customer and log the transaction.
 */
function adjustLoyaltyPoints($db, $customer_id, $points, $type, $booking_id = null, $note = '') {
    $stmt = $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?");
    $stmt->execute([$points, $customer_id]);

    $stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, booking_id, points, type, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$customer_id, $booking_id, $points, $type, $note]);
}

/**
 * Get the loyalty conversion rate from settings (points per rand).
 */
function getLoyaltyRate($db) {
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'loyalty_rate'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    return $val ? floatval($val) : 0.1;  // 0.1 points per rand = 1 point per R10
}

/**
 * Strip all tags except a safe whitelist for descriptions.
 */
function sanitizeHtml($html) {
    $allowed = '<b><strong><i><em><u><p><ul><ol><li><br><a>';
    return strip_tags($html, $allowed);
}

// For invoice generation (both PDF and email), we build the HTML using a template system. This function fetches all necessary data and replaces placeholders in the template.
function getInvoiceHTML($db, $sale_id) {
    // Fetch sale + customer
    $stmt = $db->prepare("SELECT s.*, c.full_name AS customer_name, c.email, c.phone
                          FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) return '';

    // Fetch barber name
    $barber_name = '';
    $stmt = $db->prepare("
        SELECT br.full_name FROM sale_items si
        JOIN bookings b ON si.booking_id = b.id
        JOIN barbers br ON b.barber_id = br.id
        WHERE si.sale_id = ? AND si.item_type = 'service'
        ORDER BY b.id DESC LIMIT 1
    ");
    $stmt->execute([$sale_id]);
    $barber_name = $stmt->fetchColumn() ?: 'Counter';

    // Fetch loyalty points earned in this sale
    $points_earned = 0;
    $stmt = $db->prepare("SELECT points FROM loyalty_transactions WHERE booking_id IS NULL AND customer_id = ? AND type = 'earned' AND note LIKE ? ORDER BY id DESC LIMIT 1");
    // note: POS sale points note is 'Points earned from POS sale'
    $stmt->execute([$sale['customer_id'], '%POS sale%']);
    $points_earned = (int) $stmt->fetchColumn();

    // Fetch sale items
    $stmt = $db->prepare("SELECT si.*, p.product_code FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Shop settings
    $settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $shop_name    = $settings['shop_name'] ?? 'The Professional Barbershop';
    $shop_address = $settings['address'] ?? '16 Blaine St, KwaDukuza Central';
    $shop_phone   = $settings['phone'] ?? '';

    // Logo – use an absolute URL for PDF rendering (adjust to your domain)
    $logo_url = 'https://pro-barber.horsementech.com/assets/images/default-avatar.png';
    if (!$logo_url) {
        $logo_url = 'file://' . realpath(__DIR__ . '/../assets/images/default-avatar.png');
    }

    // Template (your custom design – already includes {{loyalty_points}})
    $template = $settings['invoice_template'] ?? '
    <div style="font-family: Arial, sans-serif; max-width: 650px; margin: auto; padding: 20px; border: 1px solid #ddd; background: #fff;">
        <div style="text-align:center; border-bottom: 2px solid #111; padding-bottom: 12px;">
            <img src="' . $logo_url . '" alt="Shop Logo" style="max-height:80px; margin-bottom:10px;">
            <h2 style="margin:0; font-size:24px; letter-spacing:1px;">{{shop_name}}</h2>
            <p style="margin:5px 0; font-size:12px; color:#555;">{{shop_address}} | {{shop_phone}}</p>
            <p style="margin:5px 0; font-size:12px;"><strong>Invoice:</strong> {{invoice_number}}</p>
        </div>
        <div style="margin-top:15px; font-size:13px;">
            <p style="margin:3px 0;"><strong>Customer:</strong> {{customer_name}}</p>
            <p style="margin:3px 0;"><strong>Date:</strong> {{date}}</p>
            <p style="margin:3px 0;"><strong>Payment Method:</strong> {{payment_method}}</p>
            <p style="margin:3px 0;"><strong>Handled By:</strong> {{barber_name}}</p>
            <p style="margin:3px 0;"><strong>Loyalty Points Earned:</strong> {{loyalty_points}}</p>
        </div>
        <hr style="margin:15px 0; border:0; border-top:1px dashed #999;">
        <table style="width:100%; border-collapse: collapse; font-size:13px;">
            <thead>
                <tr style="background:#000000; color:#fff;">
                    <th style="text-align:left; padding:8px;">Item</th>
                    <th style="text-align:center; padding:8px;">Qty</th>
                    <th style="text-align:right; padding:8px;">Price</th>
                    <th style="text-align:right; padding:8px;">Total</th>
                </tr>
            </thead>
            <tbody>{{items_table}}</tbody>
        </table>
        <div style="margin-top:15px; text-align:right;">
            <p style="font-size:16px; margin:5px 0;"><strong>Total: {{total}}</strong></p>
        </div>
        <hr style="margin:15px 0; border:0; border-top:1px dashed #999;">
        <div style="text-align:center; font-size:12px; color:#555;">
            <p style="margin:5px 0;">Thank you for choosing us 💈</p>
            <p style="margin:5px 0;">Fresh cuts. Fresh confidence.</p>
            <p style="margin:5px 0; font-size:11px;">Powered by {{shop_name}} POS System</p>
        </div>
    </div>';

    // Build items HTML
    $items_html = '';
    foreach ($items as $item) {
        $items_html .= '<tr>';
        $items_html .= '<td style="padding:8px;">' . htmlspecialchars($item['item_name']) . '</td>';
        $items_html .= '<td style="text-align:center; padding:8px;">' . $item['quantity'] . '</td>';
        $items_html .= '<td style="text-align:right; padding:8px;">' . formatCurrency($item['unit_price']) . '</td>';
        $items_html .= '<td style="text-align:right; padding:8px;">' . formatCurrency($item['total_price']) . '</td>';
        $items_html .= '</tr>';
    }

    $placeholders = [
        '{{shop_name}}'      => htmlspecialchars($shop_name),
        '{{shop_address}}'   => htmlspecialchars($shop_address),
        '{{shop_phone}}'     => htmlspecialchars($shop_phone),
        '{{customer_name}}'  => $sale['customer_name'] ?: 'Walk‑in',
        '{{date}}'           => date('d M Y H:i', strtotime($sale['created_at'])),
        '{{payment_method}}' => ucfirst($sale['payment_method']),
        '{{items_table}}'    => $items_html,
        '{{total}}'          => formatCurrency($sale['total']),
        '{{invoice_number}}' => htmlspecialchars($sale['invoice_number'] ?? 'N/A'),
        '{{barber_name}}'    => htmlspecialchars($barber_name),
        '{{shop_logo}}'      => '<img src="' . $logo_url . '" style="max-height:80px;">',
        '{{loyalty_points}}' => $points_earned ?: '0',
    ];

    return str_replace(array_keys($placeholders), array_values($placeholders), $template);
}

/**
 * Send an email to all active admin users.
 *
 * @param PDO    $db      Database connection
 * @param string $subject Email subject
 * @param string $body    Email body (HTML allowed)
 */
function notifyAdminEmail($db, $subject, $body) {
    // Fetch all active admin email addresses
    $stmt = $db->prepare("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 AND email IS NOT NULL AND email != ''");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($admins as $to) {
        // Use the already working sendEmail() from includes/mailer.php
        sendEmail($to, $subject, $body, true);
    }

}

/**
 * Send a WhatsApp message to all admins who have a WhatsApp number configured.
 */
function notifyAdminWhatsApp($db, $message) {
    // Get admin WhatsApp number from settings
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_whatsapp'");
    $stmt->execute();
    $phone = $stmt->fetchColumn();
    if ($phone) {
        sendWhatsApp($phone, $message);
    }
}

?>