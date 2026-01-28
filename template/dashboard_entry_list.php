<h3><?php _e('Your entries', 'awards'); ?></h3>
<?php
// Replace the query at the top of dashboard_entry_list.php
// Change this line:
// $paged = (get_query_var('paginate')) ? get_query_var('paginate') : 1;

// To this more robust version:
$paged = isset($_GET['paginate']) ? absint($_GET['paginate']) : 1;
if (!$paged) $paged = 1;

$entry_list = new WP_Query(array(
    'author' => get_current_user_id(), 
    'posts_per_page' => 20, 
    'post_type' => 'entry', 
    'post_status' => 'any', 
    'paged' => $paged
));
if ($entry_list->have_posts()):
    ?>
    <form action="<?php echo get_the_permalink(awards_options('page_dashboard')).'/?dash-page=mass-pay'; ?>" method="get">
        <input type="hidden" name="dash-page" value="mass-pay">
        <table border="0" cellpadding="0" cellspacing="0" class="table awards-table">
            <thead>
            <th width="10"></th>
            <th nowrap="nowrap"><?php _e('Thumbnail', 'awards'); ?></th>
            <th nowrap="nowrap"><?php _e('Title', 'awards'); ?></th>
            <th nowrap="nowrap"><?php _e('Category', 'awards'); ?></th>
            <th nowrap="nowrap"><?php _e('Total Fee', 'awards'); ?></th>
            <th nowrap="nowrap"><?php _e('Status', 'awards'); ?></th>
            <th nowrap="nowrap"></th>
            </thead>
            <tbody>
            <?php
            $has_pending_payment = false;
            while ($entry_list->have_posts()): $entry_list->the_post();
                $entrystatus = get_post_meta(get_the_ID(), 'status', true);
                $winnerstatus = get_post_meta(get_the_ID(), 'winner_status', true);
                $is_mass_payment = get_post_meta(get_the_ID(), 'is_mass_payment', true);
                
                // Check if any entries are pending payment
                if ($entrystatus == 'pending_payment') {
                    $has_pending_payment = true;
                }
                ?>
                <tr>
                    <td><?php if ($entrystatus == 'pending_payment'): ?><input type="checkbox" name="selected[]"
                                                                               value="<?php echo get_the_ID() ?>"><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-edit&id=<?php echo get_the_ID(); ?>"><?php echo get_the_post_thumbnail(get_the_ID(), 'awards-mini-thumbnail', array('class' => 'img-fluid img-responsive awards-img-thumbnail')); ?></a>
                    </td>
                    <td>
                        <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-edit&id=<?php echo get_the_ID(); ?>"><?php echo(get_the_title(get_the_ID()) ? get_the_title(get_the_ID()) : __('--No title--', 'awards')); ?></a>
                        <?php if ($is_mass_payment): ?>
                            <br><small class="text-muted"><?php _e('(Paid in bulk payment)', 'awards'); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $posterms = wp_get_post_terms(get_the_ID(), 'entrycat');
                        $cats = array();
                        foreach ($posterms as $term) {
                            $cats[] = $term->name;
                        }
                        echo implode(', ', $cats);
                        ?>
                    </td>
                    <td class="text-center"><?php echo get_award_entry_total_fee(get_the_ID()); ?></td>
                    <td class="text-center">
                        <?php echo get_award_status_title($entrystatus, true); ?>
                        <?php
                        if ($winnerstatus) {
                            echo '<br>' . get_award_winnerstatus_title($winnerstatus, true);
                        }
                        ?>
                    </td>
                    <td nowrap="" class="text-center">
                        <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-edit&id=<?php echo get_the_ID(); ?>"
                           class="table-btn"><?php echo (award_entry_can_edit(get_the_ID())) ? __('Edit entry', 'awards') : __('View entry', 'awards'); ?></a>
                        <?php if(award_entry_can_edit(get_the_ID())): ?>
                            <span style="margin: 0 3px;">|</span>
                            <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-delete&id=<?php echo get_the_ID(); ?>"
                               class="table-btn text-danger js-confirm"><?php echo __('Delete entry', 'awards'); ?></a>
                        <?php endif; ?>
                        <?php if ($entrystatus == 'pending_payment'): ?>
                            <br>
                            <a href="<?php echo get_the_permalink(awards_options('page_dashboard')); ?>/?dash-page=entry-payment&id=<?php echo get_the_ID(); ?>"
                               class="table-btn btn-payment btn btn-dark button dark button-secondary small btn-sm" style="margin-top: 5px;"><?php _e('Pay Fee', 'awards'); ?></a>
                        <?php endif; ?>
                        <?php $certificate = get_post_meta(get_the_ID(), 'certificate', true); ?>
                        <?php if ($certificate): ?>
                            <a href="<?php echo esc_url($certificate); ?>" target="_blank"
                               class="btn btn-primary button primary large"><?php _e('View certificate', 'awards'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php if ($has_pending_payment): ?>
            <div class="text-left">
                <input type="submit" class="button primary main" value="<?php _e('Mass pay selected entries'); ?>">
            </div>
        <?php endif; ?>
    </form>
    <div class="awards-paginate">
    <?php
    $big = 999999999;
    
    // Fix the pagination links to properly handle the paginate parameter
    echo paginate_links(array(
        'base' => add_query_arg('paginate', '%#%', get_permalink(awards_options('page_dashboard')) . '/?dash-page=entry-list'),
        'format' => '',
        'current' => max(1, get_query_var('paginate')),
        'total' => $entry_list->max_num_pages
    ));
    ?>
</div>
<?php endif; ?>