<?php
ob_start();
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/Paystack.php';
if (!isReceptionist() && !isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';
$base_url = "http://localhost:81/barbershop-system/"; // change for production
$paystack = new Paystack();
$paystackPublicKey = $paystack->getPublicKey();
$isPaystackTestMode = $paystack->isTestMode();

$customerEmail = '';
$paystackReference = '';

// ---------- HANDLE PAYSTACK CALLBACK ----------
if (isset($_GET['paystack_callback']) && $_GET['paystack_callback'] === '1') {
    $reference = $_GET['reference'] ?? '';
    if ($reference) {
        $verification = verifyPaystackPayment($reference);
        if ($verification && $verification['status']) {
            $paymentData = $verification['data'] ?? [];
            $status = $paymentData['status'] ?? 'failed';
            
            if ($status === 'success' || $status === 'paid') {
                $stmt = $db->prepare("SELECT id, total FROM sales WHERE paystack_reference = ?");
                $stmt->execute([$reference]);
                $sale = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($sale) {
                    $saleId = $sale['id'];
                    $total = $sale['total'];
                    
                    $db->prepare("UPDATE sales SET payment_status = 'paid' WHERE id = ?")->execute([$saleId]);
                    $db->prepare("UPDATE payments SET status = 'paid' WHERE transaction_code = ?")->execute([$reference]);
                    
                    $_SESSION['pos_success'] = "Payment completed via Paystack. Invoice: " . ($_SESSION['last_invoice'] ?? 'N/A');
                    header("Location: invoice.php?id=$saleId");
                    exit;
                }
            }
        }
    }
}

// ---------- RECORD SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_sale'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $customer_id    = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $booking_id     = !empty($_POST['booking_id'])  ? intval($_POST['booking_id'])  : null;
    $payment_method = $_POST['payment_method'];
    $trans_code     = trim($_POST['transaction_code'] ?? '');
    $cart           = json_decode($_POST['cart'], true);
    $customer_email = $_POST['customer_email'] ?? '';

    if (empty($cart)) {
        $msg = "Cart is empty.";
    } else {
        try {
            $db->beginTransaction();

            $total = 0;
            foreach ($cart as $item) {
                $total += $item['price'] * $item['quantity'];
            }

            // Generate invoice number
            $invoice_number = generateInvoiceNumber($db);

            // Handle Paystack payment - create pending payment and redirect
            if ($payment_method === 'paystack') {
                $reference = 'PS-' . $invoice_number . '-' . uniqid();
                
                $initialization = $paystack->initializePayment(
                    $total,
                    $customer_email,
                    $reference,
                    $_SESSION['full_name'] ?? 'Customer',
                    ['sale_id' => 'pending', 'invoice' => $invoice_number]
                );
                
                if ($initialization['status'] ?? false) {
                    $authorizationUrl = $initialization['data']['authorization_url'] ?? '';
                    
                    // Create pending sale
                    $db->prepare("INSERT INTO sales (customer_id, total, payment_method, invoice_number, payment_status, paystack_reference) 
                                  VALUES (?, ?, 'paystack', ?, 'pending', ?)")
                        ->execute([$customer_id, $total, $invoice_number, $reference]);
                    $sale_id = $db->lastInsertId();
                    
                    $_SESSION['paystack_reference'] = $reference;
                    $_SESSION['last_invoice'] = $invoice_number;
                    
                    $db->commit();
                    
                    header("Location: " . $authorizationUrl);
                    exit;
                } else {
                    throw new Exception("Paystack initialization failed: " . ($initialization['message'] ?? 'Unknown error'));
                }
            }

            // 1. Insert payment
            $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_code, paid_at, status) VALUES (?, ?, ?, ?, NOW(), 'paid')");
            $stmt->execute([$booking_id, $total, $payment_method, $trans_code]);

            // 2. Insert sale header
            $stmt = $db->prepare("INSERT INTO sales (customer_id, total, payment_method, invoice_number, payment_status) VALUES (?, ?, ?, ?, 'paid')");
            $stmt->execute([$customer_id, $total, $payment_method, $invoice_number]);
            $sale_id = $db->lastInsertId();

            // 4. Process cart items
            foreach ($cart as $item) {
                $line_total = $item['price'] * $item['quantity'];
                $product_id = null;
                $item_booking_id = $item['booking_id'] ?? null;

                if ($item['type'] === 'product') {
                    $product_id = $item['id'];
                    // Verify stock and reduce
                    $stmt = $db->prepare("SELECT stock_quantity, name FROM products WHERE id = ? AND is_active = 1");
                    $stmt->execute([$product_id]);
                    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$prod) throw new Exception("Product '{$item['name']}' not found.");
                    if ($prod['stock_quantity'] < $item['quantity']) throw new Exception("Insufficient stock for '{$item['name']}'.");

                    $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$item['quantity'], $product_id]);

                    // Low stock alert after deduction
                    $new_stock = $prod['stock_quantity'] - $item['quantity'];
                    if ($new_stock <= 5) {
                        // WhatsApp
                        $wa_low = "Low stock alert: {$prod['name']} only $new_stock left.";
                        notifyAdminWhatsApp($db, $wa_low);
                        // Email
                        $subj = "Low Stock Alert: " . $prod['name'];
                        $body = "<p><strong>" . htmlspecialchars($prod['name']) . "</strong> is running low.</p>
                                 <p>Current stock: <strong>$new_stock</strong> units.</p>
                                 <p><a href='{$base_url}admin/products.php'>Manage Products</a></p>";
                        notifyAdminEmail($db, $subj, $body);
                    }

                } elseif ($item['type'] === 'service') {
                    $barber_id = !empty($item['barber_id']) ? intval($item['barber_id']) : null;

                    if ($item_booking_id) {
                        $db->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?")->execute([$item_booking_id]);
                    } else {
                        $db->prepare("INSERT INTO bookings (booking_code, customer_id, barber_id, service_id, booking_date, booking_time, status, booking_type)
                                      VALUES ('POS', ?, ?, ?, CURDATE(), CURTIME(), 'completed', 'walk-in')")
                           ->execute([$customer_id, $barber_id, $item['id']]);
                        $item_booking_id = $db->lastInsertId();
                        $code = 'pro-bk-' . str_pad($item_booking_id, 5, '0', STR_PAD_LEFT);
                        $db->prepare("UPDATE bookings SET booking_code = ? WHERE id = ?")->execute([$code, $item_booking_id]);
                    }
                }

                // Insert sale item
                $db->prepare("INSERT INTO sale_items (sale_id, product_id, booking_id, item_type, item_name, quantity, unit_price, total_price)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$sale_id, $product_id, $item_booking_id ?? null, $item['type'], $item['name'], $item['quantity'], $item['price'], $line_total]);
            }

            // 6. Calculate and record commissions for services
            $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ? AND item_type = 'service'");
            $stmt->execute([$sale_id]);
            while ($si = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($si['booking_id'])) {
                    $stmt = $db->prepare("SELECT barber_id FROM bookings WHERE id = ?");
                    $stmt->execute([$si['booking_id']]);
                    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($booking && $booking['barber_id']) {
                        $barber_id = $booking['barber_id'];
                        $stmt = $db->prepare("SELECT commission_rate, commission_type FROM barbers WHERE id = ?");
                        $stmt->execute([$barber_id]);
                        $barber = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($barber) {
                            $commissionAmount = 0;
                            $commissionRate = $barber['commission_rate'] / 100;
                            
                            if ($barber['commission_type'] === 'percentage') {
                                $commissionAmount = $si['total_price'] * $commissionRate;
                            } elseif ($barber['commission_type'] === 'fixed') {
                                $commissionAmount = $barber['commission_rate'];
                            }
                            
                            if ($commissionAmount > 0) {
                                $db->prepare("INSERT INTO commissions (barber_id, sale_id, booking_id, amount, rate_percent, status) 
                                              VALUES (?, ?, ?, ?, ?, 'earned')")
                                    ->execute([$barber_id, $sale_id, $si['booking_id'], $commissionAmount, $barber['commission_rate']]);
                            }
                        }
                    }
                }
            }

            // 7. Update customer totals & loyalty
            if ($customer_id) {
                $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id = ?")->execute([$total, $customer_id]);
                if (function_exists('getLoyaltyRate')) {
                    $rate = getLoyaltyRate($db);
                    $points = floor($total * $rate);
                    if ($points > 0) {
                        adjustLoyaltyPoints($db, $customer_id, $points, 'earned', null, 'Points earned from POS sale');
                    }
                }
            }

            $db->commit();

            // 6. Notify admin (email + WhatsApp)
            $cust_name = 'Walk‑in';
            if ($customer_id) {
                $stmt = $db->prepare("SELECT full_name FROM customers WHERE id = ?");
                $stmt->execute([$customer_id]);
                $cust_name = $stmt->fetchColumn() ?: 'Walk‑in';
            }

            // Email
            $subj = "New Sale: " . $invoice_number;
            $body = "<p>A sale of <strong>" . formatCurrency($total) . "</strong> has been recorded.</p>
                     <p><strong>Invoice:</strong> $invoice_number<br>
                     <strong>Customer:</strong> " . htmlspecialchars($cust_name) . "<br>
                     <strong>Payment:</strong> " . ucfirst($payment_method) . "</p>
                     <p><a href='{$base_url}reception/invoice.php?id=$sale_id'>View Invoice</a></p>";
            notifyAdminEmail($db, $subj, $body);

            // WhatsApp
            $wa_msg = "Sale completed: $invoice_number, " . formatCurrency($total) . ", $cust_name";
            notifyAdminWhatsApp($db, $wa_msg);

            // 7. Set flag for optional email to customer (handled by invoice page)
            $_SESSION['send_invoice_email'] = $sale_id;

            // Redirect to invoice
            ob_end_clean();
            $_SESSION['pos_success'] = "Sale completed. Invoice: $invoice_number";
            header("Location: invoice.php?id=$sale_id");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    }
}

