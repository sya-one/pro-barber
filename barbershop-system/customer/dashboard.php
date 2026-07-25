<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
session_start();
if (!isset($_SESSION['customer_id'])) { header("Location: login.php"); exit; }

$db = (new Database())->getConnection();
$customer_id = $_SESSION['customer_id'];

// Fetch customer info
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle booking form submission (from modal)
$booking_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_submit'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $service_id  = intval($_POST['service_id']);
    $barber_id   = intval($_POST['barber_id']);
    $date        = $_POST['booking_date'];
    $time        = $_POST['booking_time'];

    // Check availability
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE barber_id=? AND booking_date=? AND booking_time=? AND status!='cancelled'");
    $stmt->execute([$barber_id, $date, $time]);
    if ($stmt->fetchColumn() > 0) {
        $booking_success = 'Slot taken, please choose another time.';
    } else {
        $booking_code = 'TEMP';
        $stmt = $db->prepare("INSERT INTO bookings (booking_code, customer_id, barber_id, service_id, booking_date, booking_time, status, booking_type)
                              VALUES (?, ?, ?, ?, ?, ?, 'pending', 'online')");
        $stmt->execute([$booking_code, $customer_id, $barber_id, $service_id, $date, $time]);
        $booking_id = $db->lastInsertId();
        $booking_code = 'pro-bk-' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE bookings SET booking_code = ? WHERE id = ?")->execute([$booking_code, $booking_id]);

        // Update visit count
        $db->prepare("UPDATE customers SET visit_count = visit_count + 1 WHERE id = ?")->execute([$customer_id]);

        // Notify staff
        notifyRole($db, 'admin', "New online booking $booking_code by {$customer['full_name']}");
        notifyRole($db, 'receptionist', "New online booking $booking_code by {$customer['full_name']}");

        $booking_success = "Booking successful! Your code: $booking_code";
    }
}

