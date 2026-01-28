<?php
/**
 * Stripe Webhook Handler
 * This file handles webhook events from Stripe to update payment status
 */

// Prevent direct access if not a webhook request
if (!isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Define the root path to WordPress
if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);

    // Try to locate wp-load.php by going up directories
    $wp_load_path = '';
    $dir = dirname(__FILE__);
    do {
        if (file_exists($dir . '/wp-load.php')) {
            $wp_load_path = $dir . '/wp-load.php';
            break;
        }
    } while ($dir = realpath("$dir/.."));

    // If we couldn't find wp-load.php
    if (empty($wp_load_path)) {
        // Try a few fixed paths as fallbacks
        $possible_paths = array(
            dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
            dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php',
            $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $wp_load_path = $path;
                break;
            }
        }
    }

    // If we still couldn't find it, display an error
    if (empty($wp_load_path)) {
        http_response_code(500);
        die("Could not find WordPress files. Please contact the administrator.");
    }

    // Load WordPress
    require_once($wp_load_path);
}

// Simple rate limiting to prevent abuse
function is_webhook_rate_limited() {
    $rate_limit_key = 'stripe_webhook_rate_limit';
    $rate_limit_period = 60; // 1 minute
    $rate_limit_max = 10; // Max 10 requests per minute
    
    $current_count = get_transient($rate_limit_key);
    
    if ($current_count === false) {
        set_transient($rate_limit_key, 1, $rate_limit_period);
        return false;
    } elseif ($current_count < $rate_limit_max) {
        set_transient($rate_limit_key, $current_count + 1, $rate_limit_period);
        return false;
    } else {
        error_log('Stripe webhook rate limit exceeded');
        return true;
    }
}

// Check rate limiting
if (is_webhook_rate_limited()) {
    http_response_code(429);
    exit('Rate limit exceeded');
}

// Improved error logging function
function log_stripe_webhook_error($message, $data = array()) {
    $log_message = '[Stripe Webhook] ' . $message;
    
    if (!empty($data)) {
        $log_message .= ' - Data: ' . json_encode($data);
    }
    
    error_log($log_message);
    
    // Optionally store in a custom log table if available
    if (function_exists('awards_log_error')) {
        awards_log_error('stripe_webhook', $message, $data);
    }
}

// Get the webhook payload
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = awards_options('stripe_webhook_secret');

// Validate webhook signature is present
if (empty($sig_header)) {
    http_response_code(400);
    log_stripe_webhook_error('Stripe signature missing');
    exit('Stripe signature missing');
}

// Validate endpoint secret is configured
if (empty($endpoint_secret)) {
    log_stripe_webhook_error('Webhook secret not configured');
    http_response_code(500);
    exit('Webhook configuration error');
}

