<?php
require_once 'shop_functions.php';
require_once 'barbershop-system/includes/csrf.php';
require_once 'barbershop-system/includes/Paystack.php';
session_start();
$db = getDb();
$settings = getSettings();
$cart = getCart();
$total = getCartTotal();
$msg = '';

$paystackPublicKey = $settings['paystack_public_key'] ?? '';
$usePaystack = !empty($paystackPublicKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $msg = 'Invalid CSRF token';
    } elseif (empty($cart)) {
        $msg = 'Your cart is empty';
    } else {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';

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

            // Generate invoice number
            $invoice_number = generateInvoiceNumber($db);

            // Create sale
            $finalTotal = $total;
            $stmt = $db->prepare("INSERT INTO sales (customer_id, total, payment_method, invoice_number, paystack_reference) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $finalTotal, $paymentMethod, $invoice_number, $paymentMethod === 'paystack' ? $invoice_number : '']);
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
            $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id=?")->execute([$finalTotal, $customer_id]);

            // Award loyalty points
            if (function_exists('getLoyaltyRate')) {
                $rate = getLoyaltyRate($db);
                $points = floor($finalTotal * $rate);
                if ($points > 0) {
                    adjustLoyaltyPoints($db, $customer_id, $points, 'earned', null, 'Online shop order');
                }
            }

            $db->commit();

            // For Paystack, initialize payment
            if ($paymentMethod === 'paystack' && $usePaystack) {
                $paystack = new Paystack();
                $paymentData = $paystack->initializePayment($finalTotal, $email, $invoice_number, $full_name, [
                    'sale_id' => $sale_id,
                    'customer_id' => $customer_id
                ]);

                if ($paymentData && isset($paymentData['data']['authorization_url'])) {
                    clearCart();
                    header("Location: " . $paymentData['data']['authorization_url']);
                    exit;
                } else {
                    $msg = 'Payment initialization failed. Please try again.';
                }
            } else {
                // Cash/EFT order - just confirm
                notifyAdminEmail($db, "New order: $invoice_number", "<p>Order total: " . formatCurrency($finalTotal) . "</p>");
                notifyAdminWhatsApp($db, "New order: $invoice_number, " . formatCurrency($finalTotal));
                clearCart();
                header("Location: /order-success?invoice={$invoice_number}");
                exit;
            }

        } catch (Exception $e) {
            $db->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
$pageDescription = 'Secure checkout for grooming products.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Secure Checkout</h1>
            <p class="lead">Complete your order</p>
        </div>
    </section>

    <!-- Checkout Section -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($cart)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-readable-muted mb-3"></i>
                    <h4>Your cart is empty</h4>
                    <a href="/shop" class="btn btn-success mt-3"><i class="fas fa-store me-2"></i>Shop Products</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="mb-4">Customer Details</h5>

                                <?php if ($msg): ?>
                                <div class="alert alert-danger"><?= $msg ?></div>
                                <?php endif; ?>

                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="place_order" value="1">

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" name="full_name" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email *</label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone *</label>
                                            <input type="tel" name="phone" class="form-control" required>
                                        </div>
                                    </div>

                                    <h5 class="mt-5 mb-4">Payment Method</h5>
                                    <div class="payment-methods">
                                        <?php if ($usePaystack): ?>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="payment_method" id="paystack" value="paystack" checked>
                                            <label class="form-check-label" for="paystack">
                                                <i class="fab fa-paystack me-2"></i>Paystack (Card Payment)
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash" <?= !$usePaystack ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="cash">
                                                <i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-lg w-100 mt-4"><i class="fas fa-lock me-2"></i>Place Order</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="mb-4">Order Summary</h5>

                                <?php foreach ($cart as $item): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= htmlspecialchars($item['name']) ?> × <?= $item['quantity'] ?></span>
                                    <span><?= formatCurrency($item['price'] * $item['quantity']) ?></span>
                                </div>
                                <?php endforeach; ?>

                                <hr style="border-color: rgba(255,255,255,0.1);">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <span><?= formatCurrency($total) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>VAT (15%)</span>
                                    <span><?= formatCurrency($total * 0.15) ?></span>
                                </div>
                                <hr style="border-color: rgba(255,255,255,0.1);">
                                <div class="d-flex justify-content-between mb-4">
                                    <strong>Total</strong>
                                    <strong class="text-success"><?= formatCurrency($total * 1.15) ?></strong>
                                </div>

                                <a href="/cart" class="btn btn-outline-light w-100"><i class="fas fa-arrow-left me-2"></i>Back to Cart</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>