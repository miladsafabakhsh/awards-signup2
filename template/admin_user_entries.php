<h3><?php _e('User Entries', 'awards'); ?></h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th nowrap="" valign="middle" style="text-align: center"><?php _e('ID', 'awards'); ?></th>
            <th nowrap="" valign="middle" style="text-align: center"><?php _e('Title', 'awards'); ?></th>
            <th nowrap="" valign="middle" style="text-align: center"><?php _e('Modified date', 'awards'); ?></th>
            <th nowrap="" valign="middle" style="text-align: center"><?php _e('Status', 'awards'); ?></th>
            <th nowrap="" valign="middle" style="text-align: center"><?php _e('Winner Status', 'awards'); ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php while ($posts->have_posts()): $posts->the_post(); ?>
    <tr>
        <td nowrap="" style="text-align: center"><?php the_id();?></td>
        <td nowrap="" style="text-align: center"><?php the_title();?></td>
        <td nowrap="" style="text-align: center"><?php echo get_the_modified_date();?></td>
        <td nowrap="" style="text-align: center"><?php echo get_award_status_title(get_post_meta(get_the_ID(), 'status', true)); ?></td>
        <td nowrap="" style="text-align: center"><?php echo get_award_winnerstatus_title(get_post_meta(get_the_ID(), 'winner_status', true)); ?></td>
        <td><a href="<?php echo get_edit_post_link(); ?>" class="button primary" target="_blank"><?php _e('View', 'awards'); ?></a></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
<hr>