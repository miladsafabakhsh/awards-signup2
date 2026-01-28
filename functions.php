<?php
/**
 * Add this to your admin section as a utility tool
 * This will fix existing mass payment invoices with incorrect amounts
 */

/**
 * Detect and fix mass payment invoices
 * 
 * This improved function identifies entries that were part of mass payments
 * and ensures only one invoice is generated per transaction, rather than
 * separate invoices for each entry.
 * 
 * @param int $batch_size Optional batch size for processing
 * @param int $offset Optional offset for pagination
 * @return array Results with stats
 */
function fix_existing_mass_payment_invoices($batch_size = 50, $offset = 0) {
    global $wpdb;
    
    $results = array(
        'entries_found' => 0,
        'mass_payments_detected' => 0,
        'entries_fixed' => 0,
        'invoices_regenerated' => 0,
        'failed' => 0,
    );
    
    // Find entries with payments
    $entries = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_author, post_modified 
             FROM {$wpdb->posts} 
             WHERE post_type = 'entry' 
             AND post_status != 'trash'
             ORDER BY post_modified DESC
             LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        )
    );
    
    if (empty($entries)) {
        return $results;
    }
    
    $results['entries_found'] = count($entries);
    
    // First pass: Find all potential mass payments based on transaction IDs
    $transactions = array();
    $transaction_entries = array();
    
    foreach ($entries as $entry) {
        $payment = get_post_meta($entry->ID, 'payment', true);
        
        if (!$payment || !isset($payment['RefID'])) {
            continue;
        }
        
        $transaction_id = $payment['RefID'];
        $is_mass_payment = get_post_meta($entry->ID, 'is_mass_payment', true) || 
                          (isset($payment['is_mass_payment']) && $payment['is_mass_payment']);
        
        if (!isset($transactions[$transaction_id])) {
            $transactions[$transaction_id] = array(
                'count' => 0,
                'entries' => array(),
                'payment' => $payment,
                'user_id' => $entry->post_author,
                'is_tagged_as_mass' => $is_mass_payment,
            );
        }
        
        $transactions[$transaction_id]['count']++;
        $transactions[$transaction_id]['entries'][] = $entry->ID;
        
        // If any entry is marked as mass payment, mark the whole transaction
        if ($is_mass_payment) {
            $transactions[$transaction_id]['is_tagged_as_mass'] = true;
        }
    }
    
    // Second pass: Process each transaction that has multiple entries (mass payment)
    foreach ($transactions as $transaction_id => $transaction_data) {
        // Only process if it has multiple entries or is explicitly tagged as a mass payment
        if ($transaction_data['count'] > 1 || $transaction_data['is_tagged_as_mass']) {
            $results['mass_payments_detected']++;
            
            $entry_ids = $transaction_data['entries'];
            $entry_data = array();
            $total_amount = 0;
            
            // Gather entry details and calculate proper fees
            foreach ($entry_ids as $entry_id) {
                // Calculate the proper entry fee
                $entry_fee = get_award_entry_total_fee($entry_id, false);
                $entry_data[$entry_id] = array(
                    'id' => $entry_id,
                    'fee' => $entry_fee,
                );
                
                $total_amount += $entry_fee;
            }
            
            // Get the actual payment amount from the first entry
            $payment_details = $transaction_data['payment'];
            $actual_payment = isset($payment_details['Amount']) ? 
                $payment_details['Amount'] : $total_amount;
            
            // If calculated total doesn't match payment, adjust proportionally
            if (abs($total_amount - $actual_payment) > 0.01) {
                $factor = $actual_payment / $total_amount;
                
                foreach ($entry_data as $entry_id => $data) {
                    $entry_data[$entry_id]['fee'] = round($data['fee'] * $factor, 2);
                }
                
                // Ensure the adjusted total still matches
                $adjusted_total = array_sum(array_column($entry_data, 'fee'));
                if (abs($adjusted_total - $actual_payment) > 0.01) {
                    $difference = $actual_payment - $adjusted_total;
                    $first_id = reset($entry_ids);
                    $entry_data[$first_id]['fee'] += $difference;
                }
            }
            
            // Create a mass payment record ID
            $mass_payment_id = 'retrofix-' . $transaction_id;
            
            // Create a mass payment record
            $mass_payment_record = array(
                'transaction_id' => $transaction_id,
                'entry_ids' => $entry_ids,
                'total_amount' => $actual_payment,
                'payment_details' => $payment_details,
                'entry_fees' => array_column($entry_data, 'fee', 'id'),
                'time' => $payment_details['time'],
                'is_retroactively_fixed' => true,
            );
            
            // Store the mass payment record
            $existing_mass_payments = get_option('awards_mass_payments', array());
            $existing_mass_payments[$transaction_id] = $mass_payment_record;
            update_option('awards_mass_payments', $existing_mass_payments);
            
            // Check if an invoice already exists for this transaction
            $first_entry_id = reset($entry_ids);
            $existing_invoice_path = get_post_meta($first_entry_id, 'invoice_path', true);
            
            // Only generate a new invoice if one doesn't exist or if it's not a mass invoice
            $should_generate_invoice = true;
            if ($existing_invoice_path) {
                $file_name = basename($existing_invoice_path);
                $should_generate_invoice = (strpos($file_name, 'mass-invoice') === false);
            }
            
            if ($should_generate_invoice) {
                // First remove any existing invoices for the entries in this transaction
                foreach ($entry_ids as $entry_id) {
                    delete_post_meta($entry_id, 'invoice_path');
                    delete_post_meta($entry_id, 'invoice_url');
                }
                
                // Generate a new transaction invoice
                $invoice_path = generate_transaction_invoice(
                    $entry_ids, 
                    $payment_details, 
                    array_column($entry_data, 'fee', 'id')
                );
                
                if ($invoice_path) {
                    $results['invoices_regenerated']++;
                    
                    // Get the invoice URL from the first entry
                    $invoice_url = get_post_meta($first_entry_id, 'invoice_url', true);
                    
                    if ($invoice_url) {
                        // Update all entries to point to the same invoice
                        foreach ($entry_ids as $entry_id) {
                            // Update payment details to indicate mass payment
                            $entry_payment = $payment_details;
                            $entry_payment['Amount'] = $entry_data[$entry_id]['fee'];
                            $entry_payment['transaction_id'] = $transaction_id;
                            $entry_payment['is_mass_payment'] = true;
                            
                            update_post_meta($entry_id, 'payment', $entry_payment);
                            update_post_meta($entry_id, 'is_mass_payment', true);
                            update_post_meta($entry_id, 'mass_payment_id', $mass_payment_id);
                            update_post_meta($entry_id, 'mass_payment_entries', implode(',', $entry_ids));
                            
                            // Link to the same transaction invoice
                            update_post_meta($entry_id, 'invoice_path', $invoice_path);
                            update_post_meta($entry_id, 'invoice_url', $invoice_url);
                            update_post_meta($entry_id, 'transaction_id', $transaction_id);
                            
                            $results['entries_fixed']++;
                        }
                    } else {
                        $results['failed']++;
                    }
                } else {
                    $results['failed']++;
                }
            } else {
                // Just update the metadata without regenerating the invoice
                foreach ($entry_ids as $entry_id) {
                    $entry_payment = $payment_details;
                    $entry_payment['Amount'] = $entry_data[$entry_id]['fee'];
                    $entry_payment['transaction_id'] = $transaction_id;
                    $entry_payment['is_mass_payment'] = true;
                    
                    update_post_meta($entry_id, 'payment', $entry_payment);
                    update_post_meta($entry_id, 'is_mass_payment', true);
                    update_post_meta($entry_id, 'mass_payment_id', $mass_payment_id);
                    update_post_meta($entry_id, 'mass_payment_entries', implode(',', $entry_ids));
                    
                    // Link to the existing invoice
                    update_post_meta($entry_id, 'invoice_path', $existing_invoice_path);
                    update_post_meta($entry_id, 'invoice_url', get_post_meta($first_entry_id, 'invoice_url', true));
                    update_post_meta($entry_id, 'transaction_id', $transaction_id);
                    
                    $results['entries_fixed']++;
                }
            }
        }
    }
    
    return $results;
}

/**
 * Admin page for fixing mass payment invoices
 */
function awards_fix_mass_payment_invoices_page() {
    // Process form submission
    $results = null;
    $message = '';
    
    if (isset($_POST['fix_mass_invoices']) && wp_verify_nonce($_POST['_wpnonce'], 'awards_fix_mass_invoices')) {
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        $results = fix_existing_mass_payment_invoices($batch_size, $offset);
        
        $message = sprintf(
            __('Processed %d entries. Found %d mass payments. Fixed: %d entries, Regenerated: %d invoices, Failed: %d', 'awards'),
            $results['entries_found'],
            $results['mass_payments_detected'],
            $results['entries_fixed'],
            $results['invoices_regenerated'],
            $results['failed']
        );
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Fix Mass Payment Invoices', 'awards'); ?></h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><?php _e('Fix Existing Mass Payment Invoices', 'awards'); ?></h2>
            
            <p>
                <?php _e('This tool detects entries that were part of mass payments and updates their payment details and invoices to show the correct proportional amount.', 'awards'); ?>
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('awards_fix_mass_invoices'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Batch Size', 'awards'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="batch_size" id="batch_size" value="50" min="1" max="500" class="regular-text">
                            <p class="description">
                                <?php _e('Number of entries to process in one batch. Use a smaller number if you encounter timeout issues.', 'awards'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="offset"><?php _e('Offset', 'awards'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="offset" id="offset" value="0" min="0" class="regular-text">
                            <p class="description">
                                <?php _e('Skip this many entries before processing. Useful for continuing after a previous batch.', 'awards'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="fix_mass_invoices" class="button button-primary" value="<?php _e('Fix Mass Payment Invoices', 'awards'); ?>">
                </p>
            </form>
            
            <?php if ($results): ?>
                <p>
                    <?php _e('To process more entries, increase the offset value to continue from where you left off.', 'awards'); ?>
                </p>
                <p>
                    <?php printf(
                        __('Suggested next offset: %d'),
                        isset($_POST['offset']) ? (intval($_POST['offset']) + intval($_POST['batch_size'])) : $results['entries_found']
                    ); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Register the mass payment invoice fix admin page
 */
function awards_register_fix_mass_invoices_page() {
    add_submenu_page(
        'edit.php?post_type=entry',
        __('Fix Mass Payment Invoices', 'awards'),
        __('Fix Mass Payment Invoices', 'awards'),
        'manage_options',
        'awards-fix-mass-invoices',
        'awards_fix_mass_payment_invoices_page'
    );
}
add_action('admin_menu', 'awards_register_fix_mass_invoices_page');
/**
 * Integration Hooks for Mass Payment System
 * 
 * This file contains the necessary hooks and functions to integrate
 * all components of the mass payment system.
 */

/**
 * Redirect mass payment handler logic to the appropriate gateway
 * This should be added to the dashboard_paymententry function
 */
function awards_handle_gateway_mass_payment() {
    if (!isset($_GET['payment']) || !isset($_GET['gateway']) || !isset($_GET['id'])) {
        return false;
    }
    
    if (!isset($_GET['mass']) || $_GET['mass'] !== 'true') {
        return false;
    }
    
    $gateway = sanitize_text_field($_GET['gateway']);
    
    // Redirect to the appropriate gateway handler
    switch ($gateway) {
        case 'zarinpal':
            return dashboard_zarinpal_mass_payment();
        
        case 'paypal':
            return dashboard_paypal_mass_payment();
            
        case 'stripe':
            return dashboard_stripe_mass_payment();
            
        case 'now-payment':
            return dashboard_nowpayment_mass_payment();
            
        case 'plisio':
            return dashboard_plisio_mass_payment();
            
        default:
            return false;
    }
}

/**
 * Override the payment navigation for mass payments
 * This ensures the correct gateway is used
 */
function awards_modify_payment_button_for_mass() {
    add_action('awards_before_payment_button', function($entry_id) {
        if (isset($_GET['mass']) && $_GET['mass'] === 'true') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Add mass parameter to payment buttons
                    $('.payment-buttons a').each(function() {
                        let href = $(this).attr('href');
                        if (href.indexOf('mass=true') === -1) {
                            href += (href.indexOf('?') !== -1) ? '&mass=true' : '?mass=true';
                            $(this).attr('href', href);
                        }
                    });
                });
            </script>
            <?php
        }
    });
}
add_action('init', 'awards_modify_payment_button_for_mass');

/**
 * Modify the dashboard entry list to support mass payments
 * This enhances the form to properly handle mass payments
 */
function awards_enhance_entry_list_for_mass_payment() {
    add_action('awards_before_entry_list', function() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Enhance the entry list form for mass payments
                $('form[action*="dash-page=mass-pay"]').addClass('mass-payment-form');
                
                // Ensure at least one entry is selected
                $('.mass-payment-form').submit(function(e) {
                    if ($('input[name="selected[]"]:checked').length === 0) {
                        e.preventDefault();
                        alert('<?php _e("Please select at least one entry", "awards"); ?>');
                        return false;
                    }
                    return true;
                });
            });
        </script>
        <?php
    });
}
add_action('init', 'awards_enhance_entry_list_for_mass_payment');