try {
    // Required for Stripe
    require_once(__DIR__ . '/inc/vendor/stripe/init.php');
    
    // Set API key based on mode
    $test_mode = (awards_options('stripe_test_mode') == 'enable');
    $stripe_secret_key = $test_mode ? 
        awards_options('stripe_test_secret_key') : 
        awards_options('stripe_secret_key');
    
    if (empty($stripe_secret_key)) {
        log_stripe_webhook_error('API key not configured');
        http_response_code(500);
        exit('API configuration error');
    }
    
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    
    // Always verify the event signature
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
    
    // Check for duplicate events
    $event_id = $event->id;
    $processed_events_key = 'processed_stripe_events';
    $processed_events = get_option($processed_events_key, array());

    if (in_array($event_id, $processed_events)) {
        // Event already processed, return success
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Event already processed']);
        exit;
    }
    
    // Handle specific event types
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            
            // Validate metadata
            $entry_id = isset($session->metadata->entry_id) ? sanitize_text_field($session->metadata->entry_id) : null;
            $is_mass_payment = isset($session->metadata->is_mass_payment) ? 
                filter_var($session->metadata->is_mass_payment, FILTER_VALIDATE_BOOLEAN) : false;
            $user_id = isset($session->metadata->user_id) ? intval($session->metadata->user_id) : 0;
            
            // Verify required data exists
            if (!$entry_id || !$user_id) {
                log_stripe_webhook_error('Missing required metadata', [
                    'event_id' => $event_id,
                    'entry_id' => $entry_id,
                    'user_id' => $user_id
                ]);
                http_response_code(400);
                exit('Invalid metadata');
            }
            
            // Process based on payment type (mass or single)
            if ($is_mass_payment) {
                // Validate mass payment entries
                $entry_ids = explode(',', $entry_id);
                $valid_entries = array();
                
                foreach ($entry_ids as $current_entry_id) {
                    $current_entry_id = intval($current_entry_id);
                    
                    // Verify each entry exists and belongs to the user
                    $post = get_post($current_entry_id);
                    if ($post && $post->post_type === 'entry' && $post->post_author == $user_id) {
                        $valid_entries[] = $current_entry_id;
                    } else {
                        log_stripe_webhook_error("Invalid entry ID in mass payment", [
                            'entry_id' => $current_entry_id,
                            'user_id' => $user_id
                        ]);
                    }
                }
                
                // Only proceed with valid entries
                if (empty($valid_entries)) {
                    log_stripe_webhook_error('No valid entries found', [
                        'event_id' => $event_id,
                        'original_entries' => $entry_ids
                    ]);
                    http_response_code(400);
                    exit('Invalid entries');
                }
                
                $entry_ids = $valid_entries;
                
                // Verify payment amount
                $expected_amount = 0;
                foreach ($entry_ids as $current_entry_id) {
                    if (function_exists('get_copon_session') && get_copon_session()) {
                        $expected_amount += get_award_entry_total_fee_with_copon($current_entry_id, false);
                    } else {
                        $expected_amount += get_award_entry_total_fee($current_entry_id, false);
                    }
                }
                
                // Allow for minor rounding differences (1% tolerance)
                $paid_amount = $session->amount_total / 100; // Convert from cents
                $amount_tolerance = $expected_amount * 0.01;
                
                if (abs($paid_amount - $expected_amount) > $amount_tolerance) {
                    log_stripe_webhook_error("Amount mismatch in mass payment", [
                        'expected' => $expected_amount,
                        'received' => $paid_amount,
                        'entries' => $entry_ids
                    ]);
                    // Still process the payment but log the discrepancy
                }
                
                // Format payment details
                $payment_details = array(
                    'gateway' => 'stripe',
                    'Amount' => $paid_amount,
                    'RefID' => $session->payment_intent,
                    'Authority' => $session->id,
                    'time' => date('Y-m-d H:i:s'),
                    'is_mass_payment' => true
                );
                
                // Process entries
                $current_time = date('Y-m-d H:i:s');
                $mass_payment_id = 'mass_' . $session->payment_intent;
                
                foreach ($entry_ids as $current_entry_id) {
                    // Update entry status
                    update_post_meta($current_entry_id, 'status', 'pending_review');
                    
                    // Update post modified time
                    wp_update_post(array(
                        'ID' => $current_entry_id,
                        'post_modified' => $current_time,
                        'post_modified_gmt' => $current_time,
                    ));
                    
                    // Store payment details
                    update_post_meta($current_entry_id, 'payment', $payment_details);
                    
                    // Mark as part of a mass payment
                    update_post_meta($current_entry_id, 'is_mass_payment', true);
                    update_post_meta($current_entry_id, 'mass_payment_id', $mass_payment_id);
                    update_post_meta($current_entry_id, 'mass_payment_entries', implode(',', $entry_ids));
                    
                    // Process coupon if used
                    if (function_exists('process_and_clear_coupon')) {
                        process_and_clear_coupon($current_entry_id, $user_id);
                    }
                    
                    // Remove temporary session ID
                    delete_post_meta($current_entry_id, 'stripe_session_id');
                }
                
                // Generate mass payment invoice
                if (function_exists('generate_transaction_invoice')) {
                    $invoice_path = generate_transaction_invoice($entry_ids, $payment_details);
                } elseif (function_exists('generate_mass_payment_invoice')) {
                    $invoice_path = generate_mass_payment_invoice($entry_ids, $payment_details);
                }
                
                // Send email notification to user
                $user = get_userdata($user_id);
                if ($user && is_callable('wp_mail') && function_exists('self::email_body')) {
                    $entry_titles = array();
                    foreach ($entry_ids as $eid) {
                        $entry_titles[] = get_the_title($eid);
                    }
                    
                    $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">';
                    $email_body .= '<p>' . sprintf(__('Dear %s %s,', 'awards'), $user->first_name, $user->last_name) . '</p>';
                    $email_body .= '<p>' . __('Your payment for multiple entries has been successfully processed.', 'awards') . '</p>';
                    $email_body .= '<p>' . __('Entries included in this payment:') . '</p><ul>';
                    
                    foreach ($entry_titles as $title) {
                        $email_body .= '<li>' . $title . '</li>';
                    }
                    
                    $email_body .= '</ul><p>' . __('Thank you for participating in the Minimalist Photography Awards.', 'awards') . '</p>';
                    $email_body .= '</div>';
                    
                    wp_mail($user->user_email, 'Entries Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                }
                
                log_stripe_webhook_error('Mass payment processed successfully', [
                    'event_id' => $event_id,
                    'entries' => $entry_ids,
                    'amount' => $paid_amount
                ]);
            } else {
                // Handle single entry payment
                $entry_id = intval($entry_id);
                $post = get_post($entry_id);
                
                // Validate the entry
                if (!$post || $post->post_type !== 'entry' || $post->post_author != $user_id) {
                    log_stripe_webhook_error("Invalid entry for single payment", [
                        'entry_id' => $entry_id,
                        'user_id' => $user_id
                    ]);
                    http_response_code(400);
                    exit('Invalid entry');
                }
                
                // Verify payment amount
                $expected_amount = 0;
                if (function_exists('get_copon_session') && get_copon_session()) {
                    $expected_amount = get_award_entry_total_fee_with_copon($entry_id, false);
                } else {
                    $expected_amount = get_award_entry_total_fee($entry_id, false);
                }
                
                // Allow for minor rounding differences (1% tolerance)
                $paid_amount = $session->amount_total / 100; // Convert from cents
                $amount_tolerance = $expected_amount * 0.01;
                
                if (abs($paid_amount - $expected_amount) > $amount_tolerance) {
                    log_stripe_webhook_error("Amount mismatch in single payment", [
                        'expected' => $expected_amount,
                        'received' => $paid_amount,
                        'entry_id' => $entry_id
                    ]);
                    // Still process the payment but log the discrepancy
                }
                
                // Format payment details
                $payment_details = array(
                    'gateway' => 'stripe',
                    'Amount' => $paid_amount,
                    'RefID' => $session->payment_intent,
                    'Authority' => $session->id,
                    'time' => date('Y-m-d H:i:s'),
                );
                
                // Update entry status
                update_post_meta($entry_id, 'status', 'pending_review');
                
                // Update post modified time
                $current_time = date('Y-m-d H:i:s');
                wp_update_post(array(
                    'ID' => $entry_id,
                    'post_modified' => $current_time,
                    'post_modified_gmt' => $current_time,
                ));
                
                // Store payment details
                $currentmeta = get_post_meta($entry_id, 'payment', true);
                if ($currentmeta) {
                    update_post_meta($entry_id, 'payment', $payment_details);
                } else {
                    add_post_meta($entry_id, 'payment', $payment_details);
                }
                
                // Remove temporary session ID
                delete_post_meta($entry_id, 'stripe_session_id');
                
                // Generate invoice
                if (function_exists('generate_entry_invoice')) {
                    $invoice_path = generate_entry_invoice($entry_id, $payment_details);
                }
                
                // Process coupon
                if (function_exists('process_and_clear_coupon')) {
                    process_and_clear_coupon($entry_id, $user_id);
                }
                
                // Send email notification
                $user = get_userdata($user_id);
                if ($user && is_callable('wp_mail')) {
                    $entry_title = get_the_title($entry_id);
                    
                    // Get email template if available
                    $email_template = function_exists('awards_options') ? awards_options('entry_payment_success_fully_mail_text') : '';
                    
                    if ($email_template) {
                        $email_body_text = str_replace(
                            array('{entry_title}', '{user_firstname}', '{user_lastname}'),
                            array($entry_title, $user->first_name, $user->last_name),
                            $email_template
                        );
                    } else {
                        $email_body_text = "Your payment for entry \"$entry_title\" was successful. Thank you!";
                    }
                    
                    $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">' . wpautop($email_body_text) . '</div>';
                    
                    wp_mail($user->user_email, 'Entry Payment Successful', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                }
                
                log_stripe_webhook_error('Single payment processed successfully', [
                    'event_id' => $event_id,
                    'entry_id' => $entry_id,
                    'amount' => $paid_amount
                ]);
            }
            
            break;
            
        case 'payment_intent.payment_failed':
            $intent = $event->data->object;
            log_stripe_webhook_error('Payment failed', [
                'event_id' => $event_id,
                'intent_id' => $intent->id,
                'customer' => $intent->customer,
                'amount' => $intent->amount / 100, // Convert from cents
                'error' => isset($intent->last_payment_error) ? $intent->last_payment_error->message : 'Unknown error'
            ]);
            break;
            
        default:
            // Unexpected event type
            log_stripe_webhook_error('Received unknown event type', [
                'event_id' => $event_id,
                'type' => $event->type
            ]);
    }
    
    // After successful processing, store the event ID
    $processed_events[] = $event_id;
    // Keep only the last 100 events to prevent option growth
    if (count($processed_events) > 100) {
        $processed_events = array_slice($processed_events, -100);
    }
    update_option($processed_events_key, $processed_events);
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch(\UnexpectedValueException $e) {
    // Invalid payload
    log_stripe_webhook_error('Invalid payload error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    log_stripe_webhook_error('Invalid signature error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit();
} catch (\Exception $e) {
    // Other errors
    log_stripe_webhook_error('General error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit();
}