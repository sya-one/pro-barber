<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();

// -------- existing stats (corrected) --------
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$monthlyRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(paid_at) = MONTH(CURDATE()) AND YEAR(paid_at) = YEAR(CURDATE())")->fetchColumn();
$dailyRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = CURDATE()")->fetchColumn();

// Pipeline = pending + confirmed + in_progress
$pipeline = $db->query("SELECT COUNT(*) FROM bookings WHERE status IN ('pending','confirmed','in_progress')")->fetchColumn();
$walkinsToday = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_type='walk-in' AND DATE(created_at)=CURDATE()")->fetchColumn();
$onlineBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_type='online' AND DATE(created_at)=CURDATE()")->fetchColumn();
$pendingApps = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$activeBarbers = $db->query("SELECT COUNT(*) FROM barbers WHERE is_active=1")->fetchColumn();
$lowStockProducts = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1")->fetchColumn();

// Chart data: last 7 days
$chartRevenue = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartRevenue[$day] = 0;
}
$data = $db->query("
    SELECT DATE(paid_at) AS day, SUM(amount) AS total
    FROM payments
    WHERE paid_at >= DATE(NOW()) - INTERVAL 7 DAY
    GROUP BY DATE(paid_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($data as $row) {
    $chartRevenue[$row['day']] = (float) $row['total'];
}

// Top services
$topServices = $db->query("
    SELECT s.name, COUNT(b.id) as cnt
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    GROUP BY s.name
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll();

// Revenue by Method slicer
$revenueByMethod = $db->query("SELECT payment_method, SUM(amount) as total FROM payments GROUP BY payment_method")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 flex-grow-1" style="min-width: 0;">
        <h2 class="text-white mb-4">Admin Dashboard</h2>

        <!-- Stats Cards – Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-green"></i>
                        <h5 class="mt-2"><?= formatCurrency($totalRevenue) ?></h5>
                        <small>Total Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-green"></i>
                        <h5 class="mt-2"><?= formatCurrency($monthlyRevenue) ?></h5>
                        <small>This Month</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-green"></i>
                        <h5 class="mt-2"><?= formatCurrency($dailyRevenue) ?></h5>
                        <small>Today's Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-2x text-green"></i>
                        <h5 class="mt-2"><?= $lowStockProducts ?></h5>
                        <small>Low Stock Products</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards – Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-green"></i>
                        <h5 class="mt-2"><?= $pipeline ?></h5>
                        <small>Pipeline Bookings</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-spinner fa-2x text-green"></i>
                        <h5 class="mt-2"><?= $pendingApps ?></h5>
                        <small>Awaiting Confirmation</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-green"></i>
                        <h5 class="mt-2"><?= $activeBarbers ?></h5>
                        <small>Active Barbers</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-day fa-2x text-green"></i>
                        <h5 class="mt-2"><?= $walkinsToday + $onlineBookings ?></h5>
                        <small>Today's Bookings</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Revenue Chart (last 7 days) -->
            <div class="col-12">
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5>Revenue Last 7 Days</h5>
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Top Services -->
            <div class="col-12 col-lg-6">
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5>Top Services</h5>
                    <?php if (!empty($topServices)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($topServices as $s): ?>
                                <li class="list-group-item bg-dark text-white d-flex justify-content-between">
                                    <?= htmlspecialchars($s['name']) ?>
                                    <span class="badge bg-success"><?= $s['cnt'] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No service data yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Payment Methods -->
            <div class="col-12 col-lg-6">
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5>Payment Method Mix</h5>
                    <?php if (!empty($revenueByMethod)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($revenueByMethod as $method => $total): ?>
                                <li class="list-group-item bg-dark text-white d-flex justify-content-between">
                                    <?= ucfirst(htmlspecialchars($method)) ?>
                                    <span class="text-green"><?= formatCurrency($total) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No payment data yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartData = <?= json_encode($chartRevenue) ?>;
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
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => 'R' + v, color: '#aaa' },
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