/**
 * Add download invoice button to dashboard entry list
 * This makes it easier to access invoices
 */
function awards_add_invoice_button_to_entry_list() {
    add_action('awards_after_entry_actions', function($entry_id) {
        $payment = get_post_meta($entry_id, 'payment', true);
        $status = get_post_meta($entry_id, 'status', true);
        
        if ($payment && $status !== 'pending_payment') {
            $invoice_url = get_post_meta($entry_id, 'invoice_url', true);
            
            if ($invoice_url) {
                ?>
                <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="fa fa-download"></i> <?php _e('Invoice', 'awards'); ?>
                </a>
                <?php
            }
        }
    });
}
add_action('init', 'awards_add_invoice_button_to_entry_list');

/**
 * Add indicators for entries that were part of mass payments
 * This helps users understand which entries were paid together
 */
function awards_add_mass_payment_indicators() {
    add_action('awards_after_entry_status', function($entry_id) {
        $payment = get_post_meta($entry_id, 'payment', true);
        
        if (isset($payment['is_mass_payment']) && $payment['is_mass_payment']) {
            echo '<span class="badge badge-info">' . __('Mass Payment', 'awards') . '</span>';
        }
    });
}
add_action('init', 'awards_add_mass_payment_indicators');

/**
 * Enqueue CSS and JS for mass payment functionality
 */
function awards_enqueue_mass_payment_assets() {
    wp_add_inline_style('awards', '
        .mass-payment-badge {
            background: #17a2b8;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 5px;
        }
        .invoice-button {
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            padding: 4px 8px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
        }
        .invoice-button:hover {
            background: #e9ecef;
            text-decoration: none;
        }
    ');
}
add_action('wp_enqueue_scripts', 'awards_enqueue_mass_payment_assets');

/**
 * Register all mass payment functions
 * This ensures they're available throughout the system
 */
function awards_register_mass_payment_functions() {
    // These functions should be available globally
    global $awards_mass_payment_functions;
    
    $awards_mass_payment_functions = array(
        'handle_mass_payment',
        'generate_transaction_invoice',
        'dashboard_zarinpal_mass_payment',
        'dashboard_paypal_mass_payment',
        'dashboard_stripe_mass_payment',
        'dashboard_nowpayment_mass_payment',
        'dashboard_plisio_mass_payment'
    );
}
add_action('init', 'awards_register_mass_payment_functions');

/**
 * Created by PhpStorm.
 * User: Mehran
 * Date: 2018/07/21
 * Time: 12:36
 */

add_action('init', 'do_output_buffer');
function do_output_buffer()
{
    ob_start();
}

function award_plugin_url()
{
    return plugin_dir_url(__FILE__);
}

function awards_options($optionname = 'Options Empty')
{
    if ($optionname != 'Options Empty'):
        $options_load = get_option('awards_options');
        $options_output = isset($options_load[$optionname]) ? $options_load[$optionname] : '';
    endif;

    $options_output = ($options_output) ? $options_output : '';

    return $options_output;
}

function awards_setmessage($msg, $type = 'success', $title = '')
{
    $_SESSION['awards_message'] = array(
        'message' => $msg,
        'type' => $type,
        'title' => $title,
    );

    return true;
}

function awards_getmessage()
{

    if (!isset($_SESSION['awards_message'])) return false;

    $session = $_SESSION['awards_message'];
    unset($_SESSION['awards_message']);

    if (!$session || empty($session['message'])) return false;

    $title = ($session['title'] ? '<strong>' . $session['title'] . '</strong> ' : '');
    $message = $session['message'];
    $type = $session['type'];

    $output = '<div class="awards-result result-' . $type . '">' . $title . $message . '</div>';

    return $output;
}

add_shortcode('wp_awards_get_messages', 'awards_getmessage');


function is_awards_verified_user($user_id = '')
{
    if (!$user_id) $user_id = get_current_user_id();

    $user_verify_status = get_user_meta($user_id, 'award_user_verified', true);
    if (!$user_verify_status) return false;

    return true;
}

function awards_add_menuitem()
{
    add_submenu_page('options-general.php', __('Awards Setting', 'track-trace'), __('Awards Setting', 'track-trace'), 'manage_options', 'awards_options', array('wp_awards', 'config'));
}

add_action('admin_menu', 'awards_add_menuitem');

function get_gravatar($email, $size = 70)
{
    $url = 'https://www.gravatar.com/avatar/';
    $grav_url = $url . md5(strtolower(trim($email))) . "&s=" . $size;

    return $grav_url;
}

function get_award_status_title($status, $html = false)
{

    switch ($status) {
        case 'pending_payment':
            $result = __('Pending Payment', 'awards');
            $class = 'badge-info';
            break;
        case 'pending_review':
            $result = __('Pending Review', 'awards');
            $class = 'badge-light';
            break;
        case 'approved':
            $result = __('Approved', 'awards');
            $class = 'badge-success';
            break;
        case 'rejected':
            $result = __('Rejected', 'awards');
            $class = 'badge-danger';
            break;
        case 'winner':
            $result = __('Winner', 'awards');
            $class = 'badge-warning';
            break;
        default:
            $result = __('Unknown', 'awards');
            $class = 'badge-dark';
    }

    if ($html)
        return '<span class="badge ' . $class . '">' . $result . '</span>';
    else
        return $result;

}

function get_award_winnerstatus_title($status, $html = false)
{

    switch ($status) {
        case 'none':
            $result = __('None', 'awards');
            $class = 'badge-dark';
            break;
        case 'firstplace':
            $result = __('First place', 'awards');
            $class = 'badge-success';
            break;
        case 'secondplace':
            $result = __('Second place', 'awards');
            $class = 'badge-info';
            break;
        case 'thirplace':
            $result = __('Third place', 'awards');
            $class = 'badge-danger';
            break;
        case 'honorable':
            $result = __('Honorable', 'awards');
            $class = 'badge-light';
            break;
        default:
            $result = __('Unknown', 'awards');
            $class = 'badge-dark';
    }

    if ($html)
        return '<span class="badge ' . $class . '">' . $result . '</span>';
    else
        return $result;

}

function get_award_entry_total_fee($postid, $symbol = true)
{
    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    $baseprice_single = awards_options('baseprice_single');
    $baseprice_series = awards_options('baseprice_series');
    $additional_cat_price_single = awards_options('add_category');
    $additional_cat_price_single = $additional_cat_price_single['single'];
    $additional_cat_price_series = awards_options('add_category');
    $additional_cat_price_series = $additional_cat_price_series['series'];

    $entry_type = get_post_meta($postid, 'entrytype', true);

    if ($entry_type == 'single') {
        $baseprice = $baseprice_single;
    } elseif ($entry_type == 'series') {
        $baseprice = $baseprice_series;
    } else {
        $baseprice = array(
            'rial' => 0,
            'euro' => 0,
            'usd' => 0
        );
    }

    $posterms = wp_get_post_terms($postid, 'entrycat');

    $totalfee = 0;
    if (count($posterms) > 1) {
        $i = 0;
        foreach ($posterms as $term) {
            if ($i != 0) {
                switch ($userCountry) {
                    case 'Iran (Islamic Republic of)':
                        $totalfee = $totalfee + ($entry_type == 'single' ? $additional_cat_price_single['rial'] : $additional_cat_price_series['rial']);
                        break;
                    default:
                        // Handle both 'euro' and 'eur' for backwards compatibility
                        if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                            $totalfee = $totalfee + ($entry_type == 'single' ? $additional_cat_price_single['euro'] : $additional_cat_price_series['euro']);
                        } else {
                            $totalfee = $totalfee + ($entry_type == 'single' ? $additional_cat_price_single['usd'] : $additional_cat_price_series['usd']);
                        }
                }
            }
            $i++;
        }
    }

    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            $totalfee = $baseprice['rial'] + $totalfee;
            break;
        default:
            // Handle both 'euro' and 'eur' for backwards compatibility
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                $totalfee = $baseprice['euro'] + $totalfee;
            } else {
                $totalfee = $baseprice['usd'] + $totalfee;
            }
    }

    if (!$symbol) return $totalfee;

    if (!$totalfee) return __('Free', 'awards');

    return award_price_format($totalfee) . get_awards_price_symbol();
}

// FUNCTION 2: Replace get_award_entry_total_fee_with_copon function
function get_award_entry_total_fee_with_copon($postid, $symbol = true)
{
    $copon_session = get_copon_session();
    $current_price = get_award_entry_total_fee($postid, false);

    if (awards_options('copon_enable')) {
        if ($copon_session['type'] == 'fix') {
            $new_price = $current_price - $copon_session['value'];
        } elseif ($copon_session['type'] == 'percent') {
            $percent = ($current_price * $copon_session['value']) / 100;
            $new_price = $current_price - $percent;
        }
    } else {
        $new_price = $current_price;
    }

    if (!$symbol) return $new_price;

    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            return award_price_format($new_price) . ' ' . __('Rials', 'awards');
            break;
        default:
            // Handle both 'euro' and 'eur' for backwards compatibility
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                return '€' . $new_price;
            } else {
                return '$' . $new_price;
            }
    }
}

// FUNCTION 3: Replace get_award_basefee function
function get_award_basefee($type = 'single')
{
    $baseprice_single = awards_options('baseprice_single');
    $baseprice_series = awards_options('baseprice_series');

    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            $output = ($type == 'single') ? $baseprice_single['rial'] : $baseprice_series['rial'];
            break;
        default:
            // Handle both 'euro' and 'eur' for backwards compatibility
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                $output = ($type == 'single') ? $baseprice_single['euro'] : $baseprice_series['euro'];
            } else {
                $output = ($type == 'single') ? $baseprice_single['usd'] : $baseprice_series['usd'];
            }
    }

    return $output;
}

// FUNCTION 4: Replace get_award_term_fee function
function get_award_term_fee($termid)
{
    $term_meta = get_option("taxonomy_term_$termid"); // Do the check

    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            $output = $term_meta['tax_price']['rial'];
            break;
        default:
            // Handle both 'euro' and 'eur' for backwards compatibility
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                $output = $term_meta['tax_price']['euro'];
            } else {
                $output = $term_meta['tax_price']['usd'];
            }
    }

    return $output;
}

// FUNCTION 5: Replace get_awards_price_symbol function
function get_awards_price_symbol()
{
    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            $symbol = __(' Rials', 'awards');
            break;
        default:
            // Handle both 'euro' and 'eur' for backwards compatibility
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                $symbol = __('€', 'awards');
            } else {
                $symbol = __('$', 'awards');
            }
    }

    return $symbol;
}

// FUNCTION 6: Replace get_add_categories_basefee function
function get_add_categories_basefee($type = 'single')
{
    $cat_add_fee = awards_options('add_category');

    if (!$cat_add_fee) return 0;

    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');

    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            return $type == 'single' ? $cat_add_fee['single']['rial'] : $cat_add_fee['series']['rial'];
            break;
        default:
            // Handle both 'euro' and 'eur' for backwards compatibility
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                return $type == 'single' ? $cat_add_fee['single']['euro'] : $cat_add_fee['series']['euro'];
            } else {
                return $type == 'single' ? $cat_add_fee['single']['usd'] : $cat_add_fee['series']['usd'];
            }
    }
}

