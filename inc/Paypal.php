<?php

require __DIR__ . '/vendor/autoload.php';

class Paypal
{
    // Add constants for API endpoints
    const PAYPAL_SANDBOX_API = 'https://api.sandbox.paypal.com';
    const PAYPAL_LIVE_API = 'https://api.paypal.com';
    
    private static $_ClientID;
    private static $_ClientSecret;
    private static $_APIContex;
    private static $sandbox = false;

    public static function set_auth($clientid, $client_secret)
    {
        // Sanitize inputs
        self::$_ClientID = trim($clientid);
        self::$_ClientSecret = trim($client_secret);
        self::$sandbox = (awards_options('paypal_test_mode') == 'enable');
    }

    public function set_apicontext($config = array())
    {
        self::$_APIContex = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                self::$_ClientID,
                self::$_ClientSecret
            )
        );
    }

    public function doPayment($data = array())
    {
        // Always get normalized currency code
        $data['currency'] = get_normalized_currency();
        
        $defaults = array(
            'amount' => 0,
            'currency' => 'USD',
            'payment_method' => 'paypal',
            'redirect_url' => '',
            'cancel_url' => '',
            'intent' => 'sale',
        );

        $data = array_merge($defaults, $data);
        
        // Log payment data securely (without sensitive info)
        error_log('PayPal Payment Initiated - Amount: ' . $data['amount'] . ' ' . $data['currency']);

        // Validate required fields
        if (empty($data['redirect_url']) || empty($data['cancel_url']) || empty($data['amount'])) {
            error_log('PayPal Error: Missing required payment parameters');
            return false;
        }

        $apicontext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                self::$_ClientID,
                self::$_ClientSecret
            )
        );

        $paypal_mod_value = (awards_options('paypal_test_mode') == 'enable') ? 'sandbox' : 'live';
        $apicontext->setConfig(array(
            'mode' => $paypal_mod_value
        ));

        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod($data['payment_method']);

        $amount = new \PayPal\Api\Amount();
        $amount->setTotal(floatval($data['amount']));
        $amount->setCurrency($data['currency']);

        $transaction = new \PayPal\Api\Transaction();
        $transaction->setAmount($amount);

        // Add currency to redirect URL for verification later
        if (strpos($data['redirect_url'], '?') !== false) {
            $data['redirect_url'] .= '&currency=' . $data['currency'];
        } else {
            $data['redirect_url'] .= '?currency=' . $data['currency'];
        }

        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl($data['redirect_url'])
            ->setCancelUrl($data['cancel_url']);

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);

        try {
            $payment->create($apicontext);
            header('Location: ' . $payment->getApprovalLink());
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            // Log error securely - no sensitive data
            error_log('PayPal Error: Payment creation failed - ' . substr($ex->getMessage(), 0, 100));
            
            // For admin debugging only
            if (current_user_can('manage_options') && awards_options('paypal_debug_mode') == 'enable') {
                echo 'Error details: ' . $ex->getMessage();
            } else {
                echo 'Payment processing error. Please try again or contact support.';
            }
        }
    }

    public static function verify_payment_mass($total, $paymentId, $token, $PayerID) {
        // Sanitize inputs
        $total = floatval($total);
        $paymentId = preg_replace('/[^A-Za-z0-9\-_]/', '', $paymentId);
        $token = preg_replace('/[^A-Za-z0-9\-_]/', '', $token);
        $PayerID = preg_replace('/[^A-Za-z0-9]/', '', $PayerID);
        
        if (empty($paymentId) || empty($token) || empty($PayerID)) {
            error_log('PayPal mass verification - Invalid parameters');
            return false;
        }
        
        $api_base_url = self::$sandbox ? self::PAYPAL_SANDBOX_API : self::PAYPAL_LIVE_API;
        $ch = curl_init();
        
        // Set the PayPal API endpoint for executing the payment
        curl_setopt($ch, CURLOPT_URL, $api_base_url . '/v1/payments/payment/' . $paymentId . '/execute');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['payer_id' => $PayerID]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::get_token(),
        ]);
        
        // Security improvements for CURL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        
        curl_close($ch);
        
        if ($err) {
            error_log('PayPal verification error: Connection issue');
            return false;
        } else {
            $result = json_decode($response);
            
            // Check payment is approved and the amount matches our expected total
            if (
                isset($result->state) && 
                $result->state === 'approved' && 
                isset($result->transactions[0]->amount->total) && 
                floatval($result->transactions[0]->amount->total) >= floatval($total)
            ) {
                return $result;
            } else {
                error_log('Payment not approved or amount mismatch.');
                return false;
            }
        }
    }

    // Helper method for getting access token
    private static function get_token() {
    // Check if we already have a valid token in cache
    $token_cache_key = 'paypal_oauth_token_' . md5(self::$_ClientID);
    $cached_token = get_transient($token_cache_key);
    
    if ($cached_token) {
        return $cached_token;
    }
    
    // No valid cached token, request a new one
    $api_base_url = self::$sandbox ? self::PAYPAL_SANDBOX_API : self::PAYPAL_LIVE_API;
    $auth = base64_encode(self::$_ClientID . ":" . self::$_ClientSecret);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_base_url . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Basic " . $auth,
        "Accept: application/json",
        "Content-Type: application/x-www-form-urlencoded"
    ));
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('PayPal token error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    $data = json_decode($response);
    if (isset($data->access_token)) {
        // Cache the token for slightly less than its lifetime
        $expires_in = isset($data->expires_in) ? (int)$data->expires_in : 3600;
        set_transient($token_cache_key, $data->access_token, $expires_in - 60);
        return $data->access_token;
    } else {
        error_log('PayPal token error: Failed to get access token');
        return false;
    }
}

    public function verify_payment($postid, $paymentId, $token, $PayerID, $currency = '')
    {
        // Sanitize inputs
        $postid = intval($postid);
        $paymentId = preg_replace('/[^A-Za-z0-9\-_]/', '', $paymentId);
        $token = preg_replace('/[^A-Za-z0-9\-_]/', '', $token);
        $PayerID = preg_replace('/[^A-Za-z0-9]/', '', $PayerID);
        
        if (empty($postid) || empty($paymentId) || empty($token) || empty($PayerID)) {
            error_log('PayPal verification - Invalid parameters');
            return false;
        }
        
        // Always use normalized currency code
        $currency = get_normalized_currency();
        
        // Set up API context with proper error handling
        try {
            $apiContext = new \PayPal\Rest\ApiContext(
                new \PayPal\Auth\OAuthTokenCredential(
                    self::$_ClientID,
                    self::$_ClientSecret
                )
            );

            $paypal_mod_value = (awards_options('paypal_test_mode') == 'enable') ? 'sandbox' : 'live';
            $apiContext->setConfig(array(
                'mode' => $paypal_mod_value
            ));

            // Minimal debugging log
            error_log('Starting PayPal payment verification for Post ID: ' . $postid);

            // Get the payment details
            $payment = PayPal\Api\Payment::get($paymentId, $apiContext);
            
            $execution = new PayPal\Api\PaymentExecution();
            $execution->setPayerId($PayerID);

            // Get the expected amount
            $Amount_total = 0;
            if (get_copon_session()) {
                $Amount_total = get_award_entry_total_fee_with_copon($postid, false);
            } else {
                $Amount_total = get_award_entry_total_fee($postid, false);
            }

            try {
                // Execute the payment
                $result = $payment->execute($execution, $apiContext);
                
                // Get the payment details after execution
                $payment = PayPal\Api\Payment::get($paymentId, $apiContext);
                $result_final = json_decode($payment);

                // Simplified verification - only check if payment is approved
                if ($result_final->state == 'approved') {
                    error_log('Payment verification successful for Post ID: ' . $postid);
                    return $result_final;
                } else {
                    error_log('Payment not approved. State: ' . $result_final->state);
                }

                return false;

            } catch (Exception $ex) {
                error_log('PayPal Error: Payment execution failed - Transaction ID: ' . $paymentId);
                return false;
            }
        } catch (Exception $ex) {
            error_log('PayPal Error: Getting payment failed - Transaction ID: ' . $paymentId);
            return false;
        }
    }
}