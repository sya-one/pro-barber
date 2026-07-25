<?php require_once '../config/database.php'; $db = (new Database())->getConnection(); ?>
<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/default-avatar.png">
    <title>Queue Display - The Professional Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@600&display=swap" rel="stylesheet">
    <style>body{background:#000; color:white; font-family: 'Inter', sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;} .queue-number{font-size:8rem; color:#0FA958;} </style>
</head>
<body>
<div class="container text-center">
    <h1>Now Serving</h1>
    <div class="queue-number" id="currentNumber">--</div>
    <h3 id="currentName"></h3>
    <div class="mt-4">
        <h5>Next in Queue</h5>
        <ul id="nextList" class="list-unstyled"></ul>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
function refreshQueue() {
    $.getJSON('../ajax/get_queue.php', function(data) {
        if(data.length > 0) {
            let inService = data.find(q => q.status === 'in_service');
            if(inService) {
                $('#currentNumber').text(inService.queue_number);
                $('#currentName').text(inService.customer);
            }
            let waiting = data.filter(q => q.status === 'waiting').slice(0,5);
            $('#nextList').html(waiting.map(q => `<li>${q.queue_number} - ${q.customer}</li>`).join(''));
        }
    });
}
setInterval(refreshQueue, 5000);
refreshQueue();
</script>
</body></html>