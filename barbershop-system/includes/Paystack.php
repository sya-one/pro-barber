<?php
class Paystack {
    private $publicKey;
    private $secretKey;
    private $paymentUrl;
    private $useTestMode;

    public function __construct() {
        $db = getDb();
        $settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $this->publicKey = $settings['paystack_public_key'] ?? '';
        $this->secretKey = $settings['paystack_secret_key'] ?? '';
        $this->paymentUrl = $settings['paystack_payment_url'] ?? 'https://api.paystack.co';
        $this->useTestMode = $settings['paystack_use_test_mode'] === '1';
    }

    public function getPublicKey() {
        return $this->publicKey;
    }

    public function isTestMode() {
        return $this->useTestMode;
    }

    public function initializePayment($amount, $email, $reference, $fullName = '', $metadata = []) {
        $url = $this->paymentUrl . '/transaction/initialize';
        
        $data = [
            'amount' => $amount * 100, // Convert to pesewas (kobo)
            'email' => $email,
            'reference' => $reference,
        ];
        
        if (!empty($fullName)) {
            $data['full_name'] = $fullName;
        }
        
        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }
        
        $response = $this->makeRequest($url, 'POST', $data);
        return $response;
    }

    public function verifyPayment($reference) {
        $url = $this->paymentUrl . '/transaction/verify/' . $reference;
        $response = $this->makeRequest($url, 'GET');
        return $response;
    }

    public function validateReference($reference) {
        $url = $this->paymentUrl . '/transaction/verify/' . $reference;
        $response = $this->makeRequest($url, 'GET');
        return isset($response['status']) && $response['status'] === true;
    }

    public function createRefund($transactionId, $amount = null) {
        $url = $this->paymentUrl . '/transaction/refund';
        
        $data = ['transaction' => $transactionId];
        if ($amount !== null) {
            $data['amount'] = $amount * 100; // Convert to pesewas
        }
        
        $response = $this->makeRequest($url, 'POST', $data);
        return $response;
    }

    private function makeRequest($url, $method = 'GET', $data = []) {
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        return $decoded ?: ['status' => false, 'message' => 'Invalid response'];
    }
}

function processPaystackPayment($amount, $email, $reference, $fullName = '', $metadata = []) {
    $paystack = new Paystack();
    return $paystack->initializePayment($amount, $email, $reference, $fullName, $metadata);
}

function verifyPaystackPayment($reference) {
    $paystack = new Paystack();
    return $paystack->verifyPayment($reference);
}