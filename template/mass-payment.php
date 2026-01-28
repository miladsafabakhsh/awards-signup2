<div class="row">
    <div class="col-12">
        <h3><?php _e('Mass payment for these entries:', 'awards'); ?></h3>
        <?php
        $total_price = 0;
        $total_price_copon = 0;
        $payment_ids = array();
        ?>
        <?php while ($selected_posts->have_posts()): $selected_posts->the_post(); ?>
            <?php $payment_ids[] = get_the_ID(); ?>
            <div class="row no-gutters mb-3 entry-item">
                <?php the_post_thumbnail('thumbnail', array('class' => 'img-fluid img-thumbnail mr-2', 'style' => 'width:70px;')); ?>
                <div class="col">
                    <h4 class="mb-2"><?php the_title(); ?></h4>
                    <div class="text-muted"><?php echo get_award_entry_total_fee(get_the_ID()); ?></div>
                </div>
            </div>
            <?php
            $total_price += get_award_entry_total_fee(get_the_ID(), false);
            if (get_copon_session()) {
                $total_price_copon += get_award_entry_total_fee_with_copon(get_the_ID(), false);
            }
            ?>
        <?php endwhile; ?>
        <?php $payment_ids = implode(',', $payment_ids); ?>
        <br>
        <div class="text-center">
            <h2 class="text-danger"><?php echo __('Total:', 'awards') . ' ' . award_price_format($total_price) . ' ' . get_awards_price_symbol(); ?></h2>
        </div>
        <?php if (awards_options('copon_enable')): ?>
            <br>
            <form action="" method="post" class="coupon-form">
                <div class="row">
                    <div class="col-12 col-md-8 col-lg-7 mx-auto">
                        <div class="bg-light rounded p-3 my-3">
                            <h4><?php _e('Discount coupon', 'awards'); ?></h4>
                            <p><?php _e('Have a discount code? Enter it:', 'awards'); ?></p>
                            <div class="row align-items-center no-gutters">
                                <div class="col">
                                    <input name="copon" id="copon" type="text" class="form-control px-1"
                                           placeholder="<?php _e('Enter your coupon code...', 'awards'); ?>">
                                </div>
                                <div class="col-auto">
                                    <input type="submit" class="btn button btn-primary btn-secondary secondary"
                                           value="<?php _e('Apply', 'awards'); ?>">
                                </div>
                            </div>
                        </div>
                        <?php if (isset($total_price_copon) && $total_price_copon): ?>
                            <div class="text-center"><?php echo sprintf(__('Coupon Code: "%s"', 'awards'), get_copon_session()['copon']) ?>
                                <a href="?dash-page=mass-pay&remove-copon-code=true&selected=<?php echo implode(',', $payment_ids); ?>"
                                   class="small">(<?php _e('remove', 'awards'); ?>)</a></div>
                            <h2 class="text-danger text-center">
                                <?php echo sprintf(__('New price: %s %s'), award_price_format($total_price_copon), get_awards_price_symbol()); ?>
                            </h2>
                            <br>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center payment-gateways-container">
            <h3 class="text-center my-3"><?= __("Select your payment gateway:", "awards"); ?></h3>
            <div class="row align-items-center justify-content-center gateways-list">
                <?php 
                // Check which gateways are available
                $available_gateways = array();
                
                // ZarinPal - only for Iranian users
                if (get_user_meta(get_current_user_id(), 'country', true) == 'Iran (Islamic Republic of)' && awards_options('zarinpal_merchant_id')): 
                    $available_gateways['zarinpal'] = array(
                        'id' => 'zarinpal',
                        'name' => 'ZarinPal',
                        'image' => award_plugin_url() . '/img/zarinpal-logo.png',
                        'url' => get_the_permalink(awards_options('page_dashboard')) . '?payment=true&mass=true&gateway=zarinpal&id=' . $payment_ids
                    );
                endif; 
                
                // PayPal
                if (awards_options('paypal_clientid') && awards_options('paypal_clientsecret')):
                    $available_gateways['paypal'] = array(
                        'id' => 'paypal',
                        'name' => 'PayPal',
                        'image' => award_plugin_url() . '/img/paypal-icon.jpg',
                        'url' => get_the_permalink(awards_options('page_dashboard')) . '?payment=true&mass=true&gateway=paypal&id=' . $payment_ids
                    );
                endif;
                
                // Stripe
                $test_mode = (awards_options('stripe_test_mode') == 'enable');
                if (($test_mode && awards_options('stripe_test_secret_key') && awards_options('stripe_test_publishable_key')) ||
                    (!$test_mode && awards_options('stripe_secret_key') && awards_options('stripe_publishable_key'))):
                    $available_gateways['stripe'] = array(
                        'id' => 'stripe',
                        'name' => 'Credit Card (Stripe)',
                        'image' => award_plugin_url() . '/img/stripe-icon.jpg',
                        'url' => site_url('/wp-content/plugins/awards-signup2/stripe-payment.php?id=' . $payment_ids),
                    );
                endif;
                
                // Crypto payments - NowPayment
                if (awards_options('enable_crypto_payment') == 'enable' && awards_options('nowpayment_api_key')):
                    $available_gateways['now-payment'] = array(
                        'id' => 'now-payment',
                        'name' => 'Crypto (NowPayment)',
                        'image' => award_plugin_url() . '/img/cryptocurrency-icon.png',
                        'url' => get_the_permalink(awards_options('page_dashboard')) . '?payment=true&mass=true&gateway=now-payment&id=' . $payment_ids
                    );
                endif;
                
                // Crypto payments - Plisio
                if (awards_options('enable_crypto_payment') == 'enable' && awards_options('plisio_api_key')):
                    $available_gateways['plisio'] = array(
                        'id' => 'plisio',
                        'name' => 'Crypto (Plisio)',
                        'image' => award_plugin_url() . '/img/cryptocurrency-icon.png',
                        'url' => get_the_permalink(awards_options('page_dashboard')) . '?payment=true&mass=true&gateway=plisio&id=' . $payment_ids
                    );
                endif;
                
                // Display available gateways
                foreach ($available_gateways as $gateway): 
                ?>
                <div class="col-auto">
                    <a href="<?php echo $gateway['url']; ?>" class="gateway-item-link" data-gateway="<?php echo $gateway['id']; ?>">
                        <img src="<?php echo $gateway['image']; ?>" class="img-fluid" alt="<?php echo $gateway['name']; ?>">
                        <div class="gateway-name"><?php echo $gateway['name']; ?></div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($available_gateways)): ?>
            <div class="alert alert-warning text-center my-3">
                <?php _e('No payment gateways are currently available. Please contact the administrator.', 'awards'); ?>
            </div>
            <?php endif; ?>
            
            <style>
                .entry-item {
                    background-color: #f9f9f9;
                    border-radius: 5px;
                    padding: 10px;
                    margin-bottom: 10px;
                }
                .gateway-item-link {
                    display: block;
                    padding: 15px;
                    border: 2px solid #e9e9e9;
                    border-radius: 8px;
                    margin: 10px;
                    text-align: center;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    color: inherit;
                }
                .gateway-item-link:hover {
                    border-color: #007bff;
                    transform: translateY(-3px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                    text-decoration: none;
                }
                .gateway-name {
                    margin-top: 10px;
                    font-weight: bold;
                    color: #333;
                }
            </style>
            
            <script>
                jQuery(document).ready(function($) {
                    // Add a loading indicator when a gateway is clicked
                    $('.gateway-item-link').on('click', function(e) {
                        // Show loading status
                        $(this).append('<div class="spinner-border spinner-border-sm ml-2" role="status"><span class="sr-only">Loading...</span></div>');
                    });
                });
            </script>
        </div>
    </div>
</div>