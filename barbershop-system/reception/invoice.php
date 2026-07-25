<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isReceptionist() && !isAdmin()) { header("Location: ../login.php"); exit; }

$db = getDb();
$id = intval($_GET['id'] ?? 0);
if (!$id) die("Sale ID missing.");

// Fetch sale + customer
$stmt = $db->prepare("
    SELECT s.*, c.full_name AS customer_name, c.email, c.phone
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) die("Sale not found.");

// Fetch barber name (from any service booking linked to the sale)
$barber_name = 'Counter';
$stmt = $db->prepare("
    SELECT br.full_name
    FROM sale_items si
    JOIN bookings b ON si.booking_id = b.id
    JOIN barbers br ON b.barber_id = br.id
    WHERE si.sale_id = ? AND si.item_type = 'service'
    ORDER BY b.id DESC
    LIMIT 1
");
$stmt->execute([$id]);
$barber_name = $stmt->fetchColumn() ?: 'Counter';

// Fetch loyalty points earned in this sale
$points_earned = 0;
if ($sale['customer_id']) {
    $stmt = $db->prepare("SELECT points FROM loyalty_transactions WHERE customer_id = ? AND type = 'earned' AND note LIKE '%POS sale%' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sale['customer_id']]);
    $points_earned = (int) $stmt->fetchColumn();
}

// Fetch sale items
$stmt = $db->prepare("
    SELECT si.*, p.product_code
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Shop settings
$settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$shop_name    = $settings['shop_name'] ?? 'The Professional Barbershop';
$shop_address = $settings['address'] ?? '16 Blaine St, KwaDukuza Central';
$shop_phone   = $settings['phone'] ?? '';

// Logo URL
$logo_url = '../assets/images/logo.png';

// Invoice template (your custom design – must include {{loyalty_points}})
$template = $settings['invoice_template'] ?? '
<div style="font-family: Arial, sans-serif; max-width: 650px; margin: auto; padding: 20px; border: 1px solid #ddd; background: #fff;">
    <!-- HEADER -->
    <div style="text-align:center; border-bottom: 2px solid #111; padding-bottom: 12px;">
        <img src="' . $logo_url . '" alt="Shop Logo" style="max-height:80px; margin-bottom:10px;">
        <h2 style="margin:0; font-size:24px; letter-spacing:1px;">{{shop_name}}</h2>
        <p style="margin:5px 0; font-size:12px; color:#555;">{{shop_address}} | {{shop_phone}}</p>
        <p style="margin:5px 0; font-size:12px;"><strong>Invoice:</strong> {{invoice_number}}</p>
    </div>
    <!-- CUSTOMER INFO -->
    <div style="margin-top:15px; font-size:13px;">
        <p style="margin:3px 0;"><strong>Customer:</strong> {{customer_name}}</p>
        <p style="margin:3px 0;"><strong>Date:</strong> {{date}}</p>
        <p style="margin:3px 0;"><strong>Payment Method:</strong> {{payment_method}}</p>
        <p style="margin:3px 0;"><strong>Handled By:</strong> {{barber_name}}</p>
        <p style="margin:3px 0;"><strong>Loyalty Points Earned:</strong> {{loyalty_points}}</p>
    </div>
    <hr style="margin:15px 0; border:0; border-top:1px dashed #999;">
    <!-- ITEMS -->
    <table style="width:100%; border-collapse: collapse; font-size:13px;">
        <thead>
            <tr style="background:#000000; color:#fff;">
                <th style="text-align:left; padding:8px;">Item</th>
                <th style="text-align:center; padding:8px;">Qty</th>
                <th style="text-align:right; padding:8px;">Price</th>
                <th style="text-align:right; padding:8px;">Total</th>
            </tr>
        </thead>
        <tbody>{{items_table}}</tbody>
    </table>
    <!-- TOTAL -->
    <div style="margin-top:15px; text-align:right;">
        <p style="font-size:16px; margin:5px 0;"><strong>Total: {{total}}</strong></p>
    </div>
    <hr style="margin:15px 0; border:0; border-top:1px dashed #999;">
    <!-- FOOTER -->
    <div style="text-align:center; font-size:12px; color:#555;">
        <p style="margin:5px 0;">Thank you for choosing us 💈</p>
        <p style="margin:5px 0;">Fresh cuts. Fresh confidence.</p>
        <p style="margin:5px 0; font-size:11px;">Powered by {{shop_name}} POS System</p>
    </div>
</div>';

// Build items HTML
$items_html = '';
foreach ($items as $item) {
    $items_html .= '<tr>';
    $items_html .= '<td>' . htmlspecialchars($item['item_name']) . '</td>';
    $items_html .= '<td style="text-align:center;">' . $item['quantity'] . '</td>';
    $items_html .= '<td style="text-align:right;">' . formatCurrency($item['unit_price']) . '</td>';
    $items_html .= '<td style="text-align:right;">' . formatCurrency($item['total_price']) . '</td>';
    $items_html .= '</tr>';
}

// Replace placeholders (including loyalty points and barber name)
$content = str_replace(
    [
        '{{shop_name}}','{{shop_address}}','{{shop_phone}}','{{customer_name}}',
        '{{date}}','{{payment_method}}','{{items_table}}','{{total}}',
        '{{invoice_number}}','{{barber_name}}','{{loyalty_points}}'
    ],
    [
        htmlspecialchars($shop_name),
        htmlspecialchars($shop_address),
        htmlspecialchars($shop_phone),
        $sale['customer_name'] ? htmlspecialchars($sale['customer_name']) : 'Walk‑in Customer',
        date('d M Y H:i', strtotime($sale['created_at'])),
        ucfirst($sale['payment_method']),
        $items_html,
        formatCurrency($sale['total']),
        htmlspecialchars($sale['invoice_number'] ?? 'N/A'),
        htmlspecialchars($barber_name),
        $points_earned ?: '0'
    ],
    $template
);

$has_email = !empty($sale['email']);
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h2 class="text-white">Invoice <?= htmlspecialchars($sale['invoice_number'] ?? '#' . $sale['id']) ?></h2>
            <div>
                <button onclick="window.print()" class="btn btn-outline-light me-2"><i class="fas fa-print"></i> Print</button>
                <?php if ($has_email): ?>
                    <button id="sendEmailBtn" class="btn btn-outline-success me-2"><i class="fas fa-envelope"></i> Send Email</button>
                <?php endif; ?>
                <a href="pos.php" class="btn btn-outline-success"><i class="fas fa-arrow-left"></i> Back to POS</a>
            </div>
        </div>

        <div class="card bg-white text-dark p-4" id="invoice" style="max-width:800px;margin:auto;">
            <?= $content ?>
        </div>
    </div>
</div>

<!-- Auto‑send email trigger (if flag set by POS) -->
<?php if (isset($_SESSION['send_invoice_email']) && $_SESSION['send_invoice_email'] == $id): ?>
<script>
$(document).ready(function(){
    $.post('../ajax/send_invoice_email.php', { sale_id: <?= $id ?> }, function(response) {
        console.log('Invoice email auto‑sent:', response);
    });
});
</script>
<?php unset($_SESSION['send_invoice_email']); endif; ?>

<script>
$(document).ready(function(){
    $('#sendEmailBtn').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        $.post('../ajax/send_invoice_email.php', { sale_id: <?= $id ?> }, function(response) {
            if (response.success) {
                alert('Email sent successfully.');
            } else {
                alert('Error: ' + response.message);
            }
            btn.prop('disabled', false).html('<i class="fas fa-envelope"></i> Send Email');
        }, 'json').fail(function() {
            alert('Request failed. Please check the console.');
            btn.prop('disabled', false).html('<i class="fas fa-envelope"></i> Send Email');
        });
    });
});
</script>

<style>
@media print {
    .top-navbar, .sidebar, .main-footer, .no-print, .sidebar-overlay { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; background: white !important; padding: 0 !important; }
    #invoice { max-width: 100% !important; box-shadow: none !important; border: none !important; margin: 0 auto !important; }
    body { background: white !important; }
}
</style>
<?php include '../includes/footer.php'; ?>