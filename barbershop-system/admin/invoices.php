<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();

$invoices = $db->query("
    SELECT s.id, s.invoice_number, s.total, s.payment_method, s.created_at,
           COALESCE(c.full_name, 'Walk‑in') AS customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">Invoices</h2>

        <div class="card bg-dark text-white p-3">
            <table class="table table-dark datatable">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= htmlspecialchars($inv['invoice_number'] ?? '#' . $inv['id']) ?></td>
                        <td><?= date('d M Y H:i', strtotime($inv['created_at'])) ?></td>
                        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                        <td><?= formatCurrency($inv['total']) ?></td>
                        <td><?= ucfirst($inv['payment_method']) ?></td>
                        <td>
                            <a href="../reception/invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-light" target="_blank">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>