<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isReceptionist() && !isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';
$base_url = "http://localhost:81/barbershop-system/"; // change for production

// ---------- ADD NEW BOOKING ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $service_id  = intval($_POST['service_id']);
    $barber_id   = intval($_POST['barber_id']);
    $date        = $_POST['booking_date'];
    $time        = $_POST['booking_time'];
    $type        = $_POST['booking_type'];

    // 1. Insert / find customer
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $customer = $stmt->fetch();
    if ($customer) {
        $customer_id = $customer['id'];
        $db->prepare("UPDATE customers SET visit_count = visit_count + 1 WHERE id = ?")->execute([$customer_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO customers (full_name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$full_name, $email, $phone]);
        $customer_id = $db->lastInsertId();
    }

    // 2. Generate booking code
    $booking_code = 'TEMP';
    $stmt = $db->prepare("INSERT INTO bookings (booking_code, customer_id, barber_id, service_id, booking_date, booking_time, status, booking_type)
                          VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?)");
    $stmt->execute([$booking_code, $customer_id, $barber_id, $service_id, $date, $time, $type]);
    $booking_id = $db->lastInsertId();
    $booking_code = 'pro-bk-' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
    $db->prepare("UPDATE bookings SET booking_code = ? WHERE id = ?")->execute([$booking_code, $booking_id]);

    // System notifications
    notifyRole($db, 'admin', "New booking $booking_code by $full_name");
    notifyRole($db, 'receptionist', "New booking $booking_code by $full_name");

    // Email admin
    $subject = "New Booking: $booking_code";
    $body = "<p>A new booking has been created by reception.</p>";
    $body .= "<p><strong>Customer:</strong> " . htmlspecialchars($full_name) . "<br>";
    $body .= "<strong>Booking Code:</strong> $booking_code<br>";
    $body .= "<strong>Date:</strong> $date at $time</p>";
    $body .= "<p><a href='{$base_url}admin/bookings.php'>View Booking</a></p>";
    notifyAdminEmail($db, $subject, $body);

    // WhatsApp admin
    $wa_msg = "Booking created: $booking_code, $full_name, $date at $time";
    notifyAdminWhatsApp($db, $wa_msg);

    $msg = "Booking <strong>$booking_code</strong> created successfully.";
}

// ---------- APPROVE BOOKING ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_booking'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    $id = intval($_POST['booking_id']);
    $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$id]);
    $msg = "Booking confirmed.";
}

// Fetch services, barbers for the form
$services = $db->query("SELECT * FROM services WHERE is_active = 1")->fetchAll();
$barbers  = $db->query("SELECT * FROM barbers WHERE is_active = 1")->fetchAll();

// Fetch pending/confirmed online bookings for today (or future)
$bookings = $db->query("
    SELECT b.*, c.full_name AS customer, br.full_name AS barber, s.name AS service
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN barbers br ON b.barber_id = br.id
    JOIN services s ON b.service_id = s.id
    WHERE b.booking_type = 'online' AND b.booking_date >= CURDATE()
      AND b.status IN ('pending','confirmed')
    ORDER BY b.booking_date, b.booking_time
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Bookings</h2>
        <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- ADD NEW BOOKING CARD -->
        <div class="card bg-dark text-white p-4 mb-4">
            <h5>Add New Booking</h5>
            <form method="post" id="bookingForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Booking Type</label>
                        <select name="booking_type" class="form-select">
                            <option value="online">Online / Scheduled</option>
                            <option value="walk-in">Walk‑in</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Service</label>
                        <select name="service_id" id="service_id" class="form-select" required>
                            <option value="">Choose</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>">
                                    <?= htmlspecialchars($s['name']) ?> (R<?= $s['price'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Barber</label>
                        <select name="barber_id" id="barber_id" class="form-select" required>
                            <option value="">Choose</option>
                            <?php foreach ($barbers as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Date</label>
                        <input type="date" name="booking_date" id="booking_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label>Time</label>
                        <select name="booking_time" id="booking_time" class="form-select" required>
                            <option value="">Select time</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_booking" class="btn btn-success mt-3">Create Booking</button>
            </form>
        </div>

        <!-- PENDING / CONFIRMED ONLINE BOOKINGS -->
        <div class="card bg-dark text-white p-3">
            <h5>Approve Online Bookings</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Code</th><th>Customer</th><th>Barber</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= $b['booking_code'] ?></td>
                        <td><?= htmlspecialchars($b['customer']) ?></td>
                        <td><?= htmlspecialchars($b['barber']) ?></td>
                        <td><?= htmlspecialchars($b['service']) ?></td>
                        <td><?= $b['booking_date'] ?></td>
                        <td><?= $b['booking_time'] ?></td>
                        <td><span class="badge bg-<?= $b['status']=='confirmed'?'success':'warning' ?>"><?= $b['status'] ?></span></td>
                        <td>
                            <?php if ($b['status'] === 'pending'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <button type="submit" name="approve_booking" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted">Approved</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AJAX for time slots -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(function() {
    function loadSlots() {
        var date = $('#booking_date').val();
        var barber = $('#barber_id').val();
        var serviceId = $('#service_id').val();
        if (date && barber && serviceId) {
            $.getJSON('../ajax/check_availability.php', { date, barber_id: barber, service_id: serviceId }, function(data) {
                var $select = $('#booking_time').empty();
                if (data.length > 0) {
                    $.each(data, function(i, t) {
                        $select.append($('<option>', { value: t, text: t }));
                    });
                } else {
                    $select.append('<option value="">No slots available</option>');
                }
            });
        }
    }
    $('#booking_date, #barber_id, #service_id').on('change', loadSlots);
});
</script>
<?php include '../includes/footer.php'; ?>