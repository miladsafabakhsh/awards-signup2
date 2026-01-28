<?php
// Enable error reporting in development only - remove in production
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Use WordPress logging instead of writing to a public file
if (function_exists('error_log')) {
    error_log("Payment processing started for ID: " . (isset($_GET['id']) ? intval($_GET['id']) : 'none'));
}

try {
    /*
     * Stripe Payment Processing Page
     * This page handles both single entry and mass payments through Stripe
     */

    // Include WordPress - safer approach to loading WordPress
    define('WP_USE_THEMES', false);
    
    // Use a more direct approach to find WordPress
    $possible_paths = array(
        // Try most common relative paths first
        dirname(dirname(dirname(__FILE__))) . '/wp-load.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
        // Try predefined absolute path if available
        defined('ABSPATH') ? ABSPATH . 'wp-load.php' : '',
        // Last resort use document root
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
    );
    
    $wp_load_path = '';
    foreach ($possible_paths as $path) {
        if (!empty($path) && file_exists($path)) {
            $wp_load_path = $path;
            break;
        }
    }
    
    // If we couldn't find wp-load.php, display a secure error
    if (empty($wp_load_path)) {
        http_response_code(500);
        die("WordPress integration error. Please contact the administrator.");
    }

// Load WordPress
require_once($wp_load_path);

// Check if the user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(site_url('/wp-content/plugins/awards-signup2/stripe-payment.php?' . $_SERVER['QUERY_STRING'])));
    exit;
}

// Get entry ID(s) from the URL
$entry_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
if (empty($entry_id)) {
    wp_die('No entry ID provided', 'Error', array('response' => 400));
}

// Determine if this is a mass payment
$is_mass_payment = false;
if (strpos($entry_id, ',') !== false) {
    $is_mass_payment = true;
    $entry_ids = explode(',', $entry_id);
} else {
    $entry_ids = array($entry_id);
}

// Validate all entry IDs
foreach ($entry_ids as $current_entry_id) {
    if (get_post_status($current_entry_id) === false) {
        wp_die('Invalid entry ID: ' . $current_entry_id, 'Error', array('response' => 400));
    }
    
    // Check if the current user is the author
    $post = get_post($current_entry_id);
    if ($post->post_author != get_current_user_id() && !current_user_can('edit_post', $current_entry_id)) {
        wp_die('You do not have permission to pay for this entry', 'Error', array('response' => 403));
    }
    
    // Check if the entry is in pending payment status
    $status = get_post_meta($current_entry_id, 'status', true);
    if ($status !== 'pending_payment') {
        wp_die('This entry is not awaiting payment. Current status: ' . $status, 'Error', array('response' => 400));
    }
}

// Calculate total amount
$total_amount = 0;
$entry_data = array(); // Store entry details for display

foreach ($entry_ids as $current_entry_id) {
    $entry = get_post($current_entry_id);
    
    if (get_copon_session()) {
        $fee = get_award_entry_total_fee_with_copon($current_entry_id, false);
    } else {
        $fee = get_award_entry_total_fee($current_entry_id, false);
    }
    
    $total_amount += $fee;
    
    // Get categories
    $categories = array();
    $terms = wp_get_post_terms($current_entry_id, 'entrycat');
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $categories[] = $term->name;
        }
    }
    
    // Store entry details
    $entry_data[] = array(
        'id' => $current_entry_id,
        'title' => get_the_title($current_entry_id),
        'fee' => $fee,
        'type' => get_post_meta($current_entry_id, 'entrytype', true),
        'categories' => $categories,
    );
}

// Get currency
$user_country = get_user_meta(get_current_user_id(), 'country', true);
$default_currency = awards_options('currency');

// Normalize to Stripe-compatible currency code
$currency = 'usd'; // Default
$currency_symbol = '$';

if ($user_country === 'Iran (Islamic Republic of)') {
    // Note: Stripe doesn't support IRR, so we'll use USD but display IRR
    $currency = 'usd';
    $currency_symbol = 'IRR';
} elseif ($default_currency === 'euro' || $default_currency === 'eur') {
    $currency = 'eur';
    $currency_symbol = '€';
}

