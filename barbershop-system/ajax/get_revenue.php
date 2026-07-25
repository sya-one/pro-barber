<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();
$data = $db->query("SELECT DATE(paid_at) as day, SUM(amount) as total FROM payments WHERE paid_at >= DATE(NOW()) - INTERVAL 30 DAY GROUP BY DATE(paid_at) ORDER BY day")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($data);