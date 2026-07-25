<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

$db = getDb();
$msg = '';

// ---------- ADD / EDIT PRODUCT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $id          = intval($_POST['id'] ?? 0);
    $name        = trim($_POST['name']);
    $description = sanitizeHtml(trim($_POST['description'] ?? ''));
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock_quantity']);
    $size        = trim($_POST['product_size'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $image       = '';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $upload = secureFileUpload('image', getUploadPath('product'), ['jpg', 'jpeg', 'png', 'gif', 'webp'], 10);
        if (!$upload['success']) {
            $msg = "Upload failed: " . $upload['error'];
        } else {
            $image = $upload['filename'];
        }
    }

    if (empty($msg)) {
        if ($id) {
            // Update product
            if ($image) {
                $stmt = $db->prepare("UPDATE products SET name=?, description=?, price=?, stock_quantity=?, product_size=?, is_active=?, image=? WHERE id=?");
                $stmt->execute([$name, $description, $price, $stock, $size, $is_active, $image, $id]);
            } else {
                $stmt = $db->prepare("UPDATE products SET name=?, description=?, price=?, stock_quantity=?, product_size=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $description, $price, $stock, $size, $is_active, $id]);
            }
            $msg = "Product updated.";
        } else {
            // Insert product – generate code
            $max = $db->query("SELECT MAX(id) FROM products")->fetchColumn();
            $next = $max + 1;
            $product_code = 'PROD-' . str_pad($next, 5, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("INSERT INTO products (product_code, name, description, price, stock_quantity, product_size, image, is_active) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$product_code, $name, $description, $price, $stock, $size, $image ?: 'default-product.png', $is_active]);
            $msg = "Product $product_code added.";
        }
    }
}

        // Check low stock after deduction
$stmt = $db->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$prod = $stmt->fetch();
if ($prod && $prod['stock_quantity'] <= 5) {
    $subject = "Low Stock Alert: " . $prod['name'];
    $body = "<p><strong>" . htmlspecialchars($prod['name']) . "</strong> is running low.</p>";
    $body .= "<p>Current stock: <strong>" . $prod['stock_quantity'] . "</strong> units.</p>";
    $body .= "<p><a href='{$base_url}admin/products.php'>Manage Products</a></p>";
    notifyAdminEmail($db, $subject, $body);
}

// ---------- DELETE PRODUCT ----------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    $msg = "Product deleted.";
}

// Fetch all products
$products = $db->query("SELECT * FROM products ORDER BY name")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Product & Stock Management</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Add Product Button -->
        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-plus me-2"></i>New Product
        </button>

        <!-- Products Table -->
        <div class="card bg-dark text-white p-3">
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Size</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" width="40"
                                 onerror="this.onerror=null; this.src='../assets/images/default-product.png';">
                        </td>
                        <td><?= $p['product_code'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= formatCurrency($p['price']) ?></td>
                        <td>
                            <?php if ($p['stock_quantity'] <= 5): ?>
                                <span class="badge bg-danger"><?= $p['stock_quantity'] ?></span>
                            <?php else: ?>
                                <?= $p['stock_quantity'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($p['product_size']) ? htmlspecialchars($p['product_size']) : '-' ?></td>
                        <td>
                            <span class="badge bg-<?= $p['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-product-btn" data-id="<?= $p['id'] ?>"
                                    data-bs-toggle="modal" data-bs-target="#editProductModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete product?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">New Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Product Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label>Price (R)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label>Size <small class="text-muted">(optional)</small></label>
                            <input type="text" name="product_size" class="form-control" placeholder="e.g. 20ml, 159ml">
                        </div>
                        <div class="col-md-12">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                            <small class="text-muted">You can use &lt;b&gt;, &lt;ul&gt;, &lt;li&gt; etc. for formatting.</small>
                        </div>
                        <div class="col-md-6">
                            <label>Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="activeAdd" checked>
                                <label for="activeAdd">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="save_product" class="btn btn-success">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <form method="post" enctype="multipart/form-data" id="editProductForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editProductFormBody">
                    <!-- AJAX loads the edit form here -->
                </div>
                <div class="modal-footer">
                    <button type="submit" name="save_product" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function(){
    // Load edit form via AJAX
    $('.edit-product-btn').click(function(){
        var id = $(this).data('id');
        $('#edit_id').val(id);
        $.get('../ajax/get_product_form.php?id=' + id, function(html) {
            $('#editProductFormBody').html(html);
        });
    });
});
</script>
<?php include '../includes/footer.php'; ?>