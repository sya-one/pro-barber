<?php
/**
 * Payment Gateway – pluggable interface for card terminals.
 * Currently uses a "manual" mode that requires the receptionist
 * to enter the transaction code from the Yaco terminal.
 *
 * To enable full Yaco integration, replace the body of
 * processPayment() with a real API call.
 */
class PaymentGateway {

    /**
     * Process a card payment.
     *
     * @param float  $amount    Amount to charge
     * @param string $reference Sale reference (e.g., invoice number)
     * @return array            [ 'success' => bool, 'transaction_code' => string, 'message' => string ]
     */
    public static function processPayment($amount, $reference) {
        // ---------- MANUAL MODE (current) ----------
        // In this mode, the receptionist manually enters the code.
        // The function returns a placeholder and the actual code
        // will be taken from the form input.
        return [
            'success'          => true,
            'transaction_code' => '',   // filled from the form
            'message'          => 'Manual card payment – enter Yaco transaction code.'
        ];

        // ---------- FUTURE YACO API MODE ----------
        // Uncomment and fill in when you have API credentials.
        /*
        $api_url = 'https://api.yaco.co.za/v1/transactions';
        $api_key = 'YOUR_API_KEY';
        $terminal_id = 'YOUR_TERMINAL_ID';

        $payload = [
            'amount'    => $amount,
            'reference' => $reference,
            'terminal'  => $terminal_id
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success'          => true,
                'transaction_code' => $data['transaction_id'] ?? '',
                'message'          => 'Payment approved'
            ];
        } else {
            return [
                'success' => false,
                'transaction_code' => '',
                'message' => 'Payment failed: ' . ($httpCode ?? 'unknown')
            ];
        }
        */
    }
}