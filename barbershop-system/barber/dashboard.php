<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isBarber()) { header("Location: ../login.php"); exit; }

$db = getDb();
$user_id = $_SESSION['user_id'];

// Get this barber's ID and commission rate
$stmt = $db->prepare("SELECT id, commission_rate FROM barbers WHERE user_id = ?");
$stmt->execute([$user_id]);
$barber = $stmt->fetch(PDO::FETCH_ASSOC);
$barber_id = $barber['id'];
$commission_rate = $barber['commission_rate'] / 100;

// Stats
$todayAppointments = $db->prepare("SELECT COUNT(*) FROM bookings WHERE barber_id = ? AND booking_date = CURDATE() AND status != 'cancelled'");
$todayAppointments->execute([$barber_id]);
$todayAppointments = $todayAppointments->fetchColumn();

$queueCount = $db->prepare("SELECT COUNT(*) FROM queue WHERE barber_id = ? AND status != 'completed' AND DATE(created_at) = CURDATE()");
$queueCount->execute([$barber_id]);
$queueCount = $queueCount->fetchColumn();

$todayEarnings = $db->prepare("
    SELECT COALESCE(SUM(p.amount * ?), 0)
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE b.barber_id = ? AND DATE(p.paid_at) = CURDATE()
");
$todayEarnings->execute([$commission_rate, $barber_id]);
$todayEarnings = $todayEarnings->fetchColumn();

// Upcoming appointments for the barber (today and future)
$appointments = $db->prepare("
    SELECT b.*, c.full_name AS customer, s.name AS service, s.price
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN services s ON b.service_id = s.id
    WHERE b.barber_id = ? AND b.booking_date >= CURDATE() AND b.status != 'cancelled'
    ORDER BY b.booking_date, b.booking_time
    LIMIT 10
");
$appointments->execute([$barber_id]);
$appointments = $appointments->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Barber Dashboard</h2>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-green mb-2"></i>
                        <h5><?= $todayAppointments ?></h5>
                        <small>Today's Appointments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-list-ol fa-2x text-green mb-2"></i>
                        <h5><?= $queueCount ?></h5>
                        <small>Queue Waiting</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($todayEarnings) ?></h5>
                        <small>Today's Commission</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="card bg-dark text-white p-3">
            <h5>Upcoming Appointments</h5>
            <?php if (count($appointments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Price</th>
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
                                    ($a['status'] == 'pending' ? 'warning' : 'info')
                                ?>"><?= $a['status'] ?></span>
                            </td>
                            <td><?= formatCurrency($a['price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted">No upcoming appointments.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>