<?php
require_once 'shop_functions.php';
require_once 'barbershop-system/includes/csrf.php';
session_start();
$db = getDb();
$settings = getSettings();
$cart = getCart();
$total = getCartTotal();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $msg = 'Invalid CSRF token';
    } elseif (empty($cart)) {
        $msg = 'Your cart is empty';
    } else {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');

        try {
            $db->beginTransaction();

            // Find or create customer
            $stmt = $db->prepare("SELECT id FROM customers WHERE email=? OR phone=? LIMIT 1");
            $stmt->execute([$email, $phone]);
            if ($cust = $stmt->fetch()) {
                $customer_id = $cust['id'];
            } else {
                $stmt = $db->prepare("INSERT INTO customers (full_name, email, phone) VALUES (?,?,?)");
                $stmt->execute([$full_name, $email, $phone]);
                $customer_id = $db->lastInsertId();
            }

            // Create payment record
            $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, paid_at) VALUES (NULL, ?, 'card', NOW())");
            $stmt->execute([$total]);

            // Generate invoice number
            $invoice_number = generateInvoiceNumber($db);

            // Create sale
            $stmt = $db->prepare("INSERT INTO sales (customer_id, total, payment_method, invoice_number) VALUES (?, ?, 'card', ?)");
            $stmt->execute([$customer_id, $total, $invoice_number]);
            $sale_id = $db->lastInsertId();

            // Process each cart item
            foreach ($cart as $item) {
                $product_id = $item['id'];
                $qty = $item['quantity'];
                $price = $item['price'];
                $line_total = $price * $qty;

                // Check stock
                $stmt = $db->prepare("SELECT stock_quantity, name FROM products WHERE id=?");
                $stmt->execute([$product_id]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$prod || $prod['stock_quantity'] < $qty) {
                    throw new Exception("Insufficient stock for {$prod['name']}");
                }

                // Update inventory
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=?")->execute([$qty, $product_id]);

                // Add sale item
                $db->prepare("INSERT INTO sale_items (sale_id, product_id, item_type, item_name, quantity, unit_price, total_price) VALUES (?, ?, 'product', ?, ?, ?, ?)")
                   ->execute([$sale_id, $product_id, $item['name'], $qty, $price, $line_total]);
            }

            // Update customer stats
            $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id=?")->execute([$total, $customer_id]);

            // Add loyalty points
            if (function_exists('getLoyaltyRate')) {
                $rate = getLoyaltyRate($db);
                $points = floor($total * $rate);
                if ($points > 0) {
                    adjustLoyaltyPoints($db, $customer_id, $points, 'earned', null, 'Online shop order');
                }
            }

            $db->commit();

            // Notify admin
            notifyAdminEmail($db, "New online order: $invoice_number", "<p>Order total: " . formatCurrency($total) . "</p>");
            notifyAdminWhatsApp($db, "New online order: $invoice_number, " . formatCurrency($total));

            clearCart();
            header("Location: /order-success?invoice={$invoice_number}");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Shopping Cart';
$pageDescription = 'Review your cart and checkout at The Professional Barbershop shop.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Your Cart</h1>
            <p class="lead"><?= count($cart) ?> items in your cart</p>
        </div>
    </section>

    <!-- Cart Content -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($cart)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-readable-muted mb-3"></i>
                    <h4>Your cart is empty</h4>
                    <p class="text-readable-muted">Browse our grooming products</p>
                    <a href="/shop" class="btn btn-success btn-lg mt-3"><i class="fas fa-store me-2"></i>Shop Products</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-dark mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart as $id => $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="/barbershop-system/uploads/products/<?= htmlspecialchars($item['image'] ?? 'default-product.png') ?>" class="cart-item-img me-3" alt="<?= htmlspecialchars($item['name']) ?>" onerror="this.src='/barbershop-system/assets/images/default-product.png'">
                                                        <span><?= htmlspecialchars($item['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= formatCurrency($item['price']) ?></td>
                                                <td>
                                                    <input type="number" class="form-control qty-update" value="<?= $item['quantity'] ?>" min="1" data-id="<?= $id ?>" style="width:70px;">
                                                </td>
                                                <td><?= formatCurrency($item['price'] * $item['quantity']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-danger remove-item" data-id="<?= $id ?>"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="mb-4">Order Summary</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <span><?= formatCurrency($total) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>VAT</span>
                                    <span><?= formatCurrency($total * 0.15) ?></span>
                                </div>
                                <hr style="border-color: rgba(255,255,255,0.1);">
                                <div class="d-flex justify-content-between mb-4">
                                    <strong>Total</strong>
                                    <strong class="text-success"><?= formatCurrency($total * 1.15) ?></strong>
                                </div>
                                <a href="/checkout" class="btn btn-success w-100 btn-lg">Proceed to Checkout</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php $extraJs = '
    $(function(){
        $(".qty-update").on("change", function(){
            let id = $(this).data("id");
            let qty = $(this).val();
            $.post("cart_ajax.php", {action:"update", product_id:id, quantity:qty}, function(data){
                if(data.success) location.reload();
                else alert(data.message || "Stock insufficient");
            }, "json");
        });
        $(".remove-item").on("click", function(){
            if (!confirm("Remove this item?")) return;
            let id = $(this).data("id");
            $.post("cart_ajax.php", {action:"remove", product_id:id}, function(){ location.reload(); }, "json");
        });
    });
'; ?>
<?php include 'includes/footer.php'; ?>