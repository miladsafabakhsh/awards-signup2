<div class="wp-awards">
    <?php echo (awards_getmessage() ? awards_getmessage() : ''); ?>
    <form action="" method="post">
        <?php echo wp_nonce_field('_nonce_login', '_nonce_login'); ?>
        <div class="form-group">
            <label for="username"><?php _e('Username', 'awards'); ?></label>
            <input type="text" name="username" id="username" placeholder="<?php _e('Enter your login username', 'awards'); ?>" class="form-control">
        </div>
        <div class="form-group">
            <label for="password"><?php _e('Password', 'awards'); ?></label>
            <input type="password" name="password" id="password" placeholder="<?php _e('Enter your login password', 'awards'); ?>" class="form-control">
        </div>
        <input type="submit" name="login-submit" class="button btn btn-primary submit primary" value="<?php _e('Login', 'awards'); ?>"/>
    </form>
</div>