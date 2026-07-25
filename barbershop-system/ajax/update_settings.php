<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();
foreach ($_POST as $key => $value) {
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$value, $key]);
}
echo 'Settings updated';