<div class="wp-awards">
    <?php echo (awards_getmessage() ? awards_getmessage() : ''); ?>
    <form action="" method="post">
        <?php echo wp_nonce_field('_nonce_register', '_nonce_register'); ?>
        <div class="form-group">
            <label for="email"><?php _e('Email', 'awards'); ?></label>
            <input type="text" name="email" id="email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : '' ; ?>" placeholder="<?php _e('Enter your email address', 'awards'); ?>" class="form-control">
        </div>
        <div class="form-group">
            <label for="password"><?php _e('Password', 'awards'); ?></label>
            <input type="password" name="password" id="password" value="<?php echo isset($_POST['password']) ? $_POST['password'] : '' ; ?>" placeholder="<?php _e('Enter your password', 'awards'); ?>" class="form-control">
        </div>
        <div class="form-group">
            <label for="repassword"><?php _e('Confirm Password', 'awards'); ?></label>
            <input type="password" name="repassword" id="repassword" value="<?php echo isset($_POST['repassword']) ? $_POST['repassword'] : '' ; ?>" placeholder="<?php _e('Enter your password again', 'awards'); ?>" class="form-control">
        </div>
        <input type="submit" name="register-submit" class="button btn btn-primary submit primary" value="<?php _e('Register', 'awards'); ?>"/>
    </form>
</div>