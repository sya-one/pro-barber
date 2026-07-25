<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();
$id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? '';
$stmt = $db->prepare("UPDATE queue SET status = ? WHERE id = ?");
$stmt->execute([$status, $id]);
echo 'success';