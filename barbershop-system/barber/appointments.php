<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isBarber()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$user_id = $_SESSION['user_id'];

// Get this barber's id
$barber_id = $db->prepare("SELECT id FROM barbers WHERE user_id = ?");
$barber_id->execute([$user_id]);
$barber_id = $barber_id->fetchColumn();
$msg = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $booking_id = intval($_POST['booking_id']);
    $status = $_POST['status'];

    $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ? AND barber_id = ?");
    $stmt->execute([$status, $booking_id, $barber_id]);
    $msg = "Appointment status updated.";
}

// Fetch own appointments (today and future)
$appointments = $db->prepare("
    SELECT b.*, c.full_name AS customer, s.name AS service
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    WHERE b.barber_id = ? AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date, b.booking_time
");
$appointments->execute([$barber_id]);
$appointments = $appointments->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">My Appointments</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

        <div class="card bg-dark text-white p-3">
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= $a['booking_code'] ?></td>
                        <td><?= htmlspecialchars($a['customer']) ?></td>
                        <td><?= htmlspecialchars($a['service']) ?></td>
                        <td><?= $a['booking_date'] ?></td>
                        <td><?= $a['booking_time'] ?></td>
                        <td>
                            <span class="badge bg-<?=
                                $a['status'] == 'completed' ? 'success' :
                                ($a['status'] == 'pending' ? 'warning' :
                                ($a['status'] == 'confirmed' ? 'info' : 'primary'))
                            ?>"><?= $a['status'] ?></span>
                        </td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="booking_id" value="<?= $a['id'] ?>">
                                <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                    <option value="">Change</option>
                                    <option value="confirmed" <?= $a['status']=='confirmed'?'disabled':'' ?>>Confirm</option>
                                    <option value="in_progress" <?= $a['status']=='in_progress'?'disabled':'' ?>>Start</option>
                                    <option value="completed" <?= $a['status']=='completed'?'disabled':'' ?>>Complete</option>
                                    <option value="cancelled">Cancel</option>
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
<?php include '../includes/footer.php'; ?>