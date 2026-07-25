<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';
$error = '';

// Handle add supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $contact_person = trim($_POST['contact_person']);
        
        if (!$name) {
            $error = "Supplier name is required.";
        } else {
            $stmt = $db->prepare("INSERT INTO suppliers (name, email, phone, address, contact_person) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $phone, $address, $contact_person])) {
                $msg = "Supplier added successfully.";
            } else {
                $error = "Failed to add supplier.";
            }
        }
    }
}

// Handle delete supplier
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
    $msg = "Supplier deleted.";
}

// Get suppliers
$suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get purchase orders
$purchaseOrders = $db->query("
    SELECT po.*, s.name as supplier_name, u.full_name as created_by_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    JOIN users u ON po.created_by = u.id
    ORDER BY po.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Suppliers & Purchase Orders</h2>
        <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <!-- Suppliers Card -->
        <div class="card bg-dark text-white p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Suppliers</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    <i class="fas fa-plus me-1"></i>Add Supplier
                </button>
            </div>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['contact_person'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Purchase Orders Card -->
        <div class="card bg-dark text-white p-3">
            <h5>Purchase Orders</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchaseOrders as $po): ?>
                    <tr>
                        <td><?= htmlspecialchars($po['po_number']) ?></td>
                        <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $po['status'] === 'received' ? 'success' : 
                                ($po['status'] === 'cancelled' ? 'danger' : 'warning') 
                            ?>"><?= ucfirst($po['status']) ?></span>
                        </td>
                        <td><?= formatCurrency($po['total_amount']) ?></td>
                        <td><?= date('d M Y', strtotime($po['created_at'])) ?></td>
                        <td><?= htmlspecialchars($po['created_by_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Supplier Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Contact Person</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_supplier" class="btn btn-success">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>