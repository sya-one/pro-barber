<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$date       = $_GET['date'] ?? '';
$barber_id  = intval($_GET['barber_id'] ?? 0);
$service_id = intval($_GET['service_id'] ?? 0);

if (!$date || !$barber_id || !$service_id) {
    echo json_encode([]);
    exit;
}

// Get service duration
$stmt = $db->prepare("SELECT duration FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$duration = $stmt->fetchColumn();

if (!$duration) {
    echo json_encode([]);
    exit;
}

// Get already booked times for that barber on that date (exclude cancelled)
$stmt = $db->prepare("SELECT booking_time FROM bookings WHERE barber_id = ? AND booking_date = ? AND status != 'cancelled'");
$stmt->execute([$barber_id, $date]);
$booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate time slots from 09:00 to 17:00, every 15 minutes
$slots = [];
$start = strtotime('09:00');
$end   = strtotime('17:00');

while ($start <= $end) {
    $time = date('H:i', $start);
    $slot_end = $start + ($duration * 60);

    $conflict = false;
    foreach ($booked as $bt) {
        $booked_start = strtotime($bt);
        $booked_end   = $booked_start + ($duration * 60);
        if ($start < $booked_end && $slot_end > $booked_start) {
            $conflict = true;
            break;
        }
    }

    if (!$conflict) {
        $slots[] = $time;
    }
    $start = strtotime('+15 minutes', $start);
}

echo json_encode($slots);