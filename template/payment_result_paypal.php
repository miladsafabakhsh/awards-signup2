<?php if (isset($_GET['status']) && $_GET['status'] == 'return'): ?>
    <h1 class="text-center text-primary"><?php _e('Payment Successfully', 'awards'); ?></h1>
    <p class="text-center"><?php _e('Entry fee payment successfully', 'awards'); ?></p>
    <div class="text-center">
        <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-list"
           class="btn btn-primary btn-lg button primary btn-got"><?php _e('Go to entry list'); ?></a>
    </div>
<?php elseif (isset($_GET['status']) && $_GET['status'] == 'cancel'): ?>
    <h1 class="text-center text-danger"><?php _e('Payment Failed', 'awards'); ?></h1>
    <div class="text-center">
        <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-list"
           class="btn btn-primary btn-lg button primary btn-got"><?php _e('Go to entry list'); ?></a>
    </div>
<?php else: ?>
    <h1 class="text-center"><?php _e('Invalid status', 'awards'); ?></h1>
<?php endif; ?>
