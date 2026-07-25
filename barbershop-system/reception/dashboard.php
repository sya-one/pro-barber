<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isReceptionist()) { header("Location: ../login.php"); exit; }

$db = getDb();
$todayWalkins = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_type='walk-in' AND DATE(created_at)=CURDATE()")->fetchColumn();
$queueCount = $db->query("SELECT COUNT(*) FROM queue WHERE DATE(created_at)=CURDATE() AND status!='completed'")->fetchColumn();
$pendingOnline = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending' AND booking_type='online' AND booking_date >= CURDATE()")->fetchColumn();
?>
<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Reception Dashboard</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-walking fa-2x text-green mb-2"></i>
                        <h5><?= $todayWalkins ?></h5>
                        <small>Walk‑ins Today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-list-ol fa-2x text-green mb-2"></i>
                        <h5><?= $queueCount ?></h5>
                        <small>Queue Waiting</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-2x text-green mb-2"></i>
                        <h5><?= $pendingOnline ?></h5>
                        <small>Pending Online</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>