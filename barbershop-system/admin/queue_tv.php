<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
$db = getDb();

// Get current queue for TV display
$queue = $db->query("
    SELECT q.queue_number, q.status, COALESCE(c.full_name, q.walkin_name) AS customer
    FROM queue q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.status != 'completed' AND DATE(q.created_at) = CURDATE()
    ORDER BY q.queue_number ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$waitingCount = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'waiting' AND DATE(created_at) = CURDATE()")->fetchColumn();
$calledCount = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'called' AND DATE(created_at) = CURDATE()")->fetchColumn();
$inServiceCount = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'in_service' AND DATE(created_at) = CURDATE()")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="10">
    <title>Queue Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #000000;
            --secondary: #333333;
            --accent: #0FA958;
            --warning: #ffc107;
            --info: #17a2b8;
            --success: #28a745;
        }
        body {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid var(--accent);
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--accent);
            margin: 0;
            text-shadow: 0 0 20px rgba(15, 169, 88, 0.5);
        }
        .header p {
            color: #aaa;
            font-size: 1.2rem;
            margin: 10px 0 0;
        }
        .stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        .stat-box {
            text-align: center;
            padding: 20px 30px;
            border-radius: 15px;
            background: rgba(255,255,255,0.05);
            border: 2px solid;
        }
        .stat-box.waiting { border-color: var(--warning); }
        .stat-box.called { border-color: var(--info); }
        .stat-box.in-service { border-color: var(--success); }
        .stat-box h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
        }
        .stat-box p {
            color: #888;
            font-size: 1rem;
            margin: 5px 0 0;
        }
        .queue-section {
            background: rgba(255,255,255,0.03);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .queue-section h3 {
            text-align: center;
            color: var(--accent);
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        .queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            margin-bottom: 10px;
            border-radius: 12px;
            background: rgba(0,0,0,0.4);
            border-left: 5px solid;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .queue-item:hover {
            transform: translateX(10px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        .queue-item.waiting { border-left-color: var(--warning); }
        .queue-item.called { border-left-color: var(--info); }
        .queue-item.in-service { border-left-color: var(--success); }
        .queue-number {
            font-size: 2rem;
            font-weight: 800;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--accent);
            color: #000;
            flex-shrink: 0;
        }
        .queue-info {
            flex-grow: 1;
            padding: 0 15px;
        }
        .queue-info .customer {
            font-size: 1.3rem;
            font-weight: 600;
        }
        .queue-info .status {
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .status-badge.waiting { background: var(--warning); color: #000; }
        .status-badge.called { background: var(--info); color: #fff; }
        .status-badge.in-service { background: var(--success); color: #fff; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        @media (max-width: 768px) {
            .header h1 { font-size: 2rem; }
            .stat-box { padding: 15px 20px; }
            .stat-box h2 { font-size: 2rem; }
            .queue-number { width: 60px; height: 60px; font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>QUEUE DISPLAY</h1>
        <p>Professional Barbershop - Customer Waiting List</p>
    </div>

    <div class="stats">
        <div class="stat-box waiting">
            <h2><?= $waitingCount ?></h2>
            <p>Waiting</p>
        </div>
        <div class="stat-box called">
            <h2><?= $calledCount ?></h2>
            <p>Called</p>
        </div>
        <div class="stat-box in-service">
            <h2><?= $inServiceCount ?></h2>
            <p>In Service</p>
        </div>
    </div>

    <div class="queue-section">
        <h3>Current Queue</h3>
        <?php if (!empty($queue)): ?>
            <?php foreach ($queue as $q): ?>
            <div class="queue-item <?= $q['status'] ?>">
                <div class="queue-number"><?= $q['queue_number'] ?></div>
                <div class="queue-info">
                    <div class="customer"><?= htmlspecialchars($q['customer']) ?></div>
                    <div class="status">
                        <span class="status-badge <?= $q['status'] ?>"><?= $q['status'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>No customers in queue</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://kit.fontawesome.com/a2b9a5b9c1.js" crossorigin="anonymous"></script>
</body>
</html>