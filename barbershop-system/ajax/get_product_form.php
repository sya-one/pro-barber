<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id'])) die('Unauthorized');

$db = (new Database())->getConnection();
$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) exit;
?>
<div class="row g-3">
    <div class="col-md-6">
        <label>Product Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
    </div>
    <div class="col-md-3">
        <label>Price (R)</label>
        <input type="number" step="0.01" name="price" class="form-control" value="<?= $row['price'] ?>" required>
    </div>
    <div class="col-md-3">
        <label>Stock Quantity</label>
        <input type="number" name="stock_quantity" class="form-control" value="<?= $row['stock_quantity'] ?>" required>
    </div>
    <div class="col-md-3">
        <label>Size <small class="text-muted">(optional)</small></label>
        <input type="text" name="product_size" class="form-control" value="<?= htmlspecialchars($row['product_size'] ?? '') ?>" placeholder="e.g. 20ml, 159ml">
    </div>
    <div class="col-md-12">
        <label>Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($row['description']) ?></textarea>
    </div>
    <div class="col-md-6">
        <label>Image</label>
        <input type="file" name="image" class="form-control" accept="image/*">
        <small>Current: <img src="../uploads/products/<?= htmlspecialchars($row['image']) ?>" width="40"></small>
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="activeEdit" <?= $row['is_active'] ? 'checked' : '' ?>>
            <label for="activeEdit">Active</label>
        </div>
    </div>
</div>