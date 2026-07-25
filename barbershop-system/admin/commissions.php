<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// Handle commission payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_commission'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    
    $commission_id = intval($_POST['commission_id']);
    $paid_by = $_SESSION['user_id'];
    
    $db->beginTransaction();
    try {
        // Update commission as paid
        $stmt = $db->prepare("UPDATE commissions SET status = 'paid', paid_at = NOW(), paid_by = ? WHERE id = ?");
        $stmt->execute([$paid_by, $commission_id]);
        
        // Add to activity log
        logActivity($paid_by, 'Commission Paid', "Commission ID: $commission_id");
        
        $db->commit();
        $msg = "Commission marked as paid.";
    } catch (Exception $e) {
        $db->rollBack();
        $msg = "Error: " . $e->getMessage();
    }
}

// Get commission summary
$commissionSummary = $db->query("
    SELECT 
        br.id as barber_id,
        br.full_name,
        br.commission_rate,
        COALESCE(SUM(CASE WHEN c.status = 'earned' THEN c.amount ELSE 0 END), 0) as earned,
        COALESCE(SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END), 0) as paid,
        COALESCE(SUM(CASE WHEN c.status = 'earned' THEN c.amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END), 0) as outstanding
    FROM barbers br
    LEFT JOIN commissions c ON br.id = c.barber_id
    WHERE br.is_active = 1
    GROUP BY br.id, br.full_name, br.commission_rate
    ORDER BY br.full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get all pending commissions
$pendingCommissions = $db->query("
    SELECT 
        c.id,
        c.amount,
        c.rate_percent,
        c.created_at,
        br.full_name as barber_name,
        s.invoice_number,
        s.total as sale_total
    FROM commissions c
    JOIN barbers br ON c.barber_id = br.id
    JOIN sales s ON c.sale_id = s.id
    WHERE c.status = 'earned'
    ORDER BY c.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Barber Commissions</h2>
        <?php if($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

        <!-- Commission Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-green mb-2"></i>
                        <h5><?= count($pendingCommissions) ?></h5>
                        <small>Pending Commissions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-arrow-down fa-2x text-green mb-2"></i>
                        <h5>R<?= number_format(array_sum(array_column($commissionSummary, 'earned')), 2) ?></h5>
                        <small>Total Earned</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-arrow-up fa-2x text-green mb-2"></i>
                        <h5>R<?= number_format(array_sum(array_column($commissionSummary, 'paid')), 2) ?></h5>
                        <small>Paid Out</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-green mb-2"></i>
                        <h5>R<?= number_format(array_sum(array_column($commissionSummary, 'outstanding')), 2) ?></h5>
                        <small>Outstanding</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barber Commission Summary -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Commission Summary by Barber</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Barber</th>
                        <th>Rate</th>
                        <th>Earned</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commissionSummary as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['full_name']) ?></td>
                        <td><?= $c['commission_rate'] ?>%</td>
                        <td>R<?= number_format($c['earned'], 2) ?></td>
                        <td class="text-success">R<?= number_format($c['paid'], 2) ?></td>
                        <td class="text-warning">R<?= number_format($c['outstanding'], 2) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" 
                                    data-bs-target="#payModal<?= $c['barber_id'] ?>">
                                <i class="fas fa-hand-holding-usd"></i> Pay
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Pay Commission Modal -->
                    <div class="modal fade" id="payModal<?= $c['barber_id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content bg-dark text-white">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Pay Commissions to <?= htmlspecialchars($c['full_name']) ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>Outstanding Balance:</strong> R<?= number_format($c['outstanding'], 2) ?></p>
                                        <?php if (!empty($pendingCommissions)): ?>
                                        <table class="table table-sm table-dark">
                                            <thead>
                                                <tr><th>Invoice</th><th>Amount</th><th>Date</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingCommissions as $pc): ?>
                                                <?php if ($pc['barber_name'] === $c['full_name']): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($pc['invoice_number']) ?></td>
                                                    <td>R<?= number_format($pc['amount'], 2) ?></td>
                                                    <td><?= date('d M Y', strtotime($pc['created_at'])) ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php else: ?>
                                        <p class="text-muted">No pending commissions.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="pay_commission" class="btn btn-success" 
                                                data-id="<?= $pendingCommissions[0]['id'] ?? '' ?>" 
                                                <?= $c['outstanding'] <= 0 ? 'disabled' : '' ?>>
                                            Pay All (R<?= number_format($c['outstanding'], 2) ?>)
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pending Commissions Table -->
        <div class="card bg-dark text-white p-3">
            <h5>Pending Commissions</h5>
            <?php if (!empty($pendingCommissions)): ?>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Barber</th>
                        <th>Invoice</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingCommissions as $pc): ?>
                    <tr>
                        <td><?= htmlspecialchars($pc['barber_name']) ?></td>
                        <td><?= htmlspecialchars($pc['invoice_number']) ?></td>
                        <td>R<?= number_format($pc['amount'], 2) ?></td>
                        <td><?= date('d M Y H:i', strtotime($pc['created_at'])) ?></td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Mark this commission as paid?')">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="commission_id" value="<?= $pc['id'] ?>">
                                <button type="submit" name="pay_commission" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Mark Paid
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No pending commissions.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>