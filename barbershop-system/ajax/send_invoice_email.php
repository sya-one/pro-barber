<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/functions.php';
require_once '../includes/mailer.php';
require_once '../vendor/autoload.php';

$sale_id = intval($_POST['sale_id'] ?? 0);
if (!$sale_id) {
    echo json_encode(['success' => false, 'message' => 'Missing sale ID.']);
    exit;
}

// Optimization: Close the session early. 
// This prevents SMTP network latency from locking the user's session 
// and allowing them to continue using the POS in other tabs while the email sends.
$customer_id = $_SESSION['customer_id'] ?? null;
session_write_close();

$db = getDb();

$stmt = $db->prepare("
    SELECT s.*, c.full_name, c.email
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale || empty($sale['email'])) {
    echo json_encode(['success' => false, 'message' => 'Customer email not found.']);
    exit;
}

// Get shop details and generate receipt content
$settings = getSettings();
$shop_name = $settings['shop_name'] ?? 'The Professional Barbershop';
$invoice_no = $sale['invoice_number'] ?? '#' . $sale_id;
$invoice_html = getInvoiceHTML($db, $sale_id);

// Calculate points for the email body
$loyalty_earned = 0;
if (!empty($sale['customer_id'])) {
    $rate = getLoyaltyRate($db);
    $loyalty_earned = floor($sale['total'] * $rate);
}

$subject = "Your Receipt from " . $shop_name . " (" . $invoice_no . ")";

$body = "<div style='font-family: Arial, sans-serif; color: #333; max-width: 650px; margin: auto;'>";
$body .= "<p>Dear " . htmlspecialchars($sale['full_name'] ?? 'Customer') . ",</p>";
$body .= "<p>Thank you for visiting us! Please find your itemized receipt below:</p>";
$body .= $invoice_html;

if ($loyalty_earned > 0) {
    $body .= "<div style='margin-top: 20px; padding: 15px; border: 2px dashed #0FA958; text-align: center; background: #f9fff9; border-radius: 8px;'>";
    $body .= "<h3 style='color: #0FA958; margin: 0;'>🎉 Congratulations!</h3>";
    $body .= "<p style='margin: 5px 0 0 0; font-size: 16px;'>You earned <strong>$loyalty_earned loyalty points</strong> with this purchase!</p>";
    $body .= "</div>";
}

$body .= "<p style='text-align: center; margin-top: 25px; color: #888; font-size: 12px;'>Confidence starts with a great cut. See you for your next session!</p></div>";

$sent = sendEmail($sale['email'], $subject, $body, true);

echo json_encode(['success' => $sent, 'message' => $sent ? 'Email sent.' : 'Failed to send email.']);