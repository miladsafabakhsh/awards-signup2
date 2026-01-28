<?php
/*
Plugin Name: Awards
Plugin URI: http://www.novinvision.com
Description: view our portfolio on novinvision.com
Version: 1.3.6
Author: Mehran Ranji
Author URI: http://www.mehranranji.ir
License: All right reserved for Mehran Ranji and novinvision
Text Domain: awards
*/

require('functions.php');
include_once('post-type.php');
require_once 'inc/NowPayment.php';
require_once 'inc/PlisioPayment.php';

// High-Res Upload System
require_once 'inc/highres-upload-handler.php';
require_once 'admin/admin-highres-reminders.php';

//require_once('inc/PayPalAP.php');

class wp_awards
{

    public static function admin_init()
    {
        register_setting('awards_options', 'awards_options', array('wp_awards', 'config_validate'));
    }

    public static function wp_init()
    {
        add_image_size('awards-mini-thumbnail', 70, 70, true);
        load_plugin_textdomain('awards', false, basename(dirname(__FILE__)) . '/languages');
    }

    public static function basewp_init()
    {
        if (isset($_GET['award_paypal_ipn']) && $_GET['award_paypal_ipn'] == 1) {
            self::dashboard_paypal_notify();
        }
    }

    public static function dist_admin($hook)
    {

        if ('edit.php' != $hook || 'profile.php' != $hook) {
            return;
        }

        wp_enqueue_script('bootstrap', plugin_dir_url(__FILE__) . 'css/bootstrap.min.css', array(), '4.3.1');
    }

    public static function dist()
    {

        wp_register_style('awards-bootstrap-grid', plugin_dir_url(__FILE__) . 'css/bootstrap-grid.min.css');
        wp_register_style('awards', plugin_dir_url(__FILE__) . 'css/awards.css');
        wp_register_script('awards', plugin_dir_url(__FILE__) . 'js/awards.js', array('jquery'));
        $translation_array = array(
            'siteurl' => site_url(),
            'basefee_single' => get_award_basefee('single'),
            'basefee_series' => get_award_basefee('series'),
        );
        wp_localize_script('awards', 'awards', $translation_array);

        wp_enqueue_script('awards');
        if (awards_options('bootstrap_grid'))
            wp_enqueue_style('awards-bootstrap-grid');
        wp_enqueue_style('awards');
    }