// Future bookings
$bookings = $db->prepare("
    SELECT b.*, s.name AS service_name, br.full_name AS barber_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN barbers br ON b.barber_id = br.id
    WHERE b.customer_id = ? AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date, b.booking_time
");
$bookings->execute([$customer_id]);
$future = $bookings->fetchAll(PDO::FETCH_ASSOC);

// Past bookings
$past = $db->prepare("
    SELECT b.*, s.name AS service_name, br.full_name AS barber_name
    FROM bookings b
    JOIN services s ON b.service_id = s.id
    JOIN barbers br ON b.barber_id = br.id
    WHERE b.customer_id = ? AND b.booking_date < CURDATE()
    ORDER BY b.booking_date DESC
    LIMIT 10
");
$past->execute([$customer_id]);
$past = $past->fetchAll(PDO::FETCH_ASSOC);

// Fetch services & barbers for the modal dropdowns
$services = $db->query("SELECT * FROM services WHERE is_active = 1")->fetchAll();
$barbers  = $db->query("SELECT id, full_name FROM barbers WHERE is_active = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Dashboard | The Professional Barbershop</title>
    <link rel="icon" type="image/png" href="../assets/images/default-avatar.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #000; color: #fff; font-family: 'Inter', sans-serif; font-size: 14px; }
        .stat-card { border: 1px solid #0FA958; border-radius: 10px; background: rgba(27,27,27,0.85); }
        .modal-content { background: #1B1B1B; color: #fff; border: 1px solid #0FA958; }
        .form-control, .form-select { background-color: #222 !important; color: #fff !important; border: 1px solid #0FA958 !important; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.2rem rgba(15,169,88,0.25); }
        .table-dark { --bs-table-bg: #1B1B1B; }
        .btn-success { background-color: #0FA958; border-color: #0FA958; }
        .btn-outline-danger { border-color: #dc3545; color: #dc3545; }
        .btn-outline-danger:hover { background-color: #dc3545; color: #fff; }
        .booking-modal .modal-dialog { margin: 1rem; }
        @media (max-width: 576px) {
            h2 { font-size: 1.4rem; }
            .stat-card h5 { font-size: 1.2rem; }
            .btn { font-size: 0.9rem; }
        }
    </style>
</head>
<body class="bg-dark text-white">
<div class="container-fluid py-3 px-3">
    <!-- Header -->
    <div class="row align-items-center mb-4">
        <div class="col-8">
            <h2 class="mb-0 text-truncate"><?= htmlspecialchars($customer['full_name']) ?></h2>
            <small class="text-muted">Customer #<?= $customer_id ?></small>
        </div>
        <div class="col-4 text-end">
            <button class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#bookingModal" title="Book Appointment">
                <i class="fas fa-calendar-plus"></i> <span class="d-none d-sm-inline">Book</span>
            </button>
            <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
            </a>
        </div>
    </div>

    <?php if ($booking_success): ?>
        <div class="alert alert-<?= strpos($booking_success, 'successful') !== false ? 'success' : 'danger' ?> py-2">
            <?= htmlspecialchars($booking_success) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards (stacked on mobile) -->
    <div class="row g-2 mb-4">
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-dark text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Loyalty Points</span>
                    <h5 class="mb-0"><?= $customer['loyalty_points'] ?></h5>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-dark text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Total Visits</span>
                    <h5 class="mb-0"><?= $customer['visit_count'] ?></h5>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-dark text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Total Spent</span>
                    <h5 class="mb-0"><?= formatCurrency($customer['total_spent']) ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Appointments -->
    <h5 class="mb-2">Upcoming</h5>
    <?php if (empty($future)): ?>
        <p class="text-muted small">No upcoming appointments.</p>
    <?php else: ?>
        <div class="table-responsive mb-4">
            <table class="table table-dark table-sm small">
                <thead><tr><th>Code</th><th>Service</th><th>Barber</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($future as $b): ?>
                    <tr>
                        <td><?= $b['booking_code'] ?></td>
                        <td><?= htmlspecialchars($b['service_name']) ?></td>
                        <td><?= htmlspecialchars($b['barber_name']) ?></td>
                        <td><?= date('d M', strtotime($b['booking_date'])) ?> <?= $b['booking_time'] ?></td>
                        <td><span class="badge bg-<?= $b['status']=='confirmed'?'success':'warning' ?>"><?= $b['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Past Appointments -->
    <h5 class="mb-2">Past</h5>
    <?php if (empty($past)): ?>
        <p class="text-muted small">No past appointments.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-sm small">
                <thead><tr><th>Code</th><th>Service</th><th>Barber</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($past as $b): ?>
                    <tr>
                        <td><?= $b['booking_code'] ?></td>
                        <td><?= htmlspecialchars($b['service_name']) ?></td>
                        <td><?= htmlspecialchars($b['barber_name']) ?></td>
                        <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Booking Modal (mobile‑friendly) -->
<div class="modal fade booking-modal" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fas fa-cut me-1"></i>Book Appointment</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="modalBookingForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="booking_submit" value="1">
                    
                    <div class="mb-2">
                        <label class="form-label small">Service</label>
                        <select name="service_id" id="modalService" class="form-select form-select-sm" required>
                            <option value="">Select Service</option>
                            <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>">
                                <?= htmlspecialchars($s['name']) ?> - R<?= $s['price'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Barber</label>
                        <select name="barber_id" id="modalBarber" class="form-select form-select-sm" required>
                            <option value="">Choose Barber</option>
                            <?php foreach ($barbers as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Date</label>
                        <input type="date" name="booking_date" id="modalDate" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small">Time</label>
                        <select name="booking_time" id="modalTime" class="form-select form-select-sm" required>
                            <option value="">Select Time</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" class="btn btn-success btn-sm w-100">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    function loadModalSlots() {
        let date = $('#modalDate').val();
        let barber = $('#modalBarber').val();
        let service = $('#modalService').val();
        if (date && barber && service) {
            $.getJSON('../ajax/check_availability.php', { date, barber_id: barber, service_id: service }, function(data){
                let $select = $('#modalTime').empty();
                if (data.length) {
                    data.forEach(t => $select.append(`<option value="${t}">${t}</option>`));
                } else {
                    $select.append('<option value="">No slots available</option>');
                }
            });
        }
    }
    $('#modalDate, #modalBarber, #modalService').on('change', loadModalSlots);
});
</script>
</body>
</html>