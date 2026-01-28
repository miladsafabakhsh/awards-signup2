<?php
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die(__('Invalid Post ID', 'awards'));
}

$post_currentdata = new WP_Query(array(
    'post_type' => 'entry',
    'p' => sanitize_text_field($_GET['id']),
    'author' => get_current_user_id(),
    'post_status' => array('pending', 'draft', 'publish'),
));
if ($post_currentdata->have_posts()):
    while ($post_currentdata->have_posts()): $post_currentdata->the_post();
        $level = get_post_meta(get_the_ID(), 'entrylevel', true);
        $entrytype = get_post_meta(get_the_ID(), 'entrytype', true);
        ?>
        <h3><?php _e('Payment', 'awards'); ?></h3>
        <div class="row">
            <div class="col-12 col-md-4">
                <?php
                if ($entrytype == 'single'):
                    the_post_thumbnail('large', array('class' => 'img-fluid img-responsive'));
                else:
                    the_post_thumbnail('large', array('class' => 'img-fluid img-responsive'));
                    ?>
                    <br>
                    <br>
                    <div class="row">
                        <?php
                        $post_current_entries = get_post_meta(get_the_ID(), 'entryfiles', true);
                        foreach ($post_current_entries as $img) {
                            ?>
                            <div class="col-6 col-sm-4">
                                <a href="<?php echo wp_get_attachment_image_url($img, 'full') ?>" target="_blank"
                                   class="entryfile-item"><?php echo wp_get_attachment_image($img, 'thumbnail', '', array('class' => 'img-thumbnail thumbnail award-thumbnail')) ?></a>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-8">
                <p class="text-center"><strong><?php the_author() ?></strong></p>
                <h4 class="text-center text-secondary text-dark"><?php echo (get_the_title()) ? get_the_title() : __('Untitled Entry', 'awards'); ?></h4>
                <?php /*if(get_the_excerpt()): ?>
                <p class="text-center"><?php echo get_the_excerpt(); ?></p>
            <?php endif;*/ ?>
                <p class="text-center">
                    <?php if (awards_options('level_expertise') == 'enable'): ?>
                        <strong><?php _e('Level of expertise:'); ?></strong> <?php echo ($level == 'pro') ? 'Professional' : 'Non Professional'; ?>
                        |
                    <?php endif; ?>
                    <strong><?php _e('Entry type:'); ?></strong> <?php echo ($entrytype != 'single') ? 'Series' : 'Single'; ?>
                </p>
                <?php
                $currentcats_query = wp_get_post_terms(get_the_ID(), 'entrycat');
                $currentcats = array();
                foreach ($currentcats_query as $curcat) {
                    $currentcats[] = $curcat->name;
                }
                ?>
                <p class="text-center">
                    <strong><?php _e('Category:', 'awards'); ?></strong> <?php echo implode(', ', $currentcats) ?>
                </p>
                <h2 class="text-center text-danger text-price">
                    <small><?php _e('Fee:', 'awards'); ?></small> <?php echo get_award_entry_total_fee(get_the_ID()); ?>
                </h2>
                <?php if (awards_options('copon_enable')): ?>
                    <form action="" method="post">
                        <div class="row">
                            <div class="col-12 col-md-8 col-lg-7 mx-auto">
                                <div class="bg-light rounded p-3 my-3">
                                    <h4><?php _e('Discount copon', 'awards'); ?></h4>
                                    <p><?php _e('Have a discount code? enter it:', 'awards'); ?></p>
                                    <div class="row align-items-center no-gutters">
                                        <div class="col">
                                            <input name="copon" id="copon" type="text" class="form-control px-1"
                                                   placeholder="enter your copon code...">
                                        </div>
                                        <div class="col-auto">
                                            <input type="submit" class="btn button btn-primary btn-secondary secondary"
                                                   value="<?php _e('apply', 'awards'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <?php if (get_copon_session()): ?>
                                    <div class="text-center"><?php echo sprintf(__('Copon Code: "%s"', 'awards'), get_copon_session()['copon']) ?>
                                        <a href="?dash-page=entry-payment&id=<?php the_ID() ?>&remove-copon-code=true"
                                           class="small">(<?php _e('remove', 'awards'); ?>)</a></div>
                                    <h3 class="text-danger text-center">
                                        <?php echo sprintf(__('New price: %s'), get_award_entry_total_fee_with_copon(get_the_ID())); ?>
                                    </h3>
                                    <br>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
                <div class="text-center">
                    <?php require_once 'payment_btn.php'; ?>
                </div>
            </div>
        </div>
    <?php
    endwhile;
else:?>
    <div class="awards-result result-danger"><?php _e('invalid access or invalid post', 'awards'); ?></div>
<?php
endif; ?>