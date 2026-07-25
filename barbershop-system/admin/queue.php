<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// ---------- ADD WALK-IN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walkin'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $name = trim($_POST['walkin_name']) ?: 'Walk‑in';
    $barber_id = intval($_POST['barber_id']);
    $service_id = intval($_POST['service_id']);

    // Generate next queue number for today
    $max = $db->query("SELECT COALESCE(MAX(queue_number), 0) + 1 FROM queue WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    $stmt = $db->prepare("INSERT INTO queue (walkin_name, barber_id, service_id, queue_number, status) VALUES (?, ?, ?, ?, 'waiting')");
    $stmt->execute([$name, $barber_id, $service_id, $max]);
    $msg = "Walk‑in added. Queue number: $max";
}

// ---------- UPDATE QUEUE STATUS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = intval($_POST['queue_id']);
    $status = $_POST['status'];
    $stmt = $db->prepare("UPDATE queue SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);
    $msg = "Queue status updated.";
}

// Get statistics
$waitingCount = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'waiting' AND DATE(created_at) = CURDATE()")->fetchColumn();
$calledCount = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'called' AND DATE(created_at) = CURDATE()")->fetchColumn();
$inServiceCount = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'in_service' AND DATE(created_at) = CURDATE()")->fetchColumn();

$barbers = $db->query("SELECT * FROM barbers WHERE is_active = 1")->fetchAll();
$services = $db->query("SELECT * FROM services WHERE is_active = 1")->fetchAll();

// Get current queue (excluding completed)
$queue = $db->query("
    SELECT q.*, COALESCE(c.full_name, q.walkin_name) AS customer, b.full_name AS barber, s.name AS service, s.price
    FROM queue q
    LEFT JOIN customers c ON q.customer_id = c.id
    JOIN barbers b ON q.barber_id = b.id
    JOIN services s ON q.service_id = s.id
    WHERE q.status != 'completed' AND DATE(q.created_at) = CURDATE()
    ORDER BY q.queue_number ASC
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Walk‑in Queue</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h5 class="mb-1"><?= $waitingCount ?></h5>
                        <small>Waiting</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x text-info mb-2"></i>
                        <h5 class="mb-1"><?= $calledCount ?></h5>
                        <small>Called</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-briefcase fa-2x text-success mb-2"></i>
                        <h5 class="mb-1"><?= $inServiceCount ?></h5>
                        <small>In Service</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body text-center">
                        <a href="queue_tv.php" class="text-decoration-none">
                            <i class="fas fa-tv fa-2x text-green mb-2"></i>
                            <h5 class="mb-1">TV Display</h5>
                            <small class="text-muted">Waiting Room View</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Add Walk‑in Form -->
            <div class="col-md-4">
                <div class="card bg-dark text-white p-3 mb-3">
                    <h5>Add Walk‑in</h5>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="text" name="walkin_name" class="form-control mb-2" placeholder="Customer name (optional)">
                        <select name="barber_id" class="form-select mb-2" required>
                            <option value="">Choose Barber</option>
                            <?php foreach ($barbers as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="service_id" class="form-select mb-2" required>
                            <option value="">Choose Service</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (R<?= $s['price'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="add_walkin" class="btn btn-success w-100">Add to Queue</button>
                    </form>
                </div>
            </div>

            <!-- Queue List -->
            <div class="col-md-8">
                <div class="card bg-dark text-white p-3">
                    <h5>Today's Queue</h5>
                    <div class="table-responsive">
                        <table class="table table-dark" id="queueTable">
                            <thead>
                                <tr>
                                    <th>#</th><th>Customer</th><th>Barber</th><th>Service</th><th>Price</th><th>Status</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queue as $q): ?>
                                <tr>
                                    <td><strong class="fs-5"><?= $q['queue_number'] ?></strong></td>
                                    <td><?= htmlspecialchars($q['customer']) ?></td>
                                    <td><?= htmlspecialchars($q['barber']) ?></td>
                                    <td><?= htmlspecialchars($q['service']) ?></td>
                                    <td>R<?= number_format($q['price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $q['status']=='waiting'?'warning':($q['status']=='called'?'info':'success') ?> text-capitalize">
                                            <?= $q['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="queue_id" value="<?= $q['id'] ?>">
                                            <select name="status" class="form-select form-select-sm d-inline w-auto">
                                                <option value="">Change</option>
                                                <option value="called">Call</option>
                                                <option value="in_service">In Service</option>
                                                <option value="completed">Complete</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-sm btn-success ms-1">Update</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($queue)): ?>
                                <tr><td colspan="7" class="text-center text-muted">No one in queue today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Auto-refresh every 10 seconds -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    function refreshQueue() {
        $.getJSON('../ajax/get_queue.php', function(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="7" class="text-center text-muted">No one in queue today.</td></tr>';
            } else {
                $.each(data, function(i, q) {
                    let statusClass = q.status === 'waiting' ? 'warning' : (q.status === 'called' ? 'info' : 'success');
                    html += `<tr>
                        <td><strong class="fs-5">${q.queue_number}</strong></td>
                        <td>${q.customer}</td>
                        <td>${q.barber}</td>
                        <td>${q.service}</td>
                        <td>R${parseFloat(q.price).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td><span class="badge bg-${statusClass} text-capitalize">${q.status}</span></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="queue_id" value="${q.id}">
                                <select name="status" class="form-select form-select-sm d-inline w-auto">
                                    <option value="">Change</option>
                                    <option value="called">Call</option>
                                    <option value="in_service">In Service</option>
                                    <option value="completed">Complete</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-sm btn-success ms-1">Update</button>
                            </form>
                        </td>
                    </tr>`;
                });
            }
            $('#queueTable tbody').html(html);
        });
    }
    setInterval(refreshQueue, 10000);
});
</script>
<?php include '../includes/footer.php'; ?>