function award_entry_can_edit($id)
{

    $postdata = get_post($id);

    if ($postdata->post_author != get_current_user_id()) return false;

    $status = get_post_meta($id, 'status', true);

    if ($status != 'pending_payment') return false;

    return true;

}

function award_countries()
{
    $countries = array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe");
    return $countries;
}

function award_price_format($price)
{
    if (!$price) return __('Free', 'awards');

    $price = round($price);

    $price = number_format($price, 0, '.', ',');
    return $price;
}

function set_copon_session($data)
{
    $user_id = get_current_user_id();
    if (!$user_id) return false;

    set_transient("award_cp{$user_id}", ($data ? json_encode($data) : ''), 0);
}

function get_copon_session($user_id = '')
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    if (!$user_id || !is_numeric($user_id)) return false;

    $transient = get_transient("award_cp{$user_id}");
    return $transient ? json_decode(str_replace('\"', '"', $transient), true) : false;

}

function delete_copon_session()
{
    $user_id = get_current_user_id();
    if (!$user_id) return false;
    delete_transient("award_cp{$user_id}");
}

function user_entries_count($userID = '')
{
    $userID = $userID ? $userID : get_current_user_id();

    $entries = new WP_Query(array(
        'author' => $userID,
        'posts_per_page' => 20,
        'post_type' => 'entry',
        'post_status' => 'any',
    ));

    return $entries->post_count;
}

if (!function_exists('print_r_pre')) {
    function print_r_pre($data)
    {
        return '<pre>' . print_r($data, true) . '</pre>';
    }
}

/**
 * Add Stripe test mode secret key field
 * This should be added to the admin settings form in awards-signup.php
 */
function stripe_test_key_settings() {
    // Add this HTML where appropriate in your settings form
    ?>
    <tr>
        <th><?php _e('Stripe Test Secret Key', 'awards'); ?></th>
        <td>
            <input type="text" name="awards_options[stripe_test_secret_key]"
                id="awards_options[stripe_test_secret_key]"
                value="<?php echo awards_options('stripe_test_secret_key'); ?>" class="reqular-text">
        </td>
    </tr>
    <tr>
        <th><?php _e('Stripe Test Publishable Key', 'awards'); ?></th>
        <td>
            <input type="text" name="awards_options[stripe_test_publishable_key]"
                id="awards_options[stripe_test_publishable_key]"
                value="<?php echo awards_options('stripe_test_publishable_key'); ?>" class="reqular-text">
        </td>
    </tr>
    <?php
}
function add_to_user_copon_used($session_data, $user_id = '')
{
    if (!awards_options('copon_enable')) return false;

    if (!isset($session_data['id'])) return false;

    if (!$user_id) $user_id = get_current_user_id();

    $current_copons = get_user_meta($user_id, 'awards_used_copons', true);

    $new_user_used_copons = array();

    if ($current_copons && is_array($current_copons)) {
        foreach ($current_copons as $copon) {
            $new_user_used_copons[] = $copon;
        }
    }

    $new_user_used_copons[] = $session_data['id'];

    return update_user_meta($user_id, 'awards_used_copons', $new_user_used_copons);
}
function process_and_clear_coupon($entry_id, $user_id = '') {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Get the coupon from session
    $coupon_session = get_copon_session();
    
    if ($coupon_session) {
        // Add to used coupons
        add_to_user_copon_used($coupon_session, $user_id);
        
        // Store the coupon used for this specific entry
        update_post_meta($entry_id, 'coupon_used', $coupon_session);
        
        // Delete the coupon from session so it's not applied to other entries
        delete_copon_session();
    }
}
function get_copon_data($copon_code)
{
    if (!awards_options('copon_enable')) return false;

    $copon_query = array(
        'post_type' => 'awards_copon',
        'meta_key' => 'code',
        'meta_value' => $copon_code,
        'posts_per_page' => 1,
    );

    $copon = new WP_Query($copon_query);

    if (!$copon->have_posts()) return false;

    while ($copon->have_posts()): $copon->the_post();
        $title = get_the_title();
        $id = get_the_ID();
        $post_meta = get_post_meta(get_the_ID(), '', true);
        $code = $post_meta['code'][0];
        $value = $post_meta['value'][0];
        $type = $post_meta['type'][0];
    endwhile;

    $session_data = array(
        'tile' => $title,
        'id' => $id,
        'copon' => $code,
        'value' => $value,
        'type' => $type,
        'user_used' => false,
    );

    if (!user_can_user_copon_code($session_data['id'])) return false;

    return $session_data;
}

function user_can_user_copon_code($copon_id, $user_id = '')
{
    if (!awards_options('copon_enable')) return false;

    if (!$user_id) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
    }
    $current_user_meta = get_user_meta($user_id, 'awards_used_copons', true);

    if ($current_user_meta && is_array($current_user_meta)) {
        if (in_array($copon_id, $current_user_meta)) return false;
    }

    return true;
}
/**
 * Handle Stripe payment success for both single and mass payments
 */
function handle_stripe_payment_success() {
    if (!isset($_GET['paymentresult']) || $_GET['gateway'] != 'stripe' || !isset($_GET['id']) || !isset($_GET['session_id'])) {
        return;
    }
    
    $session_id = sanitize_text_field($_GET['session_id']);
    $is_mass_payment = isset($_GET['mass']) && $_GET['mass'] === 'true';
    $success = isset($_GET['success']) && $_GET['success'] === 'true';
    
    if (!$success) {
        // Handle cancelled payment
        if ($is_mass_payment) {
            wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . $_GET['id'] . '&mass=true');
        } else {
            wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . $_GET['id']);
        }
        exit;
    }
    
    // Check if this is a mass payment
    if ($is_mass_payment) {
        $entry_ids = explode(',', $_GET['id']);
        if (empty($entry_ids)) {
            return;
        }
    } else {
        $entry_id = intval($_GET['id']);
        if (!$entry_id) {
            return;
        }
        $entry_ids = array($entry_id);
    }
    
    // Verify the session with Stripe
    try {
        require_once 'inc/vendor/stripe/init.php';
        
        // Set the API key
        $isTestMode = (awards_options('stripe_test_mode') == 'enable');
        $stripeKey = $isTestMode ? awards_options('stripe_test_secret_key') : awards_options('stripe_secret_key');
        \Stripe\Stripe::setApiKey($stripeKey);
        
        // Retrieve the session
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        
        if ($session && $session->payment_status == 'paid') {
            // Payment was successful
            $current_time = date('Y-m-d H:i:s');
            
            // Process coupon and clear it
            add_to_user_copon_used(get_copon_session(), get_current_user_id());
            delete_copon_session();
            
            // Create payment details array
            $payment_details = array(
                'gateway' => 'stripe',
                'Amount' => $session->amount_total / 100, // Convert from cents
                'RefID' => $session->payment_intent,
                'Authority' => $session_id,
                'time' => $current_time,
            );
            
            if ($is_mass_payment) {
                // Process mass payment
                // Store the relationship between entries in this mass payment
                $mass_payment_id = 'mass_' . $session->payment_intent;
                
                // Process each entry
                foreach ($entry_ids as $current_entry_id) {
                    // Update entry status
                    update_post_meta($current_entry_id, 'status', 'pending_review');
                    
                    // Update post modified date
                    $update_post_date = array(
                        'ID' => $current_entry_id,
                        'post_modified' => $current_time,
                        'post_modified_gmt' => $current_time,
                    );
                    wp_update_post($update_post_date);
                    
                    // Store payment details
                    update_post_meta($current_entry_id, 'payment', $payment_details);
                    
                    // Mark as part of a mass payment
                    update_post_meta($current_entry_id, 'is_mass_payment', true);
                    update_post_meta($current_entry_id, 'mass_payment_id', $mass_payment_id);
                    update_post_meta($current_entry_id, 'mass_payment_entries', implode(',', $entry_ids));
                    
                    // Remove temporary session ID
                    delete_post_meta($current_entry_id, 'stripe_session_id');
                }
                
                // Generate one invoice for all entries
                generate_mass_payment_invoice($entry_ids, $payment_details);
                
                // Send email notification
                $user_info = wp_get_current_user();
                
                // If mass_payment_email_body exists in the wp_awards class, use it
                if (method_exists('wp_awards', 'mass_payment_email_body')) {
                    // Instead of calling the private method directly
// $email_body_text = wp_awards::mass_payment_email_body($entry_ids, $user_info->ID);

// Create a fallback email content
$entry_titles = array();
foreach ($entry_ids as $entry_id) {
    $entry_titles[] = get_the_title($entry_id);
}

// Get the email template from options
$template = awards_options('entry_payment_success_fully_mail_text');

if ($template) {
    // For backward compatibility, use the first entry title in the template
    $first_entry_title = !empty($entry_titles) ? $entry_titles[0] : '';
    
    $email_body_text = str_replace(
        array('{entry_title}', '{user_firstname}', '{user_lastname}'),
        array($first_entry_title, $user_info->first_name, $user_info->last_name),
        $template
    );
    
    // Add a note about multiple entries if applicable
    if (count($entry_ids) > 1) {
        $email_body_text .= "\n\n" . __('This payment includes the following entries:', 'awards') . "\n";
        foreach ($entry_titles as $index => $title) {
            $email_body_text .= ($index + 1) . '. ' . $title . "\n";
        }
    }
} else {
    // Create a default email if no template exists
    $email_body_text = sprintf(
        __('Dear %s %s,', 'awards'),
        $user_info->first_name,
        $user_info->last_name
    );
    
    $email_body_text .= "\n\n";
    $email_body_text .= __('Your payment for multiple entries has been successfully processed.', 'awards');
    $email_body_text .= "\n\n";
    $email_body_text .= __('Entries included in this payment:', 'awards') . "\n";
    
    foreach ($entry_titles as $index => $title) {
        $email_body_text .= ($index + 1) . '. ' . $title . "\n";
    }
    
    $email_body_text .= "\n\n";
    $email_body_text .= __('Thank you for participating in the Minimalist Photography Awards.', 'awards');
}

// Generate the HTML email body
if (method_exists('wp_awards', 'email_body')) {
    // If email_body is available (and hopefully public), use it
    $email_body = wp_awards::email_body(wpautop($email_body_text));
} else {
    // Otherwise, create a simple HTML wrapper
    $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">' . wpautop($email_body_text) . '</div>';
}
                    $email_body = wp_awards::email_body(wpautop($email_body_text));
                    wp_mail($user_info->user_email, 'Entries Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                } else {
                    // Fallback if the method doesn't exist
                    $entry_titles = array();
                    foreach ($entry_ids as $eid) {
                        $entry_titles[] = get_the_title($eid);
                    }
                    
                    $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">';
                    $email_body .= '<p>' . sprintf(__('Dear %s %s,', 'awards'), $user_info->first_name, $user_info->last_name) . '</p>';
                    $email_body .= '<p>' . __('Your payment for multiple entries has been successfully processed.', 'awards') . '</p>';
                    $email_body .= '<p>' . __('Entries included in this payment:') . '</p><ul>';
                    
                    foreach ($entry_titles as $title) {
                        $email_body .= '<li>' . $title . '</li>';
                    }
                    
                    $email_body .= '</ul><p>' . __('Thank you for participating in the Minimalist Photography Awards.', 'awards') . '</p>';
                    $email_body .= '</div>';
                    
                    wp_mail($user_info->user_email, 'Entries Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                }
                
                // Redirect to success page
                wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . implode('-', $entry_ids) . '&refid=' . $session->payment_intent . '&mass=true');
                exit;
            } else {
                // Process single entry payment
                $entry_id = reset($entry_ids);
                
                // Update entry status
                update_post_meta($entry_id, 'status', 'pending_review');
                
                // Update post modified date
                $update_post_date = array(
                    'ID' => $entry_id,
                    'post_modified' => $current_time,
                    'post_modified_gmt' => $current_time,
                );
                wp_update_post($update_post_date);
                
                // Store payment details
                $currentmeta = get_post_meta($entry_id, 'payment', true);
                if ($currentmeta) {
                    update_post_meta($entry_id, 'payment', $payment_details);
                } else {
                    add_post_meta($entry_id, 'payment', $payment_details);
                }
                
                // Remove temporary session ID
                delete_post_meta($entry_id, 'stripe_session_id');
                
                // Generate invoice for single entry
                generate_entry_invoice($entry_id, $payment_details);
                
                // Send email notification
                $user_info = wp_get_current_user();
                
                // Create email body
                $entry_title = get_the_title($entry_id);
                $email_body_text = '';
                
                // Get the email template from options
                $email_template = awards_options('entry_payment_success_fully_mail_text');
                
                // Replace placeholders if the template exists
                if ($email_template) {
                    $email_body_text = str_replace(
                        array('{entry_title}', '{user_firstname}', '{user_lastname}'),
                        array($entry_title, $user_info->first_name, $user_info->last_name),
                        $email_template
                    );
                } else {
                    // Fallback if no template exists
                    $email_body_text = "Your payment for entry \"$entry_title\" was successful. Thank you!";
                }
                
                // Wrap in HTML email body
                $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">' . wpautop($email_body_text) . '</div>';
                
                wp_mail($user_info->user_email, 'Entry Payment Successful', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                
                // Redirect to success page
                wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . $entry_id . '&refid=' . $session->payment_intent);
                exit;
            }
        } else {
            // Payment was not successful
            if ($is_mass_payment) {
                wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . implode('-', $entry_ids) . '&mass=true');
            } else {
                wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . reset($entry_ids));
            }
            exit;
        }
    } catch (Exception $e) {
        // Log the error
        error_log('Stripe verification error: ' . $e->getMessage());
        
        // Error verifying the payment
        if ($is_mass_payment) {
            wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . implode('-', $entry_ids) . '&mass=true');
        } else {
            wp_redirect(get_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . reset($entry_ids));
        }
        exit;
    }
}
add_action('init', 'handle_stripe_payment_success', 20);
/**
 * Handle mass payment verification and split payment among entries
 */
