<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$db = (new Database())->getConnection();
$term = trim($_GET['term'] ?? '');

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("
    SELECT b.id, b.booking_code, b.booking_date, b.booking_time, b.status, b.barber_id,
           c.full_name AS customer_name, c.phone, c.id AS customer_id, c.email AS customer_email,
           br.full_name AS barber_name,
           s.name AS service_name, s.price, s.id AS service_id
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN barbers br ON b.barber_id = br.id
    JOIN services s ON b.service_id = s.id
    WHERE b.booking_code LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?
    ORDER BY b.booking_date DESC, b.booking_time ASC
    LIMIT 20
");
$stmt->execute(["%$term%", "%$term%", "%$term%"]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($bookings);