// Flash message from redirect
if (isset($_SESSION['pos_success'])) {
    $msg = $_SESSION['pos_success'];
    unset($_SESSION['pos_success']);
}

// Grid data
$products = $db->query("SELECT * FROM products WHERE is_active = 1 AND stock_quantity > 0 ORDER BY name")->fetchAll();
$services = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name")->fetchAll();
$barbers  = $db->query("SELECT id, full_name FROM barbers WHERE is_active = 1 ORDER BY full_name")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Point of Sale</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="row g-3">
            <!-- Left column: booking search + product/service grid -->
            <div class="col-md-8">
                <!-- Load Appointment -->
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5><i class="fas fa-calendar-check me-2"></i>Load Appointment</h5>
                    <div class="input-group mb-2">
                        <input type="text" id="bookingSearch" class="form-control" placeholder="Phone, booking code, or name" autocomplete="off">
                        <button class="btn btn-outline-light" type="button" id="clearBookingSearch"><i class="fas fa-times"></i></button>
                    </div>
                    <div id="bookingResults" class="list-group" style="max-height:200px; overflow-y:auto; display:none;"></div>
                    <div id="selectedBookingInfo" class="d-none mt-2 p-2 rounded" style="background:rgba(15,169,88,0.2);">
                        <span id="selectedBookingText"></span>
                        <button class="btn btn-sm btn-outline-light ms-2" id="removeBookingBtn"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <!-- Products -->
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5>Products</h5>
                    <div class="row g-2">
                        <?php foreach ($products as $p): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <button class="btn btn-outline-light w-100 h-100 product-btn"
                                    data-type="product"
                                    data-id="<?= $p['id'] ?>"
                                    data-name="<?= htmlspecialchars($p['name']) ?>"
                                    data-price="<?= $p['price'] ?>"
                                    style="min-height: 80px; font-size: 0.9rem; touch-action: manipulation;">
                                <small><?= htmlspecialchars($p['name']) ?></small><br>
                                <strong><?= formatCurrency($p['price']) ?></strong>
                                <?php if ($p['product_size']): ?><br><small class="text-muted"><?= htmlspecialchars($p['product_size']) ?></small><?php endif; ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Services (manual add) -->
                <div class="card bg-dark text-white p-3">
                    <h5>Services <small class="text-muted">(manual add)</small></h5>
                    <!-- Barber selector for services -->
                    <div class="mb-2">
                        <label class="form-label">Barber for Services</label>
                        <select id="serviceBarber" class="form-select" style="width:250px;">
                            <option value="">Not assigned</option>
                            <?php foreach ($barbers as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($services as $s): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <button class="btn btn-outline-light w-100 h-100 service-btn"
                                    data-type="service"
                                    data-id="<?= $s['id'] ?>"
                                    data-name="<?= htmlspecialchars($s['name']) ?>"
                                    data-price="<?= $s['price'] ?>"
                                    style="min-height: 80px; font-size: 0.9rem; touch-action: manipulation;">
                                <small><?= htmlspecialchars($s['name']) ?></small><br>
                                <strong><?= formatCurrency($s['price']) ?></strong>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right column: Cart & Checkout -->
            <div class="col-md-4">
                <div class="card bg-dark text-white p-3 sticky-top" style="top:70px;">
                    <h5>Cart</h5>
                    <div id="cartItems" style="max-height:300px; overflow-y:auto;">
                        <p class="text-muted">No items yet.</p>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Total:</strong>
                        <strong id="cartTotal">R0.00</strong>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <label>Customer (auto‑filled from appointment)</label>
                        <input type="text" id="customerSearch" class="form-control" placeholder="Search customer (optional)" autocomplete="off">
                        <input type="hidden" id="customerId" name="customer_id" value="">
                        <div id="customerResults" class="list-group" style="max-height:150px; overflow-y:auto; display:none;"></div>
                    </div>
                    <div class="mb-2">
                        <label>Customer Email (for Paystack)</label>
                        <input type="email" id="customerEmail" class="form-control" placeholder="customer@email.com">
                    </div>
                    <div class="mb-2">
                        <label>Payment Method</label>
                        <select id="paymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="card">Card (Yaco)</option>
                            <option value="eft">EFT</option>
                            <option value="paystack">Paystack</option>
                        </select>
                    </div>
                    <!-- Transaction code (visible only for card payments) -->
                    <div class="mb-2" id="transCodeGroup" style="display:none;">
                        <label>Yaco Transaction Code</label>
                        <input type="text" id="transactionCode" class="form-control" placeholder="Enter code from terminal">
                        <small class="text-muted">Required for card payments.</small>
                    </div>

                    <!-- Paystack info -->
                    <div id="paystackInfo" class="mb-2 d-none">
                        <small class="text-muted">Test mode: <?= $isPaystackTestMode ? 'Enabled' : 'Disabled' ?></small>
                        <br>
                        <small class="text-muted">Public Key: <?= substr($paystackPublicKey, 0, 10) . '...' ?></small>
                    </div>

                    <!-- Barcode Scan Button -->
                    <button type="button" class="btn btn-outline-light w-100 mb-2" data-bs-toggle="modal" data-bs-target="#barcodeModal">
                        <i class="fas fa-barcode me-1"></i> Scan Barcode
                    </button>

                    <button type="button" id="checkoutBtn" class="btn btn-success w-100" style="padding: 12px; font-size: 1.2rem; touch-action: manipulation;">Complete Sale</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form used to submit the sale -->
<form method="post" id="posForm">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="cart" id="cartData">
    <input type="hidden" name="customer_id" id="posCustomerId">
    <input type="hidden" name="booking_id" id="posBookingId">
    <input type="hidden" name="payment_method" id="posPaymentMethod">
    <input type="hidden" name="transaction_code" id="posTransactionCode" value="">
    <input type="hidden" name="customer_email" id="posCustomerEmail" value="">
    <input type="hidden" name="record_sale" value="1">
</form>

<!-- Barcode Scanner Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fas fa-camera me-1"></i>Scan Barcode</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <div id="barcodeScanner" style="width:100%; height:300px; background:#000; border:1px solid #0FA958; position:relative; overflow:hidden;">
                    <video id="barcodeVideo" style="width:100%; height:100%; object-fit:cover;" autoplay playsinline></video>
                    <div id="barcodeOverlay" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:80%; max-width:300px; border:2px solid #0FA958; border-radius:8px; pointer-events:none;"></div>
                </div>
                <div class="mt-2">
                    <input type="text" id="manualBarcode" class="form-control d-inline-block w-auto me-2" placeholder="Enter barcode manually" style="width:auto;">
                    <button id="submitManualBarcode" class="btn btn-success btn-sm">Add</button>
                </div>
                <p class="mt-2 small text-muted">Position barcode in the green frame, or enter manually</p>
                <div id="barcodeStatus" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- QuaggaJS barcode scanner library -->
<script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.2/dist/quagga.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    let cart = [];
    let currentBooking = null;
    let lastScannedCode = '';
    let scanCooldown = false;

    // ---------- CART FUNCTIONS ----------
    function addToCart(type, id, name, price, booking_id = null, barber_id = null) {
        let existing = cart.find(item => item.type === type && item.id === id && item.booking_id == booking_id);
        if (existing) {
            existing.quantity++;
        } else {
            cart.push({ type, id, name, price, quantity: 1, booking_id, barber_id });
        }
        renderCart();
    }

    function renderCart() {
        let html = '';
        let total = 0;
        cart.forEach((item, index) => {
            let lineTotal = item.price * item.quantity;
            total += lineTotal;
            html += `<div class="d-flex justify-content-between align-items-center mb-2">
                <span>${item.name} <small class="text-muted">x${item.quantity}</small></span>
                <span>R${lineTotal.toFixed(2)}
                    <button class="btn btn-sm btn-danger ms-2 remove-item" data-index="${index}"><i class="fas fa-trash"></i></button>
                </span>
            </div>`;
        });
        if (cart.length === 0) html = '<p class="text-muted">No items yet.</p>';
        $('#cartItems').html(html);
        $('#cartTotal').text('R' + total.toFixed(2));
    }

    // Add product/service to cart via buttons
    $('.product-btn, .service-btn').on('click', function(e){
        e.preventDefault();
        let type  = $(this).data('type');
        let id    = $(this).data('id');
        let name  = $(this).data('name');
        let price = parseFloat($(this).data('price'));

        if (type === 'service') {
            let barber_id = $('#serviceBarber').val() || null;
            addToCart(type, id, name, price, null, barber_id);
        } else {
            addToCart(type, id, name, price);
        }
    });

    // Remove item from cart
    $(document).on('click', '.remove-item', function(e){
        e.preventDefault();
        let index = $(this).data('index');
        cart.splice(index, 1);
        renderCart();
    });

    // ---------- CUSTOMER SEARCH ----------
    $('#customerSearch').on('keyup', function(){
        let term = $(this).val();
        if (term.length >= 2) {
            $.getJSON('../ajax/customer_search.php', { term }, function(data){
                let html = '';
                data.forEach(c => {
                    html += `<a href="#" class="list-group-item list-group-item-action bg-dark text-white customer-select" data-id="${c.id}" data-email="${c.email}">${c.full_name} - ${c.phone}</a>`;
                });
                $('#customerResults').html(html).show();
            });
        } else {
            $('#customerResults').hide();
        }
    });
    $(document).on('click', '.customer-select', function(e){
        e.preventDefault();
        let id = $(this).data('id');
        let email = $(this).data('email');
        let name = $(this).text();
        $('#customerId').val(id);
        $('#customerSearch').val(name);
        $('#customerEmail').val(email || '');
        $('#customerResults').hide();
    });

    // ---------- BOOKING SEARCH ----------
    $('#bookingSearch').on('keyup', function(){
        let term = $(this).val();
        if (term.length >= 2) {
            $.getJSON('../ajax/search_bookings.php', { term }, function(data){
                let html = '';
                if (data.length === 0) {
                    html = '<div class="list-group-item text-muted">No matching bookings today.</div>';
                } else {
data.forEach(b => {
                        html += `<a href="#" class="list-group-item list-group-item-action bg-dark text-white booking-select"
                                   data-id="${b.id}"
                                   data-customer-id="${b.customer_id}"
                                   data-customer-name="${b.customer_name}"
                                   data-customer-email="${b.customer_email}"
                                   data-barber-name="${b.barber_name}"
                                   data-service-id="${b.service_id}"
                                   data-service-name="${b.service_name}"
                                   data-price="${b.price}">
                                   ${b.booking_code} - ${b.customer_name} (${b.phone})<br>
                                   <small>${b.barber_name} | ${b.service_name} | ${b.booking_time}</small>
                                 </a>`;
                    });
                }
                $('#bookingResults').html(html).show();
            });
        } else {
            $('#bookingResults').hide();
        }
    });
    $(document).on('click', '.booking-select', function(e){
        e.preventDefault();
        let id = $(this).data('id');
        let custId = $(this).data('customer-id');
        let custName = $(this).data('customer-name');
        let custEmail = $(this).data('customer-email');
        let barberName = $(this).data('barber-name');
        let serviceId = $(this).data('service-id');
        let serviceName = $(this).data('service-name');
        let price = parseFloat($(this).data('price'));

        currentBooking = { id, customer_id: custId, service_id: serviceId, service_name: serviceName, price };

        $('#customerId').val(custId);
        $('#customerSearch').val(custName);
        $('#customerEmail').val(custEmail || '');
        $('#selectedBookingText').text(`${custName} | ${barberName} | ${serviceName} | R${price.toFixed(2)}`);
        $('#selectedBookingInfo').removeClass('d-none');
        $('#bookingResults').hide();
        $('#bookingSearch').val('');
        addToCart('service', serviceId, serviceName, price, id);
    });

    $('#removeBookingBtn').click(function(){
        if (currentBooking) {
            cart = cart.filter(item => !(item.booking_id == currentBooking.id));
            renderCart();
            currentBooking = null;
            $('#selectedBookingInfo').addClass('d-none');
            $('#posBookingId').val('');
        }
    });

    $('#clearBookingSearch').click(function(){
        $('#bookingSearch').val('');
        $('#bookingResults').hide();
    });

    // Toggle transaction code field for card payments
    $('#paymentMethod').on('change', function(){
        if ($(this).val() === 'card') {
            $('#transCodeGroup').slideDown();
        } else {
            $('#transCodeGroup').slideUp();
        }
    });

// ---------- BARCODE SCANNER ----------
    let barcodeScannerRunning = false;
    let lastScannedCode = '';
    let scanCooldown = false;

    function stopBarcodeScanner() {
        if (barcodeScannerRunning && typeof Quagga !== 'undefined') {
            try {
                Quagga.stop();
                Quagga.offDetected();
            } catch(e) {}
            barcodeScannerRunning = false;
        }
        $('#barcodeStatus').html('');
    }

    function scanAndAddBarcode(barcode) {
        if (scanCooldown) return;
        if (barcode === lastScannedCode) return;
        scanCooldown = true;
        setTimeout(() => { scanCooldown = false; }, 1500);
        lastScannedCode = barcode;

        if (!barcode) {
            $('#barcodeStatus').html('<span class="text-danger">Invalid barcode</span>');
            return;
        }

        stopBarcodeScanner();
        $('#barcodeModal').modal('hide');

        $.ajax({
            url: '../ajax/get_product_by_barcode.php',
            method: 'GET',
            data: { barcode: barcode },
            dataType: 'json',
            success: function(data) {
                if (data && data.id) {
                    addToCart('product', data.id, data.name, parseFloat(data.price));
                    $('#barcodeStatus').html('<span class="text-success">Added: ' + data.name + '</span>');
                    setTimeout(() => { $('#barcodeStatus').html(''); }, 3000);
                } else {
                    Swal.fire({
                        title: 'Product Not Found',
                        text: 'Barcode: ' + barcode,
                        icon: 'warning',
                        confirmButtonColor: '#0FA958'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error',
                    text: 'Could not lookup barcode. Check connection.',
                    icon: 'error',
                    confirmButtonColor: '#0FA958'
                });
            }
        });
    }

    // Initialize Quagga with proper configuration for mobile
    function initBarcodeScanner() {
        if (typeof Quagga === 'undefined') {
            Swal.fire({
                title: 'Scanner Error',
                text: 'Barcode scanner library failed to load.',
                icon: 'error',
                confirmButtonColor: '#0FA958'
            });
            $('#barcodeModal').modal('hide');
            return;
        }

        // Stop any existing scanner first
        stopBarcodeScanner();

        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#barcodeScanner'),
                constraints: {
                    width: { min: 640 },
                    height: { min: 480 },
                    facingMode: "environment",
                    aspectRatio: { min: 4, max: 4 }
                },
                area: { top: "30%", right: "30%", left: "30%", bottom: "30%" },
                singleChannel: false
            },
            decoder: {
                readers: ["ean_reader", "ean_8_reader", "upc_a_reader", "upc_e_reader", "code_128_reader", "code_39_reader"],
                multiple: false,
                puzzles: []
            },
            locate: true,
            numSurroundings: 5,
            frequency: 10
        }, function(err) {
            if (err) {
                console.error('Quagga init error:', err);
                Swal.fire({
                    title: 'Camera Access Error',
                    text: 'Cannot access camera. Please:\n1. Ensure HTTPS\n2. Grant camera permission\n3. Try a different browser',
                    icon: 'warning',
                    confirmButtonColor: '#0FA958'
                });
                $('#barcodeModal').modal('hide');
                return;
            }
            Quagga.start();
            barcodeScannerRunning = true;
            $('#barcodeStatus').html('<span class="text-info">Point barcode to green frame and scan...</span>');
        });

        Quagga.onDetected(function(data) {
            if (data && data.codeResult && data.codeResult.code) {
                let barcode = data.codeResult.code;
                scanAndAddBarcode(barcode);
            }
        });
    }

    // Manual barcode submission
    $('#submitManualBarcode').on('click', function() {
        let barcode = $('#manualBarcode').val().trim();
        if (barcode) {
            scanAndAddBarcode(barcode);
            $('#manualBarcode').val('');
        }
    });

    // Enter key on manual input
    $('#manualBarcode').on('keypress', function(e) {
        if (e.key === 'Enter') {
            $('#submitManualBarcode').click();
        }
    });

    // Modal shown - start scanner
    $('#barcodeModal').on('shown.bs.modal', function() {
        lastScannedCode = '';
        initBarcodeScanner();
    });

    // Modal hidden - stop scanner
    $('#barcodeModal').on('hidden.bs.modal', function() {
        stopBarcodeScanner();
        $('#manualBarcode').val('');
        $('#barcodeStatus').html('');
    });

    // Prevent Quagga from scanning same barcode twice
    $(document).on('keydown', function(e) {
        // ESC key should close modal and stop scanner
        if (e.key === 'Escape' && $('#barcodeModal').hasClass('show')) {
            $('#barcodeModal').modal('hide');
        }
    });

    // Toggle transaction code field for card payments
    $('#paymentMethod').on('change', function(){
        let val = $(this).val();
        if (val === 'card') {
            $('#transCodeGroup').slideDown();
            $('#paystackInfo').addClass('d-none');
        } else if (val === 'paystack') {
            $('#transCodeGroup').slideUp();
            $('#paystackInfo').removeClass('d-none');
        } else {
            $('#transCodeGroup').slideUp();
            $('#paystackInfo').addClass('d-none');
        }
    });

    // ---------- CHECKOUT ----------
    $('#checkoutBtn').on('click', function(e){
        e.preventDefault();
        if (cart.length === 0) {
            alert('Cart is empty.');
            return;
        }
        
        let paymentMethod = $('#paymentMethod').val();
        let customerEmail = $('#customerEmail').val().trim();
        
        // Validate email for Paystack
        if (paymentMethod === 'paystack' && !customerEmail) {
            Swal.fire({
                title: 'Email Required',
                text: 'Please enter a customer email for Paystack payment.',
                icon: 'warning',
                confirmButtonColor: '#0FA958'
            });
            return;
        }
        
        $('#cartData').val(JSON.stringify(cart));
        $('#posCustomerId').val($('#customerId').val() || '');
        $('#posBookingId').val(currentBooking ? currentBooking.id : '');
        $('#posPaymentMethod').val(paymentMethod);
        $('#posTransactionCode').val($('#transactionCode').val() || '');
        $('#posCustomerEmail').val(customerEmail);
        $('#posForm').submit();
    });
});
</script>
<style>
    .btn { touch-action: manipulation; }
    .list-group-item { cursor:pointer; }
    #customerSearch, #bookingSearch { font-size:16px; }
    .sticky-top { position: -webkit-sticky; position: sticky; }
</style>
<?php include '../includes/footer.php'; ?>