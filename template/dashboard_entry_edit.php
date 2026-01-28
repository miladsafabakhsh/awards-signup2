<h3><?php _e('Add new entry', 'awards'); ?></h3>

<?php
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die(__('Invalid Post ID', 'awards'));
}

$post_currentdata = new WP_Query(array(
    'post_type' => 'entry',
    'p' => $_GET['id'],
    'author' => get_current_user_id(),
    'post_status' => array('pending', 'draft', 'publish'),
));

if ($post_currentdata->have_posts()):
    while ($post_currentdata->have_posts()): $post_currentdata->the_post();
        /*        if (!award_entry_can_edit(get_the_ID())) {
                    echo '<div class="awards-result result-danger">' . __('Edit only available on pending payment.', 'awards') . '</div>';
                }*/
        ?>
        <div class="personitem label-group d-none" id="personitem-template">
            <div class="row">
                <div class="col-12 col-sm-auto">
                    <div class="form-group">
                        <label for="person[][title]"><?php _e('Name', 'awards'); ?> <span
                                    class="required">*</span>
                            <a href="#remove-personitem!"
                               class="small pull-right btn-remove removeperson d-inline-block d-sm-none"
                               data-personid="" onclick="deletpersonitem()" tabindex="-1">remove</a>
                        </label>
                        <select name="person[][title]" class="form-control">
                            <option value="mr">Mr</option>
                            <option value="mrs">Mrs</option>
                            <option value="ms">Ms</option>
                        </select>
                    </div>
                </div>
                <div class="col-6 col-sm">
                    <div class="form-group">
                        <label for="person[][firstname]" class="small"><?php _e('First name', 'awards'); ?></label>
                        <input type="text" name="person[][firstname]" id="person[][firstname]"
                               placeholder="<?php _e('Enter your first name', 'awards'); ?>" class="form-control">
                    </div>
                </div>
                <div class="col-6 col-sm">
                    <div class="form-group">
                        <label for="person[][lastname]" class="small"><?php _e('Last name', 'awards'); ?>
                            <a href="#remove-personitem!"
                               class="small pull-right btn-remove removeperson d-none d-sm-inline-block"
                               data-personid="" onclick="deletpersonitem()" tabindex="-1">remove</a>
                        </label>
                        <input type="text" name="person[][lastname]" id="person[][lastname]"
                               placeholder="<?php _e('Enter your last name', 'awards'); ?>" class="form-control">
                    </div>
                </div>
            </div>
        </div>
        <form action="" method="post" enctype="multipart/form-data">
            <?php //print_r($_FILES['attachment']);
            ?>
            <div class="personitem-area">
                <?php
                if (isset($_POST['person'])) {
                    $post_currentpersons = $_POST['person'];
                } else {
                    $post_currentpersons = get_post_meta(get_the_ID(), 'persons', true);
                }
                foreach ($post_currentpersons as $key => $person):
                    ?>
                    <div class="personitem label-group">
                        <div class="row align-items-center">
                            <div class="col-12 col-sm-auto">
                                <div class="form-group">
                                    <label for="person[<?php echo $key; ?>][title]"><?php _e('Name', 'awards'); ?> <span
                                                class="required">*</span></label>
                                    <select name="person[<?php echo $key; ?>][title]"
                                            class="form-control" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>>
                                        <option value="mr" <?php echo ((isset($_POST['person'][$key]['title']) && $_POST['person'][$key]['title'] == 'mr') || $person['title'] == 'mr') ? 'selected' : 'selected'; ?>>
                                            Mr
                                        </option>
                                        <option value="mrs" <?php echo ((isset($_POST['person'][$key]['title']) && $_POST['person'][$key]['title'] == 'mrs') || $person['title'] == 'mrs') ? 'selected' : ''; ?>>
                                            Mrs
                                        </option>
                                        <option value="ms" <?php echo ((isset($_POST['person'][$key]['title']) && $_POST['person'][$key]['title'] == 'ms') || $person['title'] == 'ms') ? 'selected' : ''; ?>>
                                            Ms
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6 col-sm">
                                <div class="form-group">
                                    <label for="person[<?php echo $key; ?>][firstname]"
                                           class="small"><?php _e('First name', 'awards'); ?></label>
                                    <input type="text" name="person[<?php echo $key; ?>][firstname]"
                                           id="person[<?php echo $key; ?>][firstname]"
                                           value="<?php echo isset($_POST['person'][$key]['firstname']) ? $_POST['person'][$key]['firstname'] : $person['firstname']; ?>"
                                           placeholder="<?php _e('Enter your first name', 'awards'); ?>"
                                           class="form-control" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-6 col-sm">
                                <div class="form-group">
                                    <label for="person[<?php echo $key; ?>][lastname]"
                                           class="small"><?php _e('Last name', 'awards'); ?>
                                        <?php if (award_entry_can_edit(get_the_ID())): ?>
                                            <a href="#remove-personitem<?php echo $key; ?>!"
                                               class="small pull-right btn-remove removeperson d-none d-sm-inline-block"
                                               data-personid="#personitem<?php echo $key; ?>"
                                               tabindex="-1"><?php _e('remove', 'awards'); ?></a>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text" name="person[<?php echo $key ?>][lastname]"
                                           id="person[<?php echo $key ?>][lastname]"
                                           value="<?php echo isset($_POST['person'][$key]['lastname']) ? $_POST['person'][$key]['lastname'] : $person['lastname']; ?>"
                                           placeholder="<?php _e('Enter your last name', 'awards'); ?>"
                                           class="form-control" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (award_entry_can_edit(get_the_ID())): ?>
                <a href="#!"
                   id="add-new-person-field"><?php _e('+ add another person for collective work', 'awards'); ?></a>
                <br/>
            <?php endif; ?>
            <div class="row">
                <?php if (awards_options('select_entry_type')): ?>
                    <?php $post_current_entrytype = get_post_meta(get_the_ID(), 'entrytype', true); ?>
                    <div class="col-12 col-sm-6">
                        <div class="form-group">
                            <label for="entrytype"><?php _e('Entry type', 'awards'); ?> <span
                                        class="required">*</span></label>
                            <label for="entry-type-single" class="radio">
                                <input type="radio" name="entrytype" data-price="<?php echo get_award_basefee('single'); ?>"
                                       id="entry-type-single" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>
                                       value="single" <?php echo ((isset($_POST['entrytype']) && $_POST['entrytype'] == 'single') || $post_current_entrytype == 'single') ? 'checked' : 'checked'; ?>> <?php _e('Single', 'awards'); ?>
                            </label>
                            <label for="entry-type-series" class="radio">
                                <input type="radio" name="entrytype" data-price="<?php echo get_award_basefee('series'); ?>"
                                       id="entry-type-series" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>
                                       value="series" <?php echo ((isset($_POST['entrytype']) && $_POST['entrytype'] == 'series') || $post_current_entrytype == 'series') ? 'checked' : ''; ?>> <?php _e('Series', 'awards'); ?>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                if (awards_options('level_expertise') == 'enable'):
                    $post_current_entrylevel = get_post_meta(get_the_ID(), 'entrylevel', true);
                    ?>
                    <div class="col-12 col-sm-6">
                        <div class="form-group">
                            <label><?php _e('Level of expertise'); ?> <span class="required">*</span></label>
                            <label for="entry-level-pro" class="radio">
                                <input type="radio" name="entrylevel"
                                       id="entry-level-pro" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>
                                       value="pro" <?php echo ((isset($_POST['entrylevel']) && $_POST['entrylevel'] == 'pro') || $post_current_entrylevel == 'pro') ? 'checked' : 'checked'; ?>> <?php _e('Professional', 'awards'); ?>
                            </label>
                            <label for="entry-level-nonpro" class="radio">
                                <input type="radio" name="entrylevel"
                                       id="entry-level-nonpro" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>
                                       value="nonpro" <?php echo ((isset($_POST['entrylevel']) && $_POST['entrylevel'] == 'nonpro') || $post_current_entrylevel == 'nonpro') ? 'checked' : ''; ?>> <?php _e('Non-Professional', 'awards'); ?>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="entry-title"><?php _e('Entry title', 'awards'); ?> <span class="required">*</span></label>
                <input type="text" name="entrytitle" id="entry-title" maxlength="110"
                       placeholder="<?php _e('Enter your entry title for view in your dashboard', 'awards'); ?>"
                       value="<?php echo (isset($_POST['entrytitle'])) ? $_POST['entrytitle'] : get_the_title(); ?>"
                       class="form-control" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>>
            </div>
            <?php if (awards_options('select_category_enable')): ?>
                <div class="form-group">
                    <div class="row">
                        <div class="col">
                            <label><?php _e('Category', 'awards'); ?> <span class="required">*</span>
                                <small><?php _e('(you can select as many categories as you want that fits your work. For more info about the fee, check the <a href="https://minimalistphotographyawards.com/fees-and-deadlines/" target="_blank">fees and deadlines</a> page)', 'awards'); ?></small>
                            </label>
                        </div>
                        <div class="col-auto">
                            <?php
                            $entry_Cats = get_terms('entrycat', array(
                                'hide_empty' => false,
                            ));
                            $currentcats_query = wp_get_post_terms(get_the_ID(), 'entrycat');
                            $currentcats = array();
                            foreach ($currentcats_query as $curcat) {
                                $currentcats[] = $curcat->term_id;
                            }
                            ?>
                            <?php
                            if (isset($_POST['entrytype'])) {
                                $currentfee = ($_POST['entrytype'] == 'single') ? get_award_basefee('single') : get_award_basefee('series');
                            } elseif ($post_current_entrytype) {
                                $currentfee = get_award_basefee($post_current_entrytype);
                            } else {
                                $currentfee = get_award_basefee('single');
                            }
                            if (isset($_POST['category']) && count($_POST['category']) > 1) {
                                foreach ($_POST['category'] as $term) {
                                    $currentfee = $currentfee + get_add_categories_basefee();
                                }
                                $currentfee = $currentfee - get_add_categories_basefee();
                            } elseif (count($currentcats) <= 1) {
                                $currentfee = $currentfee;
                            } else {
                                for ($i = 1; $i <= count($currentcats); $i++) {
                                    $currentfee = $currentfee + get_add_categories_basefee((isset($_POST['entrytype']) && $_POST['entrytype'] == 'series') || $post_current_entrytype == 'series' ? 'series' : 'single');
                                }
                                $currentfee = $currentfee - get_add_categories_basefee((isset($_POST['entrytype']) && $_POST['entrytype'] == 'series') || $post_current_entrytype == 'series' ? 'series' : 'single');
                            }
                            ?>
                            <div id="entryfee" data-catfee-single="<?php echo get_add_categories_basefee('single'); ?>" data-catfee-series="<?php echo get_add_categories_basefee('series'); ?>"><?php _e('Entry fee:', 'awards'); ?>
                                <span
                                        class="entryfee-num strong"><?php echo $currentfee; ?></span><span
                                        class="strong"><?php echo get_awards_price_symbol() ?></span>
                            </div>
                        </div>
                    </div>
                    <fieldset>
                        <div class="row align-items-center">
                            <?php foreach ($entry_Cats as $cat):
                                $t_id = $cat->term_id; // Get the ID of the term you're editing
                                $term_meta_price = get_award_term_fee($t_id); // Do the check
                                ?>
                                <div class="col-6 col-sm-4 col-md-3">
                                    <div class="entryfeeitem <?php echo (award_entry_can_edit(get_the_ID())) ? '' : 'disabled'; ?> <?php echo (in_array($cat->term_id, $currentcats) || (isset($_POST['category']) && in_array($cat->term_id, $_POST['category']))) ? 'active' : ''; ?>"
                                         data-price="<?php echo $term_meta_price ? $term_meta_price : '0'; ?>">
                                        <label for="cat-<?php echo $cat->term_id; ?>" class="checkbox">
                                            <input type="checkbox" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>
                                                   name="category[]" <?php echo (in_array($cat->term_id, $currentcats) || (isset($_POST['category']) && in_array($cat->term_id, $_POST['category']))) ? 'checked' : ''; ?>
                                                   id="cat-<?php echo $cat->term_id; ?>"
                                                   value="<?php echo $cat->term_id; ?>"> <?php echo $cat->name; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="entry-description"><?php _e('Entry description', 'awards'); ?> <span
                            class="required">*</span>
                    <small><?php _e('(anything important about the work, such as: when, where or why, subject, context, etc. )', 'awards'); ?></small>
                </label>
                <textarea name="entry-description" id="entry-description"
                          rows="5" <?php echo (!award_entry_can_edit(get_the_ID())) ? 'disabled' : ''; ?>
                          class="form-control"><?php echo (isset($_POST['entry-description'])) ? $_POST['entry-description'] : get_the_excerpt(); ?></textarea>
            </div>
            <div class="form-group">
                <label><?php _e('Current entries:', 'awards'); ?></label>
                <div class="row">
                    <?php
                    $post_current_entries = get_post_meta(get_the_ID(), 'entryfiles', true);
                    if ($post_current_entries):
                        foreach ($post_current_entries as $img) {
                            ?>
                            <div class="col-6 col-md-3 col-lg-2">
                                <div class="award-image-edit">
                                    <a href="<?php echo wp_get_attachment_image_url($img, 'full') ?>" target="_blank"
                                       class="entryfile-item"><?php echo wp_get_attachment_image($img, 'thumbnail', '', array('class' => 'img-thumbnail thumbnail award-thumbnail')) ?></a>
                                </div>
                                <div class="awards-alert awards-alert-danger" id="delete-entry-image<?php echo $img; ?>">
                                    <div class="awards-alert-inner">
                                        <div class="awards-alert-content">
                                            <div class="awards-alert-content-header"><?php _e('Entry image delete', 'awards') ?></div>
                                            <div class="awards-alert-content-body">
                                                <?php _e('Do you want delete this image?', 'awards'); ?>
                                            </div>
                                            <div class="awards-alert-content-actions">
                                                <a href="<?php echo site_url('/?dash-page=entry-edit-delete-image&id=' . get_the_ID() . '&image_id=' . $img) ?>" class="button primary main"><?php _e('Yes'); ?></a>
                                                <a href="#dismis" data-dismis="awards-alert" class="button secondary"><?php _e('No'); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    endif;

                    ?>
                </div>
            </div>
            <?php if (award_entry_can_edit(get_the_ID())): ?>
                <div class="text-danger"><?php _e('<strong>Notice</strong>: Upload new entry will delete the previous entries'); ?></div>
                <br/>
                <div class="form-group">
                    <?php $post_current_entrytype = (awards_options('select_entry_type')) ? get_post_meta(get_the_ID(), 'entrytype', true) : awards_options('default_entry_type'); ?>
                    <div class="row align-items-center">
                        <div class="col"><label><?php _e('Upload your entry', 'awards'); ?>
                                <small><?php _e('(1600 pixels on the longest side, 72 dpi, sRGB, jpg)', 'awards'); ?></small>
                            </label></div>
                    </div>
                    <div id="entryfiles">
                        <div id="file-single" style="<?php echo ($post_current_entrytype == 'single' || isset($_POST['entrytype']) && $_POST['entrytype'] == 'single') ? '' : 'display: none;'; ?>">
                            <div class="form-group-file">
                                <input type="file" name="attachment[]" class="form-control-file"/>
                            </div>
                        </div>
                        <div id="file-series" style="<?php echo ($post_current_entrytype == 'series' || isset($_POST['entrytype']) && $_POST['entrytype'] == 'series') ? '' : 'display: none;'; ?>">
                            <?php for ($i = 1; $i <= awards_options('max_image_upload'); $i++): ?>
                                <div class="form-group-file">
                                    <input type="file" name="attachment[]" class="form-control-file"/>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
            <?php if (award_entry_can_edit(get_the_ID())): ?>
                <input type="submit" name="wp-awards-submit" class="btn btn-primary button submit primary"
                       value="<?php _e('Edit entry', 'awards'); ?>">
            <?php endif; ?>
        </form>
        <script>
            function deletpersonitem(id) {
                (function ($) {
                    $("#" + id).remove();
                })(jQuery);
            }
        </script>
    <?php endwhile; ?>
<?php else: ?>
    <div class="awards-result result-danger"><?php _e('Access Denied OR No valid post ID'); ?></div>
<?php endif; ?>