<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

$db = getDb();
$msg = '';

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_barber'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = $_POST['id'] ?? 0;
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $commission = floatval($_POST['commission_rate']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $photo = '';

    // Handle photo upload
    if (!empty($_FILES['photo']['name'])) {
        $upload = secureFileUpload('photo', getUploadPath('barber'), ['jpg', 'jpeg', 'png', 'gif', 'webp'], 10);
        if ($upload['success']) {
            $photo = $upload['filename'];
        } else {
            $msg = "Photo upload failed: " . $upload['error'];
        }
    }

    if ($id) {
        // Update
        if ($photo) {
            $stmt = $db->prepare("UPDATE barbers SET full_name=?, email=?, phone=?, commission_rate=?, is_active=?, photo=? WHERE id=?");
            $stmt->execute([$full_name, $email, $phone, $commission, $is_active, $photo, $id]);
        } else {
            $stmt = $db->prepare("UPDATE barbers SET full_name=?, email=?, phone=?, commission_rate=?, is_active=? WHERE id=?");
            $stmt->execute([$full_name, $email, $phone, $commission, $is_active, $id]);
        }
        $msg = "Barber updated successfully.";
    } else {
        // Insert – also create a user account for the barber
        $password = password_hash('barber123', PASSWORD_DEFAULT);
        $username = strtolower(explode(' ', $full_name)[0]) . rand(10,99);
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name, email, phone) VALUES (?,?, 'barber', ?,?,?)");
            $stmt->execute([$username, $password, $full_name, $email, $phone]);
            $user_id = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO barbers (user_id, full_name, email, phone, photo, commission_rate, is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $full_name, $email, $phone, $photo ?: 'default.jpg', $commission, $is_active]);
            $db->commit();
            $msg = "Barber added successfully. Username: $username";
        } catch (Exception $e) {
            $db->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $db->prepare("SELECT user_id FROM barbers WHERE id=?");
    $stmt->execute([$id]);
    $barber = $stmt->fetch();
    if ($barber) {
        $db->beginTransaction();
        $db->prepare("DELETE FROM barbers WHERE id=?")->execute([$id]);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$barber['user_id']]);
        $db->commit();
        $msg = "Barber deleted.";
    }
}

$barbers = $db->query("SELECT b.*, u.username FROM barbers b JOIN users u ON b.user_id = u.id ORDER BY b.full_name")->fetchAll();
$edit_barber = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM barbers WHERE id=?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_barber = $stmt->fetch();
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Manage Barbers</h2>
        <?php if($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

        <div class="card bg-dark text-white p-3 mb-4">
            <h5><?= $edit_barber ? 'Edit Barber' : 'Add New Barber' ?></h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" value="<?= $edit_barber['id'] ?? '' ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= $edit_barber['full_name'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= $edit_barber['email'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= $edit_barber['phone'] ?? '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Commission Rate (%)</label>
                        <input type="number" step="0.01" name="commission_rate" class="form-control" value="<?= $edit_barber['commission_rate'] ?? '30.00' ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Photo</label>
                        <input type="file" name="photo" class="form-control">
                        <?php if(!empty($edit_barber['photo']) && $edit_barber['photo'] != 'default.jpg'): ?>
                            <small>Current: <img src="../uploads/barbers/<?= $edit_barber['photo'] ?>" width="40"></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="active" <?= (!isset($edit_barber) || $edit_barber['is_active']) ? 'checked' : '' ?>>
                            <label for="active">Active</label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="save_barber" class="btn btn-success mt-3"><?= $edit_barber ? 'Update' : 'Add Barber' ?></button>
                <?php if($edit_barber): ?><a href="barbers.php" class="btn btn-secondary mt-3 ms-2">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="card bg-dark text-white p-3">
            <h5>Barber List</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr><th>Photo</th><th>Name</th><th>Email</th><th>Username</th><th>Commission</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach($barbers as $b): ?>
                    <tr>
                        <td><img src="../uploads/barbers/<?= htmlspecialchars($b['photo']) ?>" width="40" class="rounded-circle" onerror="this.onerror=null; this.src='../assets/images/default-avatar.png';"></td>
                        <td><?= htmlspecialchars($b['full_name']) ?></td>
                        <td><?= htmlspecialchars($b['email']) ?></td>
                        <td><?= htmlspecialchars($b['username']) ?></td>
                        <td><?= $b['commission_rate'] ?>%</td>
                        <td><span class="badge bg-<?= $b['is_active'] ? 'success' : 'secondary' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-danger delete-confirm"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>