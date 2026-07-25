<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// ---------- LOYALTY HELPERS (fallback) ----------
if (!function_exists('getLoyaltyRate')) {
    function getLoyaltyRate($db) {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'loyalty_rate'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ? floatval($val) : 0.1;
    }
}
if (!function_exists('adjustLoyaltyPoints')) {
    function adjustLoyaltyPoints($db, $customer_id, $points, $type, $booking_id = null, $note = '') {
        $stmt = $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?");
        $stmt->execute([$points, $customer_id]);
        $stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, booking_id, points, type, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $booking_id, $points, $type, $note]);
    }
}

// ---------- RECORD PAYMENT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $booking_id   = intval($_POST['booking_id']);
    $amount       = floatval($_POST['amount']);
    $method       = $_POST['payment_method'];
    $trans_code   = trim($_POST['transaction_code'] ?? '');

    try {
        $stmt = $db->prepare("SELECT b.customer_id, b.booking_code, s.name AS service_name FROM bookings b JOIN services s ON b.service_id = s.id WHERE b.id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $msg = "Booking not found.";
        } else {
            $customer_id  = $booking['customer_id'];
            $booking_code = $booking['booking_code'];

            // 1. Insert payment
            $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_code) VALUES (?,?,?,?)");
            $stmt->execute([$booking_id, $amount, $method, $trans_code]);

            // 2. Update booking status to completed
            $db->prepare("UPDATE bookings SET status='completed' WHERE id=?")->execute([$booking_id]);

            // 3. Update customer totals
            $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id = ?")->execute([$amount, $customer_id]);

            // 4. Loyalty points
            $loyaltyRate  = getLoyaltyRate($db);
            $pointsEarned = floor($amount * $loyaltyRate);
            if ($pointsEarned > 0) {
                adjustLoyaltyPoints($db, $customer_id, $pointsEarned, 'earned', $booking_id, 'Points earned from booking ' . $booking_code);
            }

            // 5. Email admin (NOW inside the success path)
            $subject = "Payment Received: " . $booking_code;
            $body = "<p>A payment of <strong>" . formatCurrency($amount) . "</strong> has been recorded.</p>";
            $body .= "<p><strong>Booking:</strong> " . htmlspecialchars($booking_code) . "<br>";
            $body .= "<strong>Method:</strong> " . ucfirst($method) . "</p>";
            notifyAdminEmail($db, $subject, $body);

            $msg = "Payment recorded. Booking completed." . ($pointsEarned > 0 ? " Customer earned $pointsEarned loyalty points." : "");
        }
    } catch (Exception $e) {
        $msg = "Error recording payment: " . $e->getMessage();
    }
}

// Fetch unpaid bookings
$unpaid = $db->query("
    SELECT b.id, b.booking_code, c.full_name AS customer, s.price, b.status
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    WHERE b.id NOT IN (SELECT booking_id FROM payments)
    ORDER BY b.booking_date DESC
")->fetchAll();

// Payment history
$payments = $db->query("
    SELECT p.*, b.booking_code, c.full_name AS customer, s.name AS service
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN customers c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    ORDER BY p.paid_at DESC
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Payment Management</h2>
        <?php if ($msg): ?>
            <div class="alert alert-<?= strpos($msg, 'Error') === 0 ? 'danger' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Record Payment Card -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Record Payment for Booking</h5>
            <?php if (count($unpaid) > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label>Select Booking</label>
                        <select name="booking_id" class="form-select" required>
                            <option value="">Choose</option>
                            <?php foreach ($unpaid as $u): ?>
                                <option value="<?= $u['id'] ?>">
                                    <?= htmlspecialchars($u['booking_code']) ?> - <?= htmlspecialchars($u['customer']) ?> (R<?= $u['price'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="eft">EFT</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Transaction Code</label>
                        <input type="text" name="transaction_code" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="record_payment" class="btn btn-success w-100">Record Payment</button>
                    </div>
                </div>
            </form>
            <?php else: ?>
                <p class="text-muted">All bookings have been paid.</p>
            <?php endif; ?>
        </div>

        <!-- Payment History Table -->
        <div class="card bg-dark text-white p-3">
            <h5>Payment History</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>ID</th><th>Booking Code</th><th>Customer</th><th>Amount</th><th>Method</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['booking_code']) ?></td>
                        <td><?= htmlspecialchars($p['customer']) ?></td>
                        <td><?= formatCurrency($p['amount']) ?></td>
                        <td><?= ucfirst($p['payment_method']) ?></td>
                        <td><?= $p['paid_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>