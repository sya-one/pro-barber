<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';

$db = getDb();
$msg = '';
$error = '';

// Handle refund request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_refund'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $sale_id = intval($_POST['sale_id']);
        $amount = floatval($_POST['amount']);
        $reason = trim($_POST['reason']);
        $requested_by = $_SESSION['user_id'];
        
        // Verify sale exists
        $stmt = $db->prepare("SELECT id, total, payment_method FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            $error = "Sale not found.";
        } elseif ($amount > $sale['total']) {
            $error = "Refund amount cannot exceed sale total.";
        } else {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO refunds (sale_id, amount, reason, status, requested_by) VALUES (?, ?, ?, 'pending', ?)");
                $stmt->execute([$sale_id, $amount, $reason, $requested_by]);
                $db->commit();
                $msg = "Refund request submitted for approval.";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle approve refund
if (isset($_GET['approve'])) {
    $refund_id = intval($_GET['approve']);
    $approved_by = $_SESSION['user_id'];
    
    $db->beginTransaction();
    try {
        // Get refund details
        $stmt = $db->prepare("SELECT * FROM refunds WHERE id = ? AND status = 'pending'");
        $stmt->execute([$refund_id]);
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($refund) {
            // Update refund status
            $db->prepare("UPDATE refunds SET status = 'approved', approved_by = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$approved_by, $refund_id]);
            
            // Get sale details
            $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
            $stmt->execute([$refund['sale_id']]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update sale payment status
            $db->prepare("UPDATE sales SET payment_status = 'refunded' WHERE id = ?")->execute([$refund['sale_id']]);
            
            // Return stock
            $stmt = $db->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$refund['sale_id']]);
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($item['product_id'] && $item['quantity']) {
                    $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
                        ->execute([$item['quantity'], $item['product_id']]);
                }
            }
            
            // Reverse loyalty points
            $stmt = $db->prepare("SELECT customer_id FROM sales WHERE id = ?");
            $stmt->execute([$refund['sale_id']]);
            $customer_id = $stmt->fetchColumn();
            
            if ($customer_id) {
                $rate = getLoyaltyRate($db);
                $pointsToReverse = floor($refund['amount'] * $rate);
                if ($pointsToReverse > 0) {
                    adjustLoyaltyPoints($db, $customer_id, -$pointsToReverse, 'redeemed', null, 'Refund: ' . $refund['amount']);
                }
            }
            
            // Log activity
            logActivity($approved_by, 'Refund Approved', "Refund ID: $refund_id, Amount: " . formatCurrency($refund['amount']));
            
            $db->commit();
            $msg = "Refund approved and processed.";
        } else {
            $error = "Refund not found or already processed.";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle reject refund
if (isset($_GET['reject'])) {
    $refund_id = intval($_GET['reject']);
    $rejected_by = $_SESSION['user_id'];
    
    $db->prepare("UPDATE refunds SET status = 'rejected', approved_by = ?, updated_at = NOW() WHERE id = ?")->execute([$rejected_by, $refund_id]);
    $msg = "Refund request rejected.";
}

// Get pending refunds
$pendingRefunds = $db->query("
    SELECT r.*, s.invoice_number, c.full_name as customer_name
    FROM refunds r
    JOIN sales s ON r.sale_id = s.id
    JOIN customers c ON s.customer_id = c.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get processed refunds
$processedRefunds = $db->query("
    SELECT r.*, s.invoice_number, u.full_name as approver_name
    FROM refunds r
    JOIN sales s ON r.sale_id = s.id
    JOIN users u ON r.approved_by = u.id
    WHERE r.status IN ('approved', 'rejected')
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Refunds</h2>
        <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <!-- Pending Refunds -->
        <div class="card bg-dark text-white p-3 mb-4">
            <h5>Pending Refunds</h5>
            <?php if (!empty($pendingRefunds)): ?>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRefunds as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td class="text-danger"><?= formatCurrency($r['amount']) ?></td>
                        <td><?= htmlspecialchars($r['reason']) ?></td>
                        <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                        <td>
                            <a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-success me-1" 
                               onclick="return confirm('Approve this refund?')">
                                <i class="fas fa-check"></i> Approve
                            </a>
                            <a href="?reject=<?= $r['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Reject this refund?')">
                                <i class="fas fa-times"></i> Reject
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No pending refunds.</p>
            <?php endif; ?>
        </div>

        <!-- Processed Refunds -->
        <div class="card bg-dark text-white p-3">
            <h5>Processed Refunds</h5>
            <?php if (!empty($processedRefunds)): ?>
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processedRefunds as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['invoice_number']) ?></td>
                        <td class="text-danger"><?= formatCurrency($r['amount']) ?></td>
                        <td><?= htmlspecialchars($r['reason']) ?></td>
                        <td>
                            <span class="badge bg-<?= $r['status'] === 'approved' ? 'success' : 'danger' ?>">
                                <?= ucfirst($r['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['approver_name']) ?></td>
                        <td><?= date('d M Y H:i', strtotime($r['updated_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No processed refunds.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>