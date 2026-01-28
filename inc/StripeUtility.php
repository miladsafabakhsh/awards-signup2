<?php
/**
 * Stripe Utility Class
 * This file should be placed in the inc directory
 */

class StripeUtility {
    /**
     * Get the appropriate Stripe API key based on mode
     * 
     * @param string $type Either 'secret' or 'publishable'
     * @return string The API key
     */
    public static function getApiKey($type = 'secret') {
        $testMode = (awards_options('stripe_test_mode') == 'enable');
        
        if ($type === 'secret') {
            return $testMode ? 
                awards_options('stripe_test_secret_key') : 
                awards_options('stripe_secret_key');
        } else {
            return $testMode ? 
                awards_options('stripe_test_publishable_key') : 
                awards_options('stripe_publishable_key');
        }
    }
    
    /**
     * Initialize Stripe with the appropriate API key
     */
    public static function init() {
        require_once(__DIR__ . '/vendor/stripe/init.php');
        \Stripe\Stripe::setApiKey(self::getApiKey('secret'));
        
        // Set API version explicitly for consistency
        \Stripe\Stripe::setApiVersion('2022-11-15');
        
        if (awards_options('stripe_debug_mode') == 'enable') {
            // Enable debug mode when needed
            \Stripe\Stripe::setLogger(new StripeLogger());
        }
    }
    