function send_entry_approved_notification($meta_id, $post_id, $meta_key, $meta_value) {
    // Only proceed if this is a status update to "approved"
    if ($meta_key !== 'status' || $meta_value !== 'approved') {
        return;
    }
    
    // Check if this is an entry post type
    if (get_post_type($post_id) !== 'entry') {
        return;
    }
    
    // Get entry and user details
    $entry_title = get_the_title($post_id);
    $post = get_post($post_id);
    $user_id = $post->post_author;
    $user = get_userdata($user_id);
    
    if (!$user) {
        return;
    }
    
    // Get email template and replace placeholders
    $email_template = awards_options('entry_approval_mail_text');
    if ($email_template) {
        $email_body_text = str_replace(
            array('{entry_title}', '{user_firstname}', '{user_lastname}'),
            array($entry_title, $user->first_name, $user->last_name),
            $email_template
        );
    } else {
        // Fallback if no template exists
        $email_body_text = "Dear {$user->first_name} {$user->last_name},

Your entry \"{$entry_title}\" has been reviewed and approved! Your submission is now officially part of the contest.

Thank you for participating in the Minimalist Photography Awards.

Best regards,
Minimalist Photography Awards Team";
    }
    
    // Format the email body with HTML
    $email_body = '<div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">' . wpautop($email_body_text) . '</div>';
    
    // Send the email
    wp_mail($user->user_email, 'Entry Approved - Minimalist Photography Awards', $email_body, array('Content-Type: text/html; charset=UTF-8'));
}
add_action('updated_post_meta', 'send_entry_approved_notification', 10, 4);
/**
 * Normalizes currency codes throughout the plugin
 */
function normalize_currency_code($currency) {
    // Convert to uppercase
    $currency = strtoupper($currency);
    
    // Handle variations of Euro
    if ($currency == 'EURO' || $currency == 'EUR') {
        return 'EUR';
    }
    
    // Handle USD
    if ($currency == 'USD' || $currency == 'DOLLAR') {
        return 'USD';
    }
    
    // Handle IRR (Iranian Rial)
    if ($currency == 'RIAL' || $currency == 'RIALS' || $currency == 'IRR') {
        return 'IRR';
    }
    
    // Return the currency code if no match
    return $currency;
}

/**
 * Send payment confirmation email with invoice attachment
 * Add this function to functions.php
 * 
 * @param int $entry_id The entry ID
 * @param array $payment_details Payment details array
 * @param string $invoice_path Path to the generated invoice
 */
function awards_send_invoice_email($entry_id, $payment_details, $invoice_path) {
    // Get entry and user information
    $entry = get_post($entry_id);
    if (!$entry) {
        return false;
    }
    
    $user_id = $entry->post_author;
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Format the payment details
    $gateway = isset($payment_details['gateway']) ? ucfirst($payment_details['gateway']) : 'Unknown';
    $amount = isset($payment_details['Amount']) ? $payment_details['Amount'] : 0;
    $currency = get_normalized_currency();
    $currency_symbol = $currency == 'EUR' ? 'EUR ' : ($currency == 'IRR' ? 'IRR ' : '$');
    $formatted_amount = $currency_symbol . number_format((float)$amount, 2);
    $ref_id = isset($payment_details['RefID']) ? $payment_details['RefID'] : 'N/A';
    
    // Create email subject
    $subject = sprintf(__('Payment Receipt - %s', 'awards'), $entry->post_title);
    
    // Create email body
    $email_body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">';
    $email_body .= '<h2 style="color: #0073aa; margin-bottom: 20px;">' . __('Payment Confirmation', 'awards') . '</h2>';
    
    // Add user greeting
    $email_body .= '<p>' . sprintf(__('Dear %s,', 'awards'), $user->first_name . ' ' . $user->last_name) . '</p>';
    
    // Add payment details
    $email_body .= '<p>' . sprintf(__('Thank you for your payment for the entry "%s".', 'awards'), $entry->post_title) . '</p>';
    
    $email_body .= '<div style="background-color: #f9f9f9; border: 1px solid #e5e5e5; padding: 15px; margin: 20px 0;">';
    $email_body .= '<p><strong>' . __('Payment Details', 'awards') . '</strong></p>';
    $email_body .= '<p>' . __('Amount:', 'awards') . ' ' . $formatted_amount . '</p>';
    $email_body .= '<p>' . __('Payment Method:', 'awards') . ' ' . $gateway . '</p>';
    $email_body .= '<p>' . __('Transaction ID:', 'awards') . ' ' . $ref_id . '</p>';
    $email_body .= '<p>' . __('Date:', 'awards') . ' ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment_details['time'])) . '</p>';
    $email_body .= '</div>';
    
    // Add note about attachment
    $email_body .= '<p>' . __('Your payment receipt is attached to this email. You can also download it anytime from your dashboard.', 'awards') . '</p>';
    
    // Add link to dashboard
    $dashboard_url = get_permalink(awards_options('page_dashboard')) . '/?dash-page=invoices';
    $email_body .= '<p><a href="' . esc_url($dashboard_url) . '" style="background-color: #0073aa; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __('View in Dashboard', 'awards') . '</a></p>';
    
    // Add footer
    $email_body .= '<p style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; font-size: 12px; color: #666;">';
    $email_body .= __('Thank you for participating in our photography contest.', 'awards');
    $email_body .= '</p>';
    
    $email_body .= '</div>';
    
    // Set up email headers
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Attach the invoice
    $attachments = array($invoice_path);
    
    // Send the email
    return wp_mail($user->user_email, $subject, $email_body, $headers, $attachments);
}

/**
 * Update the generate_entry_invoice function to send email with attachment
 * Add this at the end of the generate_entry_invoice function before the return statement
 */
/*
// Send email with invoice attachment
awards_send_invoice_email($entry_id, $payment_details, $file_path);
*/

/**
 * Helper function to get a specific plugin template file
 * 
 * @param string $template_name Template file name
 * @return string Full path to the template file
 */
function awards_get_template_path($template_name) {
    return plugin_dir_path(dirname(__FILE__)) . 'template/' . $template_name;
}
/**
 * Utility function to generate invoices for all existing entries with payments
 * This can be run via an admin tool or WP-CLI
 * 
 * @param bool $limit_entries Optional number of entries to process (for large databases)
 * @param int $offset Optional offset for pagination
 * @param bool $regenerate Whether to regenerate existing invoices
 * @return array Results with counts of successes and failures
 */
