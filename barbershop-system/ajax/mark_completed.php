<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();
$booking_id = $_POST['booking_id'];
$stmt = $db->prepare("UPDATE bookings SET status='completed' WHERE id=?");
$stmt->execute([$booking_id]);
echo 'ok';