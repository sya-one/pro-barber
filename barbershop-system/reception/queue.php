<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isReceptionist() && !isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';

// ---------- UPDATE QUEUE STATUS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = intval($_POST['queue_id']);
    $status = $_POST['status'];
    $db->prepare("UPDATE queue SET status = ? WHERE id = ?")->execute([$status, $id]);
    $msg = "Queue updated.";
}

// Fetch today's queue (excluding completed)
$queue = $db->query("
    SELECT q.*, COALESCE(c.full_name, q.walkin_name) AS customer, b.full_name AS barber, s.name AS service
    FROM queue q
    LEFT JOIN customers c ON q.customer_id = c.id
    JOIN barbers b ON q.barber_id = b.id
    JOIN services s ON q.service_id = s.id
    WHERE DATE(q.created_at) = CURDATE() AND q.status != 'completed'
    ORDER BY q.queue_number ASC
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Manage Queue</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="card bg-dark text-white p-3">
            <table class="table table-dark">
                <thead>
                    <tr><th>#</th><th>Customer</th><th>Barber</th><th>Service</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody id="queueTable">
                    <?php foreach ($queue as $q): ?>
                    <tr>
                        <td><strong><?= $q['queue_number'] ?></strong></td>
                        <td><?= htmlspecialchars($q['customer']) ?></td>
                        <td><?= htmlspecialchars($q['barber']) ?></td>
                        <td><?= htmlspecialchars($q['service']) ?></td>
                        <td><span class="badge bg-<?= $q['status']=='waiting'?'warning':($q['status']=='called'?'info':'success') ?>"><?= $q['status'] ?></span></td>
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
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Auto‑refresh every 30 seconds -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
function refreshQueue() {
    $.getJSON('../ajax/get_queue.php', function(data) {
        let html = '';
        if (!data.length) {
            html = '<tr><td colspan="6" class="text-center text-muted">Queue is empty.</td></tr>';
        } else {
            data.forEach(q => {
                let statusClass = q.status === 'waiting' ? 'warning' : (q.status === 'called' ? 'info' : 'success');
                html += `<tr>
                    <td><strong>${q.queue_number}</strong></td>
                    <td>${q.customer}</td>
                    <td>${q.barber}</td>
                    <td>${q.service}</td>
                    <td><span class="badge bg-${statusClass}">${q.status}</span></td>
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
        $('#queueTable').html(html);
    });
}
setInterval(refreshQueue, 30000);
</script>
<?php include '../includes/footer.php'; ?>