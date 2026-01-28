<?php
// Get parameters
$is_mass_payment = isset($_GET['mass']) && $_GET['mass'] === 'true';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$entry_id = isset($_GET['id']) ? $_GET['id'] : '';
$ref_id = isset($_GET['refid']) ? $_GET['refid'] : '';

// Handle different entry ID formats
if ($is_mass_payment && strpos($entry_id, '-') !== false) {
    $entry_ids = explode('-', $entry_id);
    $first_entry_id = $entry_ids[0];
} else {
    $first_entry_id = $entry_id;
}

// Get entry details for display
$entry = get_post($first_entry_id);
$entry_title = $entry ? $entry->post_title : __('Entry', 'awards');

if ($status == 'OK') {
    // Success message
    ?>
    <div class="row">
        <div class="col-12 text-center">
            <div class="alert alert-success p-5">
                <h3><?php _e('Payment successful', 'awards'); ?></h3>
                <?php if ($is_mass_payment): ?>
                    <p><?php _e('Your entries payment processed successfully.', 'awards'); ?></p>
                <?php else: ?>
                    <p><?php echo sprintf(__('Your entry "%s" payment processed successfully.', 'awards'), $entry_title); ?></p>
                <?php endif; ?>
                
                <p><?php _e('Transaction ID:', 'awards'); ?> <strong><?php echo $ref_id; ?></strong></p>
                
                <?php if ($is_mass_payment): ?>
                    <?php
                    // Get all entries in this mass payment
                    $entry_titles = array();
                    foreach ($entry_ids as $eid) {
                        $e = get_post($eid);
                        if ($e) {
                            $entry_titles[] = $e->post_title;
                        }
                    }
                    
                    if (!empty($entry_titles)):
                    ?>
                    <div class="mt-4">
                        <h4><?php _e('Entries included in this payment:', 'awards'); ?></h4>
                        <ul class="list-unstyled">
                            <?php foreach ($entry_titles as $title): ?>
                                <li><?php echo $title; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if mass invoice exists
                    $invoice_url = get_post_meta($first_entry_id, 'invoice_url', true);
                    if ($invoice_url):
                    ?>
                    <div class="mt-4">
                        <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" 
                           class="btn btn-primary button primary">
                            <?php _e('Download Invoice', 'awards'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                    // Check if invoice exists for single payment
                    $invoice_url = get_post_meta($entry_id, 'invoice_url', true);
                    if ($invoice_url):
                    ?>
                    <div class="mt-4">
                        <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" 
                           class="btn btn-primary button primary">
                            <?php _e('Download Invoice', 'awards'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="mt-5">
                    <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-list" 
                       class="btn btn-secondary button secondary">
                        <?php _e('Go to Your Entries', 'awards'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    // Error message
    ?>
    <div class="row">
        <div class="col-12 text-center">
            <div class="alert alert-danger p-5">
                <h3><?php _e('Payment failed', 'awards'); ?></h3>
                <?php if ($is_mass_payment): ?>
                    <p><?php _e('There was a problem processing your entries payment.', 'awards'); ?></p>
                <?php else: ?>
                    <p><?php echo sprintf(__('There was a problem processing your entry "%s" payment.', 'awards'), $entry_title); ?></p>
                <?php endif; ?>
                
                <div class="mt-5">
                    <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-list" 
                       class="btn btn-secondary button secondary">
                        <?php _e('Go to Your Entries', 'awards'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>