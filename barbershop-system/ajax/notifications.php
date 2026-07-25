<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Mark as read if requested
if (isset($_POST['mark_read']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Get unread count
$count = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$count->execute([$user_id]);
$unread = (int) $count->fetchColumn();

// Get recent 5 unread notifications
$notifs = $db->prepare("SELECT id, message, created_at FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$user_id]);
$list = $notifs->fetchAll(PDO::FETCH_ASSOC);

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['status' => 'ok']);
    exit;
}



header('Content-Type: application/json');
echo json_encode(['count' => $unread, 'notifications' => $list]);