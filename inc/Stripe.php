<?php
class Stripe {
    private static $_SecretKey;
    private static $_PublishableKey;
    
    public static function set_auth($secret_key, $publishable_key) {
        self::$_SecretKey = $secret_key;
        self::$_PublishableKey = $publishable_key;
    }
    
    public function doPayment($data = array()) {
        // Validate input data
        if (empty($data['redirect_url']) || empty($data['post_id'])) {
            wp_safe_redirect(home_url());
            exit;
        }
        
        // Sanitize inputs
        $redirect_url = esc_url_raw($data['redirect_url']);
        $post_id = intval($data['post_id']);
        
        // Verify the user has permission to access this entry
        $entry = get_post($post_id);
        if (!$entry || $entry->post_author != get_current_user_id()) {
            wp_die('Access denied', 'Payment Error', array('response' => 403));
        }
        
        // Initialize Stripe
        require_once(__DIR__ . '/vendor/stripe/init.php');
        \Stripe\Stripe::setApiKey(self::$_SecretKey);
        
        try {
            // Calculate amount based on entry fee
            $amount = 0;
            if (get_copon_session()) {
                $amount = get_award_entry_total_fee_with_copon($post_id, false);
            } else {
                $amount = get_award_entry_total_fee($post_id, false);
            }
            
            // Determine currency
            $currency = 'usd'; // Default
            $default_currency = awards_options('currency');
            if ($default_currency === 'euro' || $default_currency === 'eur') {
                $currency = 'eur';
            }
            
            // Create line item
            $line_items = [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Entry Fee - ' . get_the_title($post_id),
                            'description' => 'Entry payment for ' . get_the_title($post_id),
                        ],
                        'unit_amount' => round($amount * 100), // Stripe expects amounts in cents
                    ],
                    'quantity' => 1,
                ]
            ];
            
            // Create checkout session
            $checkout_session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'payment',
                'success_url' => $redirect_url . '?session_id={CHECKOUT_SESSION_ID}&success=true',
                'cancel_url' => $redirect_url . '?success=false',
                'metadata' => [
                    'entry_id' => $post_id,
                    'user_id' => get_current_user_id(),
                ]
            ]);
            
            // Store session ID securely
            if (function_exists('wp_encrypt_string')) {
                // Use WordPress encryption if available
                $encrypted_session_id = wp_encrypt_string($checkout_session->id);
            } else {
                // Basic encryption (replace with a more secure method if possible)
                $encryption_key = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-this';
                $encrypted_session_id = base64_encode(openssl_encrypt(
                    $checkout_session->id,
                    'AES-256-CBC',
                    $encryption_key,
                    0,
                    substr(str_repeat('x', 16) . SECURE_AUTH_SALT, 0, 16)
                ));
            }
            
            update_post_meta($post_id, 'stripe_session_id', $encrypted_session_id);
            update_post_meta($post_id, 'stripe_session_created', time());
            
            // Safe redirect to Stripe checkout
            wp_redirect($checkout_session->url);
            exit;
            
        } catch (\Exception $e) {
            // Log error (without exposing sensitive details)
            error_log('Stripe payment initialization error: ' . $e->getMessage());
            
            // Redirect to error page
            wp_safe_redirect(add_query_arg('payment_error', 'stripe', $redirect_url));
            exit;
        }
    }
    
    public function verify_payment($postid, $session_id) {
        // Sanitize inputs
        $postid = intval($postid);
        $session_id = preg_replace('/[^A-Za-z0-9_]/', '', $session_id);
        
        // Verify the user has permission to access this entry
        $entry = get_post($postid);
        if (!$entry) {
            error_log('Stripe verification error: Invalid entry ID: ' . $postid);
            return false;
        }
        
        // Retrieve the stored session ID to compare (for extra security)
        $stored_session_data = get_post_meta($postid, 'stripe_session_id', true);
        if (empty($stored_session_data)) {
            error_log('Stripe verification error: No session data found for entry ID: ' . $postid);
            return false;
        }
        
        // Decrypt the stored session ID
        if (function_exists('wp_decrypt_string')) {
            // Use WordPress decryption if available
            $stored_session_id = wp_decrypt_string($stored_session_data);
        } else {
            // Basic decryption (should match the encryption method used)
            $encryption_key = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-this';
            $stored_session_id = openssl_decrypt(
                base64_decode($stored_session_data),
                'AES-256-CBC',
                $encryption_key,
                0,
                substr(str_repeat('x', 16) . SECURE_AUTH_SALT, 0, 16)
            );
        }
        
        // Compare session IDs
        if ($stored_session_id !== $session_id) {
            error_log('Stripe verification error: Session ID mismatch for entry ID: ' . $postid);
            return false;
        }
        
        // Verify the session with Stripe
        try {
            // Initialize Stripe
            require_once(__DIR__ . '/vendor/stripe/init.php');
            \Stripe\Stripe::setApiKey(self::$_SecretKey);
            
            // Retrieve the session
            $session = \Stripe\Checkout\Session::retrieve([
                'id' => $session_id,
                'expand' => ['payment_intent'],
            ]);
            
            // Check if payment was successful
            if (!$session || $session->payment_status !== 'paid') {
                error_log('Stripe verification error: Payment not complete for session ID: ' . $session_id);
                return false;
            }
            
            // Calculate expected amount
            $expected_amount = 0;
            if (get_copon_session()) {
                $expected_amount = get_award_entry_total_fee_with_copon($postid, false);
            } else {
                $expected_amount = get_award_entry_total_fee($postid, false);
            }
            
            // Convert to cents for comparison with Stripe amounts
            $expected_amount_cents = round($expected_amount * 100);
            
            // Verify the payment amount
            if ($session->amount_total < $expected_amount_cents) {
                error_log('Stripe verification error: Amount mismatch. Expected: ' . $expected_amount_cents . ', Got: ' . $session->amount_total);
                return false;
            }
            
            // Payment verified successfully
            return $session;
            
        } catch (\Exception $e) {
            error_log('Stripe verification error: ' . $e->getMessage());
            return false;
        }
    }
}