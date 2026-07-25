<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

$db = getDb();
$msg = '';

// ---------- ADD / EDIT SERVICE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $id          = intval($_POST['id'] ?? 0);
    $name        = trim($_POST['name']);
    $description = sanitizeHtml(trim($_POST['description'] ?? ''));
    $price       = floatval($_POST['price']);
    $duration    = intval($_POST['duration']);
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $image       = '';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $upload = secureFileUpload('image', getUploadPath('service'), ['jpg', 'jpeg', 'png', 'gif', 'webp'], 10);
        if (!$upload['success']) {
            $msg = "Upload failed: " . $upload['error'];
        } else {
            $image = $upload['filename'];
        }
    }

    if (empty($msg)) {
        if ($id) {
            // Update existing service
            if ($image) {
                $stmt = $db->prepare("UPDATE services SET name=?, description=?, price=?, duration=?, is_active=?, image=? WHERE id=?");
                $stmt->execute([$name, $description, $price, $duration, $is_active, $image, $id]);
            } else {
                $stmt = $db->prepare("UPDATE services SET name=?, description=?, price=?, duration=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $description, $price, $duration, $is_active, $id]);
            }
            $msg = "Service updated.";
        } else {
            // Insert new service
            $stmt = $db->prepare("INSERT INTO services (name, description, price, duration, image, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $description, $price, $duration, $image ?: 'default-service.jpg', $is_active]);
            $msg = "Service added.";
        }
    }
}

// ---------- DELETE SERVICE ----------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->prepare("DELETE FROM services WHERE id=?")->execute([$id]);
    $msg = "Service deleted.";
}

// Fetch all services
$services = $db->query("SELECT * FROM services ORDER BY name")->fetchAll();

// For edit mode (if we want to pre-fill the form via GET parameter)
$edit_service = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM services WHERE id=?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_service = $stmt->fetch();
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Services</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Add / Edit Form -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5><?= $edit_service ? 'Edit Service' : 'Add New Service' ?></h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" value="<?= $edit_service['id'] ?? '' ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Service Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_service['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>Price (R)</label>
                        <input type="number" step="0.01" name="price" class="form-control" value="<?= $edit_service['price'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration" class="form-control" value="<?= $edit_service['duration'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit_service['description'] ?? '') ?></textarea>
                        <small class="text-muted">You can use &lt;b&gt;, &lt;ul&gt;, &lt;li&gt; etc. for formatting.</small>
                    </div>
                    <div class="col-md-6">
                        <label>Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($edit_service['image'])): ?>
                            <small>Current:</small><br>
                            <img src="../uploads/services/<?= htmlspecialchars($edit_service['image']) ?>" width="60" onerror="this.onerror=null; this.src='../assets/images/default-avatar.png';">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="active" <?= (!isset($edit_service) || $edit_service['is_active']) ? 'checked' : '' ?>>
                            <label for="active">Active</label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="save_service" class="btn btn-success mt-3"><?= $edit_service ? 'Update Service' : 'Add Service' ?></button>
                <?php if ($edit_service): ?><a href="services.php" class="btn btn-secondary mt-3 ms-2">Cancel</a><?php endif; ?>
            </form>
        </div>

        <!-- Services Table -->
        <div class="card bg-dark text-white p-3">
            <h5>Service List</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Image</th><th>Name</th><th>Price</th><th>Duration</th><th>Active</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                    <tr>
                        <td>
                            <img src="../uploads/services/<?= htmlspecialchars($s['image']) ?>" width="50" 
                                 onerror="this.onerror=null; this.src='../assets/images/default-avatar.png';">
                        </td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= formatCurrency($s['price']) ?></td>
                        <td><?= $s['duration'] ?> mins</td>
                        <td><span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this service?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>