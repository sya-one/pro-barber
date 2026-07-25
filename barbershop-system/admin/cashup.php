<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin() && !isReceptionist()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';
$error = '';

// Handle cash-up submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_cashup'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $actual_cash = floatval($_POST['actual_cash']);
        $date = $_POST['date'];
        $time = $_POST['time'];
        $notes = trim($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        // Calculate expected revenue
        $expected_cash = $db->query("
            SELECT COALESCE(SUM(cash_amount), 0) 
            FROM (
                SELECT total as cash_amount FROM sales WHERE payment_method = 'cash' AND DATE(created_at) = ?
            ) c
        ")->fetchColumn([$date]);
        
        // Add other payment methods
        $card_sales = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'card' AND DATE(paid_at) = ?")->fetchColumn([$date]);
        $eft_sales = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'eft' AND DATE(paid_at) = ?")->fetchColumn([$date]);
        $paystack_sales = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'paystack' AND DATE(paid_at) = ?")->fetchColumn([$date]);
        $yaco_sales = 0; // Yaco is recorded as 'card' in our system
        $online_payments = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'paystack' AND DATE(paid_at) = ?")->fetchColumn([$date]);
        $refunds = $db->query("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE DATE(created_at) = ? AND status = 'approved'")->fetchColumn([$date]);
        
        $total_expected = $expected_cash + $card_sales + $eft_sales + $paystack_sales + $online_payments - $refunds;
        $variance = $actual_cash - $expected_cash;
        
        $status = 'submitted';
        
        $stmt = $db->prepare("INSERT INTO cash_ups (cashier_id, date, time, cash_sales, card_sales, eft_sales, paystack_sales, yaco_sales, online_payments, refunds, total_expected, actual_counted, variance, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $date, $time, $expected_cash, $card_sales, $eft_sales, $paystack_sales, $yaco_sales, $online_payments, $refunds, $total_expected, $actual_cash, $variance, $notes, $status])) {
            $msg = "Cash-up submitted successfully. Variance: " . formatCurrency($variance);
        } else {
            $error = "Failed to submit cash-up.";
        }
    }
}

// Handle approve cash-up
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $db->prepare("UPDATE cash_ups SET status = 'approved' WHERE id = ?")->execute([$id]);
    $msg = "Cash-up approved.";
}

// Handle close cash-up
if (isset($_GET['close'])) {
    $id = intval($_GET['close']);
    $db->prepare("UPDATE cash_ups SET status = 'closed' WHERE id = ?")->execute([$id]);
    $msg = "Cash-up closed.";
}

// Get today's cash-up if exists
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM cash_ups WHERE DATE(date) = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$today]);
$todayCashUp = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all cash-ups
$cashUps = $db->query("SELECT c.*, u.full_name as cashier_name FROM cash_ups c JOIN users u ON c.cashier_id = u.id ORDER BY c.date DESC, c.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">End-of-Day Cash-Up</h2>
        <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <?php if (!$todayCashUp): ?>
        <!-- Submit Cash-Up Form -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Submit Cash-Up for Today (<?= date('Y-m-d') ?>)</h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label>Cash Sales</label>
                        <input type="number" step="0.01" name="expected_cash" class="form-control" 
                               value="<?= $db->query("SELECT COALESCE(SUM(total), 0) FROM sales WHERE payment_method = 'cash' AND DATE(created_at) = CURDATE()")->fetchColumn() ?>" readonly>
                        <small class="text-muted">Auto-calculated</small>
                    </div>
                    <div class="col-md-3">
                        <label>Card Sales</label>
                        <input type="number" step="0.01" name="expected_card" class="form-control" 
                               value="<?= $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method IN ('card', 'paystack') AND DATE(paid_at) = CURDATE()")->fetchColumn() ?>" readonly>
                        <small class="text-muted">Auto-calculated</small>
                    </div>
                    <div class="col-md-3">
                        <label>EFT Sales</label>
                        <input type="number" step="0.01" name="expected_eft" class="form-control" 
                               value="<?= $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'eft' AND DATE(paid_at) = CURDATE()")->fetchColumn() ?>" readonly>
                        <small class="text-muted">Auto-calculated</small>
                    </div>
                    <div class="col-md-3">
                        <label>Online Payments</label>
                        <input type="number" step="0.01" name="expected_online" class="form-control" 
                               value="<?= $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'paystack' AND DATE(paid_at) = CURDATE()")->fetchColumn() ?>" readonly>
                        <small class="text-muted">Auto-calculated</small>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <label>Actual Cash Counted (R) *</label>
                        <input type="number" step="0.01" name="actual_cash" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Time</label>
                        <input type="time" name="time" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mt-3">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about discrepancies..."></textarea>
                </div>
                <button type="submit" name="submit_cashup" class="btn btn-success mt-3">Submit Cash-Up</button>
            </form>
        </div>
        <?php else: ?>
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Today's Cash-Up Status</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-hourglass-start fa-2x mb-2"></i>
                            <h5><?= ucfirst($todayCashUp['status']) ?></h5>
                            <small>Status</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-coins fa-2x mb-2"></i>
                            <h5><?= formatCurrency($todayCashUp['total_expected']) ?></h5>
                            <small>Expected</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                            <h5><?= formatCurrency($todayCashUp['actual_counted']) ?></h5>
                            <small>Actual Counted</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-balance-scale fa-2x <?= $todayCashUp['variance'] >= 0 ? 'text-success' : 'text-danger' ?> mb-2"></i>
                            <h5><?= formatCurrency($todayCashUp['variance']) ?></h5>
                            <small>Variance</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($todayCashUp['status'] === 'submitted'): ?>
            <div class="mt-3">
                <a href="?approve=<?= $todayCashUp['id'] ?>" class="btn btn-success me-2">Approve</a>
                <a href="?close=<?= $todayCashUp['id'] ?>" class="btn btn-primary">Close</a>
            </div>
            <?php elseif ($todayCashUp['status'] === 'approved'): ?>
            <div class="mt-3">
                <a href="?close=<?= $todayCashUp['id'] ?>" class="btn btn-primary">Close</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Cash-Up History -->
        <div class="card bg-dark text-white p-3">
            <h5>Cash-Up History</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Cashier</th>
                        <th>Expected</th>
                        <th>Actual</th>
                        <th>Variance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cashUps as $cu): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($cu['date'])) ?></td>
                        <td><?= htmlspecialchars($cu['cashier_name']) ?></td>
                        <td><?= formatCurrency($cu['total_expected']) ?></td>
                        <td><?= formatCurrency($cu['actual_counted']) ?></td>
                        <td class="<?= $cu['variance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatCurrency($cu['variance']) ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                $cu['status'] === 'closed' ? 'success' : 
                                ($cu['status'] === 'approved' ? 'primary' : 'warning') 
                            ?>"><?= ucfirst($cu['status']) ?></span>
                        </td>
                        <td>
                            <?php if ($cu['status'] !== 'closed'): ?>
                                <?php if ($cu['status'] === 'submitted'): ?>
                                    <a href="?approve=<?= $cu['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                <?php endif; ?>
                                <?php if ($cu['status'] === 'approved'): ?>
                                    <a href="?close=<?= $cu['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-close"></i> Close
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>