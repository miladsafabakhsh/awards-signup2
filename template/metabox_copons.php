<table class="form-table wp-awards wp-awards-table wp-awards">
    <tbody>
    <tr>
        <th><?php _e('Copon code:', 'awards'); ?></th>
        <td>
            <input name="code" id="code" value="<?php echo get_post_meta($post->ID, 'code', true); ?>" type="text"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th><?php _e('Type:', 'awards'); ?></th>
        <td>
            <label for="fix"> <input name="type" id="fix"
                                  value="fix" <?php echo get_post_meta($post->ID, 'type', true) == 'fix' ? 'checked' : ''; ?>
                                  type="radio" class="regular-text"> <span><?php _e('Fix amount', 'awards'); ?></span></label>

            <label for="percent"> <input name="type" id="percent"
                                  value="percent" <?php echo get_post_meta($post->ID, 'type', true) == 'percent' ? 'checked' : ''; ?>
                                  type="radio" class="regular-text"> <span><?php _e('Percent', 'awards'); ?></span></label>

        </td>
    </tr>
    <tr>
        <th><?php _e('Value:', 'awards'); ?></th>
        <td>
            <input name="value" id="value" value="<?php echo get_post_meta($post->ID, 'value', true); ?>" type="text"
                   class="regular-text">
        </td>
    </tr>
    </tbody>
</table>