<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();

// Get all bookings with details
$bookings = $db->query("
    SELECT 
        b.id,
        b.booking_code,
        b.booking_date,
        b.booking_time,
        b.status,
        b.booking_type,
        c.full_name as customer_name,
        c.phone,
        br.full_name as barber_name,
        s.name as service_name,
        s.price,
        b.created_at
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN barbers br ON b.barber_id = br.id
    JOIN services s ON b.service_id = s.id
    ORDER BY b.booking_date, b.booking_time
")->fetchAll(PDO::FETCH_ASSOC);

// Get all services for filter
$services = $db->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get all barbers for filter
$barbers = $db->query("SELECT id, full_name FROM barbers WHERE is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$confirmedBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
$completedBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();
$cancelledBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();
$onlineBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_type = 'online'")->fetchColumn();
$walkins = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_type = 'walk-in'")->fetchColumn();

// Prepare bookings data for calendar
$bookingsData = [];
foreach ($bookings as $b) {
    $bookingsData[] = [
        'id' => $b['id'],
        'title' => $b['booking_code'] . ' - ' . $b['customer_name'],
        'start' => $b['booking_date'] . 'T' . $b['booking_time'],
        'end' => date('Y-m-d H:i:s', strtotime($b['booking_date'] . ' ' . $b['booking_time'] . ' + 1 hour')),
        'extendedProps' => [
            'booking_code' => $b['booking_code'],
            'customer_name' => $b['customer_name'],
            'barber_name' => $b['barber_name'],
            'service_name' => $b['service_name'],
            'price' => $b['price'],
            'status' => $b['status'],
            'booking_type' => $b['booking_type']
        ]
    ];
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Appointment Calendar</h2>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-2x text-green mb-2"></i>
                        <h5><?= $totalBookings ?></h5>
                        <small>Total Bookings</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-user-check fa-2x text-green mb-2"></i>
                        <h5><?= $confirmedBookings ?></h5>
                        <small>Confirmed</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-user-clock fa-2x text-green mb-2"></i>
                        <h5><?= $completedBookings ?></h5>
                        <small>Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-walking fa-2x text-green mb-2"></i>
                        <h5><?= $walkins ?></h5>
                        <small>Walk-ins</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar View -->
        <div class="card bg-dark text-white p-3">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var events = <?= json_encode($bookingsData) ?>;
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: events,
        eventClick: function(info) {
            var event = info.event;
            var props = event.extendedProps;
            Swal.fire({
                title: props.booking_code,
                html: 
                    '<p><strong>Customer:</strong> ' + props.customer_name + '</p>' +
                    '<p><strong>Barber:</strong> ' + props.barber_name + '</p>' +
                    '<p><strong>Service:</strong> ' + props.service_name + '</p>' +
                    '<p><strong>Status:</strong> ' + props.status + '</p>' +
                    '<p><strong>Price:</strong> R' + props.price + '</p>',
                confirmButtonColor: '#0FA958'
            });
        },
        eventColor: function(arg) {
            var status = arg.extendedProps.status;
            var type = arg.extendedProps.booking_type;
            if (status === 'completed') return '#28a745';
            if (status === 'cancelled') return '#dc3545';
            if (type === 'walk-in') return '#ffc107';
            return '#0FA958';
        }
    });
    
    calendar.render();
});
</script>
<?php include '../includes/footer.php'; ?>