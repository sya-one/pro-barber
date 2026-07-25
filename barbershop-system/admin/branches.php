<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';
$error = '';

// Handle add branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $province = trim($_POST['province']);
        $postal_code = trim($_POST['postal_code']);
        $country = trim($_POST['country']);
        
        if (!$name) {
            $error = "Branch name is required.";
        } else {
            $stmt = $db->prepare("INSERT INTO branches (name, address, city, province, postal_code, country) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $address, $city, $province, $postal_code, $country])) {
                $msg = "Branch added successfully.";
            } else {
                $error = "Failed to add branch.";
            }
        }
    }
}

// Handle delete branch
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Only allow deletion if no data exists
    $hasData = $db->prepare("SELECT COUNT(*) FROM sales WHERE branch_id = ?");
    $hasData->execute([$id]);
    if ($hasData->fetchColumn() == 0) {
        $db->prepare("DELETE FROM branches WHERE id = ?")->execute([$id]);
        $msg = "Branch deleted.";
    } else {
        $error = "Cannot delete branch with existing data.";
    }
}

// Get branches
$branches = $db->query("SELECT * FROM branches ORDER BY is_active DESC, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Branches</h2>
        <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <div class="card bg-dark text-white p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Branches</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                    <i class="fas fa-plus me-1"></i>Add Branch
                </button>
            </div>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['address'] ?? '-') ?><br>
                            <small><?= htmlspecialchars($b['city'] . ', ' . $b['province'] . ' ' . $b['postal_code']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($b['city']) ?></td>
                        <td>
                            <span class="badge bg-<?= $b['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-danger delete-confirm">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Branch</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Branch Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" value="South Africa" required>
                        </div>
                        <div class="col-12">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Street address">
                        </div>
                        <div class="col-md-4">
                            <label>City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label>Province</label>
                            <input type="text" name="province" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label>Postal Code</label>
                            <input type="text" name="postal_code" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_branch" class="btn btn-success">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $('.delete-confirm').click(function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Delete Branch?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#0FA958',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = $(this).attr('href');
            }
        });
    });
});
</script>
<?php include '../includes/footer.php'; ?>