function awards_generate_invoices_for_existing_entries($limit_entries = false, $offset = 0, $regenerate = false) {
    $results = array(
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'total' => 0,
        'mass_payments_processed' => 0,
    );
    
    // Build meta query based on regenerate option
    $meta_query = array(
        'relation' => 'AND',
        array(
            'key' => 'payment',
            'compare' => 'EXISTS',
        ),
    );
    
    // Only exclude existing invoices if we're not regenerating
    if (!$regenerate) {
        $meta_query[] = array(
            'key' => 'invoice_path',
            'compare' => 'NOT EXISTS',
        );
    }
    
    // Query for entries with payments
    $args = array(
        'post_type' => 'entry',
        'posts_per_page' => $limit_entries ? intval($limit_entries) : -1,
        'offset' => intval($offset),
        'meta_query' => $meta_query,
    );
    
    $entries = new WP_Query($args);
    $results['total'] = $entries->found_posts;
    
    if ($entries->have_posts()) {
        // Track mass payments to avoid duplicate invoice generation
        $processed_mass_payments = array();
        
        while ($entries->have_posts()) {
            $entries->the_post();
            $entry_id = get_the_ID();
            $payment_details = get_post_meta($entry_id, 'payment', true);
            
            if (!$payment_details) {
                $results['skipped']++;
                continue;
            }
            
            // Check if this entry is part of a mass payment
            $is_mass_payment = get_post_meta($entry_id, 'is_mass_payment', true);
            $mass_payment_id = get_post_meta($entry_id, 'mass_payment_id', true);
            
            if ($is_mass_payment && $mass_payment_id) {
                // Check if we've already processed this mass payment
                if (in_array($mass_payment_id, $processed_mass_payments)) {
                    // Skip this entry as it's part of an already processed mass payment
                    $results['skipped']++;
                    continue;
                }
                
                // Get all entries in this mass payment
                $mass_payment_entries = get_post_meta($entry_id, 'mass_payment_entries', true);
                if (!$mass_payment_entries) {
                    $mass_payment_entries = get_post_meta($entry_id, 'mass_payment_ids', true);
                }
                
                if ($mass_payment_entries) {
                    $entry_ids = explode(',', $mass_payment_entries);
                    $entry_ids = array_map('intval', $entry_ids);
                    $entry_ids = array_filter($entry_ids);
                    
                    if (!empty($entry_ids)) {
                        // Fix any missing fields in payment details
                        if (!isset($payment_details['time'])) {
                            $payment_details['time'] = get_the_modified_date('Y-m-d H:i:s');
                        }
                        if (!isset($payment_details['gateway'])) {
                            $payment_details['gateway'] = 'unknown';
                        }
                        if (!isset($payment_details['RefID'])) {
                            $payment_details['RefID'] = $mass_payment_id;
                        }
                        if (!isset($payment_details['Authority'])) {
                            $payment_details['Authority'] = 'mass-auth-' . $mass_payment_id;
                        }
                        
                        // Generate ONE invoice for all entries in this mass payment
                        $invoice_path = generate_mass_payment_invoice($entry_ids, $payment_details);
                        
                        if ($invoice_path) {
                            $results['success']++;
                            $results['mass_payments_processed']++;
                            
                            // Mark this mass payment as processed
                            $processed_mass_payments[] = $mass_payment_id;
                        } else {
                            $results['failed']++;
                        }
                        
                        continue;
                    }
                }
            }
            
            // This is a single entry payment
            // Fix any missing fields in payment details to prevent errors
            if (!isset($payment_details['time'])) {
                // Use post modification date if payment time isn't available
                $payment_details['time'] = get_the_modified_date('Y-m-d H:i:s');
            }
            
            if (!isset($payment_details['gateway'])) {
                $payment_details['gateway'] = 'unknown';
            }
            
            if (!isset($payment_details['RefID'])) {
                $payment_details['RefID'] = 'retrogenerated-' . $entry_id;
            }
            
            if (!isset($payment_details['Authority'])) {
                $payment_details['Authority'] = 'retrogenerated-auth-' . $entry_id;
            }
            
            // Generate the invoice for single entry
            $invoice_path = generate_entry_invoice($entry_id, $payment_details);
            
            if ($invoice_path) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
    }
    
    wp_reset_postdata();
    
    return $results;
}

/**
 * Download all invoices as a ZIP file
 */
function awards_download_all_invoices() {
    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'awards'));
    }
    
    // Get all entries with invoices
    $entries_query = new WP_Query(array(
        'post_type' => 'entry',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'payment',
                'compare' => 'EXISTS',
            ),
            array(
                'key' => 'invoice_path',
                'compare' => 'EXISTS',
            ),
        ),
    ));
    
    if (!$entries_query->have_posts()) {
        wp_die(__('No invoices found to download.', 'awards'));
    }
    
    $invoice_files = array();
    
    // Collect all invoice files
    while ($entries_query->have_posts()) {
        $entries_query->the_post();
        $entry_id = get_the_ID();
        $invoice_path = get_post_meta($entry_id, 'invoice_path', true);
        
        if ($invoice_path && file_exists($invoice_path)) {
            $invoice_files[] = array(
                'path' => $invoice_path,
                'name' => basename($invoice_path),
                'entry_id' => $entry_id,
            );
        }
    }
    
    wp_reset_postdata();
    
    if (empty($invoice_files)) {
        wp_die(__('No invoice files found on server.', 'awards'));
    }
    
    // Create ZIP file in uploads directory
    $upload_dir = wp_upload_dir();
    $zip_filename = 'all-invoices-' . date('Y-m-d-His') . '.zip';
    $zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
    
    // Delete old zip if exists
    if (file_exists($zip_path)) {
        @unlink($zip_path);
    }
    
    $zip = new ZipArchive();
    $open_result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($open_result !== TRUE) {
        wp_die(__('Could not create ZIP file. Error code: ', 'awards') . $open_result);
    }
    
    // Add files to ZIP
    $added_count = 0;
    foreach ($invoice_files as $file) {
        if (file_exists($file['path']) && is_readable($file['path'])) {
            // Create a unique filename
            $entry_title = get_the_title($file['entry_id']);
            $entry_title = sanitize_file_name($entry_title);
            $entry_title = substr($entry_title, 0, 50); // Limit length
            
            if (empty($entry_title)) {
                $entry_title = 'entry-' . $file['entry_id'];
            }
            
            $zip_name = 'invoice-' . $file['entry_id'] . '-' . $entry_title . '.pdf';
            
            $add_result = $zip->addFile($file['path'], $zip_name);
            if ($add_result) {
                $added_count++;
            }
        }
    }
    
    // Close the ZIP file - IMPORTANT: Must close before sending
    $close_result = $zip->close();
    
    if (!$close_result) {
        @unlink($zip_path);
        wp_die(__('Failed to finalize ZIP file.', 'awards'));
    }
    
    if ($added_count === 0) {
        @unlink($zip_path);
        wp_die(__('No files could be added to the ZIP archive.', 'awards'));
    }
    
    // Verify ZIP file exists and has content
    if (!file_exists($zip_path)) {
        wp_die(__('ZIP file was not created.', 'awards'));
    }
    
    $file_size = filesize($zip_path);
    if ($file_size === 0 || $file_size === false) {
        @unlink($zip_path);
        wp_die(__('ZIP file is empty.', 'awards'));
    }
    
    // Clear ALL output buffers to prevent corruption
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    // Prevent any compression
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');
    
    // Set proper headers
    header_remove(); // Remove all existing headers
    header('Content-Type: application/zip', true);
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"', true);
    header('Content-Length: ' . $file_size, true);
    header('Content-Transfer-Encoding: binary', true);
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0', true);
    header('Pragma: public', true);
    header('Expires: 0', true);
    
    // Flush and disable WordPress hooks
    ob_end_flush();
    
    // Output the file
    readfile($zip_path);
    
    // Clean up
    @unlink($zip_path);
    
    // Exit immediately to prevent any WordPress output
    die();
}

/**
 * Handle invoice download requests before any output
 */
/**
 * Admin page for batch generation of invoices
 * Add this under your plugin's admin menu
 */
