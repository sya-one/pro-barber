<?php
require_once 'shop_functions.php';
require_once __DIR__ . '/barbershop-system/includes/csrf.php';
session_start();
$db = getDb();
$settings = getSettings();
$cartCount = count(getCart());

$services = $db->query("SELECT * FROM services WHERE is_active=1")->fetchAll();
$barbers = $db->query("SELECT * FROM barbers WHERE is_active=1")->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $service_id = intval($_POST['service_id']);
        $barber_id = intval($_POST['barber_id'] ?? 0);
        $date = $_POST['booking_date'];
        $time = $_POST['booking_time'];

        // Validate required fields
        if (empty($full_name) || empty($phone) || empty($service_id) || empty($date) || empty($time)) {
            $error = 'Please fill all required fields';
        } else {
            // Check availability
            $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE barber_id=? AND booking_date=? AND booking_time=? AND status!='cancelled'");
            $stmt->execute([$barber_id, $date, $time]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Time slot no longer available. Please choose another.';
            } else {
                // Find or create customer
                $stmt = $db->prepare("SELECT id FROM customers WHERE email=? OR phone=? LIMIT 1");
                $stmt->execute([$email, $phone]);
                if ($cust = $stmt->fetch()) {
                    $customer_id = $cust['id'];
                } else {
                    $stmt = $db->prepare("INSERT INTO customers (full_name, email, phone) VALUES (?,?,?)");
                    $stmt->execute([$full_name, $email, $phone]);
                    $customer_id = $db->lastInsertId();
                }

                // Create booking
                $stmt = $db->prepare("INSERT INTO bookings (booking_code, customer_id, barber_id, service_id, booking_date, booking_time, status, booking_type) VALUES ('TEMP', ?, ?, ?, ?, ?, 'pending', 'online')");
                $stmt->execute([$customer_id, $barber_id, $service_id, $date, $time]);
                $booking_id = $db->lastInsertId();
                $booking_code = 'pro-bk-' . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
                $db->prepare("UPDATE bookings SET booking_code = ? WHERE id = ?")->execute([$booking_code, $booking_id]);

                // Notify admin
                notifyAdminEmail($db, "New online booking: $booking_code", "<p>New booking from $full_name</p><p>Date: $date at $time</p>");
                notifyAdminWhatsApp($db, "New booking: $booking_code by $full_name, $date at $time");

                $success = "Booking successful! Your code: <strong>$booking_code</strong>";
            }
        }
    }
}

// Get params for pre-selection
$prefService = intval($_GET['service'] ?? 0);
$prefBarber = intval($_GET['barber'] ?? 0);

$pageTitle = 'Book Appointment';
$pageDescription = 'Book your appointment online at The Professional Barbershop. Choose your service, preferred barber, and time slot.';
?>
<?php include 'includes/header.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Book Your Appointment</h1>
            <p class="lead">Select service, barber, and time - it takes seconds</p>
        </div>
    </section>

    <!-- Booking Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="booking-form p-4 p-md-5">
                        <?php if ($success): ?>
                            <div class="alert alert-success text-center mb-4"><?= $success ?></div>
                            <div class="text-center">
                                <a href="/book" class="btn btn-success"><i class="fas fa-plus me-2"></i>Book Another</a>
                                <a href="/shop" class="btn btn-outline-light"><i class="fas fa-shopping-bag me-2"></i>Shop Products</a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>

                            <form method="post" id="bookingForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="full_name" class="form-control" placeholder="Your name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone *</label>
                                        <input type="tel" name="phone" class="form-control" placeholder="0612345678" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" placeholder="your@email.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Service *</label>
                                        <select name="service_id" id="service" class="form-select" required>
                                            <option value="">Select Service</option>
                                            <?php foreach ($services as $s): ?>
                                            <option value="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>" <?= $prefService == $s['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['name']) ?> - <?= formatCurrency($s['price']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Barber *</label>
                                        <select name="barber_id" id="barber" class="form-select" required>
                                            <option value="">Choose Barber</option>
                                            <?php foreach ($barbers as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= $prefBarber == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date *</label>
                                        <input type="date" name="booking_date" id="booking_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Time *</label>
                                        <select name="booking_time" id="booking_time" class="form-select" required>
                                            <option value="">Select Time</option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 mt-4"><i class="fas fa-check-circle me-2"></i>Confirm Booking</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php $extraJs = '
    function loadSlots() {
        let date = $("#booking_date").val();
        let barber = $("#barber").val();
        let service = $("#service").val();
        if (date && barber && service) {
            $.getJSON("barbershop-system/ajax/check_availability.php", { date, barber_id: barber, service_id: service }, function(data) {
                let $select = $("#booking_time").empty();
                if (data.length) {
                    data.forEach(t => $select.append("<option value=\""+t+"\">"+t+"</option>"));
                } else {
                    $select.append("<option value=\"\">No slots available</option>");
                }
            });
        }
    }

    $("#booking_date, #barber, #service").on("change", loadSlots);
'; ?>
<?php include 'includes/footer.php'; ?>