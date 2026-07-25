<?php
require_once 'shop_functions.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$product_id = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);

if ($action === 'add') {
    $ok = addToCart($product_id, $quantity);
    echo json_encode(['success' => $ok, 'cartCount' => count(getCart()), 'message' => $ok ? 'Added to cart' : 'Out of stock or invalid']);
} elseif ($action === 'remove') {
    removeFromCart($product_id);
    echo json_encode(['success' => true, 'cartCount' => count(getCart())]);
} elseif ($action === 'update') {
    $ok = updateCartQuantity($product_id, $quantity);
    echo json_encode(['success' => $ok, 'cartCount' => count(getCart()), 'message' => $ok ? '' : 'Stock insufficient']);
} elseif ($action === 'count') {
    echo json_encode(['count' => count(getCart())]);
} elseif ($action === 'clear') {
    clearCart();
    echo json_encode(['success' => true, 'cartCount' => 0]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}