<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();

// Get profitability data for products
$products = $db->query("
    SELECT 
        p.id,
        p.name,
        p.barcode,
        p.price as selling_price,
        p.cost_price,
        p.stock_quantity,
        COALESCE(SUM(si.quantity), 0) as total_sold,
        COALESCE(SUM(si.quantity * si.unit_price), 0) as total_revenue,
        COALESCE(SUM(si.quantity * p.cost_price), 0) as total_cost,
        (COALESCE(SUM(si.quantity * si.unit_price), 0) - COALESCE(SUM(si.quantity * p.cost_price), 0)) as profit,
        COALESCE(COUNT(DISTINCT si.sale_id), 0) as sales_count
    FROM products p
    LEFT JOIN sale_items si ON p.id = si.product_id AND si.item_type = 'product'
    WHERE p.is_active = 1
    GROUP BY p.id, p.name, p.barcode, p.price, p.cost_price, p.stock_quantity
    HAVING total_revenue > 0
    ORDER BY profit DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Get top products by revenue
$topProducts = $db->query("
    SELECT 
        p.name,
        p.barcode,
        COALESCE(SUM(si.quantity), 0) as total_sold,
        COALESCE(SUM(si.quantity * si.unit_price), 0) as total_revenue,
        COALESCE(SUM(si.quantity * p.cost_price), 0) as total_cost,
        (COALESCE(SUM(si.quantity * si.unit_price), 0) - COALESCE(SUM(si.quantity * p.cost_price), 0)) as profit,
        ROUND(((COALESCE(SUM(si.quantity * si.unit_price), 0) - COALESCE(SUM(si.quantity * p.cost_price), 0)) / 
               NULLIF(COALESCE(SUM(si.quantity * si.unit_price), 0), 0) * 100), 2) as profit_margin
    FROM products p
    LEFT JOIN sale_items si ON p.id = si.product_id AND si.item_type = 'product'
    WHERE p.is_active = 1
    GROUP BY p.id, p.name, p.barcode
    HAVING total_revenue > 0
    ORDER BY total_revenue DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalProductRevenue = 0;
$totalProductCost = 0;
$totalProductProfit = 0;
foreach ($products as $p) {
    $totalProductRevenue += $p['total_revenue'];
    $totalProductCost += $p['total_cost'];
    $totalProductProfit += $p['profit'];
}
$overallProfitMargin = $totalProductRevenue > 0 ? round(($totalProductProfit / $totalProductRevenue) * 100, 2) : 0;
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Product Profitability Reports</h2>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($totalProductRevenue) ?></h5>
                        <small>Product Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-pie fa-2x <?= $overallProfitMargin >= 50 ? 'text-success' : ($overallProfitMargin >= 30 ? 'text-warning' : 'text-danger') ?> mb-2"></i>
                        <h5><?= $overallProfitMargin ?>%</h5>
                        <small>Profit Margin</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-2x text-green mb-2"></i>
                        <h5><?= count($topProducts) ?></h5>
                        <small>Products Sold</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x <?= $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1")->fetchColumn() > 0 ? 'text-danger' : 'text-success' ?> mb-2"></i>
                        <h5><?= $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1")->fetchColumn() ?></h5>
                        <small>Low Stock Items</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products Table -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Top Products by Revenue</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Profit</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['barcode'] ?? '-') ?></td>
                        <td><?= $p['total_sold'] ?></td>
                        <td class="text-green"><?= formatCurrency($p['total_revenue']) ?></td>
                        <td class="text-warning"><?= formatCurrency($p['total_cost']) ?></td>
                        <td class="<?= $p['profit'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($p['profit']) ?></td>
                        <td class="<?= $p['profit_margin'] >= 50 ? 'text-success' : ($p['profit_margin'] >= 30 ? 'text-warning' : 'text-danger') ?>"><?= $p['profit_margin'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Full Product List -->
        <div class="card bg-dark text-white p-3">
            <h5>All Products with Profitability</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th>Selling Price</th>
                        <th>Cost Price</th>
                        <th>Stock</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['barcode'] ?? '-') ?></td>
                        <td><?= formatCurrency($p['selling_price']) ?></td>
                        <td class="text-warning"><?= formatCurrency($p['cost_price']) ?></td>
                        <td class="<?= $p['stock_quantity'] <= 5 ? 'text-danger' : 'text-success' ?>"><?= $p['stock_quantity'] ?></td>
                        <td><?= $p['total_sold'] ?></td>
                        <td class="text-green"><?= formatCurrency($p['total_revenue']) ?></td>
                        <td class="<?= $p['profit'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($p['profit']) ?></td>
                        <td class="<?= ($p['profit'] / $p['selling_price'] * 100) >= 50 ? 'text-success' : 'text-warning' ?>"><?= round(($p['profit'] / $p['selling_price'] * 100), 2) ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>