function awards_batch_invoice_generator_page() {
    // Process form submission
    $results = null;
    $message = '';
    $cleanup_results = null;
    
    // Handle cleanup of duplicate mass payment invoices
    if (isset($_POST['cleanup_duplicates']) && wp_verify_nonce($_POST['_wpnonce'], 'awards_batch_invoices')) {
        $cleanup_results = awards_cleanup_duplicate_mass_payment_invoices();
        
        $message = sprintf(
            __('Cleanup complete. Found %d mass payment groups. Generated %d new consolidated invoices. Removed %d redundant old invoice files.', 'awards'),
            $cleanup_results['duplicates_found'],
            $cleanup_results['invoices_consolidated'],
            $cleanup_results['duplicates_removed']
        );
    }
    
    if (isset($_POST['generate_batch_invoices']) && wp_verify_nonce($_POST['_wpnonce'], 'awards_batch_invoices')) {
        $limit = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $regenerate = isset($_POST['regenerate']) ? true : false;
        
        $results = awards_generate_invoices_for_existing_entries($limit, $offset, $regenerate);
        
        if ($results['mass_payments_processed'] > 0) {
            $message = sprintf(
                __('Processed %d entries. Successfully generated: %d invoices (%d mass payments, %d single), Failed: %d, Skipped: %d', 'awards'),
                $results['total'],
                $results['success'],
                $results['mass_payments_processed'],
                $results['success'] - $results['mass_payments_processed'],
                $results['failed'],
                $results['skipped']
            );
        } else {
            $message = sprintf(
                __('Processed %d entries. Successfully generated: %d, Failed: %d, Skipped: %d', 'awards'),
                $results['total'],
                $results['success'],
                $results['failed'],
                $results['skipped']
            );
        }
    }
    
    // Count remaining entries that need invoices
    $remaining_query = new WP_Query(array(
        'post_type' => 'entry',
        'posts_per_page' => 1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'payment',
                'compare' => 'EXISTS',
            ),
            array(
                'key' => 'invoice_path',
                'compare' => 'NOT EXISTS',
            ),
        ),
    ));
    
    $remaining_count = $remaining_query->found_posts;
    
    // Count total entries with invoices
    $total_invoices_query = new WP_Query(array(
        'post_type' => 'entry',
        'posts_per_page' => 1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'payment',
                'compare' => 'EXISTS',
            ),
            array(
                'key' => 'invoice_path',
                'compare' => 'EXISTS',
            ),
        ),
    ));
    
    $total_invoices = $total_invoices_query->found_posts;
    wp_reset_postdata();
    
    // Generate download all URL - use admin-ajax for clean file download
    $download_all_url = admin_url('admin-ajax.php?action=awards_download_invoices_zip&_wpnonce=' . wp_create_nonce('download_all_invoices'));
    
    ?>
    <div class="wrap">
        <h1><?php _e('Batch Generate Invoices for Existing Entries', 'awards'); ?></h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Cleanup Duplicate Mass Payment Invoices Section -->
        <div class="card" style="margin-bottom: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <h2><?php _e('🔧 Cleanup Duplicate Mass Payment Invoices', 'awards'); ?></h2>
            <p>
                <?php _e('If you have mass payments that generated duplicate invoices (one invoice per entry instead of one invoice for all entries), use this tool to clean them up.', 'awards'); ?>
            </p>
            <p class="description">
                <?php _e('This will find all mass payments with duplicate invoices and remove the individual files, keeping only the single mass payment invoice.', 'awards'); ?>
            </p>
            <form method="post" action="" style="margin-top: 15px;">
                <?php wp_nonce_field('awards_batch_invoices'); ?>
                <input type="submit" name="cleanup_duplicates" class="button button-secondary" value="<?php _e('Clean Up Duplicate Invoices', 'awards'); ?>" onclick="return confirm('This will delete duplicate invoice files. Are you sure?');">
            </form>
        </div>
        
        <!-- Download All Invoices Section -->
        <?php if ($total_invoices > 0): ?>
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php _e('Download All Invoices', 'awards'); ?></h2>
                <p>
                    <?php printf(
                        __('There are %d invoices available for download. Click the button below to download all invoices as a ZIP file.', 'awards'),
                        $total_invoices
                    ); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url($download_all_url); ?>" class="button button-primary" target="_blank">
                        <?php _e('Download All Invoices (ZIP)', 'awards'); ?>
                    </a>
                </p>
                <p class="description">
                    <?php _e('The download will open in a new tab. Please wait for the ZIP file to be generated and downloaded.', 'awards'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><?php _e('Generate Missing Invoices', 'awards'); ?></h2>
            
            <?php if ($remaining_count > 0 || $total_invoices > 0): ?>
                <p>
                    <?php if ($remaining_count > 0): ?>
                        <?php printf(
                            __('There are approximately %d entries with payments that need invoices generated.', 'awards'),
                            $remaining_count
                        ); ?>
                    <?php endif; ?>
                    <?php if ($total_invoices > 0): ?>
                        <br>
                        <?php printf(
                            __('There are %d entries with existing invoices.', 'awards'),
                            $total_invoices
                        ); ?>
                    <?php endif; ?>
                </p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('awards_batch_invoices'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="batch_size"><?php _e('Batch Size', 'awards'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" value="50" min="1" max="500" class="regular-text">
                                <p class="description">
                                    <?php _e('Number of entries to process in one batch. Use a smaller number if you encounter timeout issues.', 'awards'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="offset"><?php _e('Offset', 'awards'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="offset" id="offset" value="0" min="0" class="regular-text">
                                <p class="description">
                                    <?php _e('Skip this many entries before processing. Useful for continuing after a previous batch.', 'awards'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="regenerate"><?php _e('Regenerate Existing', 'awards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="regenerate" id="regenerate" value="1">
                                <label for="regenerate"><?php _e('Regenerate invoices for entries that already have invoices', 'awards'); ?></label>
                                <p class="description">
                                    <?php _e('Check this box to overwrite existing invoices. Useful if invoice template or data has been updated.', 'awards'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="generate_batch_invoices" class="button button-primary" value="<?php _e('Generate Invoices', 'awards'); ?>">
                    </p>
                </form>
                
                <?php if ($results): ?>
                    <p>
                        <?php _e('If there are more entries to process, you can continue with the next batch by setting the offset value.', 'awards'); ?>
                    </p>
                    <p>
                        <?php printf(
                            __('Suggested next offset: %d'),
                            isset($_POST['offset']) ? (intval($_POST['offset']) + intval($_POST['batch_size'])) : $results['success'] + $results['failed'] + $results['skipped']
                        ); ?>
                    </p>
                <?php endif; ?>
                
            <?php else: ?>
                <p><?php _e('All entries with payments already have invoices generated.', 'awards'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Register the batch invoice generator admin page
 */
function awards_register_batch_invoice_page() {
    add_submenu_page(
        'edit.php?post_type=entry',
        __('Batch Generate Invoices', 'awards'),
        __('Batch Generate Invoices', 'awards'),
        'manage_options',
        'awards-batch-invoices',
        'awards_batch_invoice_generator_page'
    );
}
add_action('admin_menu', 'awards_register_batch_invoice_page');

/**
 * Register AJAX handler for downloading all invoices
 * This provides a cleaner way to handle file downloads without WordPress interference
 */
add_action('wp_ajax_awards_download_invoices_zip', 'awards_download_all_invoices');

/**
 * Handle invoice downloads early in admin_init to prevent output corruption
 */
/**
 * Detect mass payments and handle them differently
 * This function should be called from your payment success handler
 *
 * @param string $transaction_id The unique transaction ID
 * @param array $entry_ids Array of entry IDs in this transaction
 * @param array $payment_details Payment details array
 * @return string|bool Path to the generated invoice or false on failure
 */
function handle_mass_payment_invoice($transaction_id, $entry_ids, $payment_details) {
    if (count($entry_ids) > 1) {
        // This is a mass payment - generate a transaction invoice
        return generate_transaction_invoice($entry_ids, $payment_details);
    } else {
        // Single entry payment - use the original method
        return generate_entry_invoice($entry_ids[0], $payment_details);
    }
}
function awards_modify_translations($translation, $text, $domain) {
    // Only modify our plugin's text domain
    if ($domain !== 'awards') {
        return $translation;
    }
    
    // Replace "Account" with "Settings" in UI elements
    if ($text === 'Account') {
        return __('Settings', 'awards');
    }
    
    // Replace "My Account" with "My Settings" in UI elements
    if ($text === 'My Account') {
        return __('My Settings', 'awards');
    }
    
    return $translation;
}
add_filter('gettext', 'awards_modify_translations', 10, 3);
/**
 * Add an invoice download button to the entry details page
 */
function awards_add_invoice_button_to_entry() {
    global $post;
    
    // Only show for the entry post type
    if (!$post || $post->post_type !== 'entry') {
        return;
    }
    
    // Check if the current user is the author or an admin
    if ($post->post_author != get_current_user_id() && !current_user_can('edit_posts')) {
        return;
    }
    
    // Check if payment exists and entry is approved
    $payment_details = get_post_meta($post->ID, 'payment', true);
    $status = get_post_meta($post->ID, 'status', true);
    
    if (!$payment_details || ($status !== 'pending_review' && $status !== 'approved' && $status !== 'winner')) {
        return;
    }
    
    // Get invoice URL
    $invoice_url = get_post_meta($post->ID, 'invoice_url', true);
    
    // If no invoice but payment exists, try to generate one
    if (!$invoice_url && $payment_details) {
        // Fix any missing fields in payment details to prevent errors
        if (!isset($payment_details['time'])) {
            // Use post modification date if payment time isn't available
            $payment_details['time'] = get_the_modified_date('Y-m-d H:i:s');
        }
        
        if (!isset($payment_details['gateway'])) {
            $payment_details['gateway'] = 'unknown';
        }
        
        if (!isset($payment_details['RefID'])) {
            $payment_details['RefID'] = 'generated-' . $post->ID;
        }
        
        if (!isset($payment_details['Authority'])) {
            $payment_details['Authority'] = 'generated-auth-' . $post->ID;
        }
        
        $invoice_path = generate_entry_invoice($post->ID, $payment_details);
        if ($invoice_path) {
            $invoice_url = get_post_meta($post->ID, 'invoice_url', true);
        }
    }
    
    // If we have an invoice URL, display the download button
    if ($invoice_url) {
        ?>
        <div class="awards-invoice-download">
            <h4><?php _e('Payment Receipt', 'awards'); ?></h4>
            <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button btn btn-primary">
                <i class="dashicons dashicons-download"></i> <?php _e('Download Invoice', 'awards'); ?>
            </a>
        </div>
        <?php
    }
}

/**
 * Hook the invoice button to the appropriate action
 * You may need to adjust this hook based on your theme structure
 */
add_action('awards_entry_details_after', 'awards_add_invoice_button_to_entry');
/**
 * Display invoice download button for entries
 * Use this in your templates with: <?php awards_display_invoice_button($entry_id); ?>
 * 
 * @param int $entry_id The entry ID
 * @return void
 */
function awards_display_invoice_button($entry_id = null) {
    // If no entry ID is provided, try to get it from the global post
    if (!$entry_id) {
        global $post;
        if (!$post || $post->post_type !== 'entry') {
            return;
        }
        $entry_id = $post->ID;
    }
    
    // Check if the current user has permission to view this entry
    $post_author = get_post_field('post_author', $entry_id);
    if ($post_author != get_current_user_id() && !current_user_can('edit_posts')) {
        return;
    }
    
    // Check if payment exists
    $payment_details = get_post_meta($entry_id, 'payment', true);
    if (!$payment_details) {
        return;
    }
    
    // Get invoice URL
    $invoice_url = get_post_meta($entry_id, 'invoice_url', true);
    
    // If no invoice but payment exists, try to generate one
    if (!$invoice_url && function_exists('generate_entry_invoice')) {
        // Fix any missing fields in payment details to prevent errors
        if (!isset($payment_details['time'])) {
            $payment_details['time'] = get_the_modified_date('Y-m-d H:i:s', $entry_id);
        }
        
        if (!isset($payment_details['gateway'])) {
            $payment_details['gateway'] = 'unknown';
        }
        
        if (!isset($payment_details['RefID'])) {
            $payment_details['RefID'] = 'generated-' . $entry_id;
        }
        
        if (!isset($payment_details['Authority'])) {
            $payment_details['Authority'] = 'generated-auth-' . $entry_id;
        }
        
        $invoice_path = generate_entry_invoice($entry_id, $payment_details);
        if ($invoice_path) {
            $invoice_url = get_post_meta($entry_id, 'invoice_url', true);
        }
    }
    
    // Display invoice button if we have an invoice URL
    if ($invoice_url) {
        ?>
        <div class="awards-invoice-download">
            <h4><?php _e('Payment Receipt', 'awards'); ?></h4>
            <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button btn btn-primary">
                <i class="dashicons dashicons-download"></i> <?php _e('Download Invoice', 'awards'); ?>
            </a>
        </div>
        <?php
    }
}
function handle_mass_payment_verification($entry_ids, $payment_details, $total_amount) {
    // Convert string to array if needed
    if (is_string($entry_ids)) {
        $entry_ids = explode('-', $entry_ids);
    }
    
    if (empty($entry_ids) || !is_array($entry_ids)) {
        return false;
    }
    
    // Calculate fees for each entry
    $entry_fees = array();
    $total_calculated = 0;
    
    // Get individual entry fees
    foreach ($entry_ids as $entry_id) {
        $entry_id = intval($entry_id);
        if (!$entry_id) continue;
        
        // Get entry fee with coupon if applicable
        if (get_copon_session()) {
            $fee = get_award_entry_total_fee_with_copon($entry_id, false);
        } else {
            $fee = get_award_entry_total_fee($entry_id, false);
        }
        
        $entry_fees[$entry_id] = $fee;
        $total_calculated += $fee;
    }
    
    // Adjust for rounding errors if total calculated doesn't match the payment total
    if ($total_calculated != $total_amount) {
        // Calculate adjustment factor
        $adjustment_factor = $total_amount / $total_calculated;
        
        // Apply adjustment to each entry fee
        foreach ($entry_fees as $entry_id => $fee) {
            $entry_fees[$entry_id] = round($fee * $adjustment_factor, 2);
        }
        
        // Ensure the total still matches by adding any remaining cents to the last entry
        $adjusted_total = array_sum($entry_fees);
        if ($adjusted_total != $total_amount) {
            $difference = $total_amount - $adjusted_total;
            $last_entry_id = end(array_keys($entry_fees));
            $entry_fees[$last_entry_id] += $difference;
        }
    }
    
    // Store the mass payment information and update each entry
    $current_time = date('Y-F-d H:i:s');
    $mass_payment_id = 'mass-' . time() . '-' . rand(1000, 9999);
    
    // Create a record of the mass payment
    $mass_payment_record = array(
        'mass_payment_id' => $mass_payment_id,
        'entry_ids' => $entry_ids,
        'total_amount' => $total_amount,
        'payment_details' => $payment_details,
        'entry_fees' => $entry_fees,
        'time' => $current_time
    );
    
    // Store the mass payment record in a site option
    $existing_mass_payments = get_option('awards_mass_payments', array());
    $existing_mass_payments[$mass_payment_id] = $mass_payment_record;
    update_option('awards_mass_payments', $existing_mass_payments);
    
    // Update each entry with its portion of the payment
    foreach ($entry_fees as $entry_id => $fee) {
        // Update entry status
        update_post_meta($entry_id, 'status', 'pending_review');
        
        // Update modified time
        $update_post_date = array(
            'ID' => $entry_id,
            'post_modified' => $current_time,
            'post_modified_gmt' => $current_time,
        );
        wp_update_post($update_post_date);
        
        // Create a copy of the payment details with the correct amount for this entry
        $entry_payment_details = $payment_details;
        $entry_payment_details['Amount'] = $fee;
        $entry_payment_details['mass_payment_id'] = $mass_payment_id; // Add reference to mass payment
        
        // Update payment meta
        update_post_meta($entry_id, 'payment', $entry_payment_details);
        
        // Process coupon if used
        process_and_clear_coupon($entry_id);
    }
    
    return true;
}

/* ==========================================================================
   AWARDS PLUGIN - UNIFIED INVOICE SYSTEM (2025 REWRITE)
   ========================================================================== */

/**
 * MASTER FUNCTION: Create an invoice PDF
 * Handles both Single and Mass payments dynamically.
 */
function awards_create_invoice_pdf($entry_ids, $payment_details) {
    // 1. Sanitize Input
    if (!is_array($entry_ids)) $entry_ids = array($entry_ids);
    $entry_ids = array_filter(array_map('intval', $entry_ids));
    
    if (empty($entry_ids)) return false;

    // 2. Get User & Primary Entry Data
    $first_entry = get_post($entry_ids[0]);
    if (!$first_entry) return false;
    
    $user_id = $first_entry->post_author;
    $user = get_userdata($user_id);
    if (!$user) return false;

    // 3. Load FPDF (Robust Relative Path)
    // This looks for the library relative to THIS file, regardless of folder name.
    $fpdf_path = plugin_dir_path(__FILE__) . 'inc/vendor/fpdf/fpdf.php';
    if (file_exists($fpdf_path)) {
        require_once($fpdf_path);
    } else {
        error_log("CRITICAL: FPDF not found at $fpdf_path");
        return false;
    }

    // 4. Define PDF Class safely
    if (!class_exists('Awards_Unified_PDF')) {
        class Awards_Unified_PDF extends FPDF {
            function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
                $txt = (function_exists('iconv')) ? iconv('UTF-8', 'Windows-1252//TRANSLIT', $txt) : utf8_decode($txt);
                parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
            }
            // Standard Header for all invoices
            function Header() {
                $this->SetFont('Arial', 'B', 16);
                $this->Cell(190, 10, 'INVOICE', 0, 1, 'C');
                $this->Ln(5);
            }
            // Standard Footer for all invoices
            function Footer() {
                $this->SetY(-30);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 5, 'The total fee includes VAT for EU customers at 20%.', 0, 1, 'C');
                $this->Cell(0, 5, 'Thank you for participating in the Minimalist Photography Awards.', 0, 1, 'C');
            }
        }
    }

    try {
        // 5. Initialize PDF
        $pdf = new Awards_Unified_PDF();
        $pdf->AddPage();
        
        // 6. Invoice Meta Data
        $is_mass = (count($entry_ids) > 1);
        $ref_id = isset($payment_details['RefID']) ? $payment_details['RefID'] : 'TXN-' . time();
        $invoice_num = ($is_mass ? 'MASS-' : 'INV-') . substr($ref_id, 0, 8);
        $date = isset($payment_details['time']) ? $payment_details['time'] : current_time('mysql');

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(95, 10, 'Invoice #: ' . $invoice_num, 0, 0);
        $pdf->Cell(95, 10, 'Date: ' . date('F j, Y', strtotime($date)), 0, 1, 'R');
        $pdf->Ln(10);

        // 7. Bill To / From Sections
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(95, 7, 'Bill To:', 0, 0);
        $pdf->Cell(95, 7, 'From:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        // Customer
        $country = get_user_meta($user_id, 'country', true);
        $pdf->Cell(95, 5, $user->first_name . ' ' . $user->last_name, 0, 0);
        // Vendor
        $pdf->Cell(95, 5, 'Minimalist Photography Awards', 0, 1);
        
        $pdf->Cell(95, 5, $user->user_email, 0, 0);
        $pdf->Cell(95, 5, 'info@minimalistphotographyawards.com', 0, 1);
        
        $pdf->Cell(95, 5, ($country ? $country : ''), 0, 0);
        $pdf->Cell(95, 5, 'VAT: ATU80899636', 0, 1);
        $pdf->Ln(10);

        // 8. Items Table
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(95, 7, 'Description', 1, 0, 'L', true);
        $pdf->Cell(47.5, 7, 'Method', 1, 0, 'C', true);
        $pdf->Cell(47.5, 7, 'Amount', 1, 1, 'R', true);
        
        $pdf->SetFont('Arial', '', 10);
        
        // Currency Logic
        $currency_symbol = '$';
        $decimals = 2;
        if ((isset($payment_details['gateway']) && $payment_details['gateway'] == 'zarinpal') || 
            $country === 'Iran (Islamic Republic of)') {
            $currency_symbol = 'IRR ';
            $decimals = 0;
        } elseif (awards_options('currency') == 'euro' || awards_options('currency') == 'eur') {
            $currency_symbol = 'EUR ';
        }

        $total_calc = 0;
        
        foreach ($entry_ids as $eid) {
            $entry = get_post($eid);
            if (!$entry) continue;
            
            // Calculate fee
            $fee = function_exists('get_award_entry_total_fee') ? get_award_entry_total_fee($eid, false) : 0;
            $total_calc += $fee;
            
            $pdf->Cell(95, 7, 'Entry Fee - ' . $entry->post_title, 1);
            $pdf->Cell(47.5, 7, ucfirst($payment_details['gateway']), 1, 0, 'C');
            $pdf->Cell(47.5, 7, $currency_symbol . number_format((float)$fee, $decimals), 1, 1, 'R');
        }

        // Coupon Logic (Applies to total)
        $coupon_used = get_post_meta($entry_ids[0], 'coupon_used', true);
        if ($coupon_used) {
            if ($coupon_used['type'] == 'percent') {
                $discount_txt = $coupon_used['value'] . '%';
            } else {
                $discount_txt = '-' . $currency_symbol . number_format((float)$coupon_used['value'], $decimals);
            }
            $pdf->Cell(95, 7, 'Discount (Coupon: ' . $coupon_used['copon'] . ')', 1);
            $pdf->Cell(47.5, 7, '', 1, 0, 'C');
            $pdf->Cell(47.5, 7, $discount_txt, 1, 1, 'R');
        }

        // Total Row
        $final_total = isset($payment_details['Amount']) ? $payment_details['Amount'] : $total_calc;
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(142.5, 7, 'Total', 1, 0, 'R', true);
        $pdf->Cell(47.5, 7, $currency_symbol . number_format((float)$final_total, $decimals), 1, 1, 'R', true);

        // 9. Save & Link
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/awards-invoices/' . $user_id;
        if (!file_exists($invoice_dir)) wp_mkdir_p($invoice_dir);
        
        $file_name = $invoice_num . '-' . date('Ymd', strtotime($date)) . '.pdf';
        $file_path = $invoice_dir . '/' . $file_name;
        $file_url = $upload_dir['baseurl'] . '/awards-invoices/' . $user_id . '/' . $file_name;
        
        $pdf->Output('F', $file_path);

        // 10. Update ALL Entries
        // This ensures every entry points to this ONE file
        foreach ($entry_ids as $eid) {
            update_post_meta($eid, 'invoice_path', $file_path);
            update_post_meta($eid, 'invoice_url', $file_url);
            update_post_meta($eid, 'transaction_id', $ref_id);
            if ($is_mass) {
                update_post_meta($eid, 'is_mass_payment', true);
                update_post_meta($eid, 'mass_payment_id', $ref_id); // Use Transaction ID as Mass ID
                update_post_meta($eid, 'mass_payment_entries', implode(',', $entry_ids));
            }
        }

        return $file_path;

    } catch (Exception $e) {
        error_log("Awards PDF Gen Error: " . $e->getMessage());
        return false;
    }
}

/**
 * NEW MASS PAYMENT HANDLER
 * Replaces the old 'handle_mass_payment' with a simpler, unified logic.
 */
function handle_mass_payment($entry_ids, $payment_details, $total_amount) {
    if (is_string($entry_ids)) $entry_ids = explode('-', $entry_ids);
    $entry_ids = array_filter($entry_ids);
    if (empty($entry_ids)) return false;

    // 1. Generate the SINGLE Invoice for all these entries
    $invoice_path = awards_create_invoice_pdf($entry_ids, $payment_details);

    // 2. Update Entry Statuses
    foreach ($entry_ids as $entry_id) {
        $entry_id = intval($entry_id);
        
        // Update Status
        update_post_meta($entry_id, 'status', 'pending_review');
        
        // Save Payment Data
        $my_payment = $payment_details;
        // Note: We deliberately do NOT split the amount here for display simplicity, 
        // or you can split it if your logic requires per-entry accounting.
        // For now, we tag it as mass payment.
        update_post_meta($entry_id, 'payment', $my_payment);
        
        // Mark as Mass Payment
        update_post_meta($entry_id, 'is_mass_payment', true);
        
        // Handle Coupon cleanup
        process_and_clear_coupon($entry_id);
    }
    
    return true;
}

// ALIAS Wrappers for compatibility with old calls
function generate_transaction_invoice($ids, $details, $fees=[]) { return awards_create_invoice_pdf($ids, $details); }
function generate_mass_payment_invoice($ids, $details) { return awards_create_invoice_pdf($ids, $details); }
function generate_entry_invoice($id, $details) { return awards_create_invoice_pdf(array($id), $details); }
/**
 * Gets consistent currency codes throughout the system
 */
function get_normalized_currency() {
    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    
    switch ($userCountry) {
        case 'Iran (Islamic Republic of)':
            return 'IRR'; // Iranian Rial
        default:
            // Normalize currency code
            if ($defaultCurrency == 'euro' || $defaultCurrency == 'eur') {
                return 'EUR';
            } else {
                return 'USD';
            }
    }
}

/**
 * Ajax handler for regenerating invoices
 */
/**
 * Ajax handler for regenerating invoices
 */
function awards_regenerate_invoice_ajax() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'regenerate_invoice')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        exit;
    }
    
    // Check if user has permission
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permission denied'));
        exit;
    }
    
    // Get entry ID
    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    if (!$entry_id) {
        wp_send_json_error(array('message' => 'Invalid entry ID'));
        exit;
    }
    
    // Get payment details
    $payment_details = get_post_meta($entry_id, 'payment', true);
    if (!$payment_details) {
        wp_send_json_error(array('message' => 'No payment information found'));
        exit;
    }
    
    // Remove existing invoice data to force regeneration
    delete_post_meta($entry_id, 'invoice_path');
    delete_post_meta($entry_id, 'invoice_url');
    
    // Generate new invoice
    $invoice_path = generate_entry_invoice($entry_id, $payment_details);
    
    if ($invoice_path) {
        $invoice_url = get_post_meta($entry_id, 'invoice_url', true);
        
        // Add a timestamp to the URL to prevent caching
        $invoice_url = add_query_arg('t', time(), $invoice_url);
        
        wp_send_json_success(array(
            'message' => 'Invoice regenerated successfully',
            'invoice_url' => $invoice_url
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to regenerate invoice'));
    }
    
    exit;
}
add_action('wp_ajax_awards_regenerate_invoice', 'awards_regenerate_invoice_ajax');
add_action('wp_ajax_nopriv_awards_regenerate_invoice', 'awards_regenerate_invoice_ajax');

/**
 * Add JavaScript to admin footer to handle regenerate button click
 */
function awards_add_invoice_regenerate_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.regenerate-invoice').on('click', function() {
            var button = $(this);
            var entryId = button.data('entry-id');
            var resultDiv = $('#regenerate-invoice-result');
            
            // Disable button and show loading state
            button.prop('disabled', true);
            button.text('Processing...');
            
            // Make AJAX call
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'awards_regenerate_invoice',
                    entry_id: entryId,
                    nonce: '<?php echo wp_create_nonce('regenerate_invoice'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success notice-alt"><p>' + response.data.message + '</p></div>');
                        
                        // Update invoice link if it exists on the page
                        if (response.data.invoice_url && $('#invoice-link').length > 0) {
                            $('#invoice-link').attr('href', response.data.invoice_url);
                        }
                        
                        // Optionally refresh the page to show updated link
                        window.location.reload();
                    } else {
                        resultDiv.html('<div class="notice notice-error notice-alt"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error notice-alt"><p>Connection error occurred</p></div>');
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false);
                    button.text('Regenerate Invoice');
                }
            });
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'awards_add_invoice_regenerate_script');

