<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
if (!isAdmin()) { header("Location: ../login.php"); exit; }
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

$db = getDb();
$msg = '';

// Fetch all settings
$settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) die('Invalid CSRF');

    $keys = [
        'shop_name', 'address', 'currency', 'timezone',
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
        'invoice_template', 'admin_whatsapp',
        'paystack_public_key', 'paystack_secret_key', 'paystack_use_test_mode',
        'loyalty_rate', 'loyalty_tier_bronze', 'loyalty_tier_silver', 'loyalty_tier_gold', 'loyalty_tier_vip'
    ];

    foreach ($keys as $key) {
        $value = $_POST[$key] ?? '';

        // Keep existing SMTP password if left empty
        if ($key === 'smtp_password' && $value === '') {
            continue;
        }

        // Keep existing Paystack keys if left empty
        if (strpos($key, 'paystack') !== false && $value === '') {
            continue;
        }

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }

    // Logo upload - use secure upload helper
    if (!empty($_FILES['logo']['name'])) {
        $upload = secureFileUpload('logo', dirname(__DIR__) . '/assets/images/', ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5);
        if ($upload['success']) {
            // Rename to logo.png
            $final_path = dirname(__DIR__) . '/assets/images/logo.png';
            $temp_path = dirname(__DIR__) . '/assets/images/' . $upload['filename'];
            if (rename($temp_path, $final_path)) {
                $msg = "Settings and logo updated.";
            } else {
                $msg = "Settings saved, but logo rename failed.";
            }
        } else {
            $msg = "Settings saved, but logo upload failed: " . $upload['error'];
        }
    } else {
        $msg = "Settings updated successfully.";
    }

    // Refresh settings after update
    $settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content p-4 w-100">
        <h2 class="text-white mb-4">System Settings</h2>
        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="card bg-dark text-white p-4 col-md-8">
            <form method="post" enctype="multipart/form-data" id="settingsForm">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-3" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab">SMTP</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="paystack-tab" data-bs-toggle="tab" data-bs-target="#paystack" type="button" role="tab">Paystack</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="loyalty-tab" data-bs-toggle="tab" data-bs-target="#loyalty" type="button" role="tab">Loyalty</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab">Invoice Template</button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabContent">
                    <!-- General Tab -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">Shop Name</label>
                            <input type="text" name="shop_name" class="form-control"
                                   value="<?= htmlspecialchars($settings['shop_name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= htmlspecialchars($settings['address'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="currency" class="form-control"
                                   value="<?= htmlspecialchars($settings['currency'] ?? 'R') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <input type="text" name="timezone" class="form-control"
                                   value="<?= htmlspecialchars($settings['timezone'] ?? 'Africa/Johannesburg') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Admin WhatsApp Number</label>
                            <input type="text" name="admin_whatsapp" class="form-control"
                                   value="<?= htmlspecialchars($settings['admin_whatsapp'] ?? '') ?>">
                            <small class="text-muted">International format, no +. E.g. 27686384680</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Shop Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <small class="text-muted">Current logo:</small><br>
                            <img src="../assets/images/logo.png" alt="Logo" class="mt-2" style="max-width:120px;">
                        </div>
                    </div>

                    <!-- SMTP Tab -->
                    <div class="tab-pane fade" id="smtp" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control"
                                   value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control"
                                   value="<?= htmlspecialchars($settings['smtp_port'] ?? '465') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control"
                                   value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control"
                                   value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Encryption</label>
                            <select name="smtp_encryption" class="form-select">
                                <option value="ssl" <?= (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'ssl') ? 'selected' : '' ?>>SSL</option>
                                <option value="tls" <?= (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'tls') ? 'selected' : '' ?>>TLS</option>
                                <option value="none" <?= (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'none') ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                    </div>

                    <!-- Paystack Tab -->
                    <div class="tab-pane fade" id="paystack" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">Paystack Public Key</label>
                            <input type="text" name="paystack_public_key" class="form-control"
                                   value="<?= htmlspecialchars($settings['paystack_public_key'] ?? '') ?>">
                            <small class="text-muted">Test keys can be found in your Paystack dashboard</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Paystack Secret Key</label>
                            <input type="password" name="paystack_secret_key" class="form-control"
                                   value="<?= htmlspecialchars($settings['paystack_secret_key'] ?? '') ?>">
                            <small class="text-danger">Keep this secret! Never share this key.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Use Test Mode</label>
                            <div class="form-check">
                                <input type="checkbox" name="paystack_use_test_mode" class="form-check-input" id="testMode" <?= (isset($settings['paystack_use_test_mode']) && $settings['paystack_use_test_mode'] == '1') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="testMode">Enable Test Mode (use test keys)</label>
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <strong>Test Keys:</strong> pk_test_... / sk_test_...
                            <br>
                            <strong>Live Keys:</strong> pk_live_... / sk_live_...
                        </div>
                    </div>

                    <!-- Loyalty Tab -->
                    <div class="tab-pane fade" id="loyalty" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">Loyalty Rate (points per Rand)</label>
                            <input type="number" step="0.01" name="loyalty_rate" class="form-control"
                                   value="<?= htmlspecialchars($settings['loyalty_rate'] ?? '0.1') ?>">
                            <small class="text-muted">1 point per R10 spent (0.1 points per R1)</small>
                        </div>
                        <hr>
                        <h6>Loyalty Tiers</h6>
                        <div class="mb-3">
                            <label class="form-label">Bronze Tier (0 - max points)</label>
                            <input type="number" name="loyalty_tier_bronze" class="form-control"
                                   value="<?= htmlspecialchars($settings['loyalty_tier_bronze'] ?? '999') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Silver Tier (min - max points)</label>
                            <input type="number" name="loyalty_tier_silver" class="form-control"
                                   value="<?= htmlspecialchars($settings['loyalty_tier_silver'] ?? '2499') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gold Tier (min - max points)</label>
                            <input type="number" name="loyalty_tier_gold" class="form-control"
                                   value="<?= htmlspecialchars($settings['loyalty_tier_gold'] ?? '4999') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">VIP Tier (min - max points)</label>
                            <input type="number" name="loyalty_tier_vip" class="form-control"
                                   value="<?= htmlspecialchars($settings['loyalty_tier_vip'] ?? '999999') ?>">
                        </div>
                    </div>

                    <!-- Invoice Template Tab -->
                    <div class="tab-pane fade" id="invoice" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label">Invoice / Slip Template (HTML)</label>
                            <textarea name="invoice_template" class="form-control" rows="14" id="invoiceTemplateTextarea"><?= htmlspecialchars($settings['invoice_template'] ?? '') ?></textarea>
                            <small class="text-muted">
                                Placeholders: {{shop_name}}, {{shop_address}}, {{shop_phone}}, {{shop_logo}},
                                {{customer_name}}, {{date}}, {{payment_method}}, {{items_table}}, {{total}},
                                {{invoice_number}}, {{barber_name}}, {{loyalty_points}}.
                            </small>
                            <br>
                            <button type="button" class="btn btn-sm btn-outline-light mt-2" onclick="previewTemplate()">
                                <i class="fas fa-eye me-1"></i> Preview
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success mt-3">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<script>
function previewTemplate() {
    var template = document.getElementById('invoiceTemplateTextarea').value;
    var sampleItems =
        '<tr><td style="padding:4px 8px;">Beard Oil (20ml)</td><td style="text-align:center;">1</td><td style="text-align:right;">R80.00</td><td style="text-align:right;">R80.00</td></tr>' +
        '<tr><td style="padding:4px 8px;">Classic Haircut</td><td style="text-align:center;">1</td><td style="text-align:right;">R120.00</td><td style="text-align:right;">R120.00</td></tr>';
    var html = template
        .replace(/\{\{shop_name\}\}/g, <?= json_encode($settings['shop_name'] ?? 'The Professional Barbershop') ?>)
        .replace(/\{\{shop_address\}\}/g, <?= json_encode($settings['address'] ?? '16 Blaine St, KwaDukuza Central') ?>)
        .replace(/\{\{shop_phone\}\}/g, '0612345678')
        .replace(/\{\{customer_name\}\}/g, 'John Doe')
        .replace(/\{\{date\}\}/g, new Date().toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }))
        .replace(/\{\{payment_method\}\}/g, 'Cash')
        .replace(/\{\{items_table\}\}/g, sampleItems)
        .replace(/\{\{total\}\}/g, 'R200.00')
        .replace(/\{\{invoice_number\}\}/g, 'INV-2026-0001')
        .replace(/\{\{barber_name\}\}/g, 'Sipho Dlamini')
        .replace(/\{\{loyalty_points\}\}/g, '10')
        .replace(/\{\{shop_logo\}\}/g, '<img src="../assets/images/logo.png" style="max-height:60px;">');
    var win = window.open('', 'InvoicePreview', 'width=650,height=600');
    win.document.write('<html><head><title>Invoice Preview</title>');
    win.document.write('<style>body { font-family: Arial, sans-serif; padding: 20px; } table { width: 100%; border-collapse: collapse; margin: 10px 0; } th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f5f5f5; }</style>');
    win.document.write('</head><body>');
    win.document.write(html);
    win.document.write('</body></html>');
    win.document.close();
}
</script>
<?php include '../includes/footer.php'; ?>