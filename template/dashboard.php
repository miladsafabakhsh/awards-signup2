<div class="wp-awards wp-awards-dashboard">
    <div class="row">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="wp-awards-card position-sticky">
                <div class="wp-awards-card-body">
                    <!-- Profile Section -->
                    <div class="wp-awards-profile">
                        <!-- MPA Logo -->
                        <img src="https://minimalistphotographyawards.com/wp-content/uploads/2020/12/MPA2.png" 
                             alt="Minimalist Photography Awards" 
                             class="profile-logo">
                        
                        <?php $user = get_userdata(get_current_user_id()); ?>
                        <?php $profilephoto = get_user_meta($user->ID, 'profilephoto', true); ?>
<?php if ($profilephoto): ?>
    <?php echo wp_get_attachment_image($profilephoto, 'awards-mini-thumbnail', '', array('class'=>'img-responsive img-circle', 'height'=>'80', 'width'=>'80')); ?>
<?php endif; ?>
                        <?php if ($user->display_name): ?>
                            <h4><?php echo esc_html($user->display_name); ?></h4>
                        <?php else: ?>
                            <?php if (awards_options('dashboard')): ?>
                                <h4><a href="<?php echo esc_url(get_permalink(awards_options('dashboard'))); ?>"><?php _e('Enter your name', 'awards'); ?></a></h4>
                            <?php else: ?>
                                <h4><a href="<?php echo esc_url(site_url() . '/dashboard/?profile'); ?>"><?php _e('Enter your name', 'awards'); ?></a></h4>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($user->user_email): ?>
                            <p style="font-size: 13px; color: #7f8c8d; margin-top: -8px;"><?php echo esc_html($user->user_email); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Navigation Menu -->
                    <ul class="wp-awards-menu">
                        <li class="<?php echo !isset($_GET['dash-page']) ? 'active' : ''; ?>">
                            <a href="<?php echo get_permalink(); ?>">
                                <svg width="16" height="16" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                                </svg>
                                <?php _e('Dashboard', 'awards'); ?>
                            </a>
                        </li>
                        <li class="<?php echo (isset($_GET['dash-page']) && $_GET['dash-page'] == 'entry-add') ? 'active' : ''; ?>">
                            <a href="<?php echo get_permalink(); ?>?dash-page=entry-add">
                                <svg width="16" height="16" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/>
                                </svg>
                                <?php _e('Add Entry', 'awards'); ?>
                            </a>
                        </li>
                        <li class="<?php echo (isset($_GET['dash-page']) && $_GET['dash-page'] == 'entry-list') ? 'active' : ''; ?>">
                            <a href="<?php echo get_permalink(); ?>?dash-page=entry-list">
                                <svg width="16" height="16" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/>
                                </svg>
                                <?php _e('Your Entries', 'awards'); ?>
                            </a>
                        </li>
                        <li class="<?php echo (isset($_GET['dash-page']) && $_GET['dash-page'] == 'account') ? 'active' : ''; ?>">
                            <a href="<?php echo get_permalink(); ?>?dash-page=account">
                                <svg width="16" height="16" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                                </svg>
                                <?php _e('Settings', 'awards'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo wp_logout_url(); ?>" style="color: #e74c3c;">
                                <svg width="16" height="16" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                                </svg>
                                <?php _e('Logout', 'awards'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-8 col-lg-9">
            <?php echo do_shortcode('[wp_awards_get_messages]'); ?>
            <?php if(awards_options('email_verify_required') && !is_awards_verified_user()): ?>
                <div class="awards-result result-danger">
                    <strong><?php _e('Email Verification Required', 'awards'); ?></strong>
                    <p style="margin: 8px 0 0 0;"><?php _e('Please verify your email address. We sent a verification link to your email.', 'awards'); ?></p>
                    <a href="<?php echo site_url('?verify-award-email-resend'); ?>" class="button primary" style="margin-top: 12px; display: inline-block; background: rgba(255,255,255,0.2); border: 2px solid #fff;">
                        <?php _e('Resend Verification Email', 'awards'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <?php
            if (isset($_GET['dash-page'])) {
                switch ($_GET['dash-page']) {
                    case 'entry-add':
                        require_once('dashboard_entry_add.php');
                        break;
                    case 'entry-edit':
                        require_once('dashboard_entry_edit.php');
                        break;
                    case 'entry-list':
                        require_once('dashboard_entry_list.php');
                        break;
                    case 'entry-payment':
                        require_once('dashboard_entry_payment.php');
                        break;
                    case 'payment-result':
                        require_once('payment_result.php');
                        break;
                    case 'account':
                        require_once('dashboard_account.php');
                        break;
                    case 'mass-pay':
                        require_once('mass-payment.php');
                        break;
                }
            } else {
                require_once('dashboard_home.php');
            }
            ?>
        </div>
    </div>
</div>
