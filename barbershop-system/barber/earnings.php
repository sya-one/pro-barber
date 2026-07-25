<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isBarber()) { header("Location: ../login.php"); exit; }

$db = getDb();
$user_id = $_SESSION['user_id'];

// Get barber's ID and commission rate
$stmt = $db->prepare("SELECT id, commission_rate, commission_type FROM barbers WHERE user_id = ?");
$stmt->execute([$user_id]);
$barber = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barber) {
    // Create barber record if missing (same logic as profile)
    $user = $db->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $u = $user->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $stmt = $db->prepare("INSERT INTO barbers (user_id, full_name, email, phone, photo, commission_rate, is_active) VALUES (?, ?, ?, ?, 'default.jpg', 30.00, 1)");
        $stmt->execute([$user_id, $u['full_name'], $u['email'], $u['phone']]);
        $barber_id = $db->lastInsertId();
        $commission_rate = 30.00;
        $commission_type = 'percentage';
    } else {
        die('User not found.');
    }
} else {
    $barber_id = $barber['id'];
    $commission_rate = $barber['commission_rate'];
    $commission_type = $barber['commission_type'] ?? 'percentage';
}

// Fetch monthly earnings (last 12 months), pre-fill with zeros
$earnings = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $earnings[$month] = 0;
}

// Get commissions from the commissions table
$stmt = $db->prepare("
    SELECT DATE_FORMAT(c.created_at, '%Y-%m') AS month, SUM(c.amount) AS commission
    FROM commissions c
    WHERE c.barber_id = ?
    AND c.status IN ('earned', 'paid')
    AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
");
$stmt->execute([$barber_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $earnings[$row['month']] = (float) $row['commission'];
}

// Prepare chart data
$labels = array_keys($earnings);
$values = array_values($earnings);

// Total earned this month
$thisMonth = date('Y-m');
$thisMonthEarnings = $earnings[$thisMonth] ?? 0;

// Total all time
$totalEarnings = array_sum($values);

// Get commission summary
$commissionSummary = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN c.status = 'earned' THEN c.amount ELSE 0 END), 0) as earned,
        COALESCE(SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END), 0) as paid,
        COUNT(DISTINCT c.sale_id) as sales_count
    FROM commissions c
    WHERE c.barber_id = ?
")->fetch(PDO::FETCH_ASSOC);

$totalEarned = $commissionSummary['earned'] ?? 0;
$totalPaid = $commissionSummary['paid'] ?? 0;
$outstanding = $commissionSummary['earned'] - $commissionSummary['paid'];
$salesCount = $commissionSummary['sales_count'] ?? 0;

// Get cuts completed (services)
$cutsCompleted = $db->prepare("
    SELECT COUNT(*) 
    FROM bookings b 
    JOIN sale_items si ON b.id = si.booking_id
    WHERE b.barber_id = ? AND si.item_type = 'service'
")->fetchColumn();

// Get products sold
$productsSold = $db->prepare("
    SELECT SUM(si.quantity)
    FROM sale_items si
    JOIN commissions c ON si.sale_id = c.sale_id
    WHERE c.barber_id = ? AND si.item_type = 'product'
")->fetchColumn();

// Average customer spend
$avgSpend = $db->prepare("
    SELECT AVG(s.total)
    FROM sales s
    JOIN commissions c ON s.id = c.sale_id
    WHERE c.barber_id = ?
")->fetchColumn();
$avgSpend = $avgSpend ?: 0;
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">My Earnings</h2>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-cut fa-2x text-green mb-2"></i>
                        <h5><?= $cutsCompleted ?? 0 ?></h5>
                        <small>Cuts Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-cube fa-2x text-green mb-2"></i>
                        <h5><?= $productsSold ?? 0 ?></h5>
                        <small>Products Sold</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($thisMonthEarnings) ?></h5>
                        <small>This Month's Commission</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($totalEarnings) ?></h5>
                        <small>Total Earnings (12 months)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-earn fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($totalEarned) ?></h5>
                        <small>Total Earned</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-hand-holding-usd fa-2x text-success mb-2"></i>
                        <h5><?= formatCurrency($totalPaid) ?></h5>
                        <small>Paid Out</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <h5><?= formatCurrency($outstanding) ?></h5>
                        <small>Outstanding</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-tag fa-2x text-green mb-2"></i>
                        <h5><?= $commission_rate ?>%</h5>
                        <small>Commission Rate (<?= ucfirst($commission_type) ?>)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earnings Chart -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Monthly Earnings (Commission)</h5>
            <div style="height:200px;">
                <canvas id="earningsChart"></canvas>
            </div>
        </div>

        <!-- Sales History -->
        <div class="card bg-dark text-white p-3">
            <h5>Recent Sales</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Your Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $salesStmt = $db->prepare("
                        SELECT s.invoice_number, s.created_at, s.total, c.amount as commission
                        FROM sales s
                        JOIN commissions c ON s.id = c.sale_id
                        WHERE c.barber_id = ?
                        ORDER BY s.created_at DESC
                        LIMIT 20
                    ");
                    $salesStmt->execute([$barber_id]);
                    while ($sale = $salesStmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($sale['invoice_number']) ?></td>
                        <td><?= date('d M Y H:i', strtotime($sale['created_at'])) ?></td>
                        <td><?= formatCurrency($sale['total']) ?></td>
                        <td class="text-success"><?= formatCurrency($sale['commission']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const values = <?= json_encode($values) ?>;

const ctx = document.getElementById('earningsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Commission (R)',
            data: values,
            backgroundColor: '#0FA958',
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#aaa',
                    callback: function(value) { return 'R' + value; }
                },
                grid: { color: 'rgba(255,255,255,0.05)' }
            },
            x: {
                ticks: { color: '#aaa' },
                grid: { display: false }
            }
        }
    }
});
</script>
<?php include '../includes/footer.php'; ?>