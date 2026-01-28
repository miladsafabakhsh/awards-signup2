<form action="<?php echo get_the_permalink(awards_options('page_dashboard')) ?>" method="get">
    <input name="payment" value="true" type="hidden">
    <input name="id" value="<?php echo get_the_ID(); ?>" type="hidden">
    <h3 class="text-center my-3"><?= __("Select your payment gateway:", "awards"); ?></h3>
    <div class="row align-items-center justify-content-center">
        <?php if (get_user_meta(get_current_user_id(), 'country', true) == 'Iran (Islamic Republic of)'): ?>
            <div class="col-auto">
                <label for="zarinpal" class="gateway-item">
                    <input name="gateway" id="zarinpal" value="zarinpal" type="radio">
                    <img src="<?= award_plugin_url() ?>/img/zarinpal-logo.png" class="img-fluid" alt="zarinpal">
                </label>
            </div>
        <?php endif; ?>
        <div class="col-auto">
            <label for="paypal" class="gateway-item">
                <input name="gateway" id="paypal" value="paypal" type="radio" checked>
                <img src="<?= award_plugin_url() ?>/img/paypal-icon.jpg" class="img-fluid" alt="paypal">
            </label>
        </div>
        <?php if(awards_options('enable_crypto_payment')): ?>
            <div class="col-auto">
            <label for="now-payment" class="gateway-item">
                <input name="gateway" id="now-payment" value="now-payment" type="radio">
                <img src="<?= award_plugin_url() ?>/img/cryptocurrency-icon.png" class="img-fluid" alt="cryptocurrency">
            </label>
        </div>
        <?php endif; ?>
    </div>
    <div class="text-center my-3">
        <button type="submit" class="button btn btn-primary btn-payment large primary btn-lg lg"><?php _e('Go to Payment', 'awards'); ?></button>
    </div>
</form>