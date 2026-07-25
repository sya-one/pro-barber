<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin() && !isReceptionist()) { header("Location: ../login.php"); exit; }

$db = getDb();

// Initialize with default values
$todayRevenue = 0;
$weeklyRevenue = 0;
$monthlyRevenue = 0;
$yearlyRevenue = 0;
$totalRevenue = 0;
$todayExpenses = 0;
$monthlyExpenses = 0;
$totalExpenses = 0;
$netProfit = 0;
$totalCOGS = 0;
$grossProfit = 0;
$pipelineRevenue = 0;
$outstandingBookings = 0;
$monthlyRefunds = 0;
$totalRefunds = 0;

// Try to get revenue data
try {
    $totalRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
    $todayRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(paid_at) = CURDATE()")->fetchColumn();
    $weeklyRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND paid_at < CURDATE()")->fetchColumn();
    $monthlyRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(paid_at) = MONTH(CURDATE()) AND YEAR(paid_at) = YEAR(CURDATE())")->fetchColumn();
    $yearlyRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE YEAR(paid_at) = YEAR(CURDATE())")->fetchColumn();
} catch (Exception $e) {}

// Try to get expenses data
try {
    $todayExpenses = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE(date) = CURDATE()")->fetchColumn();
    $monthlyExpenses = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")->fetchColumn();
    $totalExpenses = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
} catch (Exception $e) {}

// Net profit
$netProfit = $todayRevenue - $todayExpenses;

// Gross profit (revenue - cost of goods sold)
try {
    $stmt = $db->prepare("SELECT SUM(si.quantity * p.cost_price) FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.item_type = 'product'");
    $stmt->execute();
    $totalCOGS = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

$grossProfit = $totalRevenue - $totalCOGS;

// Pipeline revenue (pending, confirmed, in-progress)
try {
    $pipelineRevenue = $db->query("
        SELECT COALESCE(SUM(s.total), 0) 
        FROM sales s
        JOIN bookings b ON s.id = b.sale_id
        WHERE s.payment_status = 'pending'
    ")->fetchColumn();
} catch (Exception $e) {}

// Outstanding bookings (bookings without sales)
try {
    $outstandingBookings = $db->query("
        SELECT COUNT(*)
        FROM bookings b
        LEFT JOIN sales s ON b.id = s.booking_id
        WHERE s.id IS NULL AND b.status IN ('pending', 'confirmed')
    ")->fetchColumn();
} catch (Exception $e) {}

// Refunds
try {
    $monthlyRefunds = $db->query("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'approved' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $totalRefunds = $db->query("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'approved'")->fetchColumn();
} catch (Exception $e) {}

// Chart data: last 30 days
$chartData = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartData[$day] = 0;
}
$data = $db->query("
    SELECT DATE(paid_at) AS day, SUM(amount) AS total
    FROM payments
    WHERE paid_at >= DATE(NOW()) - INTERVAL 30 DAY
    GROUP BY DATE(paid_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($data as $row) {
    $chartData[$row['day']] = (float) $row['total'];
}

$labels = array_keys($chartData);
$values = array_values($chartData);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Financial Dashboard</h2>

        <!-- Key Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($todayRevenue) ?></h5>
                        <small>Today's Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($monthlyRevenue) ?></h5>
                        <small>This Month</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-bar fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($yearlyRevenue) ?></h5>
                        <small>This Year</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-pie fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($grossProfit) ?></h5>
                        <small>Gross Profit</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-danger mb-2"></i>
                        <h5><?= formatCurrency($totalExpenses) ?></h5>
                        <small>Total Expenses</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-balance-scale fa-2x <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?> mb-2"></i>
                        <h5><?= formatCurrency($netProfit) ?></h5>
                        <small>Net Profit</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h5><?= formatCurrency($pipelineRevenue) ?></h5>
                        <small>Pipeline Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-undo fa-2x text-danger mb-2"></i>
                        <h5><?= formatCurrency($totalRefunds) ?></h5>
                        <small>Total Refunds</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card bg-dark text-white p-3">
                    <h5>Revenue Last 30 Days</h5>
                    <div style="height: 250px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5><i class="fas fa-file-invoice me-2"></i>Quick Actions</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="sales.php" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar"></i> Sales Report</a>
                        <a href="expenses.php" class="btn btn-outline-light btn-sm"><i class="fas fa-receipt"></i> Expenses</a>
                        <a href="cashup.php" class="btn btn-outline-light btn-sm"><i class="fas fa-calculator"></i> Cash-Up</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card bg-dark text-white p-3">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Alerts</h5>
                    <?php
                    $lowStock = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1")->fetchColumn();
                    $pendingCommissions = $db->query("SELECT COUNT(*) FROM commissions WHERE status = 'earned'")->fetchColumn();
                    ?>
                    <?php if ($lowStock > 0): ?>
                    <div class="alert alert-warning p-2 mb-2">
                        <i class="fas fa-box"></i> <strong><?= $lowStock ?></strong> products low on stock
                    </div>
                    <?php endif; ?>
                    <?php if ($pendingCommissions > 0): ?>
                    <div class="alert alert-info p-2 mb-2">
                        <i class="fas fa-coins"></i> <strong><?= $pendingCommissions ?></strong> pending commissions
                    </div>
                    <?php endif; ?>
                    <?php if ($lowStock == 0 && $pendingCommissions == 0): ?>
                    <p class="text-muted">No alerts at this time.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;
const labels = Object.keys(chartData);
const values = Object.values(chartData);

const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Revenue (R)',
            data: values,
            borderColor: '#0FA958',
            backgroundColor: 'rgba(15,169,88,0.1)',
            tension: 0.3,
            fill: true,
            pointRadius: 3
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
                ticks: { color: '#aaa', maxRotation: 45, minRotation: 45 },
                grid: { display: false }
            }
        }
    }
});
</script>
<?php include '../includes/footer.php'; ?>