/**
 * Add cache-busting to invoice download links
 */
function awards_modify_invoice_link($url, $entry_id) {
    if (!empty($url)) {
        // Add a timestamp parameter to prevent caching
        $url = add_query_arg('t', time(), $url);
    }
    return $url;
}
// Hook this to wherever you output the invoice URL in your theme
add_filter('awards_invoice_url', 'awards_modify_invoice_link', 10, 2);
add_action('init', 'handle_stripe_payment_success', 20);
/**
 * Modify the dashboard_entry_list.php mass payment form
 * Adds the necessary parameters to the form for mass payment handling
 */
function modify_mass_payment_form() {
    add_action('awards_before_mass_payment_form', function() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Enhance the mass payment form
                $('form.mass-payment-form').submit(function() {
                    // Add the mass parameter
                    $(this).append('<input type="hidden" name="mass" value="true">');
                    
                    // Collect selected entries
                    var selectedEntries = [];
                    $('input[name="selected[]"]:checked').each(function() {
                        selectedEntries.push($(this).val());
                    });
                    
                    // Update the ID parameter with comma-separated IDs
                    $('input[name="id"]').val(selectedEntries.join(','));
                    
                    return true;
                });
            });
        </script>
        <?php
    });
}
add_action('init', 'modify_mass_payment_form');

/**
 * Override the default dashboard_mass_pay function
 * This provides a more robust implementation for mass payments
 */
