<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();
$name = $_POST['name'] ?? 'Walk-in';
$barber_id = $_POST['barber_id'];
$service_id = $_POST['service_id'];
// get next queue number
$max = $db->query("SELECT COALESCE(MAX(queue_number),0)+1 FROM queue WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stmt = $db->prepare("INSERT INTO queue (walkin_name, barber_id, service_id, queue_number, status) VALUES (?,?,?,?,'waiting')");
$stmt->execute([$name, $barber_id, $service_id, $max]);
echo json_encode(['success'=>true, 'queue_number'=>$max]);