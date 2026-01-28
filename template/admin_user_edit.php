<hr>
<h3><?php _e('User awards details', 'awards'); ?></h3>
<table class="form-table">
    <tbody>
    <tr>
        <th><?php _e("Country", 'awards'); ?></th>
        <td>
            <select name="country" id="country" id="country" class="form-control">
                <option value="0"><?php _e('Select country...'); ?></option>
                <?php
                $currentcountry = get_user_meta($user->ID, 'country', true);
                foreach (award_countries() as $country) {
                    echo '<option value="' . $country . '" ' . (($_POST['country'] == $country || $currentcountry == $country) ? 'selected' : '') . '>' . $country . '</option>';
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php _e("State", 'awards'); ?></th>
        <td><input type="text" name="state" id="state" value="<?php echo esc_attr( get_the_author_meta( 'state', $user->ID ) ); ?>" class="regular-text" /></td>
    </tr>
    <tr>
        <th><?php _e("City", 'awards'); ?></th>
        <td><input type="text" name="city" id="city" value="<?php echo esc_attr( get_the_author_meta( 'city', $user->ID ) ); ?>" class="regular-text" /></td>
    </tr>
<tr>
    <th><label for="instagram"><?php _e('Instagram', 'awards'); ?></label></th>
    <td>
        <input type="text" name="instagram" id="instagram" value="<?php echo esc_attr(get_user_meta($user->ID, 'instagram', true)); ?>" class="regular-text" />
        <p class="description"><?php _e('User\'s Instagram handle (without @)', 'awards'); ?></p>
    </td>
</tr>
    <tr>
        <th><?php _e("Phone", 'awards'); ?></th>
        <td><input type="text" name="phone" id="phone" value="<?php echo esc_attr( get_the_author_meta( 'phone', $user->ID ) ); ?>" class="regular-text" /></td>
    </tr>
    <tr>
        <th><?php _e("Verify", 'awards'); ?></th>
        <td><label for="verified"><input type="checkbox" name="verified" id="verified" value="true" <?php echo get_user_meta($user->ID, 'award_user_verified', true) ? 'checked' : ''; ?>> <?php _e('Is verified user', 'awards'); ?></label></td>
    </tr>
    </tbody>
</table>
<hr>