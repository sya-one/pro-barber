<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isReceptionist() && !isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$barbers = $db->query("SELECT * FROM barbers WHERE is_active=1")->fetchAll();
$services = $db->query("SELECT * FROM services WHERE is_active=1")->fetchAll();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');
    
    // Use the selected customer ID if provided, otherwise NULL
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $name = trim($_POST['name'] ?: 'Walk‑in');
    $barber_id = intval($_POST['barber_id']);
    $service_id = intval($_POST['service_id']);
    
    // If a customer ID was selected, we use that customer's name; otherwise, store the free‑text name
    if ($customer_id) {
        // Fetch customer name for the queue record (optional)
        $stmt = $db->prepare("SELECT full_name FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $custName = $stmt->fetchColumn();
        $walkin_name = $custName ?: $name;
    } else {
        $walkin_name = $name;
    }

    $max = $db->query("SELECT COALESCE(MAX(queue_number),0)+1 FROM queue WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $stmt = $db->prepare("INSERT INTO queue (customer_id, walkin_name, barber_id, service_id, queue_number, status) VALUES (?,?,?,?,?,'waiting')");
    $stmt->execute([$customer_id, $walkin_name, $barber_id, $service_id, $max]);
    $msg = "Added to queue. Queue number: $max";
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Add Walk‑in</h2>
        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="card bg-dark text-white p-3 col-md-6">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <!-- Customer search (auto‑complete) -->
                <div class="mb-3">
                    <label class="form-label">Customer (search existing or type new name)</label>
                    <input type="text" id="customerSearch" class="form-control" 
                           placeholder="Start typing a name or phone…" autocomplete="off">
                    <input type="hidden" name="customer_id" id="customerId" value="">
                    <div id="customerResults" class="list-group mt-1" style="max-height:150px; overflow-y:auto; display:none;"></div>
                    <small class="text-muted">Leave blank to create a walk‑in without linking to a customer.</small>
                </div>

                <!-- Walk‑in name (only used if no customer selected) -->
                <div class="mb-3">
                    <label class="form-label">Walk‑in Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Sipho">
                </div>

                <div class="mb-3">
                    <label class="form-label">Barber</label>
                    <select name="barber_id" class="form-select" required>
                        <option value="">Select Barber</option>
                        <?php foreach ($barbers as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Service</label>
                    <select name="service_id" class="form-select" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (R<?= $s['price'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">Add to Queue</button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function(){
    // Auto‑complete customer search
    $('#customerSearch').on('keyup', function(){
        let term = $(this).val();
        if (term.length >= 2) {
            $.getJSON('../ajax/customer_search.php', { term: term }, function(data){
                let html = '';
                if (data.length === 0) {
                    html = '<div class="list-group-item text-muted">No matches found. Type a new name below.</div>';
                } else {
                    data.forEach(c => {
                        html += `<a href="#" class="list-group-item list-group-item-action bg-dark text-white customer-select" data-id="${c.id}" data-name="${c.full_name}">
                                    ${c.full_name} - ${c.phone}
                                </a>`;
                    });
                }
                $('#customerResults').html(html).show();
            });
        } else {
            $('#customerResults').hide();
        }
    });

    // When a customer is selected
    $(document).on('click', '.customer-select', function(e){
        e.preventDefault();
        let id = $(this).data('id');
        let name = $(this).data('name');
        $('#customerId').val(id);
        $('#customerSearch').val(name);
        $('#customerResults').hide();
        // Clear the manual name field (since we now have a customer)
        $('input[name="name"]').val('');
    });

    // Hide results when clicking outside
    $(document).on('click', function(e){
        if (!$(e.target).closest('#customerSearch, #customerResults').length) {
            $('#customerResults').hide();
        }
    });
});
</script>
<?php include '../includes/footer.php'; ?>