function awards_dashboard_mass_pay() {
    // Debug the incoming request
    error_log('Mass Pay Request: ' . print_r($_REQUEST, true));
    
    // Get selected entries from either POST or GET
    $selected_entries = array();
    
    if (isset($_POST['selected']) && !empty($_POST['selected'])) {
        $selected_entries = $_POST['selected'];
    } elseif (isset($_GET['selected']) && !empty($_GET['selected'])) {
        $selected_entries = $_GET['selected'];
    }
    
    // Debug selected entries
    error_log('Selected Entries: ' . print_r($selected_entries, true));
    
    // Validate entries
    $selected_entries_ids = array();
    if (!empty($selected_entries)) {
        foreach ($selected_entries as $selected_entry) {
            if (!is_numeric($selected_entry)) continue;
            $selected_entries_ids[] = intval($selected_entry);
        }
    }
    
    // If we don't have valid entries, show an error message
    if (empty($selected_entries_ids)) {
        echo '<div class="alert alert-danger">';
        echo __('No entries selected for payment. Please select at least one entry from your entries list.', 'awards');
        echo '</div>';
        
        echo '<p><a href="' . get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list" class="btn btn-primary">';
        echo __('Go back to your entries', 'awards');
        echo '</a></p>';
        return;
    }
    
    // Query the entries
    $selected_posts = new WP_Query(array(
        'post_type' => 'entry',
        'post__in' => $selected_entries_ids,
        'author' => get_current_user_id(),
        'post_status' => 'any',
        'meta_key' => 'status',
        'meta_value' => 'pending_payment',
    ));
    
    // If we don't have any posts, show an error
    if (!$selected_posts->have_posts()) {
        echo '<div class="alert alert-danger">';
        echo __('The selected entries are not available for payment.', 'awards');
        echo '</div>';
        
        echo '<p><a href="' . get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list" class="btn btn-primary">';
        echo __('Go back to your entries', 'awards');
        echo '</a></p>';
        return;
    }
    
    // Calculate total amount and prepare entry details
    $total_amount = 0;
    $entry_details = array();
    
    while ($selected_posts->have_posts()) {
        $selected_posts->the_post();
        $entry_id = get_the_ID();
        
        // Calculate fee with coupon if applicable
        if (get_copon_session()) {
            $fee = get_award_entry_total_fee_with_copon($entry_id, false);
        } else {
            $fee = get_award_entry_total_fee($entry_id, false);
        }
        
        $total_amount += $fee;
        
        // Get categories
        $categories = array();
        $terms = wp_get_post_terms($entry_id, 'entrycat');
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = $term->name;
            }
        }
        
        // Store entry details for display
        $entry_details[] = array(
            'id' => $entry_id,
            'title' => get_the_title(),
            'fee' => $fee,
            'categories' => $categories,
        );
    }
    wp_reset_postdata();
    
    // Determine currency format
    $userCountry = get_user_meta(get_current_user_id(), 'country', true);
    $defaultCurrency = awards_options('currency');
    $currency = 'USD';
    $currency_symbol = '$';
    
    if ($userCountry === 'Iran (Islamic Republic of)') {
        $currency = 'IRR';
        $currency_symbol = 'IRR ';
        $formatted_total = $currency_symbol . number_format($total_amount, 0);
    } else if ($defaultCurrency === 'euro' || $defaultCurrency === 'eur') {
    $currency = 'EUR';
    $currency_symbol = 'EUR '; // Use the defined Euro symbol variable
    $formatted_total = $currency_symbol . number_format($total_amount, 2);
} else {
    $formatted_total = $currency_symbol . number_format($total_amount, 2);
}
    
    // Get available payment methods
    $payment_methods = array();
    
    // PayPal
    if (awards_options('paypal_clientid') && awards_options('paypal_clientsecret')) {
        $payment_methods['paypal'] = array(
            'name' => 'PayPal',
            'icon' => 'paypal-icon.jpg',
            'url' => get_permalink(awards_options('page_dashboard')) . '/?payment=true&gateway=paypal&mass=true&id=' . implode(',', $selected_entries_ids),
        );
    }
    
    // Stripe
    if ((awards_options('stripe_test_mode') == 'enable' && awards_options('stripe_test_secret_key') && awards_options('stripe_test_publishable_key')) ||
        (awards_options('stripe_secret_key') && awards_options('stripe_publishable_key'))) {
        $payment_methods['stripe'] = array(
            'name' => 'Stripe',
            'icon' => 'stripe-icon.jpg',
            'url' => get_permalink(awards_options('page_dashboard')) . '/?payment=true&gateway=stripe&mass=true&id=' . implode(',', $selected_entries_ids),
        );
    }
    
    // Zarinpal (only for Iranian users)
    if ($userCountry === 'Iran (Islamic Republic of)' && awards_options('zarinpal_merchant_id')) {
        $payment_methods['zarinpal'] = array(
            'name' => 'Zarinpal',
            'icon' => 'zarinpal-logo.png', 
            'url' => get_permalink(awards_options('page_dashboard')) . '/?payment=true&gateway=zarinpal&mass=true&id=' . implode(',', $selected_entries_ids),
        );
    }
    
    // NowPayment (crypto)
    if (awards_options('enable_crypto_payment') == 'enable' && awards_options('nowpayment_api_key')) {
        $payment_methods['now-payment'] = array(
            'name' => 'Crypto',
            'icon' => 'cryptocurrency-icon.png',
            'url' => get_permalink(awards_options('page_dashboard')) . '/?payment=true&gateway=now-payment&mass=true&id=' . implode(',', $selected_entries_ids),
        );
    }
    
    // Plisio (crypto)
    if (awards_options('enable_crypto_payment') == 'enable' && awards_options('plisio_api_key')) {
        $payment_methods['plisio'] = array(
            'name' => 'Plisio Crypto',
            'icon' => 'plisio-icon.jpg',
            'url' => get_permalink(awards_options('page_dashboard')) . '/?payment=true&gateway=plisio&mass=true&id=' . implode(',', $selected_entries_ids),
        );
    }
    
    // Include the template
    require('template/mass-payment.php');
}

// Override the existing dashboard_mass_pay function
remove_action('wp_awards_dashboard_mass_pay', 'wp_awards::dashboard_mass_pay');
add_action('wp_awards_dashboard_mass_pay', 'awards_dashboard_mass_pay');

/**
 * CLEANUP FUNCTION: Consolidated logic with screen feedback
 */
function awards_cleanup_duplicate_mass_payment_invoices() {
    global $wpdb;
    $results = array('duplicates_found' => 0, 'duplicates_removed' => 0, 'invoices_consolidated' => 0, 'errors' => 0);
    
    // Find groups
    $entries_query = new WP_Query(array(
        'post_type' => 'entry',
        'posts_per_page' => -1,
        'meta_query' => array(array('key' => 'is_mass_payment', 'value' => '1', 'compare' => '='))
    ));
    
    if (!$entries_query->have_posts()) return $results;
    
    $processed = array();
    
    while ($entries_query->have_posts()) {
        $entries_query->the_post();
        $entry_id = get_the_ID();
        
        $mass_id = get_post_meta($entry_id, 'mass_payment_id', true);
        if (!$mass_id || in_array($mass_id, $processed)) continue;
        $processed[] = $mass_id;
        
        // Get IDs
        $mass_entries = get_post_meta($entry_id, 'mass_payment_entries', true);
        if (!$mass_entries) $mass_entries = get_post_meta($entry_id, 'mass_payment_ids', true);
        
        $ids = is_array($mass_entries) ? $mass_entries : explode(',', $mass_entries);
        $ids = array_unique(array_filter(array_map('intval', $ids)));
        
        if (count($ids) <= 1) continue;
        
        // 1. Check for existing mass invoice
        $existing_mass = null;
        $old_paths = array();
        
        foreach ($ids as $eid) {
            $path = get_post_meta($eid, 'invoice_path', true);
            if ($path && file_exists($path)) {
                $old_paths[] = $path;
                if (strpos(basename($path), 'mass-invoice-') === 0) $existing_mass = $path;
            }
        }
        $old_paths = array_unique($old_paths);
        $target = $existing_mass;
        
        // 2. Generate if missing
        if (!$target) {
            $payment = get_post_meta($entry_id, 'payment', true);
            if ($payment) {
                // Ensure defaults
                if (!isset($payment['RefID'])) $payment['RefID'] = $mass_id;
                if (!isset($payment['time'])) $payment['time'] = get_the_modified_date('Y-m-d H:i:s');
                if (!isset($payment['gateway'])) $payment['gateway'] = 'unknown';

                // Attempt Generation
                $generated = generate_mass_payment_invoice($ids, $payment);
                
                if ($generated && file_exists($generated)) {
                    $target = $generated;
                    $results['invoices_consolidated']++;
                } else {
                    $results['errors']++;
                    // Echoing specific failure for this group to screen
                    echo "<div style='color:red;'>Failed to generate for group $mass_id</div>";
                    continue; 
                }
            } else {
                continue;
            }
        }
        
        // 3. Delete Old Files
        if ($target) {
            $results['duplicates_found']++;
            foreach ($old_paths as $old_path) {
                if ($old_path !== $target && strpos(basename($old_path), 'mass-invoice-') === false) {
                    @unlink($old_path);
                    $results['duplicates_removed']++;
                }
            }
        }
    }
    
    wp_reset_postdata();
    return $results;
}

/**
 * Add these debugging functions to functions.php
 */

/**
 * Test Stripe API credentials
 * This function checks if the configured Stripe API keys are valid
 */
function awards_test_stripe_credentials() {
    // Only available for administrators
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    // Load Stripe library
    require_once('inc/vendor/stripe/init.php');
    
    // Get the API keys based on mode
    $test_mode = (awards_options('stripe_test_mode') == 'enable');
    
    if ($test_mode) {
        $stripe_secret_key = awards_options('stripe_test_secret_key');
        $stripe_publishable_key = awards_options('stripe_test_publishable_key');
        $mode_label = 'Test Mode';
    } else {
        $stripe_secret_key = awards_options('stripe_secret_key');
        $stripe_publishable_key = awards_options('stripe_publishable_key');
        $mode_label = 'Live Mode';
    }
    
    echo "<h2>Testing Stripe API Credentials ($mode_label)</h2>";
    
    // Check if keys are configured
    if (empty($stripe_secret_key) || empty($stripe_publishable_key)) {
        echo '<div class="notice notice-error"><p>Stripe API keys are not properly configured.</p></div>';
        return;
    }
    
    // Test the secret key
    echo '<h3>Testing Secret Key</h3>';
    try {
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        $account = \Stripe\Account::retrieve();
        
        echo '<div class="notice notice-success"><p>Success! Connected to Stripe account: ' . $account->id . '</p>';
        echo '<p>Account name: ' . $account->business_profile->name . '</p>';
        echo '<p>Account country: ' . $account->country . '</p></div>';
    } catch (\Exception $e) {
        echo '<div class="notice notice-error"><p>Error: ' . $e->getMessage() . '</p></div>';
    }
    
    // The publishable key can't be directly tested through the API, but we can validate its format
    echo '<h3>Checking Publishable Key</h3>';
    $expected_prefix = $test_mode ? 'pk_test_' : 'pk_live_';
    
    if (strpos($stripe_publishable_key, $expected_prefix) === 0) {
        echo '<div class="notice notice-success"><p>Publishable key format appears correct.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Publishable key does not have the expected format. It should start with "' . $expected_prefix . '"</p></div>';
    }
    
    // Check webhook settings
    echo '<h3>Webhook Information</h3>';
    $webhook_url = site_url('/wp-content/plugins/awards-signup2/stripe-webhook.php');
    $webhook_secret = awards_options('stripe_webhook_secret');
    
    echo '<p>Your webhook URL is: <code>' . $webhook_url . '</code></p>';
    
    if (empty($webhook_secret)) {
        echo '<div class="notice notice-warning"><p>No webhook secret configured. While payments will still work, you won\'t receive payment confirmations through webhooks.</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Webhook secret is configured.</p></div>';
    }
    
    echo '<p>Make sure to set up this webhook URL in your Stripe dashboard with the following events:</p>';
    echo '<ul>';
    echo '<li>checkout.session.completed</li>';
    echo '<li>payment_intent.payment_failed</li>';
    echo '</ul>';
}

/**
 * Add a Stripe test page to the admin menu
 */
function awards_add_stripe_test_page() {
    add_submenu_page(
        'edit.php?post_type=entry',
        'Stripe API Test',
        'Stripe API Test',
        'manage_options',
        'awards-stripe-test',
        'awards_test_stripe_credentials'
    );
}
add_action('admin_menu', 'awards_add_stripe_test_page');

/**
 * Add a troubleshooting tab to the admin page
 */
function awards_add_troubleshooting_section() {
    // Add this in your admin page after the settings sections
    ?>
    <h2>Troubleshooting</h2>
    <table class="form-table">
        <tr>
            <th><?php _e('Stripe Configuration Test', 'awards'); ?></th>
            <td>
                <a href="<?php echo admin_url('edit.php?post_type=entry&page=awards-stripe-test'); ?>" class="button">Test Stripe Configuration</a>
                <p class="description"><?php _e('This will test your Stripe API credentials to ensure they are configured correctly.', 'awards'); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php _e('Clear Payment Sessions', 'awards'); ?></th>
            <td>
                <form method="post" action="" onsubmit="return confirm('Are you sure you want to clear all Stripe session data?');">
                    <?php wp_nonce_field('awards_clear_sessions', 'awards_clear_sessions_nonce'); ?>
                    <input type="hidden" name="action" value="clear_stripe_sessions">
                    <input type="submit" class="button" value="<?php _e('Clear Stripe Sessions', 'awards'); ?>">
                </form>
                <p class="description"><?php _e('This will clear any stored Stripe session IDs that might be causing issues with payments.', 'awards'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Process the clear sessions action
 */
function awards_process_clear_sessions() {
    if (isset($_POST['action']) && $_POST['action'] === 'clear_stripe_sessions' && 
        isset($_POST['awards_clear_sessions_nonce']) && 
        wp_verify_nonce($_POST['awards_clear_sessions_nonce'], 'awards_clear_sessions')) {
        
        global $wpdb;
        
        // Delete all stripe_session_id meta entries
        $deleted = $wpdb->delete($wpdb->postmeta, array('meta_key' => 'stripe_session_id'));
        
        // Set a notice
        add_settings_error(
            'awards_options',
            'sessions_cleared',
            sprintf(__('%d Stripe session entries have been cleared.', 'awards'), $deleted),
            'updated'
        );
    }
}
add_action('admin_init', 'awards_process_clear_sessions');
/**
 * Register custom query vars for pagination
 * Add this to your functions.php file
 */
function awards_register_query_vars($vars) {
    $vars[] = 'paginate';
    return $vars;
}
add_filter('query_vars', 'awards_register_query_vars');
?>