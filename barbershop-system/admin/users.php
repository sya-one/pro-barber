<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// ---------- ADD USER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];
    $fullname = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);

    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        $msg = "Username already exists.";
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name, email, phone, is_active) VALUES (?,?,?,?,?,?,1)");
        $stmt->execute([$username, $password, $role, $fullname, $email, $phone]);

        // If we added a barber, also create the barber record
        if ($role === 'barber') {
            $user_id = $db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO barbers (user_id, full_name, email, phone, photo, commission_rate, is_active) VALUES (?,?,?,?, 'default.jpg', 30.00, 1)");
            $stmt->execute([$user_id, $fullname, $email, $phone]);
        }
        $msg = "User " . htmlspecialchars($username) . " added.";
    }
}

// ---------- EDIT USER ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id       = intval($_POST['id']);
    $fullname = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $role     = $_POST['role'];
    $active   = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $db->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=?, is_active=? WHERE id=?");
    $stmt->execute([$fullname, $email, $phone, $role, $active, $id]);
    $msg = "User updated.";
}

// ---------- RESET PASSWORD ----------
if (isset($_POST['reset_password'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id       = intval($_POST['id']);
    $newpass  = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$newpass, $id]);
    $msg = "Password reset.";
}

// ---------- DELETE USER (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = intval($_POST['user_id']);

    if ($id == $_SESSION['user_id']) {
        $msg = "You cannot delete your own account.";
    } else {
        // Get the user's role and barber id (if barber)
        $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['role'] == 'barber') {
            $barber = $db->prepare("SELECT id FROM barbers WHERE user_id = ?");
            $barber->execute([$id]);
            $barber_id = $barber->fetchColumn();

            if ($barber_id) {
                // Delete associated payments first
                $db->prepare("DELETE p FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE b.barber_id = ?")->execute([$barber_id]);
                // Delete bookings for this barber
                $db->prepare("DELETE FROM bookings WHERE barber_id = ?")->execute([$barber_id]);
                // Delete queue entries for this barber
                $db->prepare("DELETE FROM queue WHERE barber_id = ?")->execute([$barber_id]);
                // Delete barber record
                $db->prepare("DELETE FROM barbers WHERE id = ?")->execute([$barber_id]);
            }
        }

        // Delete notifications / activity logs for this user (optional but clean)
        $db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$id]);

        // Finally, delete the user
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $msg = "User and all related records deleted.";
    }
}


// Fetch all users
$users = $db->query("SELECT id, username, role, full_name, email, phone, is_active, last_login FROM users ORDER BY role, username")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">User Management</h2>
        <?php if ($msg): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Add User Card -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Add New User</h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Role</label>
                        <select name="role" class="form-select" required>
                            <option value="receptionist">Receptionist</option>
                            <option value="barber">Barber</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="add_user" class="btn btn-success w-100">Add User</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- User List -->
        <div class="card bg-dark text-white p-3">
            <h5>All Users</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Active</th>
                        <th>Last Login</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge bg-<?= $u['role']=='admin'?'danger':($u['role']=='barber'?'warning':'info') ?>"><?= $u['role'] ?></span></td>
                        <td>
                            <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= $u['last_login'] ?? 'Never' ?></td>
                        <td>
                            <!-- Edit button modal trigger -->
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>

                            <!-- Delete form (POST) -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>

                    <!-- Edit Modal for this user -->
                    <div class="modal fade" id="editModal<?= $u['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit <?= htmlspecialchars($u['username']) ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label>Full Name</label>
                                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($u['full_name']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>Phone</label>
                                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label>Role</label>
                                            <select name="role" class="form-select">
                                                <option value="admin" <?= $u['role']=='admin'?'selected':'' ?>>Admin</option>
                                                <option value="barber" <?= $u['role']=='barber'?'selected':'' ?>>Barber</option>
                                                <option value="receptionist" <?= $u['role']=='receptionist'?'selected':'' ?>>Receptionist</option>
                                            </select>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input type="checkbox" name="is_active" class="form-check-input" id="active<?= $u['id'] ?>" <?= $u['is_active']?'checked':'' ?>>
                                            <label for="active<?= $u['id'] ?>">Active</label>
                                        </div>
                                        <hr>
                                        <h6>Reset Password</h6>
                                        <div class="mb-3">
                                            <input type="password" name="new_password" class="form-control" placeholder="New password (leave blank to keep)">
                                        </div>
                                        <button type="submit" name="reset_password" class="btn btn-secondary btn-sm">Reset Password</button>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="edit_user" class="btn btn-success">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>