<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id'])) die('Unauthorized');

$db = (new Database())->getConnection();
$term = $_GET['term'] ?? '';
$stmt = $db->prepare("SELECT id, full_name, phone, email FROM customers WHERE full_name LIKE ? OR phone LIKE ? LIMIT 10");
$stmt->execute(["%$term%", "%$term%"]);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($customers);