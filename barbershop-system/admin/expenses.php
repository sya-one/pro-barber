<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin() && !isReceptionist()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

$db = getDb();
$msg = '';
$error = '';

// Handle add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $category = trim($_POST['category']);
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $description = trim($_POST['description']);
        $created_by = $_SESSION['user_id'];
        
        if (!$category || !$amount) {
            $error = "Category and amount are required.";
        } else {
            // Handle receipt upload
            $receipt_image = null;
            if (!empty($_FILES['receipt']['name'])) {
                $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
                // Accept images and PDFs for receipts
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                if (in_array($ext, $allowed) && $_FILES['receipt']['size'] < 10 * 1024 * 1024 && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                    $result = secureFileUpload('receipt', getUploadPath('expense'), $allowed, 10);
                    if ($result['success']) {
                        $receipt_image = $result['filename'];
                    } else {
                        $error = "Receipt upload failed: " . $result['error'];
                    }
                }
            }
            
            if (!$error) {
                $stmt = $db->prepare("INSERT INTO expenses (category, amount, date, description, receipt_image, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$category, $amount, $date, $description, $receipt_image, $created_by])) {
                    $msg = "Expense recorded successfully.";
                } else {
                    $error = "Failed to record expense.";
                }
            }
        }
    }
}

// Handle delete expense
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $db->prepare("SELECT receipt_image FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    $expense = $stmt->fetch();
    
    if ($expense && $expense['receipt_image']) {
        $filepath = "../uploads/expenses/" . $expense['receipt_image'];
        if (file_exists($filepath)) unlink($filepath);
    }
    
    $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    $msg = "Expense deleted.";
}

// Get expense categories
$categories = $db->query("SELECT name FROM expense_categories WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Get expenses
$expenses = $db->query("
    SELECT e.*, u.full_name as created_by_name
    FROM expenses e
    JOIN users u ON e.created_by = u.id
    ORDER BY e.date DESC, e.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalExpenses = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE(date) = CURDATE()")->fetchColumn();
$thisMonthExpenses = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")->fetchColumn();

// Get revenue for comparison
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
$netProfit = $totalRevenue - $totalExpenses;
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Expense Management</h2>
        <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-danger mb-2"></i>
                        <h5><?= formatCurrency($thisMonthExpenses) ?></h5>
                        <small>This Month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($totalExpenses) ?></h5>
                        <small>Total Expenses</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-arrow-up fa-2x text-green mb-2"></i>
                        <h5><?= formatCurrency($totalRevenue) ?></h5>
                        <small>Total Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <i class="fas fa-balance-scale fa-2x <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?> mb-2"></i>
                        <h5><?= formatCurrency($netProfit) ?></h5>
                        <small>Net Profit</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Expense Button -->
        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-plus me-2"></i>Add Expense
        </button>

        <!-- Expenses Table -->
        <div class="card bg-dark text-white p-3">
            <h5>Expenses</h5>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Added By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($e['date'])) ?></td>
                        <td><?= htmlspecialchars($e['category']) ?></td>
                        <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
                        <td class="text-danger"><?= formatCurrency($e['amount']) ?></td>
                        <td>
                            <?php if ($e['receipt_image']): ?>
                                <a href="../uploads/expenses/<?= $e['receipt_image'] ?>" target="_blank" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($e['created_by_name']) ?></td>
                        <td>
                            <a href="?delete=<?= $e['id'] ?>" class="btn btn-sm btn-danger delete-confirm">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Expense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Amount (R) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label>Receipt (optional)</label>
                            <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
                            <small class="text-muted">JPG, PNG, PDF up to 10MB</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_expense" class="btn btn-success">Record Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    // Delete confirmation
    $('.delete-confirm').click(function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Delete Expense?',
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