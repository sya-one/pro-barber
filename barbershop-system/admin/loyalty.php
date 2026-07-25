<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// ---------- ENSURE LOYALTY FUNCTIONS EXIST ----------
if (!function_exists('adjustLoyaltyPoints')) {
    function adjustLoyaltyPoints($db, $customer_id, $points, $type, $booking_id = null, $note = '') {
        $stmt = $db->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?");
        $stmt->execute([$points, $customer_id]);
        $stmt = $db->prepare("INSERT INTO loyalty_transactions (customer_id, booking_id, points, type, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $booking_id, $points, $type, $note]);
    }
}
if (!function_exists('getLoyaltyRate')) {
    function getLoyaltyRate($db) {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'loyalty_rate'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val ? floatval($val) : 0.1;
    }
}

// ---------- SAVE CONVERSION RATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rate'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $rate = floatval($_POST['loyalty_rate']);
    // Insert or update setting
    $exists = $db->prepare("SELECT id FROM settings WHERE setting_key = 'loyalty_rate'")->fetchColumn();
    if ($exists) {
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'loyalty_rate'");
        $stmt->execute([$rate]);
    } else {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('loyalty_rate', ?)");
        $stmt->execute([$rate]);
    }
    $msg = "Loyalty rate updated.";
}

// ---------- MANUAL POINTS ADJUSTMENT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_points'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $customer_id = intval($_POST['customer_id']);
    $points      = intval($_POST['points']);
    $note        = trim($_POST['note']);
    adjustLoyaltyPoints($db, $customer_id, $points, 'manual', null, $note);
    $msg = "Points adjusted.";
}

// Fetch customers with points
$customers = $db->query("
    SELECT c.id, c.full_name, c.loyalty_points, c.phone,
           COALESCE(SUM(CASE WHEN lt.type='earned' THEN lt.points ELSE 0 END),0) AS total_earned,
           COALESCE(SUM(CASE WHEN lt.type='redeemed' THEN -lt.points ELSE 0 END),0) AS total_redeemed
    FROM customers c
    LEFT JOIN loyalty_transactions lt ON c.id = lt.customer_id
    GROUP BY c.id
    ORDER BY c.loyalty_points DESC
")->fetchAll();

$rate = getLoyaltyRate($db);

$transactions = $db->query("
    SELECT lt.*, c.full_name, b.booking_code
    FROM loyalty_transactions lt
    JOIN customers c ON lt.customer_id = c.id
    LEFT JOIN bookings b ON lt.booking_id = b.id
    ORDER BY lt.created_at DESC
    LIMIT 20
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Loyalty Program</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Conversion Rate Settings -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Conversion Rate</h5>
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="col-md-4">
                    <label>Points per R1 spent</label>
                    <input type="number" step="0.01" name="loyalty_rate" class="form-control" value="<?= $rate ?>" required>
                    <small>Current: <?= $rate ?> pts per R1 (R10 = <?= round(10*$rate,1) ?> points)</small>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="save_rate" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>

        <!-- Manual Adjustment -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Manual Points Adjustment</h5>
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="col-md-3">
                    <label>Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= $c['loyalty_points'] ?> pts)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Points (+ / -)</label>
                    <input type="number" name="points" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label>Note</label>
                    <input type="text" name="note" class="form-control" placeholder="Reason">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="adjust_points" class="btn btn-warning">Adjust</button>
                </div>
            </form>
        </div>

        <!-- Customer Points Table -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Customer Loyalty Points</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr><th>Customer</th><th>Phone</th><th>Points Balance</th><th>Total Earned</th><th>Total Redeemed</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['full_name']) ?></td>
                        <td><?= htmlspecialchars($c['phone']) ?></td>
                        <td><strong class="text-green"><?= $c['loyalty_points'] ?></strong></td>
                        <td><?= $c['total_earned'] ?></td>
                        <td><?= $c['total_redeemed'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Transactions -->
        <div class="card bg-dark text-white p-3">
            <h5>Recent Point Transactions</h5>
            <table class="table table-dark">
                <thead><tr><th>Customer</th><th>Booking</th><th>Points</th><th>Type</th><th>Note</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['full_name']) ?></td>
                        <td><?= $t['booking_code'] ?? '—' ?></td>
                        <td class="<?= $t['points'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $t['points'] > 0 ? '+' . $t['points'] : $t['points'] ?></td>
                        <td><?= $t['type'] ?></td>
                        <td><?= htmlspecialchars($t['note']) ?></td>
                        <td><?= $t['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>