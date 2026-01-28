<?php
/**
 * Template for displaying Stripe payment result
 * This file should be placed in the template directory
 */

// Verify the nonce for CSRF protection
if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'stripe_redirect_' . get_current_user_id())) {
    wp_die('Security check failed', 'Error', array('response' => 403));
}

$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$entry_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
$is_mass_payment = isset($_GET['mass']) && $_GET['mass'] === 'true';
$refid = isset($_GET['refid']) ? sanitize_text_field($_GET['refid']) : '';

// Determine if this was a success or failure
$success = ($status === 'OK');

// Verify that this user owns the entries being displayed
if ($is_mass_payment) {
    $entry_ids = explode('-', $entry_id);
} else {
    $entry_ids = array($entry_id);
}

foreach ($entry_ids as $id) {
    $post = get_post(intval($id));
    if (!$post || $post->post_author != get_current_user_id()) {
        wp_die('Access denied', 'Error', array('response' => 403));
    }
}

// Get entry details
if ($is_mass_payment) {
    $entry_count = count($entry_ids);
    $entry_titles = array();
    
    foreach ($entry_ids as $id) {
        $entry_titles[] = esc_html(get_the_title(intval($id)));
    }
} else {
    $entry_title = esc_html(get_the_title(intval($entry_id)));
}

// Get payment details if successful
if ($success && !$is_mass_payment) {
    $payment_details = get_post_meta(intval($entry_id), 'payment', true);
    $amount = isset($payment_details['Amount']) ? floatval($payment_details['Amount']) : '';
    $currency = get_awards_price_symbol();
    
    // Get invoice URL if available
    $invoice_url = get_post_meta(intval($entry_id), 'invoice_url', true);
    if ($invoice_url) {
        $invoice_url = esc_url($invoice_url);
    }
}

// For mass payments
if ($success && $is_mass_payment) {
    // Just get the details from the first entry
    $first_entry_id = intval($entry_ids[0]);
    $payment_details = get_post_meta($first_entry_id, 'payment', true);
    $amount = isset($payment_details['Amount']) ? floatval($payment_details['Amount']) : '';
    $currency = get_awards_price_symbol();
    
    // Get invoice URL if available
    $invoice_url = get_post_meta($first_entry_id, 'invoice_url', true);
    if ($invoice_url) {
        $invoice_url = esc_url($invoice_url);
    }
}

// Verify that payment actually exists in the database
if ($success && empty($payment_details)) {
    $success = false;
    error_log('Payment result page accessed with invalid payment data. Entry ID: ' . $entry_id);
}
?>

<div class="payment-result <?php echo $success ? 'payment-success' : 'payment-error'; ?>">
    <?php if ($success): ?>
        <div class="alert alert-success text-center">
            <h3><i class="fa fa-check-circle"></i> <?php _e('Payment Successful!', 'awards'); ?></h3>
            <p><?php _e('Your payment has been successfully processed.', 'awards'); ?></p>
        </div>
        
        <div class="payment-details">
            <?php if ($is_mass_payment): ?>
                <h4><?php _e('Mass Payment Details', 'awards'); ?></h4>
                <p><?php printf(__('You have successfully paid for %d entries:', 'awards'), $entry_count); ?></p>
                <ul class="entry-list">
                    <?php foreach ($entry_titles as $title): ?>
                        <li><?php echo $title; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <h4><?php _e('Payment Details', 'awards'); ?></h4>
                <p><?php printf(__('You have successfully paid for entry "%s".', 'awards'), $entry_title); ?></p>
            <?php endif; ?>
            
            <?php if ($amount): ?>
                <p><?php printf(__('Amount: %s%s', 'awards'), award_price_format($amount), esc_html($currency)); ?></p>
            <?php endif; ?>
            
            <?php if ($refid): ?>
                <p><?php printf(__('Transaction Reference: %s', 'awards'), esc_html($refid)); ?></p>
            <?php endif; ?>
            
            <?php if (isset($invoice_url) && $invoice_url): ?>
                <div class="invoice-download">
                    <a href="<?php echo $invoice_url; ?>" target="_blank" class="btn btn-primary">
                        <i class="fa fa-download"></i> <?php _e('Download Invoice', 'awards'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="next-steps text-center mt-4">
            <p><?php _e('Your submission is now under review. You will be notified when it is approved.', 'awards'); ?></p>
            <div class="buttons">
                <a href="<?php echo esc_url(get_permalink(awards_options('page_dashboard'))); ?>?dash-page=entry-list" class="btn btn-outline-primary">
                    <?php _e('View My Entries', 'awards'); ?>
                </a>
                <a href="<?php echo esc_url(get_permalink(awards_options('page_dashboard'))); ?>" class="btn btn-outline-secondary">
                    <?php _e('Go to Dashboard', 'awards'); ?>
                </a>
            </div>
        </div>
        
    <?php else: ?>
        <div class="alert alert-danger text-center">
            <h3><i class="fa fa-times-circle"></i> <?php _e('Payment Failed', 'awards'); ?></h3>
            <p><?php _e('We were unable to process your payment.', 'awards'); ?></p>
        </div>
        
        <div class="error-details">
            <p><?php _e('There was an issue processing your payment. This could be due to:', 'awards'); ?></p>
            <ul>
                <li><?php _e('Insufficient funds in your account', 'awards'); ?></li>
                <li><?php _e('The card was declined by your bank', 'awards'); ?></li>
                <li><?php _e('Technical issues with the payment processor', 'awards'); ?></li>
            </ul>
            <p><?php _e('Please try again or use a different payment method.', 'awards'); ?></p>
        </div>
        
        <div class="next-steps text-center mt-4">
            <div class="buttons">
                <?php if ($is_mass_payment): ?>
                    <a href="<?php echo esc_url(get_permalink(awards_options('page_dashboard'))); ?>?dash-page=mass-pay&selected=<?php echo esc_attr(implode(',', $entry_ids)); ?>" class="btn btn-primary">
                        <?php _e('Try Again', 'awards'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(get_permalink(awards_options('page_dashboard'))); ?>?dash-page=entry-list" class="btn btn-primary">
                        <?php _e('Go to My Entries', 'awards'); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(get_permalink(awards_options('page_dashboard'))); ?>" class="btn btn-outline-secondary">
                    <?php _e('Go to Dashboard', 'awards'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .payment-result {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .payment-details, .error-details {
        margin: 20px 0;
        padding: 15px;
        background-color: #fff;
        border-radius: 5px;
        border: 1px solid #e9e9e9;
    }
    
    .entry-list {
        list-style-type: disc;
        padding-left: 20px;
    }
    
    .buttons {
        margin-top: 20px;
    }
    
    .buttons .btn {
        margin: 0 5px;
    }
    
    .invoice-download {
        margin: 20px 0;
        text-align: center;
    }
</style>