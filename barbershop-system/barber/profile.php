<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isBarber()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

$db = getDb();
$user_id = $_SESSION['user_id'];
$msg = '';

// Check if barber record exists; if not, create one
$stmt = $db->prepare("SELECT b.*, u.email, u.phone, u.full_name AS user_fullname FROM barbers b JOIN users u ON b.user_id = u.id WHERE b.user_id = ?");
$stmt->execute([$user_id]);
$barber = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barber) {
    // Fetch user data to create barber record
    $user = $db->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $user = $user->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $stmt = $db->prepare("INSERT INTO barbers (user_id, full_name, email, phone, photo, commission_rate, is_active)
                              VALUES (?, ?, ?, ?, 'default.jpg', 30.00, 1)");
        $stmt->execute([$user_id, $user['full_name'], $user['email'], $user['phone']]);

        // Re-fetch the new record
        $stmt = $db->prepare("SELECT b.*, u.email, u.phone FROM barbers b JOIN users u ON b.user_id = u.id WHERE b.user_id = ?");
        $stmt->execute([$user_id]);
        $barber = $stmt->fetch(PDO::FETCH_ASSOC);
        $msg = "Your barber profile has been set up. Please review your details.";
    } else {
        die('User not found.');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);

    // Photo upload
    $photo = $barber['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $upload = secureFileUpload('photo', getUploadPath('barber'), ['jpg', 'jpeg', 'png', 'gif', 'webp'], 2);
        if ($upload['success']) {
            $photo = $upload['filename'];
        } else {
            $msg = "Photo upload failed: " . $upload['error'];
        }
    }

    if (empty($msg)) {
        // Update barbers table
        $stmt = $db->prepare("UPDATE barbers SET full_name = ?, photo = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $photo, $user_id]);
        // Update users table
        $stmt = $db->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$email, $phone, $user_id]);
        $msg = "Profile updated successfully.";
        // Refresh data
        $stmt = $db->prepare("SELECT b.*, u.email, u.phone FROM barbers b JOIN users u ON b.user_id = u.id WHERE b.user_id = ?");
        $stmt->execute([$user_id]);
        $barber = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">My Profile</h2>
        <?php if ($msg): ?>
            <div class="alert alert-<?= strpos($msg, 'Invalid') !== false ? 'danger' : 'success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="card bg-dark text-white p-4 col-md-8 col-lg-6">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="text-center mb-4">
                   <img src="../uploads/barbers/<?= htmlspecialchars($barber['photo']) ?>" 
     alt="Profile Photo" class="rounded-circle" 
     style="width:120px; height:120px; object-fit:cover; border: 3px solid var(--green);"
     onerror="this.onerror=null; this.src='../assets/images/default-avatar.png';">
                    <p class="mt-2"><?= htmlspecialchars($barber['full_name']) ?></p>
                </div>

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?= htmlspecialchars($barber['full_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($barber['email']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($barber['phone']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Change Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <small class="text-muted">JPG, PNG, or GIF (max 2MB)</small>
                </div>
                <button type="submit" name="update_profile" class="btn btn-success">Save Changes</button>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>