<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();
$msg = '';

// Check if gallery table exists with new schema
$has_new_schema = false;
try {
    $db->query("SELECT title FROM gallery LIMIT 1");
    $has_new_schema = true;
} catch (Exception $e) {
    // No gallery table - will be created by migration
}

// Create gallery directory with proper permissions
$target_dir = __DIR__ . '/../assets/gallery/';
if (!is_dir($target_dir)) {
    if (!@mkdir($target_dir, 0755, true)) {
        $msg = 'Failed to create gallery directory. Check permissions.';
        $target_dir = null;
    }
}

// Check if directory is writable
if ($target_dir && !is_writable($target_dir)) {
    $msg = 'Gallery directory is not writable. Check permissions.';
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_gallery'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $msg = 'Invalid CSRF token';
    } elseif (!empty($_FILES['gallery_image']['name'])) {
        if (!$target_dir) {
            $msg = 'Gallery directory not available.';
        } else {
            $ext = strtolower(pathinfo($_FILES['gallery_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            // Check file extension
            if (!in_array($ext, $allowed)) {
                $msg = "Invalid file type: '$ext'. Only JPG, PNG, GIF, WEBP allowed.";
            } 
            // Check file size
            elseif ($_FILES['gallery_image']['size'] > 10 * 1024 * 1024) {
                $msg = 'File too large. Maximum 10MB. Current size: ' . round($_FILES['gallery_image']['size'] / 1024 / 1024, 2) . 'MB';
            }
            // Validate actual image
            elseif (!@getimagesize($_FILES['gallery_image']['tmp_name'])) {
                $msg = 'Invalid image file. The file is not a valid image.';
            }
            // Check for upload errors
            elseif ($_FILES['gallery_image']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
                    UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                $error_code = $_FILES['gallery_image']['error'];
                $msg = 'Upload error: ' . ($upload_errors[$error_code] ?? "Unknown error code: $error_code");
            }
            else {
                $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $full_path = $target_dir . $filename;
                
                // Attempt file move with error reporting
                if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $full_path)) {
                    // Verify file was saved
                    if (file_exists($full_path)) {
                        $stmt = $db->prepare("INSERT INTO gallery (title, description, image_path, category, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_POST['caption'] ?? '',
                            $_POST['description'] ?? null,
                            $filename,
                            $_POST['category'] ?? 'general',
                            isset($_POST['is_active']) ? 'active' : 'inactive',
                            $_SESSION['user_id'] ?? null
                        ]);
                        $msg = 'Image uploaded successfully: ' . $filename;
                    } else {
                        $msg = 'File move reported success but file not found at destination.';
                    }
                } else {
                    // Detailed error for move failure
                    $msg = 'Upload failed. Check directory permissions. Target: ' . $full_path . 
                           ' | Upload error: ' . ($_FILES['gallery_image']['error'] ?? 'none');
                }
            }
        }
    }
}

// Delete image
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $db->prepare("SELECT image_path FROM gallery WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetch();
    if ($img) {
        $file_path = __DIR__ . '/../assets/gallery/' . $img['image_path'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        $db->prepare("DELETE FROM gallery WHERE id = ?")->execute([$id]);
        $msg = 'Image deleted.';
    }
}

// Toggle active status - update both status and is_active for compatibility
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $db->prepare("UPDATE gallery SET status = CASE WHEN COALESCE(status, 'active') = 'active' THEN 'inactive' ELSE 'active' END, is_active = CASE WHEN COALESCE(status, 'active') = 'active' THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
    $msg = 'Status updated.';
}

// Update sort order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    foreach ($_POST['sort_order'] ?? [] as $id => $order) {
        $db->prepare("UPDATE gallery SET sort_order = ? WHERE id = ?")->execute([intval($order), intval($id)]);
    }
    $msg = 'Order updated.';
}

// Fetch all gallery images
$images = $db->query("SELECT * FROM gallery ORDER BY sort_order ASC, created_at DESC")->fetchAll();

// Categories
$categories = ['haircuts', 'beard', 'barbers', 'shop', 'lifestyle', 'products'];
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4"><i class="fas fa-images me-2"></i>Gallery Management</h2>
        
        <?php if ($msg): ?>
        <div class="alert <?= strpos($msg, 'successfully') !== false ? 'alert-success' : 'alert-danger' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        
        <!-- Upload Form -->
        <div class="card bg-dark text-white mb-4">
            <div class="card-header">
                <h5 class="mb-0">Upload New Image</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label">Image *</label>
                        <input type="file" name="gallery_image" class="form-control" accept="image/*" required>
                        <small class="text-muted">Max 10MB. JPG, PNG, GIF, WEBP only.</small>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Caption</label>
                        <input type="text" name="caption" class="form-control" placeholder="Image description">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" checked>
                            <label for="activeCheck">Active</label>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="upload_gallery" class="btn btn-success"><i class="fas fa-upload me-2"></i>Upload Image</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Gallery Grid -->
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            
            <div class="card bg-dark text-white">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Gallery Images</h5>
                    <button type="submit" name="update_order" class="btn btn-sm btn-outline-light">Update Order</button>
                </div>
                <div class="card-body">
                    <?php if (empty($images)): ?>
                        <p class="text-center text-muted">No images in gallery yet.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($images as $img): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="card bg-dark position-relative">
                                    <img src="/barbershop-system/assets/gallery/<?= htmlspecialchars($img['image_path']) ?>" 
                                         class="card-img-top" style="height: 150px; object-fit: cover;" 
                                         alt="Gallery image"
                                         onerror="this.src='/barbershop-system/assets/images/default-avatar.png'">
                                    <div class="card-body p-2">
                                        <small class="text-white d-block mb-1"><?= htmlspecialchars($img['title'] ?? $img['caption'] ?? '') ?></small>
                                        <small class="text-muted"><?= $img['category'] ?? 'general' ?></small>
                                        <input type="number" name="sort_order[<?= $img['id'] ?>]" 
                                               class="form-control form-control-sm mt-1" value="<?= $img['sort_order'] ?? 0 ?>" min="0">
                                    </div>
                                    <div class="card-footer p-2 d-flex justify-content-between">
                                        <a href="?toggle=<?= $img['id'] ?>" class="btn btn-sm <?= ($img['status'] ?? $img['is_active'] ?? 1) == 'active' || ($img['is_active'] ?? 1) ? 'btn-success' : 'btn-secondary' ?>">
                                            <?= ($img['status'] ?? $img['is_active'] ?? 1) == 'active' || ($img['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                                        </a>
                                        <a href="?delete=<?= $img['id'] ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Delete this image?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include '../includes/footer.php'; ?>