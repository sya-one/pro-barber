<?php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/Paystack.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$reference = $input['reference'] ?? $input['trxref'] ?? '';

if (!$reference) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction reference']);
    exit;
}

$db = getDb();

// Check if this is a duplicate callback
$stmt = $db->prepare("SELECT id, payment_status FROM sales WHERE paystack_reference = ? LIMIT 1");
$stmt->execute([$reference]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if ($sale) {
    if ($sale['payment_status'] === 'paid') {
        echo json_encode(['success' => true, 'message' => 'Payment already processed', 'status' => 'paid']);
        exit;
    }
}

// Verify payment with Paystack
$paystack = new Paystack();
$verification = $paystack->verifyPayment($reference);

if (!$verification || !$verification['status']) {
    echo json_encode(['success' => false, 'message' => 'Payment verification failed']);
    exit;
}

$paymentData = $verification['data'] ?? [];
$amount = isset($paymentData['amount']) ? $paymentData['amount'] / 100 : 0;
$status = $paymentData['status'] ?? 'failed';

// Find the sale by reference
$stmt = $db->prepare("SELECT s.*, c.email, c.phone FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.paystack_reference = ? OR s.invoice_number = ?");
$stmt->execute([$reference, $reference]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    echo json_encode(['success' => false, 'message' => 'Sale not found for this transaction']);
    exit;
}

try {
    $db->beginTransaction();
    
    $saleId = $sale['id'];
    $total = $sale['total'];
    
    if ($status === 'success' || $status === 'paid') {
        // Update sale payment status
        $db->prepare("UPDATE sales SET payment_status = 'paid', paystack_reference = ? WHERE id = ?")
            ->execute([$reference, $saleId]);
        
        // Record payment
        $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_code, status, paid_at) 
                      VALUES (NULL, ?, 'paystack', ?, 'paid', NOW())")
            ->execute([$total, $reference]);
        
        // Update customer totals
        if ($sale['customer_id']) {
            $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id = ?")
                ->execute([$total, $sale['customer_id']]);
            
            // Award loyalty points
            $rate = getLoyaltyRate($db);
            $points = floor($total * $rate);
            if ($points > 0) {
                adjustLoyaltyPoints($db, $sale['customer_id'], $points, 'earned', null, 'Paystack online payment');
            }
        }
        
        // Deduct inventory for products
        $stmt = $db->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ? AND item_type = 'product'");
        $stmt->execute([$saleId]);
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")
                ->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Calculate and record commission for barbers
        $stmt = $db->prepare("SELECT si.*, b.barber_id FROM sale_items si JOIN bookings b ON si.booking_id = b.id WHERE si.sale_id = ?");
        $stmt->execute([$saleId]);
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($item['barber_id']) {
                $stmt = $db->prepare("SELECT commission_rate, commission_type FROM barbers WHERE id = ?");
                $stmt->execute([$item['barber_id']]);
                $barber = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($barber) {
                    $commissionAmount = 0;
                    if ($barber['commission_type'] === 'percentage') {
                        $commissionAmount = $item['total_price'] * ($barber['commission_rate'] / 100);
                    }
                    
                    if ($commissionAmount > 0) {
                        $db->prepare("INSERT INTO commissions (barber_id, sale_id, booking_id, amount, rate_percent, status) 
                                      VALUES (?, ?, ?, ?, ?, 'earned')")
                            ->execute([$item['barber_id'], $saleId, $item['booking_id'] ?? null, $commissionAmount, $barber['commission_rate']]);
                    }
                }
            }
        }
        
        $db->commit();
        
        // Send notification
        notifyAdminEmail($db, "Paystack Payment Confirmed: " . $sale['invoice_number'], 
            "<p>Payment of <strong>" . formatCurrency($total) . "</strong> received via Paystack.</p>");
        notifyAdminWhatsApp($db, "Paystack payment confirmed: " . $sale['invoice_number'] . " - " . formatCurrency($total));
        
        echo json_encode(['success' => true, 'message' => 'Payment processed successfully', 'status' => 'paid']);
    } else {
        // Update sale with failed status
        $db->prepare("UPDATE sales SET payment_status = 'failed' WHERE id = ?")->execute([$saleId]);
        $db->commit();
        
        echo json_encode(['success' => false, 'message' => 'Payment failed or cancelled', 'status' => $status]);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}