// Load Stripe PHP library
require_once('inc/vendor/stripe/init.php');

// Determine if we're in test mode
$test_mode = (awards_options('stripe_test_mode') == 'enable');

// Set the appropriate API key
if ($test_mode) {
    $stripe_secret_key = awards_options('stripe_test_secret_key');
    $stripe_publishable_key = awards_options('stripe_test_publishable_key');
} else {
    $stripe_secret_key = awards_options('stripe_secret_key');
    $stripe_publishable_key = awards_options('stripe_publishable_key');
}

// Check if keys are set
if (empty($stripe_secret_key) || empty($stripe_publishable_key)) {
    wp_die('Stripe API keys not configured. Please contact the administrator.', 'Configuration Error');
}

// Set Stripe API key
\Stripe\Stripe::setApiKey($stripe_secret_key);

// Get user information
$current_user = wp_get_current_user();

// Prepare line items
$line_items = array();

if ($is_mass_payment) {
    // For mass payment, create a single line item
    $line_items[] = [
        'price_data' => [
            'currency' => $currency,
            'product_data' => [
                'name' => 'Mass Payment - ' . count($entry_ids) . ' Entries',
                'description' => 'Payment for multiple contest entries',
            ],
            'unit_amount' => round($total_amount * 100), // Stripe expects amounts in cents
        ],
        'quantity' => 1,
    ];
} else {
    // For single payment, create a line item for the entry
    $entry = get_post($entry_ids[0]);
    $entry_type = get_post_meta($entry_ids[0], 'entrytype', true);
    
    $line_items[] = [
        'price_data' => [
            'currency' => $currency,
            'product_data' => [
                'name' => 'Entry Fee - ' . $entry->post_title,
                'description' => 'Type: ' . ucfirst($entry_type),
            ],
            'unit_amount' => round($total_amount * 100), // Stripe expects amounts in cents
        ],
        'quantity' => 1,
    ];
}

// Create metadata to identify the entry(s) in webhook
$metadata = [
    'entry_id' => $entry_id,
    'is_mass_payment' => $is_mass_payment ? 'true' : 'false',
    'user_id' => get_current_user_id(),
];

// Create redirect URLs
$success_url = site_url() . '/?paymentresult=true&gateway=stripe&id=' . $entry_id . '&success=true';
$cancel_url = site_url() . '/?paymentresult=true&gateway=stripe&id=' . $entry_id . '&success=false';

// Add mass parameter for mass payments
if ($is_mass_payment) {
    $success_url .= '&mass=true';
    $cancel_url .= '&mass=true';
}

// Add CSRF protection if forms are involved
if (isset($_POST['stripe_submit'])) {
    check_admin_referer('stripe_payment_nonce');
}

try {
    // Create Stripe Checkout Session
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'customer_email' => $current_user->user_email,
        'mode' => 'payment',
        'success_url' => $success_url . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $cancel_url,
        'metadata' => $metadata,
    ]);
    
    // Store the session ID temporarily
    foreach ($entry_ids as $current_entry_id) {
        update_post_meta($current_entry_id, 'stripe_session_id', $checkout_session->id);
    }
    
    // Redirect to Stripe Checkout
    header("Location: " . $checkout_session->url);
    exit;
} catch (Exception $e) {
    // Log the error
    error_log('Stripe error: ' . $e->getMessage());
    
    // Display user-friendly error
    wp_die('Sorry, there was an error processing your payment: ' . $e->getMessage(), 'Payment Error');
}

// If we get here, something went wrong with the redirect
wp_die('There was an error redirecting to the payment page. Please try again or contact support.', 'Redirect Error');

} catch (Exception $e) {
    // Log the error
    file_put_contents($log_file, "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    
    // Display a user-friendly error message
    echo '<div style="text-align: center; margin: 50px auto; max-width: 600px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    echo '<h2>Payment Processing Error</h2>';
    echo '<p>We encountered an error while processing your payment. Please try again or contact support.</p>';
    echo '<p>Error details: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="' . get_permalink(awards_options('page_dashboard')) . '?dash-page=entry-list" class="button">Return to Entries</a></p>';
    echo '</div>';
}