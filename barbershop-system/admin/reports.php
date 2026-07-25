<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();

// ---------- CSV EXPORT (before any output) ----------
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Booking Code', 'Customer', 'Amount', 'Method', 'Date']);
    $rows = $db->query("
        SELECT p.id, b.booking_code, c.full_name, p.amount, p.payment_method, p.paid_at
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN customers c ON b.customer_id = c.id
        ORDER BY p.paid_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) fputcsv($output, $row);
    fclose($output);
    exit;
}

// Summary stats
$totalRevenue     = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$monthlyRevenue   = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())")->fetchColumn();
$totalBookings    = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$avgBookingValue  = $totalBookings > 0 ? $totalRevenue / $totalBookings : 0;

// Revenue by service
$serviceRevenue = $db->query("
    SELECT s.name, COALESCE(SUM(p.amount),0) AS total
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    RIGHT JOIN services s ON b.service_id = s.id
    GROUP BY s.name
    ORDER BY total DESC
")->fetchAll();

// Top barbers
$barberRevenue = $db->query("
    SELECT br.full_name, COALESCE(SUM(p.amount),0) AS total, COUNT(b.id) AS jobs
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    RIGHT JOIN barbers br ON b.barber_id = br.id
    GROUP BY br.full_name
    ORDER BY total DESC
")->fetchAll();

// Monthly trend
$monthlyTrend = $db->query("
    SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month, SUM(amount) AS total
    FROM payments
    WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month
")->fetchAll();

// ---------- MOST SOLD PRODUCTS ----------
$productSales = $db->query("
    SELECT p.name, COALESCE(SUM(si.quantity),0) AS total_qty
    FROM products p
    LEFT JOIN sale_items si ON si.product_id = p.id AND si.item_type = 'product'
    GROUP BY p.name
    ORDER BY total_qty DESC
")->fetchAll();

// Helper: prepare chart data (always returns labels/values, even if empty)
function prepareChartData($data, $labelKey, $valueKey) {
    $labels = [];
    $values = [];
    if (empty($data)) {
        $labels = ['No data yet'];
        $values = [0];
    } else {
        foreach ($data as $row) {
            $labels[] = $row[$labelKey];
            $values[] = (float) $row[$valueKey];
        }
    }
    return ['labels' => $labels, 'values' => $values];
}

$serviceChart = prepareChartData($serviceRevenue, 'name', 'total');
$barberChart  = prepareChartData($barberRevenue, 'full_name', 'total');
$trendChart   = prepareChartData($monthlyTrend, 'month', 'total');
$productChart = prepareChartData($productSales, 'name', 'total_qty');
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Reports & Analytics</h2>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <h5><?= formatCurrency($totalRevenue) ?></h5>
                        <small>Total Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <h5><?= formatCurrency($monthlyRevenue) ?></h5>
                        <small>This Month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <h5><?= $totalBookings ?></h5>
                        <small>Total Bookings</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <h5><?= formatCurrency($avgBookingValue) ?></h5>
                        <small>Avg. Booking Value</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card bg-dark text-white p-3">
                    <h5>Revenue by Service</h5>
                    <div style="height:180px;">
                        <canvas id="serviceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark text-white p-3">
                    <h5>Barber Performance</h5>
                    <div style="height:180px;">
                        <canvas id="barberChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card bg-dark text-white p-3">
                    <h5>Most Sold Products</h5>
                    <div style="height:180px;">
                        <canvas id="productChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark text-white p-3">
                    <h5>Monthly Revenue Trend</h5>
                    <div style="height:180px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="card bg-dark text-white p-3">
            <h5>Export Reports</h5>
            <a href="?export=csv" class="btn btn-success me-2"><i class="fas fa-file-csv"></i> Export CSV</a>
            <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print / PDF</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Shared chart defaults
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
};

// --- Revenue by Service (Doughnut) ---
new Chart(document.getElementById('serviceChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($serviceChart['labels']) ?>,
        datasets: [{
            data: <?= json_encode($serviceChart['values']) ?>,
            backgroundColor: ['#0FA958','#198754','#20c997','#28a745','#218838','#1B4332','#40916c'],
            borderColor: '#1B1B1B',
            borderWidth: 2
        }]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#aaa' } },
            title: {
                display: (<?= json_encode($serviceChart['values']) ?>.length === 1 && <?= json_encode($serviceChart['values']) ?>[0] === 0),
                text: 'No service revenue yet',
                color: '#aaa',
                font: { size: 14 }
            }
        }
    }
});

// --- Barber Performance (Bar) ---
new Chart(document.getElementById('barberChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($barberChart['labels']) ?>,
        datasets: [{
            label: 'Revenue (R)',
            data: <?= json_encode($barberChart['values']) ?>,
            backgroundColor: '#0FA958',
            borderRadius: 5
        }]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { display: false },
            title: {
                display: (<?= json_encode($barberChart['values']) ?>.length === 1 && <?= json_encode($barberChart['values']) ?>[0] === 0),
                text: 'No barber earnings yet',
                color: '#aaa'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#aaa', callback: v => 'R' + v },
                grid: { color: 'rgba(255,255,255,0.05)' }
            },
            x: {
                ticks: { color: '#aaa' },
                grid: { display: false }
            }
        }
    }
});

// --- Most Sold Products (Bar) ---
new Chart(document.getElementById('productChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($productChart['labels']) ?>,
        datasets: [{
            label: 'Units Sold',
            data: <?= json_encode($productChart['values']) ?>,
            backgroundColor: '#20c997',
            borderRadius: 5
        }]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { display: false },
            title: {
                display: (<?= json_encode($productChart['values']) ?>.length === 1 && <?= json_encode($productChart['values']) ?>[0] === 0),
                text: 'No product sales yet',
                color: '#aaa'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#aaa', stepSize: 1, callback: v => v },
                grid: { color: 'rgba(255,255,255,0.05)' }
            },
            x: {
                ticks: { color: '#aaa' },
                grid: { display: false }
            }
        }
    }
});

// --- Monthly Trend (Line) ---
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendChart['labels']) ?>,
        datasets: [{
            label: 'Revenue (R)',
            data: <?= json_encode($trendChart['values']) ?>,
            borderColor: '#0FA958',
            backgroundColor: 'rgba(15,169,88,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { display: false },
            title: {
                display: (<?= json_encode($trendChart['values']) ?>.length === 1 && <?= json_encode($trendChart['values']) ?>[0] === 0),
                text: 'No revenue data yet',
                color: '#aaa'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#aaa', callback: v => 'R' + v },
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