<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isBarber()) { header("Location: ../login.php"); exit; }
$db = getDb();
$barber_id = $db->prepare("SELECT id FROM barbers WHERE user_id=?");
$barber_id->execute([$_SESSION['user_id']]);
$barber_id = $barber_id->fetchColumn();

$queue = $db->prepare("SELECT q.*, COALESCE(c.full_name, q.walkin_name) as customer, s.name as service FROM queue q LEFT JOIN customers c ON q.customer_id=c.id JOIN services s ON q.service_id=s.id WHERE q.barber_id=? AND q.status!='completed' AND DATE(q.created_at)=CURDATE() ORDER BY q.queue_number ASC");
$queue->execute([$barber_id]);
$queue = $queue->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">My Queue</h2>
        <table class="table table-dark">
            <thead><tr><th>#</th><th>Customer</th><th>Service</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($queue as $q): ?>
                <tr>
                    <td><?= $q['queue_number'] ?></td>
                    <td><?= htmlspecialchars($q['customer']) ?></td>
                    <td><?= $q['service'] ?></td>
                    <td><?= $q['status'] ?></td>
                    <td>
                        <form method="post" action="../ajax/update_queue_status.php" class="d-inline" id="form-<?= $q['id'] ?>">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <select name="status" class="form-select form-select-sm d-inline w-auto">
                                <option value="in_service" <?= $q['status']=='in_service'?'selected':'' ?>>In Service</option>
                                <option value="completed">Complete</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-success update-btn" data-id="<?= $q['id'] ?>">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
$(function(){
    $('.update-btn').click(function(e){
        e.preventDefault();
        let id = $(this).data('id');
        let form = $('#form-'+id);
        $.post('../ajax/update_queue_status.php', form.serialize(), function(){
            location.reload();
        });
    });
});
</script>
<?php include '../includes/footer.php'; ?>