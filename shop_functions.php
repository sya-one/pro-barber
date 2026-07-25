<?php
session_start();
// Use the exact same database config as the backend
require_once __DIR__ . '/barbershop-system/config/database.php';
require_once __DIR__ . '/barbershop-system/includes/functions.php';
require_once __DIR__ . '/barbershop-system/includes/mailer.php';
require_once __DIR__ . '/barbershop-system/includes/whatsapp.php';

// ---------- CART HELPERS ----------
function getCart() {
    return $_SESSION['website_cart'] ?? [];
}

function addToCart($product_id, $quantity = 1) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, name, price, stock_quantity, image FROM products WHERE is_active=1 AND id=?");
    $stmt->execute([$product_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p || $p['stock_quantity'] < $quantity) return false;

    $cart = getCart();
    if (isset($cart[$product_id])) {
        $newQty = $cart[$product_id]['quantity'] + $quantity;
        if ($newQty > $p['stock_quantity']) return false;
        $cart[$product_id]['quantity'] = $newQty;
    } else {
        $cart[$product_id] = [
            'id'       => $p['id'],
            'name'     => $p['name'],
            'price'    => $p['price'],
            'quantity' => $quantity,
            'image'    => $p['image'] ?? 'default-product.png'
        ];
    }
    $_SESSION['website_cart'] = $cart;
    return true;
}

function removeFromCart($product_id) {
    unset($_SESSION['website_cart'][$product_id]);
}

function updateCartQuantity($product_id, $quantity) {
    if ($quantity <= 0) {
        removeFromCart($product_id);
        return true;
    }
    $db = getDb();
    $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id=?");
    $stmt->execute([$product_id]);
    $stock = $stmt->fetchColumn();
    if ($quantity > $stock) return false;
    $_SESSION['website_cart'][$product_id]['quantity'] = $quantity;
    return true;
}

function getCartTotal() {
    $total = 0;
    foreach (getCart() as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function clearCart() {
    unset($_SESSION['website_cart']);
}

// Format currency - use global function or define locally
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'R ' . number_format($amount, 2);
    }
}

// Gallery functions
function getGalleryImages($limit = 0) {
    $db = getDb();
    try {
        // Check which schema exists
        $has_status = false;
        try {
            $db->query("SELECT status FROM gallery LIMIT 1");
            $has_status = true;
        } catch (Exception $e) {
            // Old schema with is_active
        }
        
        if ($has_status) {
            $sql = $limit > 0 ? 
                "SELECT * FROM gallery WHERE status='active' ORDER BY sort_order ASC LIMIT $limit" :
                "SELECT * FROM gallery WHERE status='active' ORDER BY sort_order ASC";
        } else {
            $sql = $limit > 0 ?
                "SELECT * FROM gallery WHERE is_active=1 ORDER BY sort_order ASC LIMIT $limit" :
                "SELECT * FROM gallery WHERE is_active=1 ORDER BY sort_order ASC";
        }
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getTestimonialsPublished() {
    $db = getDb();
    try {
        $db->query("SELECT 1 FROM testimonials LIMIT 1");
        return $db->query("SELECT * FROM testimonials WHERE is_approved=1 ORDER BY created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}