<?php
require_once '../config/database.php';
require_once '../includes/csrf.php';
require_once '../includes/functions.php';
session_start();

$db = (new Database())->getConnection();
$services = $db->query("SELECT * FROM services WHERE is_active=1")->fetchAll();
$barbers  = $db->query("SELECT * FROM barbers WHERE is_active=1")->fetchAll();
$success  = '';
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/barbershop-system/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $service_id  = intval($_POST['service_id']);
    $barber_id   = intval($_POST['barber_id']);
    $date        = $_POST['booking_date'];
    $time        = $_POST['booking_time'];

    // Check availability
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE barber_id=? AND booking_date=? AND booking_time=? AND status!='cancelled'");
    $stmt->execute([$barber_id, $date, $time]);
    if ($stmt->fetchColumn() > 0) {
        die('Slot taken, please choose another time.');
    }

    // Find or create customer
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $customer = $stmt->fetch();
    if ($customer) {
        $customer_id = $customer['id'];
        $db->prepare("UPDATE customers SET visit_count=visit_count+1 WHERE id=?")->execute([$customer_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO customers (full_name, email, phone) VALUES (?,?,?)");
        $stmt->execute([$full_name, $email, $phone]);
        $customer_id = $db->lastInsertId();
    }

    // Insert booking and generate code
    $stmt = $db->prepare("INSERT INTO bookings (booking_code, customer_id, barber_id, service_id, booking_date, booking_time, status, booking_type)
                          VALUES ('TEMP', ?, ?, ?, ?, ?, 'pending', 'online')");
    $stmt->execute([$customer_id, $barber_id, $service_id, $date, $time]);
    $booking_id = $db->lastInsertId();
    $booking_code = 'pro-bk-' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
    $db->prepare("UPDATE bookings SET booking_code = ? WHERE id = ?")->execute([$booking_code, $booking_id]);

    // System notifications
    notifyRole($db, 'admin', "New online booking $booking_code by $full_name");
    notifyRole($db, 'receptionist', "New online booking $booking_code by $full_name");

    // Email admin
    $subject = "New Booking: $booking_code";
    $body = "<p>A new online booking has been placed.</p>";
    $body .= "<p><strong>Customer:</strong> " . htmlspecialchars($full_name) . "<br>";
    $body .= "<strong>Booking Code:</strong> $booking_code<br>";
    $body .= "<strong>Date:</strong> $date at $time</p>";
    $body .= "<p><a href='{$base_url}admin/bookings.php'>View Booking</a></p>";
    notifyAdminEmail($db, $subject, $body);

    // WhatsApp admin
    $wa_msg = "New booking: $booking_code by $full_name, $date at $time";
    notifyAdminWhatsApp($db, $wa_msg);

    $success = "Booking successful! Your code: <strong>$booking_code</strong>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/default-avatar.png">
    <title>Online Booking | The Professional Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #000; color: #fff; font-family: 'Inter', sans-serif; }
        .booking-card { border: 1px solid #0FA958; border-radius: 15px; background: rgba(27,27,27,0.85); backdrop-filter: blur(10px); }
        .form-control, .form-select { background-color: #222 !important; color: #fff !important; border: 1px solid #0FA958 !important; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.2rem rgba(15,169,88,0.25); }
        .form-control::placeholder { color: #aaa !important; opacity: 1; }
        .form-select option { background: #222; color: #fff; }
        .btn-success { background-color: #0FA958; border-color: #0FA958; }
        .btn-success:hover { background-color: #0d9147; border-color: #0d9147; }
        .btn-outline-light:hover { background-color: #0FA958; border-color: #0FA958; color: #fff; }
        .traffic-free { color: #0FA958; }
        .traffic-moderate { color: #ffc107; }
        .traffic-busy { color: #dc3545; }
        .portal-link { transition: color 0.3s ease; }
        .portal-link:hover { color: #0FA958 !important; }
    </style>
</head>
<body class="bg-dark text-white">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Logo and shop name -->
            <div class="text-center mb-4">
                <img src="../assets/images/logo.png" alt="Logo" style="max-width: 120px; height: auto;">
                <h4 class="text-white mt-2">The Professional Barbershop</h4>
            </div>

            <!-- Barber Traffic Button -->
            <div class="text-center mb-3">
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#trafficModal">
                    <i class="fas fa-traffic-light me-1"></i> Barber Traffic
                </button>
            </div>

            <!-- Booking Form -->
            <div class="card booking-card bg-dark p-4">
                <h2 class="text-center mb-4"><i class="fas fa-cut"></i> Book Your Appointment</h2>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <form method="post" id="bookingForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                        </div>
                        <div class="col-md-6">
                            <input type="email" name="email" class="form-control" placeholder="Email">
                        </div>
                        <div class="col-md-6">
                            <input type="tel" name="phone" class="form-control" placeholder="Phone" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <select name="service_id" id="service" class="form-select" required>
                                <option value="">Select Service</option>
                                <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>"><?= $s['name'] ?> - R<?= $s['price'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <select name="barber_id" id="barber" class="form-select" required>
                                <option value="">Choose Barber</option>
                                <?php foreach ($barbers as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['full_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <input type="date" name="booking_date" id="booking_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <select name="booking_time" id="booking_time" class="form-select" required>
                                <option value="">Select Time</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mt-4">Confirm Booking</button>
                </form>
            </div>

            <!-- Customer Portal Link -->
            <div class="text-center mt-4">
                <a href="../customer/login.php" class="btn btn-outline-light btn-sm portal-link">
                    <i class="fas fa-user me-1"></i> My Account / Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Barber Traffic Modal -->
<div class="modal fade" id="trafficModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white" style="border:1px solid #0FA958;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-traffic-light me-2"></i>Barber Traffic</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="trafficContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">Loading traffic data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted">Traffic reflects today's live queue & appointments.</small>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    // Time slot loading
    function loadSlots() {
        let date = $('#booking_date').val();
        let barber = $('#barber').val();
        let service = $('#service').val();
        if (date && barber && service) {
            $.getJSON('../ajax/check_availability.php', { date, barber_id: barber, service_id: service }, function(data){
                let $select = $('#booking_time').empty();
                if (data.length) {
                    data.forEach(t => $select.append(`<option value="${t}">${t}</option>`));
                } else {
                    $select.append('<option value="">No slots available</option>');
                }
            });
        }
    }
    $('#booking_date, #barber, #service').on('change', loadSlots);

    // Barber Traffic modal
    $('#trafficModal').on('show.bs.modal', function() {
        $.getJSON('../ajax/barber_traffic.php', function(data) {
            let html = '';
            if (!data || data.length === 0) {
                html = '<p class="text-center">No barbers available.</p>';
            } else {
                data.forEach(function(barber) {
                    let totalLoad = parseInt(barber.appointments) + parseInt(barber.queue);
                    let statusClass = 'traffic-free';
                    let statusText = 'Free';
                    if (totalLoad >= 4) {
                        statusClass = 'traffic-busy';
                        statusText = 'Busy';
                    } else if (totalLoad >= 1) {
                        statusClass = 'traffic-moderate';
                        statusText = 'Moderate';
                    }
                    html += `<div class="d-flex align-items-center mb-3 p-2 rounded" style="background:rgba(255,255,255,0.05);">
                        <img src="../uploads/barbers/${barber.photo || 'default.jpg'}" width="40" height="40" class="rounded-circle me-3" onerror="this.src='../assets/images/default-avatar.png';" style="object-fit:cover;">
                        <div class="flex-grow-1">
                            <strong>${barber.full_name}</strong><br>
                            <small class="text-muted">Appts: ${barber.appointments} | Queue: ${barber.queue}</small>
                        </div>
                        <span class="fw-bold ${statusClass}">${statusText}</span>
                    </div>`;
                });
            }
            $('#trafficContent').html(html);
        });
    });
});
</script>
</body>
</html>