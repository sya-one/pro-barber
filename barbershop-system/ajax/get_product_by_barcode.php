<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(null);
    exit;
}

$barcode = trim($_GET['barcode'] ?? '');
if (!$barcode) {
    header('Content-Type: application/json');
    echo json_encode(null);
    exit;
}

$db = (new Database())->getConnection();

// Check if barcode column exists, if not fall back to product_code
$stmt = $db->query("SHOW COLUMNS FROM products LIKE 'barcode'");
if ($stmt->rowCount() > 0) {
    $stmt = $db->prepare("SELECT id, name, price FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$barcode]);
} else {
    // Fallback to product_code if barcode column doesn't exist yet
    $stmt = $db->prepare("SELECT id, name, price FROM products WHERE product_code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$barcode]);
}

$product = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($product ?: null);