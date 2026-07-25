<?php
session_start();
require_once '../config/database.php';

$db = (new Database())->getConnection();
$today = date('Y-m-d');

$stmt = $db->prepare("
    SELECT 
        br.id,
        br.full_name,
        br.photo,
        COALESCE(appointments.cnt, 0) as appointments,
        COALESCE(queue.cnt, 0) as queue
    FROM barbers br
    LEFT JOIN (
        SELECT barber_id, COUNT(*) as cnt 
        FROM bookings 
        WHERE booking_date = ? AND status IN ('pending', 'confirmed', 'in_progress')
        GROUP BY barber_id
    ) appointments ON br.id = appointments.barber_id
    LEFT JOIN (
        SELECT barber_id, COUNT(*) as cnt 
        FROM queue 
        WHERE DATE(created_at) = ? AND status = 'waiting'
        GROUP BY barber_id
    ) queue ON br.id = queue.barber_id
    WHERE br.is_active = 1
    ORDER BY br.full_name
");
$stmt->execute([$today, $today]);
$barbers = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($barbers);