    /**
     * Securely encrypt sensitive data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    private static function secureEncrypt($data) {
        if (function_exists('wp_encrypt_string')) {
            // Use WordPress encryption if available
            return wp_encrypt_string($data);
        } else {
            // Fallback encryption with secure IV generation
            $encryption_key = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-this';
            
            // Generate a random initialization vector
            $iv_size = openssl_cipher_iv_length('AES-256-CBC');
            $iv = openssl_random_pseudo_bytes($iv_size);
            
            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                'AES-256-CBC',
                $encryption_key,
                0,
                $iv
            );
            
            // Return both the IV and encrypted data
            return base64_encode($iv . $encrypted);
        }
    }
    
    /**
     * Securely decrypt sensitive data
     * 
     * @param string $encrypted_data Encrypted data
     * @return string|false Decrypted data or false on failure
     */
    private static function secureDecrypt($encrypted_data) {
        if (function_exists('wp_decrypt_string')) {
            // Use WordPress decryption if available
            return wp_decrypt_string($encrypted_data);
        } else {
            // Fallback decryption
            $encryption_key = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-this';
            
            // Decode the combined data
            $decoded = base64_decode($encrypted_data);
            if ($decoded === false) {
                return false;
            }
            
            // Extract the IV size
            $iv_size = openssl_cipher_iv_length('AES-256-CBC');
            if (strlen($decoded) <= $iv_size) {
                return false;
            }
            
            // Extract IV and encrypted data
            $iv = substr($decoded, 0, $iv_size);
            $encrypted = substr($decoded, $iv_size);
            
            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $encryption_key,
                0,
                $iv
            );
            
            return $decrypted;
        }
    }
    
    /**
     * Create a checkout session for an entry or multiple entries
     * 
     * @param array $entry_ids Array of entry IDs
     * @param bool $is_mass_payment Whether this is a mass payment
     * @return \Stripe\Checkout\Session|false The session object or false on failure
     */
    public static function createCheckoutSession($entry_ids, $is_mass_payment = false) {
        if (empty($entry_ids)) {
            return false;
        }
        
        // Sanitize entry IDs
        $sanitized_entry_ids = array_map('intval', $entry_ids);
        
        // Verify current user owns all entries
        $current_user_id = get_current_user_id();
        foreach ($sanitized_entry_ids as $entry_id) {
            $entry = get_post($entry_id);
            if (!$entry || $entry->post_author != $current_user_id) {
                error_log('Stripe Error: User ' . $current_user_id . ' attempted to create session for unauthorized entry ' . $entry_id);
                return false;
            }
            
            // Verify entry status is pending_payment
            $status = get_post_meta($entry_id, 'status', true);
            if ($status !== 'pending_payment') {
                error_log('Stripe Error: Entry ' . $entry_id . ' is not in pending_payment status. Current status: ' . $status);
                return false;
            }
        }
        
        // Initialize Stripe
        self::init();
        
        // Calculate total amount
        $total_amount = 0;
        $line_items = array();
        $entry_data = array();
        
        // Get current user
        $current_user = wp_get_current_user();
        
        // Get currency
        $user_country = get_user_meta($current_user_id, 'country', true);
        $default_currency = awards_options('currency');
        
        // Normalize to Stripe-compatible currency code
        $currency = 'usd'; // Default
        
        if ($default_currency === 'euro' || $default_currency === 'eur') {
            $currency = 'eur';
        }
        
        // Calculate total and prepare line items
        foreach ($sanitized_entry_ids as $entry_id) {
            $entry = get_post($entry_id);
            
            if (!$entry) {
                continue;
            }
            
            // Get entry fee
            if (function_exists('get_copon_session') && get_copon_session()) {
                $fee = get_award_entry_total_fee_with_copon($entry_id, false);
            } else {
                $fee = get_award_entry_total_fee($entry_id, false);
            }
            
            $total_amount += $fee;
            
            // Get entry type and other details
            $entry_type = get_post_meta($entry_id, 'entrytype', true);
            
            // Store entry data
            $entry_data[$entry_id] = array(
                'title' => $entry->post_title,
                'type' => $entry_type,
                'fee' => $fee
            );
        }
        
        // Generate a secure one-time token for payment verification
        $payment_verification_token = wp_generate_password(32, false);
        $token_expiry = time() + 7200; // 2 hour expiry
        
        // Prepare line items based on whether this is a mass payment
        if ($is_mass_payment) {
            // For mass payment, create one line item for all entries
            $line_items[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Mass Payment - ' . count($sanitized_entry_ids) . ' Entries',
                        'description' => 'Payment for multiple contest entries',
                    ],
                    'unit_amount' => round($total_amount * 100), // Stripe expects amounts in cents
                ],
                'quantity' => 1,
            ];
        } else {
            // For single entry, create a line item for the entry
            $entry_id = reset($sanitized_entry_ids);
            $entry_info = $entry_data[$entry_id];
            
            $line_items[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Entry Fee - ' . esc_html($entry_info['title']),
                        'description' => 'Type: ' . ucfirst(esc_html($entry_info['type'])),
                    ],
                    'unit_amount' => round($total_amount * 100), // Stripe expects amounts in cents
                ],
                'quantity' => 1,
            ];
        }
        
        // Create metadata to identify the entry(s) in webhook
        $metadata = [
            'entry_id' => $is_mass_payment ? implode(',', $sanitized_entry_ids) : reset($sanitized_entry_ids),
            'is_mass_payment' => $is_mass_payment ? 'true' : 'false',
            'user_id' => $current_user_id,
            // Add verification token for additional security
            'verification_token' => $payment_verification_token,
            'expected_amount' => round($total_amount * 100), // Store expected amount for verification
        ];
        
        // Create redirect URLs
        $entry_id_param = $is_mass_payment ? implode(',', $sanitized_entry_ids) : reset($sanitized_entry_ids);
        $success_url = site_url() . '/?paymentresult=true&gateway=stripe&id=' . $entry_id_param . '&success=true';
        $cancel_url = site_url() . '/?paymentresult=true&gateway=stripe&id=' . $entry_id_param . '&success=false';
        
        // Add mass parameter for mass payments
        if ($is_mass_payment) {
            $success_url .= '&mass=true';
            $cancel_url .= '&mass=true';
        }
        
        // Add a token to URLs to prevent CSRF
        $redirect_token = wp_create_nonce('stripe_redirect_' . $current_user_id);
        $success_url .= '&_wpnonce=' . $redirect_token;
        $cancel_url .= '&_wpnonce=' . $redirect_token;
        
        try {
            // Create Stripe Checkout Session
            $checkout_session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'customer_email' => $current_user->user_email,
                'mode' => 'payment',
                'success_url' => $success_url . '&token=' . $payment_verification_token,
                'cancel_url' => $cancel_url,
                'metadata' => $metadata,
            ]);
            
            // Store the session ID and verification token securely with each entry
            foreach ($sanitized_entry_ids as $entry_id) {
                // Encrypt session ID before storing
                $encrypted_session_id = self::secureEncrypt($checkout_session->id);
                
                // Store session data and verification token
                update_post_meta($entry_id, 'stripe_session_id', $encrypted_session_id);
                update_post_meta($entry_id, 'stripe_session_created', time());
                update_post_meta($entry_id, 'stripe_expected_amount', round($total_amount * 100));
                update_post_meta($entry_id, 'stripe_verification_token', $payment_verification_token);
                update_post_meta($entry_id, 'stripe_token_expiry', $token_expiry);
            }
            
            return $checkout_session;
        } catch (\Exception $e) {
            // Log the error without exposing sensitive details
            error_log('Stripe Error: Failed to create session - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify and process a successful payment
     * 
     * @param string $session_id The Stripe session ID
     * @param array $entry_ids Array of entry IDs
     * @param bool $is_mass_payment Whether this is a mass payment
     * @param string $verification_token Optional verification token from URL
     * @return bool Success status
     */
    public static function processSuccessfulPayment($session_id, $entry_ids, $is_mass_payment = false, $verification_token = '') {
        if (empty($session_id) || empty($entry_ids)) {
            return false;
        }
        
        // Sanitize inputs
        $session_id = preg_replace('/[^A-Za-z0-9_]/', '', $session_id);
        $sanitized_entry_ids = array_map('intval', $entry_ids);
        $verification_token = preg_replace('/[^A-Za-z0-9]/', '', $verification_token);
        
        // Verify current user owns all entries
        $current_user_id = get_current_user_id();
        foreach ($sanitized_entry_ids as $entry_id) {
            $entry = get_post($entry_id);
            if (!$entry || $entry->post_author != $current_user_id) {
                error_log('Stripe Error: User ' . $current_user_id . ' attempted to verify payment for unauthorized entry ' . $entry_id);
                return false;
            }
        }
        
        // Verify the stored session ID matches for at least one entry
        $session_found = false;
        $stored_token = '';
        
        foreach ($sanitized_entry_ids as $entry_id) {
            $encrypted_session_id = get_post_meta($entry_id, 'stripe_session_id', true);
            if (!empty($encrypted_session_id)) {
                $decrypted_session_id = self::secureDecrypt($encrypted_session_id);
                
                // Also verify the token if provided
                $stored_token = get_post_meta($entry_id, 'stripe_verification_token', true);
                $token_expiry = get_post_meta($entry_id, 'stripe_token_expiry', true);
                
                if ($decrypted_session_id === $session_id) {
                    $session_found = true;
                    
                    // If verification token was provided, check that it matches and hasn't expired
                    if (!empty($verification_token) && (!empty($stored_token) && $verification_token !== $stored_token)) {
                        error_log('Stripe Error: Verification token mismatch for entry ' . $entry_id);
                        return false;
                    }
                    
                    if (!empty($token_expiry) && time() > intval($token_expiry)) {
                        error_log('Stripe Error: Verification token expired for entry ' . $entry_id);
                        return false;
                    }
                    
                    break;
                }
            }
        }
        
        if (!$session_found) {
            error_log('Stripe Error: No matching session found for: ' . $session_id);
            return false;
        }
        
        // Initialize Stripe
        self::init();
        
        try {
            // Retrieve the session
            $session = \Stripe\Checkout\Session::retrieve([
                'id' => $session_id,
                'expand' => ['payment_intent'],
            ]);
            
            if (!$session || $session->payment_status !== 'paid') {
                error_log('Stripe Error: Session not paid - ' . $session_id);
                return false;
            }
            
            // Calculate expected amount
            $expected_amount = 0;
            foreach ($sanitized_entry_ids as $entry_id) {
                // Get previously stored expected amount
                $stored_expected_amount = get_post_meta($entry_id, 'stripe_expected_amount', true);
                
                if ($stored_expected_amount) {
                    // Use previously calculated amount
                    $expected_amount = intval($stored_expected_amount);
                    break; // Only need one for mass payments
                } else {
                    // Recalculate if not stored
                    if (function_exists('get_copon_session') && get_copon_session()) {
                        $expected_amount += round(get_award_entry_total_fee_with_copon($entry_id, false) * 100);
                    } else {
                        $expected_amount += round(get_award_entry_total_fee($entry_id, false) * 100);
                    }
                }
            }
            
            // Verify the payment amount matches expected amount
            if ($session->amount_total < $expected_amount) {
                error_log('Stripe Error: Amount mismatch. Expected: ' . $expected_amount . ', Got: ' . $session->amount_total);
                return false;
            }
            
            // Verify payment intent matches expected session
            if (empty($session->payment_intent)) {
                error_log('Stripe Error: Missing payment intent in session ' . $session_id);
                return false;
            }
            
            // Payment was successful - create payment details
            $current_time = date('Y-m-d H:i:s');
            
            // Create payment details array
            $payment_details = array(
                'gateway' => 'stripe',
                'Amount' => $session->amount_total / 100, // Convert from cents
                'RefID' => $session->payment_intent,
                'Authority' => $session_id,
                'time' => $current_time,
            );
            
            // Process payment differently based on whether it's a mass payment
            if ($is_mass_payment) {
                // Process mass payment
                $mass_payment_id = 'mass_' . $session->payment_intent;
                
                // Update each entry
                foreach ($sanitized_entry_ids as $entry_id) {
                    // Update entry status
                    update_post_meta($entry_id, 'status', 'pending_review');
                    
                    // Update post modified date
                    wp_update_post([
                        'ID' => $entry_id,
                        'post_modified' => $current_time,
                        'post_modified_gmt' => $current_time,
                    ]);
                    
                    // Store payment details
                    update_post_meta($entry_id, 'payment', $payment_details);
                    
                    // Mark as part of a mass payment
                    update_post_meta($entry_id, 'is_mass_payment', true);
                    update_post_meta($entry_id, 'mass_payment_id', $mass_payment_id);
                    update_post_meta($entry_id, 'mass_payment_entries', implode(',', $sanitized_entry_ids));
                    
                    // Remove temporary session data
                    delete_post_meta($entry_id, 'stripe_session_id');
                    delete_post_meta($entry_id, 'stripe_session_created');
                    delete_post_meta($entry_id, 'stripe_expected_amount');
                    delete_post_meta($entry_id, 'stripe_verification_token');
                    delete_post_meta($entry_id, 'stripe_token_expiry');
                    
                    // Process coupon if used
                    if (function_exists('process_and_clear_coupon')) {
                        process_and_clear_coupon($entry_id);
                    }
                }
                
                // Generate one invoice for all entries
                if (function_exists('generate_mass_payment_invoice')) {
                    generate_mass_payment_invoice($sanitized_entry_ids, $payment_details);
                }
                
                // Send notification email
                self::sendMassPaymentEmail($sanitized_entry_ids, $payment_details);
            } else {
                // Process single entry payment
                $entry_id = reset($sanitized_entry_ids);
                
                // Update entry status
                update_post_meta($entry_id, 'status', 'pending_review');
                
                // Update post modified date
                wp_update_post([
                    'ID' => $entry_id,
                    'post_modified' => $current_time,
                    'post_modified_gmt' => $current_time,
                ]);
                
                // Store payment details
                update_post_meta($entry_id, 'payment', $payment_details);
                
                // Remove temporary session data
                delete_post_meta($entry_id, 'stripe_session_id');
                delete_post_meta($entry_id, 'stripe_session_created');
                delete_post_meta($entry_id, 'stripe_expected_amount');
                delete_post_meta($entry_id, 'stripe_verification_token');
                delete_post_meta($entry_id, 'stripe_token_expiry');
                
                // Generate invoice
                if (function_exists('generate_entry_invoice')) {
                    generate_entry_invoice($entry_id, $payment_details);
                }
                
                // Process coupon if used
                if (function_exists('process_and_clear_coupon')) {
                    process_and_clear_coupon($entry_id);
                }
                
                // Send notification email
                self::sendPaymentEmail($entry_id, $payment_details);
            }
            
            return true;
        } catch (\Exception $e) {
            // Log the error
            error_log('Stripe Payment Processing Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification email for mass payment
     * 
     * @param array $entry_ids Array of entry IDs
     * @param array $payment_details Payment details
     */
    private static function sendMassPaymentEmail($entry_ids, $payment_details) {
        $user_info = wp_get_current_user();
        
        // If mass_payment_email_body exists in the wp_awards class, use it
        if (method_exists('wp_awards', 'mass_payment_email_body')) {
            $email_body_text = wp_awards::mass_payment_email_body($entry_ids, $user_info->ID);
            $email_body = wp_awards::email_body(wpautop($email_body_text));
        } else {
            // Fallback if the method doesn't exist
            $entry_titles = array();
            foreach ($entry_ids as $entry_id) {
                $entry_titles[] = esc_html(get_the_title($entry_id));
            }
            
            $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">';
            $email_body .= '<p>' . sprintf(__('Dear %s %s,', 'awards'), esc_html($user_info->first_name), esc_html($user_info->last_name)) . '</p>';
            $email_body .= '<p>' . __('Your payment for multiple entries has been successfully processed.', 'awards') . '</p>';
            $email_body .= '<p>' . __('Entries included in this payment:') . '</p><ul>';
            
            foreach ($entry_titles as $title) {
                $email_body .= '<li>' . $title . '</li>';
            }
            
            $email_body .= '</ul><p>' . __('Thank you for participating in the Minimalist Photography Awards.', 'awards') . '</p>';
            $email_body .= '</div>';
        }
        
        // Add invoice attachment if available
        $attachments = array();
        $first_entry_id = reset($entry_ids);
        $invoice_path = get_post_meta($first_entry_id, 'invoice_path', true);
        
        if (!empty($invoice_path) && file_exists($invoice_path)) {
            $attachments[] = $invoice_path;
        }
        
        wp_mail($user_info->user_email, 'Entries Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'), $attachments);
    }
    
    /**
     * Send notification email for single payment
     * 
     * @param int $entry_id The entry ID
     * @param array $payment_details Payment details
     */
    private static function sendPaymentEmail($entry_id, $payment_details) {
        $user_info = wp_get_current_user();
        $entry_title = esc_html(get_the_title($entry_id));
        
        // Get the email template from options
        $email_template = awards_options('entry_payment_success_fully_mail_text');
        
        // Replace placeholders if the template exists
        if ($email_template) {
            $email_body_text = str_replace(
                array('{entry_title}', '{user_firstname}', '{user_lastname}'),
                array($entry_title, esc_html($user_info->first_name), esc_html($user_info->last_name)),
                $email_template
            );
        } else {
            // Fallback if no template exists
            $email_body_text = "Your payment for entry \"$entry_title\" was successful. Thank you!";
        }
        
        // Wrap in HTML email body
        if (method_exists('wp_awards', 'email_body')) {
            $email_body = wp_awards::email_body(wpautop($email_body_text));
        } else {
            $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">' . wpautop($email_body_text) . '</div>';
        }
        
        // Add invoice attachment if available
        $attachments = array();
        $invoice_path = get_post_meta($entry_id, 'invoice_path', true);
        
        if (!empty($invoice_path) && file_exists($invoice_path)) {
            $attachments[] = $invoice_path;
        }
        
        wp_mail($user_info->user_email, 'Entry Payment Successful', $email_body, array('Content-Type: text/html; charset=UTF-8'), $attachments);
    }
    
    /**
     * Verify Stripe webhook signature
     * This should be added to your webhook handler script
     *
     * @param string $payload The raw request body
     * @param string $sig_header The Stripe signature header
     * @return \Stripe\Event|false The verified event or false on failure
     */
    public static function verifyWebhookSignature($payload, $sig_header) {
        if (empty($payload) || empty($sig_header)) {
            return false;
        }
        
        // Get webhook secret
        $webhook_secret = awards_options('stripe_webhook_secret');
        if (empty($webhook_secret)) {
            error_log('Stripe Webhook Error: No webhook secret configured');
            return false;
        }
        
        try {
            // Initialize Stripe
            self::init();
            
            // Verify the signature
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $webhook_secret
            );
            
            return $event;
        } catch (\Exception $e) {
            error_log('Stripe Webhook Error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Stripe Logger class for debugging
 */
class StripeLogger implements \Stripe\LoggerInterface {
    public function error($message, array $context = array()) {
        // Remove sensitive data before logging
        $safe_context = $this->sanitizeContext($context);
        error_log("Stripe Error: " . $message . " Context: " . json_encode($safe_context));
    }
    
    public function info($message, array $context = array()) {
        if (awards_options('stripe_debug_mode') == 'enable') {
            // Remove sensitive data before logging
            $safe_context = $this->sanitizeContext($context);
            error_log("Stripe Info: " . $message . " Context: " . json_encode($safe_context));
        }
    }
    
    /**
     * Sanitize context data to remove sensitive information
     *
     * @param array $context The context array
     * @return array Sanitized context
     */
    private function sanitizeContext(array $context) {
        $sensitive_fields = ['card', 'customer', 'source', 'payment_method', 'token', 'email'];
        $safe_context = [];
        
        foreach ($context as $key => $value) {
            if (in_array($key, $sensitive_fields)) {
                $safe_context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $safe_context[$key] = $this->sanitizeContext($value);
            } else {
                $safe_context[$key] = $value;
            }
        }
        
        return $safe_context;
    }
}