    public static function config()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Plugin Settings', 'awards') ?></h1>
            <br/>
            <?php if (true === $_REQUEST['settings-updated']) : ?>
                <div class="updated fade"><p>
                        <strong><?php _e('تنظیمات با موفقیت ذخیره شدند', 'awards'); ?></strong>
                    </p></div>
            <?php endif; ?>
            <form action="options.php" method="post">
                <?php settings_fields('awards_options'); ?>
                <?php do_settings_sections('awards_options'); ?>
                <h2>Config</h2>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th><?php _e('Enable add entry:', 'awards'); ?></th>
                        <td>
                            <label for="enable_add_entry">
                                <input type="checkbox" id="enable_add_entry"
                                       name="awards_options[enable_add_entry]" <?php echo (awards_options('enable_add_entry') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Add new entry required email verification', 'awards'); ?></th>
                        <td>
                            <label for="email_verification">
                                <input type="checkbox" id="email_verification"
                                       name="awards_options[email_verify_required]" <?php echo (awards_options('email_verify_required') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Yes', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Verification email text:', 'awards'); ?></th>
                        <td>
                            <?php wp_editor(awards_options('verification_email_text'), 'verification_email_text', array(
                                'textarea_name' => 'awards_options[verification_email_text]',
                                'textarea_rows' => 7,
                            )); ?>
                            <div style="margin-top: 15px"><?php _e('use <code>{verify_link}</code> for enter verify link'); ?></div>
                        </td>
                    </tr>
<tr>
    <th><?php _e('Entry approval email:', 'awards'); ?></th>
    <td>
        <?php wp_editor(awards_options('entry_approval_mail_text'), 'entry_approval_mail_text', array(
            'textarea_name' => 'awards_options[entry_approval_mail_text]',
            'textarea_rows' => 7,
        )); ?>
        <div style="margin-top: 15px"><?php _e('use <code>{entry_title}</code> for enter entry title'); ?></div>
        <br>
        <div><?php _e('use <code>{user_firstname}</code> && <code>{user_lastname}</code> for display user name'); ?></div>
    </td>
</tr>
                    <tr>
                        <th><?php _e('Add entry e-mail:', 'awards'); ?></th>
                        <td>
                            <?php wp_editor(awards_options('entry_added_success_fully_mail_text'), 'entry_added_success_fully_mail_text', array(
                                'textarea_name' => 'awards_options[entry_added_success_fully_mail_text]',
                                'textarea_rows' => 7,
                            )); ?>
                            <div style="margin-top: 15px"><?php _e('use <code>{entry_title}</code> for enter entry title'); ?></div>
                            <br>
                            <div><?php _e('use <code>{user_firstname}</code> && <code>{user_lastname}</code> for display user name'); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('payment entry success e-mail:', 'awards'); ?></th>
                        <td>
                            <?php wp_editor(awards_options('entry_payment_success_fully_mail_text'), 'entry_payment_success_fully_mail_text', array(
                                'textarea_name' => 'awards_options[entry_payment_success_fully_mail_text]',
                                'textarea_rows' => 7,
                            )); ?>
                            <div style="margin-top: 15px"><?php _e('use <code>{entry_title}</code> for enter entry title'); ?></div>
                            <br>
                            <div><?php _e('use <code>{user_firstname}</code> && <code>{user_lastname}</code> for display user name'); ?></div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Load Bootstrap grid:', 'awards'); ?></th>
                        <td>
                            <label for="bootstrap_grid">
                                <input type="checkbox" id="bootstrap_grid"
                                       name="awards_options[bootstrap_grid]" <?php echo (awards_options('bootstrap_grid') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('ZarinPal Mechant ID', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[zarinpal_merchant_id]"
                                   id="awards_options[zarinpal_merchant_id]"
                                   value="<?php echo awards_options('zarinpal_merchant_id'); ?>" class="reqular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Crypto Payment Enable', 'awards'); ?></th>
                        <td>
                            <label for="enable_crypto_payment">
                                <input type="checkbox" id="enable_crypto_payment"
                                       name="awards_options[enable_crypto_payment]" <?php echo (awards_options('enable_crypto_payment') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('PLISIO API', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[plisio_api_key]"
                                   id="awards_options[plisio_api_key]"
                                   value="<?php echo awards_options('plisio_api_key'); ?>" class="reqular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('PLISIO currencies', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[plisio_currencies]"
                                   id="awards_options[plisio_currencies]"
                                   value="<?php echo awards_options('plisio_currencies'); ?>" class="reqular-text">
                            <div>seperate with comma , without space</div>
                        </td>
                    </tr>
<tr>
    <th><?php _e('Stripe API Keys', 'awards'); ?></th>
    <td>
        <p class="description"><?php _e('Enter your Stripe API keys to enable credit card payments.', 'awards'); ?></p>
        <p class="description"><?php _e('Get your API keys from your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a>.', 'awards'); ?></p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Live Secret Key', 'awards'); ?></th>
    <td>
        <input type="text" name="awards_options[stripe_secret_key]"
               id="awards_options[stripe_secret_key]"
               value="<?php echo awards_options('stripe_secret_key'); ?>" class="regular-text">
        <p class="description"><?php _e('Starts with sk_live_', 'awards'); ?></p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Live Publishable Key', 'awards'); ?></th>
    <td>
        <input type="text" name="awards_options[stripe_publishable_key]"
               id="awards_options[stripe_publishable_key]"
               value="<?php echo awards_options('stripe_publishable_key'); ?>" class="regular-text">
        <p class="description"><?php _e('Starts with pk_live_', 'awards'); ?></p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Test Secret Key', 'awards'); ?></th>
    <td>
        <input type="text" name="awards_options[stripe_test_secret_key]"
               id="awards_options[stripe_test_secret_key]"
               value="<?php echo awards_options('stripe_test_secret_key'); ?>" class="regular-text">
        <p class="description"><?php _e('Starts with sk_test_', 'awards'); ?></p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Test Publishable Key', 'awards'); ?></th>
    <td>
        <input type="text" name="awards_options[stripe_test_publishable_key]"
               id="awards_options[stripe_test_publishable_key]"
               value="<?php echo awards_options('stripe_test_publishable_key'); ?>" class="regular-text">
        <p class="description"><?php _e('Starts with pk_test_', 'awards'); ?></p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Test mode', 'awards'); ?></th>
    <td>
        <label for="stripe_test_mode">
            <input type="checkbox" id="stripe_test_mode"
                   name="awards_options[stripe_test_mode]" <?php echo (awards_options('stripe_test_mode') == 'enable') ? 'checked' : ''; ?>
                   value="enable"> <?php _e('Enable', 'awards'); ?>
        </label>
        <p class="description"><?php _e('When enabled, the plugin will use your test API keys instead of live keys.', 'awards'); ?></p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Webhook Secret', 'awards'); ?></th>
    <td>
        <input type="text" name="awards_options[stripe_webhook_secret]"
               id="awards_options[stripe_webhook_secret]"
               value="<?php echo awards_options('stripe_webhook_secret'); ?>" class="regular-text">
        <p class="description">
            <?php 
            $webhook_url = site_url('/wp-content/plugins/awards-signup2/stripe-webhook.php');
            printf(
                __('Set up a webhook in your Stripe dashboard pointing to: <code>%s</code>', 'awards'),
                $webhook_url
            ); 
            ?>
        </p>
        <p class="description">
            <?php _e('Events to listen for: checkout.session.completed, payment_intent.payment_failed', 'awards'); ?>
        </p>
    </td>
</tr>
<tr>
    <th><?php _e('Stripe Debugging', 'awards'); ?></th>
    <td>
        <label for="stripe_debug_mode">
            <input type="checkbox" id="stripe_debug_mode"
                   name="awards_options[stripe_debug_mode]" <?php echo (awards_options('stripe_debug_mode') == 'enable') ? 'checked' : ''; ?>
                   value="enable"> <?php _e('Enable Debug Mode', 'awards'); ?>
        </label>
        <p class="description"><?php _e('When enabled, detailed logs will be written to the error log.', 'awards'); ?></p>
    </td>
</tr>
                    <tr>
    <th><?php _e('NowPayment API', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[nowpayment_api_key]"
                                   id="awards_options[nowpayment_api_key]"
                                   value="<?php echo awards_options('nowpayment_api_key'); ?>" class="reqular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('NowPayment currencies', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[nowpayment_currencies]"
                                   id="awards_options[nowpayment_currencies]"
                                   value="<?php echo awards_options('nowpayment_currencies'); ?>" class="reqular-text">
                            <div>seperate with comma , without space</div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('NowPayment Sandbox mode', 'awards'); ?></th>
                        <td>
                            <label for="nowpayment_test_mode">
                                <input type="checkbox" id="nowpayment_test_mode"
                                       name="awards_options[nowpayment_test]" <?php echo (awards_options('nowpayment_test') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('NowPayment API Sandbox', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[nowpayment_api_key_sandbox]"
                                   id="awards_options[nowpayment_api_key_sandbox]"
                                   value="<?php echo awards_options('nowpayment_api_key_sandbox'); ?>"
                                   class="reqular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('ZarinPal Test mode', 'awards'); ?></th>
                        <td>
                            <label for="zarinpal_test_mode">
                                <input type="checkbox" id="zarinpal_test_mode"
                                       name="awards_options[zarinpal_test]" <?php echo (awards_options('zarinpal_test') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Paypal App Client ID', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[paypal_clientid]"
                                   id="awards_options[paypal_clientid]"
                                   value="<?php echo awards_options('paypal_clientid'); ?>" class="reqular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Paypal App Client Secret ID', 'awards'); ?></th>
                        <td>
                            <input type="text" name="awards_options[paypal_clientsecret]"
                                   id="awards_options[paypal_clientsecret]"
                                   value="<?php echo awards_options('paypal_clientsecret'); ?>" class="reqular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Paypal Test mode', 'awards'); ?></th>
                        <td>
                            <label for="paypal_test_mode">
                                <input type="checkbox" id="paypal_test_mode"
                                       name="awards_options[paypal_test_mode]" <?php echo (awards_options('paypal_test_mode') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Enable Discount copons:', 'awards'); ?></th>
                        <td>
                            <label for="copon_enable">
                                <input type="checkbox" id="copon_enable"
                                       name="awards_options[copon_enable]" <?php echo (awards_options('copon_enable') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('Level of expertise', 'awards'); ?></th>
                        <td>
                            <label for="level_expertise_enable">
                                <input type="radio" id="level_expertise_enable"
                                       name="awards_options[level_expertise]" <?php echo (awards_options('level_expertise') == 'enable' || !awards_options('level_expertise')) ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Enable', 'awards'); ?>
                            </label>
                            <br>
                            <br>
                            <label for="level_expertise_disable">
                                <input type="radio" id="level_expertise_disable"
                                       name="awards_options[level_expertise]" <?php echo (awards_options('level_expertise') == 'disable') ? 'checked' : ''; ?>
                                       value="disable"> <?php _e('Disable', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        
<tr>
    <th><?php _e('Default Currency', 'awards'); ?></th>
    <td>
        <label for="currency_usd">
            <input type="radio" id="currency_usd"
                   name="awards_options[currency]" <?php echo (awards_options('currency') == 'usd' || !awards_options('currency')) ? 'checked' : ''; ?>
                   value="usd"> <?php _e('USD', 'awards'); ?>
        </label>
        <br>
        <br>
        <label for="currency_eur">
            <input type="radio" id="currency_eur"
                   name="awards_options[currency]" <?php echo (awards_options('currency') == 'eur') ? 'checked' : ''; ?>
                   value="eur"> <?php _e('Euro', 'awards'); ?>
        </label>
        <p class="description"><?php _e('Select your default currency. This affects pricing and payment processing.', 'awards'); ?></p>
    </td>
</tr>
                    <tr>
                        <th><?php _e('User can select entry type:', 'awards'); ?></th>
                        <td>
                            <label for="select_entry_type">
                                <input type="checkbox" id="select_entry_type"
                                       name="awards_options[select_entry_type]" <?php echo (awards_options('select_entry_type') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Yes', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Default entry type', 'awards'); ?></th>
                        <td>
                            <label for="default_entry_type_single">
                                <input type="radio" id="default_entry_type_single"
                                       name="awards_options[default_entry_type]" <?php echo (awards_options('default_entry_type') == 'single' || !awards_options('default_entry_type')) ? 'checked' : ''; ?>
                                       value="single"> <?php _e('Single', 'awards'); ?>
                            </label>
                            <br>
                            <br>
                            <label for="default_entry_type_series">
                                <input type="radio" id="default_entry_type_series"
                                       name="awards_options[default_entry_type]" <?php echo (awards_options('default_entry_type') == 'series') ? 'checked' : ''; ?>
                                       value="series"> <?php _e('Series', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('User can select category:', 'awards'); ?></th>
                        <td>
                            <label for="select_category_enable">
                                <input type="checkbox" id="select_category_enable"
                                       name="awards_options[select_category_enable]" <?php echo (awards_options('select_category_enable') == 'enable') ? 'checked' : ''; ?>
                                       value="enable"> <?php _e('Yes', 'awards'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Default Category:', 'awards'); ?></th>
                        <td>
                            <?php $entry_Cats = get_terms('entrycat', array(
                                'hide_empty' => false,
                            )); ?>
                            <select name="awards_options[default_category]" id="default_category">
                                <?php if ($entry_Cats) foreach ($entry_Cats as $term): ?>
                                    <option value="<?= $term->term_id ?>"><?= $term->name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Maximum images number', 'awards'); ?></th>
                        <td>
                            <label for="max_image_upload">
                                <input type="number" min="1" max="15" id="max_image_upload"
                                       name="awards_options[max_image_upload]"
                                       value="<?php echo awards_options('max_image_upload'); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Maximum user entries', 'awards'); ?></th>
                        <td>
                            <label for="max_user_entries">
                                <input type="number" min="1" id="max_user_entries"
                                       name="awards_options[max_user_entries]"
                                       value="<?php echo awards_options('max_user_entries'); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Single type base price', 'awards'); ?></th>
                        <td>
                            <input type="number" min="0" name="awards_options[baseprice_single][usd]"
                                   id="awards_options[baseprice_single][usd]"
                                   value="<?php echo (awards_options('baseprice_single')) ? awards_options('baseprice_single')['usd'] : ''; ?>"
                                   class="reqular-text"><?php _e('USD', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[baseprice_single][euro]"
                                   id="awards_options[baseprice_single][euro]"
                                   value="<?php echo (awards_options('baseprice_single')) ? awards_options('baseprice_single')['euro'] : ''; ?>"
                                   class="reqular-text"><?php _e('Euro', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[baseprice_single][rial]"
                                   id="awards_options[baseprice_single][rial]"
                                   value="<?php echo (awards_options('baseprice_single')) ? awards_options('baseprice_single')['rial'] : ''; ?>"
                                   class="reqular-text"><?php _e('Rial', 'awards'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Series type base price', 'awards'); ?></th>
                        <td>
                            <input type="number" min="0" name="awards_options[baseprice_series][usd]"
                                   id="awards_options[baseprice_series][usd]"
                                   value="<?php echo (awards_options('baseprice_series')) ? awards_options('baseprice_series')['usd'] : ''; ?>"
                                   class="reqular-text"><?php _e('USD', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[baseprice_series][euro]"
                                   id="awards_options[baseprice_series][euro]"
                                   value="<?php echo (awards_options('baseprice_series')) ? awards_options('baseprice_series')['euro'] : ''; ?>"
                                   class="reqular-text"><?php _e('Euro', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[baseprice_series][rial]"
                                   id="awards_options[baseprice_series][rial]"
                                   value="<?php echo (awards_options('baseprice_series')) ? awards_options('baseprice_series')['rial'] : ''; ?>"
                                   class="reqular-text"><?php _e('Rial', 'awards'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Additional Category Price Single', 'awards'); ?></th>
                        <td>
                            <input type="number" min="0" name="awards_options[add_category][single][usd]"
                                   id="awards_options[add_category][single][usd]"
                                   value="<?php echo (awards_options('add_category')) ? awards_options('add_category')['single']['usd'] : ''; ?>"
                                   class="reqular-text"><?php _e('USD', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[add_category][single][euro]"
                                   id="awards_options[add_category][single][euro]"
                                   value="<?php echo (awards_options('add_category')) ? awards_options('add_category')['single']['euro'] : ''; ?>"
                                   class="reqular-text"><?php _e('Euro', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[add_category][single][rial]"
                                   id="awards_options[add_category][single][rial]"
                                   value="<?php echo (awards_options('add_category')) ? awards_options('add_category')['single']['rial'] : ''; ?>"
                                   class="reqular-text"><?php _e('Rial', 'awards'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Additional Category Price Series', 'awards'); ?></th>
                        <td>
                            <input type="number" min="0" name="awards_options[add_category][series][usd]"
                                   id="awards_options[add_category][series][usd]"
                                   value="<?php echo (awards_options('add_category')) ? awards_options('add_category')['series']['usd'] : ''; ?>"
                                   class="reqular-text"><?php _e('USD', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[add_category][series][euro]"
                                   id="awards_options[add_category][series][euro]"
                                   value="<?php echo (awards_options('add_category')) ? awards_options('add_category')['series']['euro'] : ''; ?>"
                                   class="reqular-text"><?php _e('Euro', 'awards'); ?>
                            <br>
                            <input type="number" min="0" name="awards_options[add_category][series][rial]"
                                   id="awards_options[add_category][series][rial]"
                                   value="<?php echo (awards_options('add_category')) ? awards_options('add_category')['series']['rial'] : ''; ?>"
                                   class="reqular-text"><?php _e('Rial', 'awards'); ?>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <br>
                <h2>Pages</h2>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th><?php _e('Dashboard Body:', 'awards'); ?></th>
                        <td>
                            <?php wp_editor(awards_options('dashboard_text'), 'my_editor_dashboard_text', array(
                                'textarea_name' => 'awards_options[dashboard_text]',
                            )); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Dashboard:', 'awards'); ?></th>
                        <td>
                            <?php
                            $pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1));
                            if ($pages):
                                echo '<select name="awards_options[page_dashboard]">';
                                foreach ($pages as $page) {
                                    echo '<option value="' . $page->ID . '" ' . ((awards_options('page_dashboard') == $page->ID) ? 'selected' : '') . '>' . $page->post_title . '</option>';
                                }
                                echo '</select>';
                            else:
                                echo '<h4>' . __('no page available', 'awards') . '</h4>';
                            endif;
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Login:', 'awards'); ?></th>
                        <td>
                            <?php
                            $pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1));
                            if ($pages):
                                echo '<select name="awards_options[page_login]">';
                                foreach ($pages as $page) {
                                    echo '<option value="' . $page->ID . '" ' . ((awards_options('page_login') == $page->ID) ? 'selected' : '') . '>' . $page->post_title . '</option>';
                                }
                                echo '</select>';
                            else:
                                echo '<h4>' . __('no page available', 'awards') . '</h4>';
                            endif;
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Register:', 'awards'); ?></th>
                        <td>
                            <?php
                            $pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1));
                            if ($pages):
                                echo '<select name="awards_options[page_register]">';
                                foreach ($pages as $page) {
                                    echo '<option value="' . $page->ID . '" ' . ((awards_options('page_register') == $page->ID) ? 'selected' : '') . '>' . $page->post_title . '</option>';
                                }
                                echo '</select>';
                            else:
                                echo '<h4>' . __('no page available', 'awards') . '</h4>';
                            endif;
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Terms & Conditions:', 'awards'); ?></th>
                        <td>
                            <?php
                            $pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1));
                            if ($pages):
                                echo '<select name="awards_options[page_terms]">';
                                foreach ($pages as $page) {
                                    echo '<option value="' . $page->ID . '" ' . ((awards_options('page_terms') == $page->ID) ? 'selected' : '') . '>' . $page->post_title . '</option>';
                                }
                                echo '</select>';
                            else:
                                echo '<h4>' . __('no page available', 'awards') . '</h4>';
                            endif;
                            ?>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <br>
                <h2>Actions</h2>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th><?php _e('Download:', 'awards'); ?></th>
                        <td>
                            <div><a href="<?php echo site_url('/wp-admin/?download-awards-users=true') ?>"
                                    target="_blank"
                                    class="button submit"><?php _e('Download Users List', 'awards'); ?></a></div>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <br/>
                <h2>Short Codes</h2>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th><?php _e('Dashboard Page', 'awards'); ?></th>
                        <td><code>[wp_awards_dashboard]</code></td>
                    </tr>
                    <tr>
                        <th><?php _e('Login Page', 'awards'); ?></th>
                        <td><code>[wp_awards_login_form]</code></td>
                    </tr>
                    <tr>
                        <th><?php _e('Register Page', 'awards'); ?></th>
                        <td><code>[wp_awards_register_form]</code></td>
                    </tr>
                    </tbody>
                </table>
                <br/>
                <p><?php _e('Developed By <a href="https://www.novinvision.com" target="_blank">NovinVision</a>', 'awards'); ?></p>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo site_url('/wp-admin/?download-entries=true') ?>" target="_blank">
                <h2>Download Entries</h2>
                <div>
                    <div>status:</div>
                    <select name="status" id="status">
                        <option value=""><?php _e('-- Unselected --', 'awards'); ?></option>
                        <option value="pending_payment"><?php _e('Pending Payment', 'awards'); ?></option>
                        <option value="pending_review"><?php _e('Pending Review', 'awards'); ?></option>
                        <option value="approved"><?php _e('Approved', 'awards'); ?></option>
                        <option value="rejected"><?php _e('Rejected', 'awards'); ?></option>
                        <option value="winner"><?php _e('Winner', 'awards'); ?></option>
                    </select>
                    <br>
                    <div>Place:</div>
                    <select name="status" id="status">
                        <option value=""><?php _e('-- Unselected --', 'awards'); ?></option>
                        <option value="none"><?php _e('None', 'awards'); ?></option>
                        <option value="firstplace"><?php _e('First place', 'awards'); ?></option>
                        <option value="secondplace"><?php _e('second place', 'awards'); ?></option>
                        <option value="thirplace"><?php _e('third place', 'awards'); ?></option>
                        <option value="honorable"><?php _e('Honorable mention', 'awards'); ?></option>
                    </select>
                    <br>
                    <div>payment status:</div>
                    <select name="payment" id="payment">
                        <option value=""><?php _e('-- Unselected --', 'awards'); ?></option>
                        <option value="paymented"><?php _e('Paymented', 'awards'); ?></option>
                    </select>
                    <br>
                    <div>User:</div>
                    <select name="user" id="user">
                        <option value=""><?php _e('-- Unselected --', 'awards'); ?></option>
                        <?php
                        $users = get_users();
                        foreach ($users as $user):
                            ?>
                            <option value="<?php echo $user->ID; ?>"><?php echo $user->display_name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                </div>
                <br>
                <input type="submit" class="button" value="download entries">
            </form>
        </div>
        <?php
    }

    public function config_validate($input)
    {

//        $input['server'] = wp_filter_post_kses($_POST['tracktrace']['server']);
//        $input['dbport'] = wp_filter_post_kses($_POST['tracktrace']['dbport']);
//        $input['dbname'] = wp_filter_post_kses($_POST['tracktrace']['dbname']);
//        $input['dbusername'] = wp_filter_post_kses($_POST['tracktrace']['dbusername']);
//        $input['dbpass'] = wp_filter_post_kses($_POST['tracktrace']['dbpass']);
//        $input['viewname'] = wp_filter_post_kses($_POST['tracktrace']['viewname']);
//        $input['connection_type'] = wp_filter_post_kses($_POST['tracktrace']['connection_type']);
        $input['dashboardbody'] = wp_filter_post_kses($_POST['tracktrace']['connection_type']);

        return $input;
    }

    public function login()
    {

        if (!awards_options('page_dashboard') || !awards_options('page_login') || !awards_options('page_register')) {
            die(__('first config awards settings', 'awards'));
        }

        if (is_user_logged_in()) {
            if (awards_options('dashboard'))
                wp_redirect(esc_url(get_permalink(awards_options('dashboard'))));
            else
                wp_redirect(site_url());
        }

        if (isset($_POST['login-submit'])) {

            if (isset($_GET['redirect'])) {
                $redirect = esc_url($_GET['redirect']);
            } else {
                $redirect = esc_url(get_permalink(awards_options('dashboard')));
            }

            $dologin = self::dologin($_POST['username'], $_POST['password'], $redirect);

            if (is_wp_error($dologin)) {
                awards_setmessage($dologin->get_error_message(), 'danger');
            }

        }

        print_r(awards_getmessage());

        require_once('template/login.php');

    }

    public function dologin($username, $password, $redirecturl)
    {

        $error = new WP_Error();

        if (!wp_verify_nonce($_POST['_nonce_login'], '_nonce_login')) {
            $error->add('none_invalid', __('None Field invalid value', 'awards'));
            return $error;
        };

        if (empty($username) || empty($password)) {
            $error->add('enter_username_password', __('Enter username and password', 'awards'));
            return $error;
        }

        global $wpdb;

        $username = $wpdb->prepare($username, array());
        $password = $wpdb->prepare($password, array());
        $rememberme = true;

        $logindata = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $rememberme,
        );

        $loginresult = wp_signon($logindata, false);

        if (is_wp_error($loginresult)) {
            return $loginresult;
        } else {

//            if(is_super_admin($loginresult->ID)) wp_logout();

            wp_set_current_user($loginresult->ID, $username);
            do_action('set_current_user');
            wp_redirect(esc_url($redirecturl));
            return true;
        }

    }

    public static function login_url($login_url, $redirect, $force_reauth)
    {

        if (!awards_options('page_login')) return false;

        return get_permalink(awards_options('page_login')) . '?redirect=' . $redirect;
    }

    public function register_url()
    {
        if (!awards_options('page_register')) return false;
        return get_permalink(awards_options('page_register'));
    }

    public function register()
    {

        if (!awards_options('page_dashboard') || !awards_options('page_login') || !awards_options('page_register')) {
            die(__('first config awards settings', 'awards'));
        }

        if (is_user_logged_in()) {
            if (awards_options('dashboard'))
                wp_redirect(esc_url(get_permalink(awards_options('dashboard'))));
            else
                wp_redirect(site_url());
        }

        if (isset($_POST['register-submit'])) {

            $doregister = self::doregister($_POST['email'], $_POST['password'], $_POST['repassword']);
            if (is_wp_error($doregister)) {
                awards_setmessage($doregister->get_error_message(), 'danger');
            } else {
                if (awards_options('dashboard'))
                    wp_redirect(esc_url(get_permalink(awards_options('dashboard'))));
                else
                    wp_redirect(site_url());
            }

        }

        print_r(awards_getmessage());

        require_once('template/register.php');

    }

    public function doregister($email, $password, $repassword)
    {

        $error = new WP_Error();

        if (!wp_verify_nonce($_POST['_nonce_register'], '_nonce_register')) {
            $error->add('none_invalid', __('None Field invalid value', 'awards'));
            return $error;
        };

        if (email_exists($email)) {
            $error->add('email_exist', sprintf(__('Email already registered. <a href="%s">Login to site</a>', wp_login_url()), 'awards'));
            return $error;
        }

        if (empty($email) || empty($password) || empty($repassword)) {
            $error->add('fieldrequired', __('Please complete all fields', 'awards'));
            return $error;
        }

        if (!is_email($email)) {
            $error->add('email_invalid', __('Please enter valid email', 'awards'));
            return $error;
        }

        if (strlen($password) < 5) {
            $error->add('password_len_min', __('Password lenght most larger than 5 charachter', 'awards'));
            return $error;
        }

        if ($password != $repassword) {
            $error->add('password_and_repassword', __('Password and repassword not equal', 'awards'));
            return $error;
        }

        $username = explode('@', $email);
        $username = $username[0];

        $userdata = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
        );

        $userresult = wp_insert_user($userdata);

        if (is_wp_error($userresult)) return $userresult;

        $logindata = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true
        );

        $loginresult = wp_signon($logindata, false);

        if (is_wp_error($loginresult)) return $loginresult;

        $verify_code = $username . '-' . rand(1, 9999) . $password . '-' . rand(1111, 9999);
        $verify_code = hash('sha256', $verify_code);

        update_user_meta($loginresult->ID, 'awards_verify_code', $verify_code);

        $email_body_text = self::register_email_body($username, $verify_code);
        $email_body = self::email_body($email_body_text);
        wp_mail($email, 'Verify your email', $email_body, array('Content-Type: text/html; charset=UTF-8'));

        wp_set_current_user($loginresult->ID, $username);
        do_action('set_current_user');

        return true;

    }

    public static function dashboard()
    {
        self::dl_users_csv();

        if (!is_user_logged_in()) wp_redirect(wp_login_url());

        if (!awards_options('page_dashboard') || !awards_options('page_login') || !awards_options('page_register')) {
            exit(__('first config awards settings', 'awards'));
        }

        if (isset($_GET['remove-copon-code']) && $_GET['remove-copon-code'] == 'true') {
            delete_copon_session();
            $removed_redirect_url = remove_query_arg('remove-copon-code');
            awards_setmessage(__('Copon removed', 'awards'));
            wp_redirect($removed_redirect_url);
            exit();
        }

        if (isset($_POST['copon']) && $_POST['copon'] != '') {
            self::validate_copon($_POST['copon']);
        }

        if (isset($_GET['dash-page']) && $_GET['dash-page'] == 'entry-edit-delete-image') {
            return self::dashboard_delete_entry_image();
        }

        if (isset($_GET['dash-page']) && $_GET['dash-page'] == 'entry-delete') {
            return self::dashboard_delete_entry();
        }

        if (isset($_GET['dash-page']) && $_GET['dash-page'] == 'mass-pay') {
            return self::dashboard_mass_pay();
        }


        if (isset($_GET['dash-page']) && (isset($_POST['wp-awards-submit']))) {

            switch ($_GET['dash-page']) {
                case 'entry-add':
                    $postresult = self::dashboard_addentry();
                    break;
                case 'entry-edit':
                    $postresult = self::dashboard_edit_entry();
                    break;
                case 'entry-delete':
                    $postresult = self::dashboard_delete_entry();
                    break;
                case 'entry-edit-delete-image':
                    $postresult = self::dashboard_delete_entry_image();
                    break;
                case 'entry-list':
                    $postresult = self::dashboard_listentry();
                    break;
                case 'account':
                    $postresult = self::dashboard_account();
                    break;
            }

            if (is_wp_error($postresult) && !empty($postresult->get_error_messages())) {
                $error_html = '<ul>';
                foreach ($postresult->get_error_messages() as $error) {
                    $error_html .= '<li>' . $error . '</li>';
                }
                $error_html .= '</ul>';
                awards_setmessage($error_html, 'danger');
            } else {
                switch ($_GET['dash-page']) {
                    case 'entry-add':
                        awards_setmessage(__('Entry add successfully.', 'awards'));
                        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list';
                        break;
                    case 'entry-edit':
                        awards_setmessage(__('Entry edit successfully.', 'awards'));
                        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list';
                        break;
                    case 'entry-delete':
                        awards_setmessage(__('Entry delete successfully.', 'awards'));
                        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list';
                        break;
                    case 'entry-edit-delete-image':
                        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-edit&id=' . $_GET['id'];
                        break;
                    case 'account':
                        awards_setmessage(__('Account data updated.', 'awards'));
                        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=account';
                        break;
                    case 'entry-payment':
                        awards_setmessage(__('Account data updated.', 'awards'));
                        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-payment';
                        break;
                    default:
                        $redirecturl = get_permalink(awards_options('page_dashboard'));
                }
                wp_redirect($redirecturl);
            }
        } elseif (isset($_GET['payment'])) {
            return self::dashboard_paymententry();
        } elseif (isset($_GET['paymentresult'])) {
            return self::dashboard_payment_verify();
        } elseif (isset($_GET['paypal-notify'])) {
            return self::dashboard_paypal_notify();
        }

        require_once('template/dashboard.php');
    }

    public static function dashboard_addentry()
    {

        $error = new WP_Error();

        if (awards_options('email_verify_required') && !is_awards_verified_user()) {
            $error->add('verify_email', __('Before add entry must verify your email address', 'awards'));
        }

        if (!awards_options('enable_add_entry')) {
            $error->add('enable_add_entry', __('Add entry disabled, you cannot add new entry', 'awards'));
        }

        for ($i = 0; $i < count($_POST['person']); $i++):
            if (!isset($_POST['person'][$i]['title']) || ($_POST['person'][$i]['title'] != 'mr' && $_POST['person'][$i]['title'] != 'ms' && $_POST['person'][$i]['title'] != 'mrs')) {
                $error->add('nametitleinvalid' . $i, __('Invalid name title', 'awards'));
            }

            if (!isset($_POST['person'][$i]['firstname']) || !isset($_POST['person'][$i]['lastname']) || empty($_POST['person'][$i]['firstname']) || empty($_POST['person'][$i]['lastname'])) {
                $error->add('entername' . $i, __('Enter Person ' . ($i + 1) . ' first name and last name', 'awards'));
            }

            if (!is_string($_POST['person'][$i]['firstname']) || !is_string($_POST['person'][$i]['lastname'])) {
                $error->add('invalidname' . $i, __('Invalid Person ' . ($i + 1) . ' first name or last name', 'awards'));
            }
        endfor;


        if (awards_options('select_entry_type') && (!isset($_POST['entrytype']) || ($_POST['entrytype'] != 'single' && $_POST['entrytype'] != 'series'))) {
            $error->add('invalid_entrytype', __('Invalid entry type', 'awards'));
        }

        if (awards_options('level_expertise') == 'enable') {
            if (!isset($_POST['entrylevel']) || ($_POST['entrylevel'] != 'pro' && $_POST['entrylevel'] != 'nonpro')) {
                $error->add('invalid_entryelevel', __('Invalid entry level', 'awards'));
            }
        }

        if (empty($_POST['entrytitle']) || strlen($_POST['entrytitle']) < 3 || !is_string($_POST['entrytitle'])) {
            $error->add('invalid_entrytitle', __('Invalid entry title', 'awards'));
        }

        if (!empty($_POST['entrytitle']) && strlen($_POST['entrytitle']) < 3 || strlen($_POST['entrytitle']) > 110) {
            $error->add('invalid_entrytitle_len', __('Entry title most between 3 and 30 character', 'awards'));
        }

        if (awards_options('select_category_enable') && empty($_POST['category'])) {
            $error->add('select_cat', __('Category not selected', 'awards'));
        }

        if (empty($_POST['entry-description'])) {
            $error->add('entrydescription_empty', __('Enter description', 'awards'));
        }

        $entryType = awards_options('select_entry_type') ? (isset($_POST['entrytype']) ? $_POST['entrytype'] : false) : awards_options('default_entry_type');

        if ($entryType == 'single' && empty($_FILES['attachment']['name'][0])) {
            $error->add('image', __('You most upload your entry', 'awards'));
        } elseif ($entryType != 'single' && empty($_FILES['attachment']['name'][1])) {
            $error->add('image', __('You most upload your entry', 'awards'));
        }

        if (!isset($_POST['terms'])) {
            $error->add('terms', __('You most agree our terms and conditions', 'awards'));
        }

        for ($i = 0; $i <= count($_FILES['attachment']['type']); $i++) {

            if (empty($_FILES['attachment']['type'][$i])) continue;
            $filetype = $_FILES['attachment']['type'][$i];
            $filesize = $_FILES['attachment']['size'][$i];
            if ($filetype != 'image/jpeg' && $filetype != 'image/jpg') {
                $error->add('image' . $i, __('All files exception most JPG or JPEG', 'awards'));
            }
            if ($filesize > 5342880) {
                $error->add('image_size' . $i, __('File size most less than 5MB', 'awards'));
            }
        }

        if (is_wp_error($error) && !empty($error->get_error_messages())) return $error;

        /*
         * upload files to wp
         */
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $image_upload_result = array();
        for ($i = 0; $i < count($_FILES['attachment']['name']); $i++) {
            if (!empty($_FILES['attachment']['name'][$i])) {
                $file = array(
                    'name' => $_FILES['attachment']['name'][$i],
                    'type' => $_FILES['attachment']['type'][$i],
                    'tmp_name' => $_FILES['attachment']['tmp_name'][$i],
                    'error' => $_FILES['attachment']['error'][$i],
                    'size' => $_FILES['attachment']['size'][$i]
                );

                $_FILES['singlefile'] = $file;
                $attachment_id = media_handle_upload("singlefile", 0);

                if (is_wp_error($attachment_id)) {
                    $error->add('image' . $i, __('Error on upload: ' . $_FILES['attachment']['name'][$i] . '-' . implode($attachment_id->get_error_messages()), 'awards'));
                    return $error;
                } else {
                    $image_upload_result[] = $attachment_id;
                }
            }
        }

        if (is_wp_error($error) && !empty($error->get_error_messages())) return $error;

        $post_data = array(
            'post_type' => 'entry',
            'post_author' => get_current_user_id(),
            'post_title' => $_POST['entrytitle'],
            'post_excerpt' => $_POST['entry-description'],
            // 'tax_input'     => array(
            //     'products_category' => $form_data['product_category'],
            // ),
            'meta_input' => array(
                'persons' => $_POST['person'],
                'entrytype' => $entryType,
                'entrylevel' => $_POST['entrylevel'],
                'entryfiles' => $image_upload_result,
                'status' => 'pending_payment',
            )
        );

        $post = wp_insert_post($post_data);

        $postCategory = awards_options('select_category_enable') && isset($_POST['category']) ? $_POST['category'] : array(awards_options('default_category'));
        if ($post) set_post_thumbnail($post, $image_upload_result[0]);
        if ($post) wp_set_post_terms($post, $postCategory, 'entrycat');

        $user_info = get_currentuserinfo();
        $email_body = self::entry_add_email_body($post_data['post_title'], $post_data['post_author']);
        $email_body = self::email_body(wpautop($email_body));
        wp_mail($user_info->user_email, 'Entry Added', $email_body, array('Content-Type: text/html; charset=UTF-8'));

        return $post;
    }

    public static function dashboard_edit_entry()
    {

        if (!award_entry_can_edit($_GET['id'])) die(__('Cannot edit post', 'awards'));

        $error = new WP_Error();

        if (awards_options('email_verify_required') && !is_awards_verified_user()) {
            $error->add('verify_email', __('Before add entry must verify your email address', 'awards'));
        }

        for ($i = 0; $i < count($_POST['person']); $i++):
            if (!isset($_POST['person'][$i]['title']) || ($_POST['person'][$i]['title'] != 'mr' && $_POST['person'][$i]['title'] != 'ms' && $_POST['person'][$i]['title'] != 'mrs')) {
                $error->add('nametitleinvalid' . $i, __('Invalid name title', 'awards'));
            }

            if (!isset($_POST['person'][$i]['firstname']) || !isset($_POST['person'][$i]['lastname']) || empty($_POST['person'][$i]['firstname']) || empty($_POST['person'][$i]['lastname'])) {
                $error->add('entername' . $i, __('Enter Person ' . ($i + 1) . ' first name and last name', 'awards'));
            }

            if (!is_string($_POST['person'][$i]['firstname']) || !is_string($_POST['person'][$i]['lastname'])) {
                $error->add('invalidname' . $i, __('Invalid Person ' . ($i + 1) . ' first name or last name', 'awards'));
            }
        endfor;


        if (awards_options('select_entry_type') && (!isset($_POST['entrytype']) || ($_POST['entrytype'] != 'single' && $_POST['entrytype'] != 'series'))) {
            $error->add('invalid_entrytype', __('Invalid entry type', 'awards'));
        }

        if (awards_options('level_expertise') == 'enable') {
            if (!isset($_POST['entrylevel']) || ($_POST['entrylevel'] != 'pro' && $_POST['entrylevel'] != 'nonpro')) {
                $error->add('invalid_entryelevel', __('Invalid entry level', 'awards'));
            }
        }

        if (empty($_POST['entrytitle']) || strlen($_POST['entrytitle']) < 3 || !is_string($_POST['entrytitle'])) {
            $error->add('invalid_entrytitle', __('Invalid entry title', 'awards'));
        }


        if (!empty($_POST['entrytitle']) && strlen($_POST['entrytitle']) < 3 || strlen($_POST['entrytitle']) > 110) {
            $error->add('invalid_entrytitle_len', __('Entry title most between 3 and 30 character', 'awards'));
        }

        if (awards_options('select_category_enable') && empty($_POST['category'])) {
            $error->add('select_cat', __('Category not selected', 'awards'));
        }

        if (empty($_POST['entry-description'])) {
            $error->add('entrydescription_empty', __('Enter description', 'awards'));
        }


        if (isset($_FILES)) {
            for ($i = 0; $i <= count($_FILES['attachment']['type']); $i++) {
                if (empty($_FILES['attachment']['type'][$i])) continue;
                $filetype = $_FILES['attachment']['type'][$i];
                if ($filetype != 'image/jpeg' && $filetype != 'image/jpg') {
                    $error->add('image' . $i, __('All files exception most JPG or JPEG', 'awards'));
                }
            }
        }

        if (is_wp_error($error) && !empty($error->get_error_messages())) return $error;

        /*
         * upload files to wp
         */
        if (isset($_FILES)) {

            for ($i = 0; $i <= count($_FILES['attachment']['type']); $i++) {

                if (empty($_FILES['attachment']['type'][$i])) continue;
                $filetype = $_FILES['attachment']['type'][$i];
                $filesize = $_FILES['attachment']['size'][$i];
                if ($filetype != 'image/jpeg' && $filetype != 'image/jpg') {
                    $error->add('image' . $i, __('All files exception most JPG or JPEG', 'awards'));
                }
                if ($filesize > 5342880) {
                    $error->add('image_size' . $i, __('File size most less than 5MB', 'awards'));
                }
            }

            if (is_wp_error($error) && !empty($error->get_error_messages())) return $error;

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $image_upload_result = array();
            for ($i = 0; $i < count($_FILES['attachment']['name']); $i++) {

                if (!empty($_FILES['attachment']['name'][$i])) {
                    $file = array(
                        'name' => $_FILES['attachment']['name'][$i],
                        'type' => $_FILES['attachment']['type'][$i],
                        'tmp_name' => $_FILES['attachment']['tmp_name'][$i],
                        'error' => $_FILES['attachment']['error'][$i],
                        'size' => $_FILES['attachment']['size'][$i]
                    );

                    $_FILES['singlefile'] = $file;
                    $attachment_id = media_handle_upload("singlefile", 0);

                    if (is_wp_error($attachment_id)) {
                        $error->add('image' . $i, __('Error on upload: ' . $_FILES['attachment']['name'][$i] . '-' . implode($attachment_id->get_error_messages()), 'awards'));
                        return $error;
                    } else {
                        $image_upload_result[] = $attachment_id;
                    }
                }
            }
        }

        if (is_wp_error($error) && !empty($error->get_error_messages())) return $error;

        $post_currentdata = new WP_Query(array(
            'post_type' => 'entry',
            'p' => $_GET['id'],
            'author' => get_current_user_id(),
        ));

        if (!$post_currentdata) die(__('Access Denied', 'awards'));

        $post_data = array(
            'ID' => $_GET['id'],
            'post_title' => $_POST['entrytitle'],
            'post_excerpt' => $_POST['entry-description'],
        );

        $postid = wp_update_post($post_data, true);

        if (is_wp_error($postid) && !empty($postid->get_error_messages())) return $postid;

        $entryType = awards_options('select_entry_type') ? (isset($_POST['entrytype']) ? $_POST['entrytype'] : awards_options('default_entry_type')) : awards_options('default_entry_type');

        $postmeta = update_post_meta($_GET['id'], 'person', $_POST['person']);
        $postmeta = update_post_meta($_GET['id'], 'entrytype', $entryType);
        $postmeta = update_post_meta($_GET['id'], 'entrylevel', $_POST['entrylevel']);
        $postmeta = update_post_meta($_GET['id'], 'status', 'pending_payment');

        $postCategory = awards_options('select_category_enable') && isset($_POST['category']) ? $_POST['category'] : array(awards_options('default_category'));
        $postcat = wp_set_post_terms($_GET['id'], $postCategory, 'entrycat');

        if (isset($image_upload_result[0])) {
            $postmeta = update_post_meta($_GET['id'], 'entryfiles', $image_upload_result);
            $postthumbnail = set_post_thumbnail($_GET['id'], $image_upload_result[0]);
        }

        return $postid;
    }

    public static function dashboard_delete_entry()
    {
        if (!award_entry_can_edit($_GET['id'])) die(__('Cannot edit post', 'awards'));
        
        $post_id = intval($_GET['id']);
        
        // --- 1. Get associated image IDs ---
        $entry_files = get_post_meta($post_id, 'entryfiles', true);
        
        // --- 2. Loop through and delete each attachment ---
        if ($entry_files && is_array($entry_files)) {
            foreach ($entry_files as $attachment_id) {
                // wp_delete_attachment deletes the file from server and DB
                // The 'true' parameter forces deletion instead of moving to trash
                wp_delete_attachment($attachment_id, true);
            }
        }
        
        // --- 3. Delete the entry post ---
        wp_delete_post($post_id, true); // Added 'true' to force delete (skip trash)

        awards_setmessage(__('Entry and associated images deleted successfully.', 'awards'));
        $redirecturl = get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list';
        wp_redirect($redirecturl);
    }

    public static function dashboard_delete_entry_image()
    {

        if (!award_entry_can_edit($_GET['id'])) die(__('Cannot edit post', 'awards'));

        $post_id = sanitize_text_field($_GET['id']);
        $image_id = sanitize_text_field($_GET['image_id']);

        $entry_files = get_post_meta($post_id, 'entryfiles', true);
        if (!$entry_files) die(__('invalid image', 'awards'));

        if (!in_array($image_id, $entry_files)) die(__('invalid entry image', 'awards'));

        $new_entry_files = array();

        foreach ($entry_files as $entry_file) {
            if ($entry_file == $image_id) continue;
            $new_entry_files[] = $entry_file;
        }

        if (update_post_meta($post_id, 'entryfiles', $new_entry_files)) {
            awards_setmessage(__('Image Deleted', 'awrads'));
            wp_redirect(site_url('/?dash-page=entry-edit&id=' . $post_id));
            exit();
        }
    }

    public static function dashboard_listentry()
    {

    }

    public static function dashboard_paymententry()
    {
        if (!isset($_GET['payment']) || !isset($_GET['gateway']) || !isset($_GET['id'])) return false;

        require_once 'inc/zarinpal.php';
        require_once 'inc/Paypal.php';

        if (isset($_GET['mass'])) {

            $selected_entries = $_GET['id'];
            $selected_entries = explode(',', $selected_entries);

            $selected_entries_ids = array();
            foreach ($selected_entries as $selected_entry) {
                if (!is_numeric($selected_entry)) continue;
                $selected_entries_ids[] = $selected_entry;
            }

            if (empty($selected_entries_ids)) {
                awards_setmessage(__('invalid entries ids', 'awards'), 'danger');
                wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                exit();
            }

            $selected_posts = new WP_Query(array(
                'post_type' => 'entry',
                'post__in' => $selected_entries_ids,
                'author' => get_current_user_id(),
                'post_status' => 'any',
                'meta_key' => 'status',
                'meta_value' => 'pending_payment',
            ));

            if (!$selected_posts->have_posts()) {
                awards_setmessage(__('invalid enries', 'awards'), 'danger');
                wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                exit();
            }

            $total = 0;

            while ($selected_posts->have_posts()): $selected_posts->the_post();
                if (get_copon_session()) {
                    $total += get_award_entry_total_fee_with_copon(get_the_ID(), false);
                } else {
                    $total += get_award_entry_total_fee(get_the_ID(), false);
                }
            endwhile;

            if (!$total) {
                awards_setmessage(__('invalid total', 'awards'), 'danger');
                wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                exit();
            }

            if ($_GET['gateway'] == 'zarinpal') {
                $zarinpal_redirect_url = implode('-', $selected_entries_ids);
                $zarinpal_redirect_url = get_the_permalink(awards_options('page_dashboard')) . '/?paymentresult=true&gateway=zarinpal&mass=true&id=' . $zarinpal_redirect_url;
                if (!self::zarinpal_payment_request($total, $zarinpal_redirect_url)) {
                    awards_setmessage(__('zarinpal request error', 'awards'), 'danger');
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                    exit();
                }
            } elseif ($_GET['gateway'] == 'paypal') {
                $paypal_clientid = awards_options('paypal_clientid');
                $paypal_clientsecret = awards_options('paypal_clientsecret');

                if (!$paypal_clientid || !$paypal_clientsecret) {
                    echo _e('Client ID and Secret Not found or invalid', 'awards');
                    exit;
                }
                $paypal_ids = implode('-', $selected_entries_ids);
                $paypal_cancel_url = get_the_permalink(awards_options('page_dashboard')) . '?paymentresult=true&gateway=paypal&id=' . $paypal_ids . '&status=cancel&mass=true';
                $paypal_redirect_url = get_the_permalink(awards_options('page_dashboard')) . '?paymentresult=true&gateway=paypal&id=' . $paypal_ids . '&status=return&mass=true';
                Paypal::set_auth($paypal_clientid, $paypal_clientsecret);
$options = array(
    'cancel_url' => $paypal_cancel_url,
    'redirect_url' => $paypal_redirect_url,
    'amount' => $total,
);

$paypal = new Paypal(); // Instantiate the class first
$response = $paypal->doPayment($options); // Call method on the instance
print_r($response);
            } elseif ($_GET['gateway'] == 'now-payment') {
                $selectedCurrency = (isset($_GET['pay_currency']) && in_array($_GET['pay_currency'], explode(',', awards_options('nowpayment_currencies')))) ? strtolower($_GET['pay_currency']) : '';


                if ($selectedCurrency) {

                    $orderID = implode('-', $selected_entries_ids);
                    $userCurrency = awards_options('currency');
                    $Amount = $total;

                    $nowPayment = new NowPayment();


                    $nowPayment->setAmount($Amount);
                    $nowPayment->setPriceCurrency($userCurrency);
                    $nowPayment->setCallback(site_url("/?now-payment-ipn=true&order-id={$orderID}"));

                    $paymentRequest = $nowPayment->invoice((strlen($orderID) > 3 ? "mass-payment" : $orderID), $selectedCurrency);
                    wp_send_json([
                        $selected_entries_ids,
                        $paymentRequest
                    ]);
                    exit();
                    if (!$paymentRequest) {
                        echo '<div class="alert alert-danger">' . __("error on create invoice") . '</div>';
                        return false;
                    }

                    $invoiceURL = isset($paymentRequest->invoice_url) && $paymentRequest->invoice_url ? $paymentRequest->invoice_url : false;
                    if (!$invoiceURL) {
                        echo '<div class="alert alert-danger">' . __("invalid invoice url") . '</div>';
                        return false;
                    }

                    if ($paymentRequest->id && $selected_entries_ids) foreach ($selected_entries_ids as $entries_id) {
                        update_post_meta($entries_id, 'now_payment_payment_id', $paymentRequest->id);
                    }

                    ?>
                    <div class="text-center">
                        <h3><?php _e("redirecting to wallet invoice") ?></h3>
                        <img src="<?= award_plugin_url() ?>/img/loading-bar.gif" class="img-fluid my-3" alt="">
                        <script>
                            window.location.replace("<?= $invoiceURL ?>");
                        </script>
                        <a href="<?= esc_url($invoiceURL) ?>" class="button btn btn-primary">Proccess to Payment</a>
                    </div>
                    <?php
                }
            } elseif ($_GET['gateway'] == 'plisio') {
                $availableCurrencies = explode(',', awards_options('plisio_currencies'));
                if (!$availableCurrencies) return false;

                if (!isset($_GET['currency']) || !in_array($_GET['currency'], $availableCurrencies)) {
                    ?>
                    <h3>Select Your Crypto Currency</h3>
                    <div class="row my-4">
                        <?php foreach ($availableCurrencies as $currency): ?>
                            <div class="col-auto">
                                <a href="<?= add_query_arg(['currency' => $currency]) ?>" class="currency-item">
                                    <?= $currency ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    return false;
                }

                $selected_posts = new WP_Query(array(
                    'post_type' => 'entry',
                    'post__in' => explode(',', $_GET['id']),
                    'author' => get_current_user_id(),
                    'post_status' => 'any',
                    'meta_key' => 'status',
                    'meta_value' => 'pending_payment',
                ));

                if (!$selected_posts->have_posts()) {
                    awards_setmessage(__('invalid entries', 'awards'), 'danger');
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                    exit();
                }

                $amount = 0;
                while ($selected_posts->have_posts()): $selected_posts->the_post();
                    if (get_copon_session()) {
                        $amount += get_award_entry_total_fee_with_copon(get_the_ID(), false);
                    } else {
                        $amount += get_award_entry_total_fee(get_the_ID(), false);
                    }
                endwhile;
                wp_reset_postdata();

                $plisioPayment = new PlisioPayment();
                $plisioPayment->setCallbackUrl(site_url("?plisio-callback&post-id={$_GET['id']}"));
                $invoice = $plisioPayment->createInvoice($amount, $_GET['currency']);
                if (!$invoice) {
                    echo $plisioPayment->errorMessage();
                    return false;
                }

                ?>
                <div class="text-center">
                    <h3><?php _e("redirecting to wallet invoice") ?></h3>
                    <img src="<?= award_plugin_url() ?>/img/loading-bar.gif" class="img-fluid my-3" alt="">
                    <script>
                        window.location.replace("<?= $invoice->invoice_url ?>");
                    </script>
                    <a href="<?= esc_url($invoice->invoice_url) ?>" class="button btn btn-primary">Proccess to
                        Payment</a>
                </div>
                <?php
            }
            exit();
        }
        if (get_post_status($_GET['id']) === FALSE) return false;

        if ($_GET['gateway'] == 'zarinpal') {

            if (get_copon_session()) {
                $Amount = get_award_entry_total_fee_with_copon($_GET['id'], false);
            } else {
                $Amount = get_award_entry_total_fee($_GET['id'], false);
            }

            self::zarinpal_payment_request($Amount);

        } elseif ($_GET['gateway'] == 'paypal') {
    $paypal_clientid = awards_options('paypal_clientid');
    $paypal_clientsecret = awards_options('paypal_clientsecret');
    if (!$paypal_clientid || !$paypal_clientsecret) {
        echo _e('Client ID and Secret Not found or invalid', 'awards');
        exit;
    }
    if (get_copon_session()) {
        $Amount = get_award_entry_total_fee_with_copon($_GET['id'], false);
    } else {
        $Amount = get_award_entry_total_fee($_GET['id'], false);
    }
    $userCurrency = awards_options('currency');
    Paypal::set_auth($paypal_clientid, $paypal_clientsecret);
    $options = array(
        'cancel_url' => get_the_permalink(awards_options('page_dashboard')) . '?paymentresult=true&gateway=paypal&id=' . $_GET['id'] . '&status=cancel',
        'redirect_url' => get_the_permalink(awards_options('page_dashboard')) . '?paymentresult=true&gateway=paypal&id=' . $_GET['id'] . '&status=return',
        'currency' => $userCurrency == 'euro' ? 'EUR' : 'USD',
        'amount' => $Amount,
    );
    $paypal = new Paypal();
$response = $paypal->doPayment($options);
    print_r($response);
} elseif ($_GET['gateway'] == 'stripe') {
    // Redirect to our custom Stripe payment page
    wp_redirect(site_url('/wp-content/plugins/awards-signup2/stripe-payment.php?id=' . $_GET['id']));
    exit;
} elseif ($_GET['gateway'] == 'now-payment') {
    $orderID = (isset($_GET['id']) && is_numeric($_GET['id'])) ? $_GET['id'] : '';
    if (!$orderID) return false;
    $selectedCurrency = (isset($_GET['pay_currency']) && in_array($_GET['pay_currency'], explode(',', awards_options('nowpayment_currencies')))) ? strtolower($_GET['pay_currency']) : '';

            if ($selectedCurrency) {

                $userCurrency = awards_options('currency');
                if (get_copon_session()) {
                    $Amount = get_award_entry_total_fee_with_copon($orderID, false);
                } else {
                    $Amount = get_award_entry_total_fee($orderID, false);
                }

                $nowPayment = new NowPayment();

                $nowPayment->setAmount($Amount);
                $nowPayment->setPriceCurrency($userCurrency);
                $nowPayment->setCallback(site_url("/?now-payment-ipn=true&order-id={$orderID}"));

                $paymentRequest = $nowPayment->invoice($orderID, $selectedCurrency);

                if (!$paymentRequest) {
                    echo '<div class="alert alert-danger">' . __("error on create invoice") . '</div>';
                    return false;
                }

                if ($paymentRequest->id) {
                    update_post_meta($orderID, 'now_payment_payment_id', $paymentRequest->id);
                }

                $invoiceURL = isset($paymentRequest->invoice_url) && $paymentRequest->invoice_url ? $paymentRequest->invoice_url : false;
                if (!$invoiceURL) {
                    echo '<div class="alert alert-danger">' . __("invalid invoice url") . '</div>';
                    return false;
                }

                ?>
                <div class="text-center">
                    <h3><?php _e("redirecting to wallet invoice") ?></h3>
                    <img src="<?= award_plugin_url() ?>/img/loading-bar.gif" class="img-fluid my-3" alt="">
                    <script>
                        window.location.replace("<?= $invoiceURL ?>");
                    </script>
                    <a href="<?= esc_url($invoiceURL) ?>" class="button btn btn-primary">Proccess to Payment</a>
                </div>
                <?php
            } else {
                ?>
                <h3>Select Your Crypto Currency</h3>
                <div class="row my-4">
                    <?php if ($currencies = explode(',', awards_options('nowpayment_currencies'))) foreach ($currencies as $currency): ?>
                        <div class="col-auto">
                            <a href="<?= add_query_arg(['pay_currency' => $currency]) ?>" class="currency-item">
                                <?= $currency ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
            }
        } elseif ($_GET['gateway'] == 'plisio') {
            $availableCurrencies = explode(',', awards_options('plisio_currencies'));
            if (!$availableCurrencies) return false;

            if (!isset($_GET['currency']) || !in_array($_GET['currency'], $availableCurrencies)) {
                ?>
                <h3>Select Your Crypto Currency</h3>
                <div class="row my-4">
                    <?php foreach ($availableCurrencies as $currency): ?>
                        <div class="col-auto">
                            <a href="<?= add_query_arg(['currency' => $currency]) ?>" class="currency-item">
                                <?= $currency ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                return false;
            }

            if (get_copon_session()) {
                $amount = get_award_entry_total_fee_with_copon($_GET['id'], false);
            } else {
                $amount = get_award_entry_total_fee($_GET['id'], false);
            }

            $plisioPayment = new PlisioPayment();

            $plisioPayment->setCallbackUrl(site_url("?plisio-callback&post-id={$_GET['id']}"));
            $invoice = $plisioPayment->createInvoice($amount, $_GET['currency']);
            if (!$invoice) {
                echo $plisioPayment->errorMessage();
                return false;
            }

            ?>
            <div class="text-center">
                <h3><?php _e("redirecting to wallet invoice") ?></h3>
                <img src="<?= award_plugin_url() ?>/img/loading-bar.gif" class="img-fluid my-3" alt="">
                <script>
                    window.location.replace("<?= $invoice->invoice_url ?>");
                </script>
                <a href="<?= esc_url($invoice->invoice_url) ?>" class="button btn btn-primary">Proccess to Payment</a>
            </div>
            <?php
        }
    }

    public function dashboard_payment_verify()
{
    if (!isset($_GET['gateway']) || !isset($_GET['id'])) return false;
    $postid = sanitize_text_field($_GET['id']);
    
    // Check if this is a mass payment
    $is_mass_payment = isset($_GET['mass']) && $_GET['mass'] === 'true';
    
    if ($is_mass_payment) {
        $entry_ids = explode('-', $postid);
        // Validate all entry IDs
        foreach ($entry_ids as $entry_id) {
            if (get_post_status($entry_id) === FALSE) {
                return __('Invalid Post', 'awards');
            }
        }
    } else {
        // Single entry payment - validate the entry ID
        if (get_post_status($postid) === FALSE) {
            return __('Invalid Post', 'awards');
        }
    }

    if ($_GET['gateway'] == 'zarinpal') {
        require_once("inc/zarinpal.php");

        // If status or authority not found
        if (!isset($_GET['Authority']) || !isset($_GET['Status'])) {
            return __('Invalid Authority OR Status', 'awards');
        }

        if ($_GET['Status'] == 'OK') {
            $MerchantID = (awards_options('zarinpal_merchant_id') ? awards_options('zarinpal_merchant_id') : 'test');
            
            if ($is_mass_payment) {
                $selected_posts = new WP_Query(array(
                    'post_type' => 'entry',
                    'post__in' => $entry_ids,
                    'author' => get_current_user_id(),
                    'post_status' => 'any',
                    'meta_key' => 'status',
                    'meta_value' => 'pending_payment',
                ));

                if (!$selected_posts->have_posts()) {
                    awards_setmessage(__('Invalid entries', 'awards'), 'danger');
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                    exit();
                }

                $total = 0;
                while ($selected_posts->have_posts()): $selected_posts->the_post();
                    if (get_copon_session()) {
                        $total += get_award_entry_total_fee_with_copon(get_the_ID(), false);
                    } else {
                        $total += get_award_entry_total_fee(get_the_ID(), false);
                    }
                endwhile;
                
                $Amount = $total;
            } else {
                if (get_copon_session()) {
                    $Amount = get_award_entry_total_fee_with_copon($postid, false);
                } else {
                    $Amount = get_award_entry_total_fee($postid, false);
                }
            }

            $Amount = substr_replace($Amount, "", -1); // Remove one zero for Zarinpal (converts to Tomans)

            $ZarinGate = false;
            $SandBox = awards_options('zarinpal_test') ? true : false;

            $zp = new zarinpal();
            $result = $zp->verify($MerchantID, $Amount, $SandBox, $ZarinGate);

            if (isset($result["Status"]) && $result["Status"] == 100) {
                add_to_user_copon_used(get_copon_session(), get_current_user_id());
                $current_time = date('Y-F-d H:i:s');

                $payment_details = array(
                    'gateway' => 'zarinpal',
                    'Amount' => $result["Amount"],
                    'RefID' => $result["RefID"],
                    'Authority' => $result["Authority"],
                    'time' => $current_time,
                );

                if ($is_mass_payment) {
                    // Process each entry in the mass payment
                    // First reset the post data
                    wp_reset_postdata();
                    
                    // Store the relationship between entries in this mass payment
                    $mass_payment_id = 'mass_' . $result["RefID"];
                    
                    // Re-run the query to get fresh post data
                    $selected_posts = new WP_Query(array(
                        'post_type' => 'entry',
                        'post__in' => $entry_ids,
                        'author' => get_current_user_id(),
                        'post_status' => 'any',
                        'meta_key' => 'status',
                        'meta_value' => 'pending_payment',
                    ));
                    
                    while ($selected_posts->have_posts()) {
                        $selected_posts->the_post();
                        $current_entry_id = get_the_ID();
                        
                        // Update entry status
                        update_post_meta($current_entry_id, 'status', 'pending_review');
                        
                        // Update post modified time
                        $update_post_date = array(
                            'ID' => $current_entry_id,
                            'post_modified' => $current_time,
                            'post_modified_gmt' => $current_time,
                        );
                        wp_update_post($update_post_date);
                        
                        // Store payment details for each entry
                        update_post_meta($current_entry_id, 'payment', $payment_details);
                        
                        // Mark as part of a mass payment
                        update_post_meta($current_entry_id, 'is_mass_payment', true);
                        update_post_meta($current_entry_id, 'mass_payment_id', $mass_payment_id);
                        update_post_meta($current_entry_id, 'mass_payment_entries', implode(',', $entry_ids));
                    }
                    
                    // Generate one invoice for all entries
                    generate_mass_payment_invoice($entry_ids, $payment_details);
                    
                    // Send notification email
                    $user_info = wp_get_current_user();
                    $email_body = self::mass_payment_email_body($entry_ids, $user_info->ID);
                    $email_body = self::email_body(wpautop($email_body));
                    wp_mail($user_info->user_email, 'Entries Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                    
                    // Redirect to success page
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . $entry_ids[0] . '&refid=' . $result["RefID"] . '&mass=true');
                    exit;
                } else {
                    // Process single entry payment
                    update_post_meta($postid, 'status', 'pending_review');
                    
                    $update_post_date = array(
                        'ID' => $postid,
                        'post_modified' => $current_time,
                        'post_modified_gmt' => $current_time,
                    );
                    wp_update_post($update_post_date);
                    
                    $currentmeta = get_post_meta($postid, 'payment', true);
                    if ($currentmeta) {
                        update_post_meta($postid, 'payment', $payment_details);
                    } else {
                        add_post_meta($postid, 'payment', $payment_details);
                    }
                    
                    // Generate invoice for single entry
                    generate_entry_invoice($postid, $payment_details);
                    
                    // Send notification email
                    $user_info = wp_get_current_user();
                    $email_body = self::entry_payment_email_body(get_the_title($postid), $user_info->ID);
                    $email_body = self::email_body(wpautop($email_body));
                    wp_mail($user_info->user_email, 'Entry Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                    
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . $postid . '&refid=' . $result["RefID"]);
                    exit;
                }
            } else {
                echo "پرداخت ناموفق";
                echo "<br />مبلغ : " . $Amount;
                echo "<br />کد خطا : " . $result["Status"];
                echo "<br />تفسیر و علت خطا : " . $result["Message"];
            }

        } else {
            // Handle failed payment
            if ($is_mass_payment) {
                wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=error&mass=true&id=' . implode('-', $entry_ids));
            } else {
                wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=error&id=' . $postid);
            }
        }
    } elseif ($_GET['gateway'] == 'paypal') {
        require_once 'inc/Paypal.php';
        if (isset($_GET['status']) && $_GET['status'] == 'cancel') {
            require_once 'template/payment_result_paypal_cancel.php';
        } else if (isset($_GET['status']) && $_GET['status'] == 'return') {

            if (!isset($_GET['paymentId']) || !isset($_GET['token']) || !isset($_GET['PayerID'])) {
                echo _e('invalid payment data', 'awards');
                exit;
            }

            $paypal_clientid = awards_options('paypal_clientid');
            $paypal_clientsecret = awards_options('paypal_clientsecret');

            if (!$paypal_clientid || !$paypal_clientsecret) {
                echo _e('Client ID and Secret Not found or invalid', 'awards');
                exit;
            }

            Paypal::set_auth($paypal_clientid, $paypal_clientsecret);

            $is_mass_payment = isset($_GET['mass']) && $_GET['mass'] === 'true';
            
            if ($is_mass_payment) {
                $entry_ids = explode('-', $postid);
                if (empty($entry_ids) || !$entry_ids) {
                    return __('invalid ids', 'awards');
                }

                $selected_posts = new WP_Query(array(
                    'post_type' => 'entry',
                    'post__in' => $entry_ids,
                    'author' => get_current_user_id(),
                    'post_status' => 'any',
                    'meta_key' => 'status',
                    'meta_value' => 'pending_payment',
                ));

                if (!$selected_posts->have_posts()) {
                    awards_setmessage(__('invalid entries', 'awards'), 'danger');
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')));
                    exit();
                }

                $total = 0;
                while ($selected_posts->have_posts()): $selected_posts->the_post();
                    if (get_copon_session()) {
                        $total += get_award_entry_total_fee_with_copon(get_the_ID(), false);
                    } else {
                        $total += get_award_entry_total_fee(get_the_ID(), false);
                    }
                endwhile;

                // Use the first entry's ID as a fallback
$verify = Paypal::verify_payment($entry_ids[0], $_GET['paymentId'], $_GET['token'], $_GET['PayerID']);
            } else {
                $verify = Paypal::verify_payment($_GET['id'], $_GET['paymentId'], $_GET['token'], $_GET['PayerID']);
            }

            if ($verify !== FALSE) {
                add_to_user_copon_used(get_copon_session(), get_current_user_id());
                $current_time = date('Y-F-d H:i:s');
                
                $paymentid = sanitize_text_field($_GET['paymentId']);
                $tokenid = sanitize_text_field($_GET['token']);

                $payment_details = array(
                    'gateway' => 'paypal',
                    'Amount' => $verify->transactions[0]->amount->total,
                    'RefID' => $paymentid,
                    'Authority' => $tokenid,
                    'time' => $current_time,
                );
                
                if ($is_mass_payment) {
                    // Process each entry in the mass payment
                    // Store the relationship between entries in this mass payment
                    $mass_payment_id = 'mass_' . $paymentid;
                    
                    // Reset post data before processing entries
                    wp_reset_postdata();
                    
                    // Re-run the query to get fresh post data
                    $selected_posts = new WP_Query(array(
                        'post_type' => 'entry',
                        'post__in' => $entry_ids,
                        'author' => get_current_user_id(),
                        'post_status' => 'any',
                        'meta_key' => 'status',
                        'meta_value' => 'pending_payment',
                    ));
                    
                    while ($selected_posts->have_posts()) {
                        $selected_posts->the_post();
                        $current_entry_id = get_the_ID();
                        
                        // Update entry status
                        update_post_meta($current_entry_id, 'status', 'pending_review');
                        
                        // Update post modified time
                        $update_post_date = array(
                            'ID' => $current_entry_id,
                            'post_modified' => $current_time,
                            'post_modified_gmt' => $current_time,
                        );
                        $update_post = wp_update_post($update_post_date);
                        if (!$update_post) {
                            error_log('cannot edit post modified time, post:' . $current_entry_id);
                        }
                        
                        // Store payment details for each entry
                        update_post_meta($current_entry_id, 'payment', $payment_details);
                        
                        // Mark as part of a mass payment
                        update_post_meta($current_entry_id, 'is_mass_payment', true);
                        update_post_meta($current_entry_id, 'mass_payment_id', $mass_payment_id);
                        update_post_meta($current_entry_id, 'mass_payment_entries', implode(',', $entry_ids));
                    }
                    
                    // Generate one invoice for all entries
                    generate_mass_payment_invoice($entry_ids, $payment_details);
                    
                    // Send notification email
                    $user_info = wp_get_current_user();
                    $email_body = self::mass_payment_email_body($entry_ids, $user_info->ID);
                    $email_body = self::email_body(wpautop($email_body));
                    wp_mail($user_info->user_email, 'Entries Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                    
                    // Redirect to success page
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . $entry_ids[0] . '&refid=' . $paymentid . '&mass=true');
                } else {
                    // Process single entry payment
                    update_post_meta($postid, 'status', 'pending_review');
                    
                    // Update post modified time
                    $update_post_date = array(
                        'ID' => $postid,
                        'post_modified' => $current_time,
                        'post_modified_gmt' => $current_time,
                    );
                    $update_post = wp_update_post($update_post_date);
                    if (!$update_post) {
                        error_log('cannot edit post modified time, post:' . $postid);
                    }
                    
                    // Store payment details
                    $currentmeta = get_post_meta($postid, 'payment', true);
                    if ($currentmeta) {
                        update_post_meta($postid, 'payment', $payment_details);
                    } else {
                        add_post_meta($postid, 'payment', $payment_details);
                    }
                    
                    // Generate invoice for single entry
                    generate_entry_invoice($postid, $payment_details);
                    
                    // Send notification email
                    $user_info = wp_get_current_user();
                    $email_body = self::entry_payment_email_body(get_the_title($postid), $user_info->ID);
                    $email_body = self::email_body(wpautop($email_body));
                    wp_mail($user_info->user_email, 'Entry Payment Confirmation', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                    
                    // Redirect to success page
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . $postid . '&refid=' . $paymentid);
                }
            } else {
                // Payment verification failed
                if ($is_mass_payment) {
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . implode('-', $entry_ids) . '&mass=true');
                } else {
                    wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . $postid);
                }
            }
        } else {
            echo 'error: invalid arguments';
        }
    } else if ($_GET['gateway'] == 'stripe') {
        // Handle Stripe payments (handled separately in stripe-payment.php)
        // We just need to update our payment verification result page
        if (isset($_GET['success']) && $_GET['success'] == 'true') {
            // Stripe payment success (already processed in the handle_stripe_payment_success function)
            $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
            
            if ($is_mass_payment) {
                wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . implode('-', $entry_ids) . '&refid=' . $session_id . '&mass=true');
            } else {
                wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=OK&id=' . $postid . '&refid=' . $session_id);
            }
        } else {
            // Stripe payment failed
            if ($is_mass_payment) {
                wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . implode('-', $entry_ids) . '&mass=true');
            } else {
                wp_redirect(get_the_permalink(awards_options('page_dashboard')) . '/?dash-page=payment-result&status=NOK&id=' . $postid);
            }
        }
    } else {
        echo __('Invalid Gateway', 'awards');
    }
    
    delete_copon_session();
}

    public function dashboard_paypal_notify()
    {
        require_once 'inc/PayPalAP.php';

        if (!isset($_GET['entryid'])) return false;

        $postid = sanitize_text_field($_GET['entryid']);

        if (!get_post($postid)) return false;

        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2)
                $myPost[$keyval[0]] = urldecode($keyval[1]);
        }

        PayPalAP::setEnv('paypal'); // Forces Live Mode


        if (PayPalAP::handleIpn($myPost)) {

            update_post_meta($postid, 'status', 'pending_review');
            $payment_details = array(
                'gateway' => 'paypal',
                'Amount' => $_POST['transaction'][0],
                'RefID' => $_POST['tracking_id'],
                'Authority' => $_POST['pay_key'],
                'time' => date('Y-F-d H:i:s'),
            );
            $currentmeta = get_post_meta($postid, 'payment', true);
            if ($currentmeta) {
                update_post_meta($postid, 'payment', $payment_details);
            } else {
                add_post_meta($postid, 'payment', $payment_details);
            }
        }

    }

    public static function dashboard_account()
    {

        $error = new WP_Error();
        $currentinfo = wp_get_current_user();

        if (!isset($_POST['firstname']) || empty($_POST['firstname'])) {
            $error->add('firstname', __('Invalid firstname', 'awards'));
        }

        if (!isset($_POST['lastname']) || empty($_POST['lastname'])) {
            $error->add('lastname', __('Invalid lastname', 'awards'));
        }

        if (!isset($_POST['email']) || empty($_POST['email'])) {
            $error->add('email', __('Invalid email', 'awards'));
        }

        if (isset($_POST['email']) && $currentinfo->user_email != $_POST['email']) {
            if (email_exists($_POST['email'])) {
                $error->add('email_exist', __('Email exist', 'awards'));
            }
        }

        if (!isset($_POST['country']) || !$_POST['country']) {
            $error->add('country', __('Select country', 'awards'));
        }

        if (isset($_FILES['profilephoto']['name'][0]) && ($_FILES['profilephoto']['type'] != 'image/jpeg' && $_FILES['profilephoto']['type'] != 'image/png')) {
            $error->add('profilephoto_invalid', __('The profile picture can only be a jpeg or png', 'awards'));
        }

        if (isset($_FILES['profilephoto']['name'][0]) && $_FILES['profilephoto']['size'] > 500000) {
            $error->add('profilephoto_invalidsize', __('Profile photo maximum size is 500Kb', 'awards'));
        }

        $newpass = false;
        if (isset($_POST['oldpassword']) && !empty($_POST['oldpassword'])) {
            if (!wp_check_password(sanitize_text_field($_POST['oldpassword']), $currentinfo->user_pass, $currentinfo->ID)) {
                $error->add('country', __('Invalid old password', 'awards'));
            } else {
                if (isset($_POST['password']) && strlen($_POST['password']) > 4) {
                    if (isset($_POST['password']) && sanitize_text_field($_POST['password']) == $_POST['repassword']) {
                        $newpass = $_POST['password'];
                    } else {
                        $error->add('password', __('Password and repassword not matches', 'awards'));
                    }
                } else {
                    $error->add('password_len', __('Password length most larger than 4 charachter', 'awards'));
                }
            }
        }

        if (is_wp_error($error) && !empty($error->get_error_messages())) return $error;

        if ($newpass) {
            wp_set_password($newpass, $currentinfo->ID);
        }

        if (isset($_FILES['profilephoto']) && !empty($_FILES['profilephoto']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $attachment_id = media_handle_upload("profilephoto", 0);

            if (is_wp_error($attachment_id)) {
                $error->add('profilephoto_error', __('Error on upload: ' . $_FILES['profilephoto']['name'] . '-' . implode($attachment_id->get_error_messages()), 'awards'));
                return $error;
            }
        }

        $update = wp_update_user(array(
            'ID' => $currentinfo->ID,
            'first_name' => sanitize_text_field($_POST['firstname']),
            'last_name' => sanitize_text_field($_POST['lastname']),
            'description' => sanitize_text_field($_POST['bio']),
            'user_email' => sanitize_text_field($_POST['email']),
        ));

        if (!is_wp_error($update)) {

            if ($_POST['email'] != $currentinfo->user_email) {
                update_user_meta($currentinfo->ID, 'award_user_verified', false);

                $verify_code = $currentinfo->user_login . '-' . rand(1, 9999) . $currentinfo->user_login . '-' . rand(1111, 9999);
                $verify_code = hash('sha256', $verify_code);
                update_user_meta($currentinfo->ID, 'awards_verify_code', $verify_code);

                $email_body = self::register_email_body($currentinfo->user_login, $verify_code);
                $email_body = self::email_body($email_body);
                wp_mail($currentinfo->user_email, 'Please verify email change', $email_body, array('Content-Type: text/html; charset=UTF-8'));
            }

            if (isset($_POST['phone'])) update_user_meta($currentinfo->ID, 'phone', $_POST['phone']);
            if (isset($_POST['instagram'])) update_user_meta($currentinfo->ID, 'instagram', sanitize_text_field($_POST['instagram']));
            if (isset($_POST['company'])) update_user_meta($currentinfo->ID, 'company', $_POST['company']);
            if (isset($_POST['country'])) update_user_meta($currentinfo->ID, 'country', $_POST['country']);
            if (isset($_POST['city'])) update_user_meta($currentinfo->ID, 'city', $_POST['city']);
            if (isset($_POST['state'])) update_user_meta($currentinfo->ID, 'state', $_POST['state']);
            if ($attachment_id) update_user_meta($currentinfo->ID, 'profilephoto', $attachment_id);
        }

        return $update;
    }

    public static function dashboard_mass_pay()
    {
        $selected_entries = $_GET['selected'];

        foreach ($selected_entries as $selected_entry) {
            if (!is_numeric($selected_entry)) continue;
            $selected_entries_ids[] = $selected_entry;
        }

        if (empty($selected_entries_ids)) {
            awards_setmessage(__('invalid entries ids', 'awards'), 'danger');
            wp_redirect(get_the_permalink(awards_options('page_dashboard')));
            exit();
        }

        $selected_posts = new WP_Query(array(
            'post_type' => 'entry',
            'post__in' => $selected_entries_ids,
            'author' => get_current_user_id(),
            'post_status' => 'any',
            'meta_key' => 'status',
            'meta_value' => 'pending_payment',
        ));

        if (!$selected_posts->have_posts()) {
            awards_setmessage(__('invalid enries', 'awards'), 'danger');
            wp_redirect(get_the_permalink(awards_options('page_dashboard')));
            exit();
        }

        require_once('template/dashboard.php');
    }

    public function enqueue_media_uploader()
    {
        wp_enqueue_media();
    }

    public static function email_body($text)
    {

        $html = '
            <div style="line-height: 1.5; color: #666;font-size: 15px; font-family: \'Times New Roman\', Arial, Verdana, tahoma;padding: 15px;">' . $text . '</div>        
        ';
        return $html;
    }

    private static function register_email_body($username, $verify_code)
    {
        $email_body_text = str_replace(array(
            '{verify_link}',
        ), array(
            site_url('?verify-award-email=true&username=' . $username . '&verify-code=' . $verify_code)
        ), awards_options('verification_email_text'));

        return $email_body_text;
    }

    private static function entry_add_email_body($entry_title, $user_id = '')
    {
        if (!$user_id) {
            $user = get_currentuserinfo();
        } else {
            $user = get_userdata($user_id);
        }

        $email_body_text = str_replace(array(
            '{entry_title}',
            '{user_firstname}',
            '{user_lastname}',
        ), array(
            $entry_title,
            $user->first_name,
            $user->last_name,
        ), awards_options('entry_added_success_fully_mail_text'));

        return $email_body_text;
    }

    private static function entry_payment_email_body($entry_title, $user_id = '')
    {
        if (!$user_id) {
            $user = get_currentuserinfo();
        } else {
            $user = get_userdata($user_id);
        }

        $email_body_text = str_replace(array(
            '{entry_title}',
            '{user_firstname}',
            '{user_lastname}',
        ), array(
            $entry_title,
            $user->first_name,
            $user->last_name,
        ), awards_options('entry_payment_success_fully_mail_text'));

        return $email_body_text;
    }

private static function mass_payment_email_body($entry_ids, $user_id = '')
{
    if (!$user_id) {
        $user = wp_get_current_user();
    } else {
        $user = get_userdata($user_id);
    }

    // Get entry titles for inclusion in the email
    $entry_titles = array();
    foreach ($entry_ids as $entry_id) {
        $entry_titles[] = get_the_title($entry_id);
    }
    
    // If we have a template from settings, use it
    $template = awards_options('entry_payment_success_fully_mail_text');
    
    if ($template) {
        // For backward compatibility, use the first entry title in the template
        $first_entry_title = !empty($entry_titles) ? $entry_titles[0] : '';
        
        $email_body_text = str_replace(array(
            '{entry_title}',
            '{user_firstname}',
            '{user_lastname}',
        ), array(
            $first_entry_title,
            $user->first_name,
            $user->last_name,
        ), $template);
        
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
            $user->first_name,
            $user->last_name
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

    return $email_body_text;
}

    public function verify_user_account()
    {

        if (is_user_logged_in() && isset($_GET['verify-award-email-resend'])) {
            $user_info = get_currentuserinfo();
            $user_verify_status = get_user_meta($user_info->ID, 'award_user_verified', true);

            if (!$user_verify_status) {

                $verify_code = get_user_meta($user_info->ID, 'awards_verify_code', true);
                if (!$verify_code) {
                    $verify_code = $user_info->user_login . '-' . rand(1, 9999) . $user_info->user_login . '-' . rand(1111, 9999);
                    $verify_code = hash('sha256', $verify_code);
                    update_user_meta($user_info->ID, 'awards_verify_code', $verify_code);
                }

                $email_body = self::register_email_body($user_info->user_login, $verify_code);
                $email_body = self::email_body(wpautop($email_body));
                wp_mail($user_info->user_email, 'Please verify your email', $email_body, array('Content-Type: text/html; charset=UTF-8'));
                awards_setmessage(__('verify link resented', 'awards'));
                wp_redirect(get_permalink(awards_options('page_dashboard')));
                exit();
            }
        }


        if (!isset($_GET['username']) || !isset($_GET['verify-code'])) return false;

        $username = sanitize_text_field($_GET['username']);
        $verify_code = sanitize_text_field($_GET['verify-code']);

        $user_details = get_user_by('login', $username);
        if (!$user_details) {
            echo(__('invalid username', 'awards'));
            exit();
        }

        if (is_awards_verified_user($user_details->ID)) {
            awards_setmessage(__('your account verified successfully', 'awards'));
            wp_redirect(get_the_permalink(awards_options('page_dashboard')));
            exit();
        }

        $user_verify_code = get_user_meta($user_details->ID, 'awards_verify_code', true);

        if ($user_verify_code != $verify_code) {
            echo(__('invalid verify key', 'awards'));
            exit();
        }

        update_user_meta($user_details->ID, 'awards_verify_code', '');
        update_user_meta($user_details->ID, 'award_user_verified', true);

        awards_setmessage(__('your account verified successfully', 'awards'));
        wp_redirect(get_the_permalink(awards_options('page_dashboard')));
        exit();
    }

    public function remove_entry_file()
    {

        if (!is_user_logged_in() || !is_super_admin()) return false;

        if (!isset($_GET['remove-entry-file']) || $_GET['remove-entry-file'] != true) return false;
        if (!isset($_GET['image']) || !isset($_GET['post_id'])) return false;

        $image_id = $_GET['image'];
        $post_id = $_GET['post_id'];

        if (!is_numeric($image_id)) return false;
        if (!is_numeric($post_id)) return false;

        $entry_files = get_post_meta($post_id, 'entryfiles', true);

        if (!in_array($image_id, $entry_files)) {
            wp_send_json_error();
            exit;
        }

        $new_entry_files = array();

        foreach ($entry_files as $entry_file) {
            if ($entry_file == $image_id) continue;
            $new_entry_files[] = $entry_file;
        }

        if (update_post_meta($post_id, 'entryfiles', $new_entry_files)) {
            wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
            exit();
        }

        wp_send_json_error();
        exit;
    }

    public function user_custom_fields_show($user)
    {
        require_once 'template/admin_user_edit.php';
    }

    public function user_custom_fields_save($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) return false;

        update_user_meta($user_id, 'country', $_POST['country']);
        update_user_meta($user_id, 'award_user_verified', $_POST['verified']);
        update_user_meta($user_id, 'state', $_POST['state']);
        update_user_meta($user_id, 'city', $_POST['city']);
        update_user_meta($user_id, 'phone', $_POST['phone']);
        update_user_meta($user_id, 'instagram', sanitize_text_field($_POST['instagram']));
    }

    public function user_entries_list_on_edit_page($user)
    {
        $posts = new WP_Query(array(
            'author' => $user->ID,
            'post_type' => 'entry',
            'posts_per_page' => -1,
        ));
        if ($posts->have_posts()) {
            require_once 'template/admin_user_entries.php';
        }
    }

    private function zarinpal_payment_request($amount, $redirect_url = '')
    {
        $Amount = substr_replace($amount, "", -1); // حذف یک صفر از عدد برای پرداخت زین پال که با تومان است
        $Description = "تراکنش زرین پال";
        $Email = "";
        $Mobile = "";
        $CallbackURL = $redirect_url ? $redirect_url : get_the_permalink(awards_options('page_dashboard')) . '/?paymentresult=true&gateway=zarinpal&id=' . $_GET['id'];
        $ZarinGate = false;
        $SandBox = awards_options('zarinpal_test') ? true : false;
        $MerchantID = (awards_options('zarinpal_merchant_id') ? awards_options('zarinpal_merchant_id') : 'test');

        $zp = new zarinpal();
        $result = $zp->request($MerchantID, $Amount, $Description, $Email, $Mobile, $CallbackURL, $SandBox, $ZarinGate);

        if (isset($result["Status"]) && $result["Status"] == 100) {
            // Success and redirect to pay
//            print_r($result);
//            return true;
            $zp->redirect($result["StartPay"]);
        } else {
            return false;
//            // error
//            echo "خطا در ایجاد تراکنش";
//            echo "<br />کد خطا : " . $result["Status"];
//            echo "<br />تفسیر و علت خطا : " . $result["Message"];
        }
    }

    private static function validate_copon($copon_code = '')
    {
        $session_data = get_copon_data($copon_code);
        if (!$session_data) {
            awards_setmessage(__('Can\'t use this code', 'awards'), 'danger');
        } else {
            set_copon_session($session_data);
        }
    }

    public static function dl_users_csv()
    {
        if (!current_user_can('manage_options')) return false;
        if (!isset($_GET['download-awards-users'])) return false;

        ob_start();

        $users = get_users();
        $rand_file_name = 'awards-users-' . date('Y-m-d') . '.csv';

        $fp = @fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $rand_file_name . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public'); // HTTP/1.0

        foreach ($users as $user) {
            $user_meta = get_user_meta($user->ID);
            $fields = array(
                $user->user_firstname,
                $user->user_lastname,
                $user->user_email,
            );
            $fields[] = $user_meta['country'][0] ? $user_meta['country'][0] : '';
            $fields[] = $user_meta['state'][0] ? $user_meta['state'][0] : '';
            $fields[] = $user_meta['city'][0] ? $user_meta['city'][0] : '';
            $fields[] = $user_meta['company'][0] ? $user_meta['company'][0] : '';
            $fields[] = $user_meta['phone'][0] ? $user_meta['phone'][0] : '';
            fputcsv($fp, $fields);
        }

        fclose($fp);
        ob_end_flush();
        die();
    }

    public function dl_all_entries()
    {
        if (!current_user_can('manage_options')) return false;
        if (!isset($_GET['download-entries'])) return false;

        ob_start();

        $entries_query = array(
            'post_type' => 'entry',
            'posts_per_page' => -1,
        );

        if (isset($_POST['status']) && $_POST['status'] != '') {
            $entries_query['meta_query'] = array(
                'key' => 'status',
                'value' => $_POST['status'],
            );
        }

        if (isset($_POST['winnerstatus']) && $_POST['winnerstatus'] != '') {
            $entries_query['meta_query'] = array(
                'key' => 'winner_status',
                'value' => $_POST['winnerstatus'],
            );
        }

        if (isset($_POST['user']) && $_POST['user'] != '') {
            $entries_query['author'] = $_POST['user'];
        }

        $entries = new WP_Query($entries_query);

        if (!$entries->have_posts()) return false;

        $users = get_users();
        $rand_file_name = 'awards-entries-' . date('Y-m-d') . '.csv';

        $fp = @fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $rand_file_name . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public'); // HTTP/1.0

        while ($entries->have_posts()) {
            $entries->the_post();
            $post_meta = get_post_meta(get_the_ID());
            $fields = array(
                get_the_ID(),
                get_the_title() ? get_the_title() : '',
            );
            $fields[] = $post_meta['entryfiles'][0] ? count($post_meta['entryfiles'][0]) : '';
            $fields[] = $post_meta['status'][0] ? $post_meta['status'][0] : '';
            $fields[] = $post_meta['winner_status'][0] ? $post_meta['winner_status'][0] : '';
            $fields[] = $post_meta['payment'][0] ? true : '';
            fputcsv($fp, $fields);
        }

        fclose($fp);
        ob_end_flush();
        die();
    }

    public function dl_selected_entry_files()
    {
        if (!current_user_can('manage_options')) return false;
        if (!isset($_GET['download-entry-files'])) return false;

        ob_start();

        $selected_images = $_GET['images'];
        $selected_images = explode('-', $selected_images);


        if (empty($selected_images) || !is_array($selected_images)) return false;


        $rand_file_name = 'awards-entry-images-' . date('Y-m-d-H-i-s') . '.zip';
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'];
        $file_path = $upload_path . '/' . $rand_file_name;
        $file_path_url = $upload_dir['url'] . '/' . $rand_file_name;

        $zip = new ZipArchive;

        if ($zip->open($file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) return false;

        foreach ($selected_images as $selected_image) {
            $file_wp_path = get_attached_file($selected_image);
            if ($selected_image != '' && $file_wp_path != false) {
                $zip->addFile($file_wp_path, basename($file_wp_path));
            }
        }

        $zip->close();

        if (!file_exists($file_path)) return false;

        if (isset($_GET['ajax'])) {
            wp_send_json(array('url' => $file_path_url));
            die();
        }


        header('Content-Type: application/zip');
        header('Content-Disposition: attachment;filename="' . $rand_file_name . '"');

        readfile($file_path);

        ob_end_flush();
        die();
    }

}
function awards_enqueue_styles() {
    wp_enqueue_style(
        'awards-css', 
        plugins_url('css/awards.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'css/awards.css')
    );
}
add_action('wp_enqueue_scripts', 'awards_enqueue_styles');

$wpAwardsClass = new wp_awards();

add_shortcode('wp_awards_login_form', array($wpAwardsClass, 'login'));
add_shortcode('wp_awards_register_form', array($wpAwardsClass, 'register'));
add_shortcode('wp_awards_dashboard', array($wpAwardsClass, 'dashboard'));
add_action('init', array($wpAwardsClass, 'remove_entry_file'));
add_action('init', array($wpAwardsClass, 'basewp_init'));
add_action('init', array($wpAwardsClass, 'verify_user_account'), 90);
add_action('admin_init', array($wpAwardsClass, 'admin_init'));
add_action('plugins_loaded', array($wpAwardsClass, 'wp_init'));
add_action('wp_enqueue_scripts', array($wpAwardsClass, 'dist'));
add_action('admin_enqueue_scripts', array($wpAwardsClass, 'dist_admin'), 999, 1);
add_filter('login_url', array($wpAwardsClass, 'login_url'), 10, 3);
add_filter('register_url', array($wpAwardsClass, 'register_url'), 10, 3);
add_action('show_user_profile', array($wpAwardsClass, 'user_custom_fields_show'), 999, 1);
add_action('edit_user_profile', array($wpAwardsClass, 'user_custom_fields_show'), 999, 1);
add_action('personal_options_update', array($wpAwardsClass, 'user_custom_fields_save'));
add_action('edit_user_profile_update', array($wpAwardsClass, 'user_custom_fields_save'));
add_action('show_user_profile', array($wpAwardsClass, 'user_entries_list_on_edit_page'), 999, 1);
add_action('edit_user_profile', array($wpAwardsClass, 'user_entries_list_on_edit_page'), 999, 1);
add_action('admin_init', array($wpAwardsClass, 'dl_users_csv'));
add_action('admin_init', array($wpAwardsClass, 'dl_all_entries'));
add_action('admin_init', array($wpAwardsClass, 'dl_selected_entry_files'));
