<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// ---------- UPDATE CUSTOMER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = intval($_POST['id']);
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $notes     = trim($_POST['notes']);

    $stmt = $db->prepare("UPDATE customers SET full_name=?, email=?, phone=?, notes=? WHERE id=?");
    $stmt->execute([$full_name, $email, $phone, $notes, $id]);
    $msg = "Customer updated.";
}

// ---------- DELETE CUSTOMER (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = intval($_POST['customer_id']);

    // Delete related records to satisfy foreign keys
    $db->prepare("DELETE p FROM payments p INNER JOIN bookings b ON p.booking_id = b.id WHERE b.customer_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM bookings WHERE customer_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM queue WHERE customer_id = ?")->execute([$id]);
    // Loyalty transactions (if you have cascade on customer_id, this may not be needed, but safe)
    $db->prepare("DELETE FROM loyalty_transactions WHERE customer_id = ?")->execute([$id]);
    // Finally, delete the customer
    $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);

    $msg = "Customer and all related records deleted.";
}

// Fetch customers with loyalty points (loyalty_points column already exists in customers table)
$customers = $db->query("
    SELECT c.*, b.full_name AS preferred_barber
    FROM customers c
    LEFT JOIN barbers b ON c.preferred_barber_id = b.id
    ORDER BY c.full_name
")->fetchAll();

// Edit mode
$edit_customer = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_customer = $stmt->fetch();
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Customer Management</h2>
        <?php if ($msg): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Edit Customer Form (only visible when editing) -->
        <?php if ($edit_customer): ?>
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Edit Customer</h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" value="<?= $edit_customer['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($edit_customer['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_customer['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_customer['phone']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control"><?= htmlspecialchars($edit_customer['notes']) ?></textarea>
                    </div>
                </div>
                <button type="submit" name="update_customer" class="btn btn-success mt-3">Update Customer</button>
                <a href="customers.php" class="btn btn-secondary mt-3 ms-2">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

        <!-- Customer List -->
        <div class="card bg-dark text-white p-3">
            <h5>Customer List</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Visits</th>
                        <th>Total Spent</th>
                        <th>Loyalty Points</th>
                        <th>Preferred Barber</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['full_name']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td><?= htmlspecialchars($c['phone']) ?></td>
                        <td><?= $c['visit_count'] ?></td>
                        <td><?= formatCurrency($c['total_spent']) ?></td>
                        <td>
                            <span class="badge bg-<?= $c['loyalty_points'] > 0 ? 'success' : 'secondary' ?>">
                                <?= $c['loyalty_points'] ?>
                            </span>
                        </td>
                        <td><?= $c['preferred_barber'] ?? '-' ?></td>
                        <td>
                            <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <!-- Delete form (POST) with browser confirm -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this customer and all their bookings?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                <button type="submit" name="delete_customer" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>