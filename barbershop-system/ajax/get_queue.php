<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();

$queue = $db->query("
    SELECT 
        q.id,
        q.queue_number, 
        COALESCE(c.full_name, q.walkin_name) as customer, 
        b.full_name as barber, 
        s.name as service,
        s.price,
        q.status
    FROM queue q 
    LEFT JOIN customers c ON q.customer_id = c.id 
    JOIN barbers b ON q.barber_id = b.id 
    JOIN services s ON q.service_id = s.id 
    WHERE q.status != 'completed' 
    AND DATE(q.created_at) = CURDATE()
    ORDER BY q.queue_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($queue);