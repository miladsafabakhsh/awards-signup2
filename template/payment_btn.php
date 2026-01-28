<?php
/**
 * Payment button template
 * This displays the payment gateway options for a single entry
 */
?>
<form action="<?php echo get_the_permalink(awards_options('page_dashboard')) ?>" method="get" class="payment-form">
    <input name="payment" value="true" type="hidden">
    <input name="id" value="<?php echo get_the_ID(); ?>" type="hidden">
    
    <h3 class="text-center my-3"><?= __("Select your payment gateway:", "awards"); ?></h3>
    
    <div class="row align-items-center justify-content-center">
        <?php 
        // Check which gateways are available
        $available_gateways = array();
        
        // ZarinPal - only for Iranian users
        if (get_user_meta(get_current_user_id(), 'country', true) == 'Iran (Islamic Republic of)' && awards_options('zarinpal_merchant_id')): 
            $available_gateways['zarinpal'] = array(
                'id' => 'zarinpal',
                'name' => 'ZarinPal',
                'image' => award_plugin_url() . '/img/zarinpal-logo.png'
            );
        endif; 
        
        // PayPal
        if (awards_options('paypal_clientid') && awards_options('paypal_clientsecret')):
            $available_gateways['paypal'] = array(
                'id' => 'paypal',
                'name' => 'PayPal',
                'image' => award_plugin_url() . '/img/paypal-icon.jpg'
            );
        endif;
        
        // Stripe
        $test_mode = (awards_options('stripe_test_mode') == 'enable');
        if (($test_mode && awards_options('stripe_test_secret_key') && awards_options('stripe_test_publishable_key')) ||
            (!$test_mode && awards_options('stripe_secret_key') && awards_options('stripe_publishable_key'))):
            $available_gateways['stripe'] = array(
                'id' => 'stripe',
                'name' => 'Credit Card (Stripe)',
                'image' => award_plugin_url() . '/img/stripe-icon.jpg'
            );
        endif;
        
        // Crypto payments - NowPayment
        if (awards_options('enable_crypto_payment') == 'enable' && awards_options('nowpayment_api_key')):
            $available_gateways['now-payment'] = array(
                'id' => 'now-payment',
                'name' => 'Crypto (NowPayment)',
                'image' => award_plugin_url() . '/img/cryptocurrency-icon.png'
            );
        endif;
        
        // Crypto payments - Plisio
        if (awards_options('enable_crypto_payment') == 'enable' && awards_options('plisio_api_key')):
            $available_gateways['plisio'] = array(
                'id' => 'plisio',
                'name' => 'Crypto (Plisio)',
                'image' => award_plugin_url() . '/img/cryptocurrency-icon.png'
            );
        endif;
        
        // Display available gateways
        foreach ($available_gateways as $gateway): 
        ?>
        <div class="col-auto">
            <label for="<?php echo $gateway['id']; ?>" class="gateway-item">
                <input name="gateway" id="<?php echo $gateway['id']; ?>" value="<?php echo $gateway['id']; ?>" type="radio" 
                       <?php checked($gateway['id'] == 'paypal' || (empty($available_gateways['paypal']) && $gateway === reset($available_gateways))); ?>>
                <img src="<?php echo $gateway['image']; ?>" class="img-fluid" alt="<?php echo $gateway['name']; ?>">
                <div class="gateway-name"><?php echo $gateway['name']; ?></div>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Added spacer div for more vertical space -->
    <div class="spacer" style="height: 40px;"></div>
    
    <?php if (empty($available_gateways)): ?>
    <div class="alert alert-warning text-center my-3">
        <?php _e('No payment gateways are currently available. Please contact the administrator.', 'awards'); ?>
    </div>
    <?php else: ?>
    <div class="text-center my-5">
        <button type="submit" class="button btn btn-primary btn-payment large primary btn-lg lg"><?php _e('Go to Payment', 'awards'); ?></button>
    </div>
    <?php endif; ?>
</form>

<style>
    .gateway-item {
        display: block;
        position: relative;
        cursor: pointer;
        padding: 15px;
        border: 2px solid #e9e9e9;
        border-radius: 8px;
        margin: 10px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .gateway-item:hover, .gateway-item.selected {
        border-color: #007bff;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .gateway-item input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .gateway-item input[type="radio"]:checked + img {
        opacity: 1;
    }
    
    .gateway-item input[type="radio"]:checked ~ .gateway-name {
        font-weight: bold;
        color: #007bff;
    }
    
    .gateway-name {
        margin-top: 10px;
        font-weight: bold;
        color: #333;
    }
    
    .gateway-item:before {
        content: '';
        position: absolute;
        top: 10px;
        right: 10px;
        width: 0;
        height: 0;
        opacity: 0;
        border-radius: 50%;
        border: 2px solid #007bff;
        transition: all 0.3s ease;
    }
    
    .gateway-item input[type="radio"]:checked ~ .gateway-name:before {
        content: '✓';
        position: absolute;
        top: 10px;
        right: 10px;
        width: 20px;
        height: 20px;
        background: #007bff;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        opacity: 1;
    }
    
    /* Added style for spacing */
    .row.align-items-center.justify-content-center {
        margin-bottom: 30px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Add selected class to the checked gateway
        $('input[name="gateway"]').change(function() {
            $('.gateway-item').removeClass('selected');
            $(this).closest('.gateway-item').addClass('selected');
        });
        
        // Initialize the selected class for the default checked gateway
        $('.gateway-item input[type="radio"]:checked').closest('.gateway-item').addClass('selected');
    });
</script>