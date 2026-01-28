<?php
$currentdata = wp_get_current_user();
?>
<div class="wp-awards-section-header" style="margin-bottom: 32px;">
    <h3 style="font-size: 28px; margin-bottom: 8px; border-bottom: none;">
        <svg width="24" height="24" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
        </svg>
        <?php _e('Account Settings', 'awards'); ?>
    </h3>
    <p style="color: #7f8c8d; font-size: 15px; margin: 0;"><?php _e('Manage your personal information and password', 'awards'); ?></p>
</div>

<form action="" method="post" enctype="multipart/form-data">
    <div class="row">
        <div class="col-12 col-lg-8">
            <!-- Personal Information Card -->
            <div style="background: #ffffff; border-radius: 16px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                <h4 style="font-size: 18px; margin-bottom: 20px; color: #2c3e50; padding-bottom: 12px; border-bottom: 2px solid #ecf0f1;">
                    <svg width="18" height="18" style="margin-right: 6px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                    </svg>
                    <?php _e('Personal Information', 'awards'); ?>
                </h4>
                
                <div class="row label-group">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="firstname"><?php _e('First Name', 'awards'); ?> <span class="required">*</span></label>
                            <input type="text" name="firstname" id="firstname" class="form-control"
                                   value="<?php echo (isset($_POST['firstname'])) ? esc_attr($_POST['firstname']) : esc_attr($currentdata->user_firstname); ?>" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="lastname"><?php _e('Last Name', 'awards'); ?> <span class="required">*</span></label>
                            <input type="text" name="lastname" id="lastname" class="form-control"
                                   value="<?php echo (isset($_POST['lastname'])) ? esc_attr($_POST['lastname']) : esc_attr($currentdata->user_lastname); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="email"><?php _e('Email Address', 'awards'); ?> <span class="required">*</span></label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?php echo (isset($_POST['email'])) ? esc_attr($_POST['email']) : esc_attr($currentdata->user_email); ?>" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="phone"><?php _e('Phone Number', 'awards'); ?></label>
                            <input type="tel" name="phone" id="phone" class="form-control"
                                   value="<?php echo (isset($_POST['phone'])) ? esc_attr($_POST['phone']) : esc_attr(get_user_meta($currentdata->ID, 'phone', true)); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Information Card -->
            <div style="background: #ffffff; border-radius: 16px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                <h4 style="font-size: 18px; margin-bottom: 20px; color: #2c3e50; padding-bottom: 12px; border-bottom: 2px solid #ecf0f1;">
                    <svg width="18" height="18" style="margin-right: 6px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                    </svg>
                    <?php _e('Location', 'awards'); ?>
                </h4>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="country"><?php _e('Country', 'awards'); ?> <span class="required">*</span></label>
                            <select name="country" id="country" class="form-control" required>
                                <option value="0"><?php _e('Select country...', 'awards'); ?></option>
                                <?php
                                $currentcountry = get_user_meta($currentdata->ID, 'country', true);
                                foreach (award_countries() as $country) {
                                    $selected = ($_POST['country'] == $country || $currentcountry == $country) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($country) . '" ' . $selected . '>' . esc_html($country) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="city"><?php _e('City', 'awards'); ?></label>
                            <input type="text" name="city" id="city" class="form-control"
                                   value="<?php echo (isset($_POST['city'])) ? esc_attr($_POST['city']) : esc_attr(get_user_meta($currentdata->ID, 'city', true)); ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="state"><?php _e('State / Province', 'awards'); ?></label>
                            <input type="text" name="state" id="state" class="form-control"
                                   value="<?php echo (isset($_POST['state'])) ? esc_attr($_POST['state']) : esc_attr(get_user_meta($currentdata->ID, 'state', true)); ?>">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="company"><?php _e('Company / Organization', 'awards'); ?></label>
                            <input type="text" name="company" id="company" class="form-control"
                                   value="<?php echo (isset($_POST['company'])) ? esc_attr($_POST['company']) : esc_attr(get_user_meta($currentdata->ID, 'company', true)); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social & Bio Card -->
            <div style="background: #ffffff; border-radius: 16px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                <h4 style="font-size: 18px; margin-bottom: 20px; color: #2c3e50; padding-bottom: 12px; border-bottom: 2px solid #ecf0f1;">
                    <svg width="18" height="18" style="margin-right: 6px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z"/>
                        <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"/>
                    </svg>
                    <?php _e('Social & Bio', 'awards'); ?>
                </h4>
                
                <div class="form-group">
                    <label for="instagram">
                        <svg width="16" height="16" style="margin-right: 4px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5 10a5 5 0 1110 0 5 5 0 01-10 0z" clip-rule="evenodd"/>
                        </svg>
                        <?php _e('Instagram Username', 'awards'); ?>
                    </label>
                    <input type="text" name="instagram" id="instagram" class="form-control" placeholder="username"
                           value="<?php echo (isset($_POST['instagram'])) ? esc_attr($_POST['instagram']) : esc_attr(get_user_meta($currentdata->ID, 'instagram', true)); ?>">
                    <p class="description"><?php _e('Enter your Instagram username without the @ symbol', 'awards'); ?></p>
                </div>
                
                <div class="form-group">
                    <label for="bio"><?php _e('Biography', 'awards'); ?></label>
                    <textarea name="bio" id="bio" cols="30" rows="6" class="form-control" placeholder="<?php _e('Tell us about yourself...', 'awards'); ?>"><?php echo (isset($_POST['bio'])) ? esc_textarea($_POST['bio']) : esc_textarea($currentdata->description); ?></textarea>
                    <p class="description"><?php _e('A brief description about yourself and your work', 'awards'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Password Change Card -->
        <div class="col-12 col-lg-4">
            <div class="passwordchangeform" style="position: sticky; top: 20px;">
                <h4 style="font-size: 18px; margin: 0 0 20px 0; color: #2c3e50; display: flex; align-items: center;">
                    <svg width="18" height="18" style="margin-right: 6px;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    <?php _e('Change Password', 'awards'); ?>
                </h4>
                <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 20px;"><?php _e('Leave blank if you don\'t want to change your password', 'awards'); ?></p>
                
                <div class="form-group">
                    <label for="oldpassword"><?php _e('Current Password', 'awards'); ?></label>
                    <input type="password" name="oldpassword" id="oldpassword" class="form-control" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="password"><?php _e('New Password', 'awards'); ?></label>
                    <input type="password" name="password" id="password" class="form-control" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="repassword"><?php _e('Confirm New Password', 'awards'); ?></label>
                    <input type="password" name="repassword" id="repassword" class="form-control" autocomplete="new-password">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Submit Button -->
    <div class="text-center" style="margin-top: 32px;">
        <button type="submit" name="wp-awards-submit" id="wp-awards-submit" class="btn btn-primary button submit primary" style="padding: 14px 48px; font-size: 16px;">
            <svg width="18" height="18" style="margin-right: 6px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z"/>
            </svg>
            <?php _e('Save Changes', 'awards'); ?>
        </button>
    </div>
</form>

<!-- Invoices Section -->
<div class="awards-account-section" style="margin-top: 48px;">
    <div class="wp-awards-section-header" style="margin-bottom: 24px;">
        <h3 style="font-size: 24px; margin-bottom: 8px; border-bottom: none;">
            <svg width="24" height="24" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
            </svg>
            <?php _e('My Invoices', 'awards'); ?>
        </h3>
        <p style="color: #7f8c8d; font-size: 15px; margin: 0;"><?php _e('Download your payment invoices', 'awards'); ?></p>
    </div>
    
    <?php
    // Get current user's entries with payments
    $current_user_id = get_current_user_id();
    $entries = new WP_Query(array(
        'post_type' => 'entry',
        'author' => $current_user_id,
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'payment',
                'compare' => 'EXISTS',
            ),
        ),
    ));
    
    if ($entries->have_posts()): 
    ?>
        <div style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Entry', 'awards'); ?></th>
                        <th><?php _e('Date', 'awards'); ?></th>
                        <th><?php _e('Amount', 'awards'); ?></th>
                        <th><?php _e('Status', 'awards'); ?></th>
                        <th><?php _e('Invoice', 'awards'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($entries->have_posts()): $entries->the_post(); 
                        $entry_id = get_the_ID();
                        $payment_details = get_post_meta($entry_id, 'payment', true);
                        $invoice_url = get_post_meta($entry_id, 'invoice_url', true);
                        
                        // If there's payment but no invoice, try to generate one now
                        if ($payment_details && !$invoice_url) {
                            if (!isset($payment_details['time'])) {
                                $payment_details['time'] = get_the_modified_date('Y-m-d H:i:s');
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
                        
                        $payment_date = isset($payment_details['time']) ? date_i18n(get_option('date_format'), strtotime($payment_details['time'])) : '-';
                        
                        if (isset($payment_details['Amount'])) {
                            $amount = $payment_details['Amount'];
                            $currency = get_normalized_currency();
                            $currency_symbol = $currency == 'EUR' ? '€' : ($currency == 'IRR' ? 'IRR ' : '$');
                            $formatted_amount = $currency_symbol . number_format((float)$amount, 2);
                        } else {
                            $formatted_amount = '-';
                        }
                        
                        $status = get_post_meta($entry_id, 'status', true);
                    ?>
                    <tr>
                        <td><strong><?php the_title(); ?></strong></td>
                        <td><?php echo $payment_date; ?></td>
                        <td><strong style="color: #27ae60;"><?php echo $formatted_amount; ?></strong></td>
                        <td><?php echo get_award_status_title($status, true); ?></td>
                        <td>
                            <?php if ($invoice_url): ?>
                                <a href="<?php echo esc_url(apply_filters('awards_invoice_url', $invoice_url, $entry_id)); ?>" target="_blank" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                                    <svg width="14" height="14" style="margin-right: 4px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                    <?php _e('Download', 'awards'); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #95a5a6; font-size: 13px;"><?php _e('Not available', 'awards'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="background: #f8f9fa; border-radius: 12px; padding: 32px; text-align: center; border: 2px dashed #bdc3c7;">
            <svg width="48" height="48" style="margin-bottom: 16px; opacity: 0.5;" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p style="color: #7f8c8d; font-size: 16px; margin: 0;"><?php _e('You don\'t have any paid entries yet.', 'awards'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php wp_reset_postdata(); ?>
</div>
