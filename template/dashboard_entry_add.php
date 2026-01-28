<?php if (!awards_options('enable_add_entry')): ?>
    <div class="awards-result result-danger"><?php _e('Add entry disabled, you cannot add new entry', 'awards'); ?></div>
<?php elseif (user_entries_count() >= ($maxEntriesCount = awards_options('max_user_entries'))): ?>
    <div class="awards-result result-danger"><?php printf(__('Cannot add more than %s entries', 'awards'), $maxEntriesCount); ?></div>
<?php elseif (!awards_options('email_verify_required') || (awards_options('email_verify_required') && is_awards_verified_user())): ?>
    <div class="entry-add-container">
        <h3 style="margin-bottom: 24px;">
            <svg width="24" height="24" style="margin-right: 8px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            <?php _e('Add New Entry', 'awards'); ?>
        </h3>
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
        <?php //print_r($_FILES['attachment']); ?>
        <div class="personitem-area">
            <div class="personitem label-group">
                <div class="row align-items-center">
                    <div class="col-12 col-sm-auto">
                        <div class="form-group">
                            <label for="person[0][title]"><?php _e('Name', 'awards'); ?> <span
                                        class="required">*</span></label>
                            <select name="person[0][title]" class="form-control">
                                <option value="mr" <?php echo ((isset($_POST['person'][0]['title']) ? $_POST['person'][0]['title'] : 'mr') == 'mr') ? 'selected' : 'selected'; ?>>
                                    Mr
                                </option>
                                <option value="mrs" <?php echo ((isset($_POST['person'][0]['title']) ? $_POST['person'][0]['title'] : '') == 'mrs') ? 'selected' : ''; ?>>
                                    Mrs
                                </option>
                                <option value="ms" <?php echo ((isset($_POST['person'][0]['title']) ? $_POST['person'][0]['title'] : '') == 'ms') ? 'selected' : ''; ?>>
                                    Ms
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6 col-sm">
                        <div class="form-group">
                            <label for="person[0][firstname]" class="small"><?php _e('First name', 'awards'); ?></label>
                            <input type="text" name="person[0][firstname]" id="person[0][firstname]"
                                   value="<?php echo isset($_POST['person'][0]['firstname']) ? $_POST['person'][0]['firstname'] : ''; ?>"
                                   placeholder="<?php _e('Enter your first name', 'awards'); ?>" class="form-control">
                        </div>
                    </div>
                    <div class="col-6 col-sm">
                        <div class="form-group">
                            <label for="person[0][lastname]" class="small"><?php _e('Last name', 'awards'); ?></label>
                            <input type="text" name="person[0][lastname]" id="person[0][lastname]"
                                   value="<?php echo isset($_POST['person'][0]['lastname']) ? $_POST['person'][0]['lastname'] : ''; ?>"
                                   placeholder="<?php _e('Enter your last name', 'awards'); ?>" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <?php 
            // FIX: Check if $_POST['person'] exists and is an array before counting
            $person_count = (isset($_POST['person']) && is_array($_POST['person'])) ? count($_POST['person']) : 0;
            for ($i = 1; $i < $person_count; $i++): 
            ?>
                <div class="personitem label-group" id="personitem<?php echo $i; ?>">
                    <div class="row align-items-center">
                        <div class="col-12 col-sm-auto">
                            <div class="form-group">
                                <label for="person[<?php echo $i; ?>][title]"><?php _e('Name', 'awards'); ?> <span
                                            class="required">*</span>
                                    <a href="#remove-personitem<?php echo $i; ?>!"
                                       class="small pull-right btn-remove removeperson d-inline-block d-sm-none"
                                       data-personid="#personitem<?php echo $i; ?>" tabindex="-1">remove</a>
                                </label>
                                <select name="person[<?php echo $i; ?>][title]" class="form-control">
                                    <option value="mr" <?php echo (($_POST['person'][$i]['title'] ?? '') == 'mr') ? 'selected' : 'selected'; ?>>
                                        Mr
                                    </option>
                                    <option value="mrs" <?php echo (($_POST['person'][$i]['title'] ?? '') == 'mrs') ? 'selected' : ''; ?>>
                                        Mrs
                                    </option>
                                    <option value="ms" <?php echo (($_POST['person'][$i]['title'] ?? '') == 'ms') ? 'selected' : ''; ?>>
                                        Ms
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm">
                            <div class="form-group">
                                <label for="person[<?php echo $i; ?>][firstname]"
                                       class="small"><?php _e('First name', 'awards'); ?></label>
                                <input type="text" name="person[<?php echo $i; ?>][firstname]"
                                       id="person[<?php echo $i; ?>][firstname]"
                                       value="<?php echo $_POST['person'][$i]['firstname'] ?? ''; ?>"
                                       placeholder="<?php _e('Enter your first name', 'awards'); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="col-12 col-sm">
                            <div class="form-group">
                                <label for="person[<?php echo $i; ?>][lastname]"
                                       class="small"><?php _e('Last name', 'awards'); ?>
                                    <a href="#remove-personitem<?php echo $i; ?>!"
                                       class="small pull-right btn-remove removeperson d-none d-sm-inline-block"
                                       data-personid="#personitem<?php echo $i; ?>" tabindex="-1">remove</a>
                                </label>
                                <input type="text" name="person[<?php echo $i; ?>][lastname]"
                                       id="person[<?php echo $i; ?>][lastname]"
                                       value="<?php echo $_POST['person'][$i]['lastname'] ?? ''; ?>"
                                       placeholder="<?php _e('Enter your last name', 'awards'); ?>" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <a href="#!" id="add-new-person-field"><?php _e('+ add another person for collective work', 'awards'); ?></a>
        <br/>
        <div class="row">
            <?php if (awards_options('select_entry_type')): ?>
                <div class="col-12 col-sm-6">
                    <div class="form-group">
                        <label for="entrytype"><?php _e('Entry type', 'awards'); ?> <span class="required">*</span></label>
                        <label for="entry-type-single" class="radio">
                            <input type="radio" name="entrytype" data-price="<?php echo get_award_basefee('single'); ?>" id="entry-type-single"
                                   value="single" <?php echo (isset($_POST['entrytype']) && $_POST['entrytype'] == 'single') ? 'checked' : 'checked'; ?>> <?php _e('Single', 'awards'); ?>
                        </label>
                        <label for="entry-type-series" class="radio">
                            <input type="radio" name="entrytype" data-price="<?php echo get_award_basefee('series'); ?>" id="entry-type-series"
                                   value="series" <?php echo (isset($_POST['entrytype']) && $_POST['entrytype'] == 'series') ? 'checked' : ''; ?>> <?php _e('Series', 'awards'); ?>
                        </label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (awards_options('level_expertise') == 'enable'): ?>
                <div class="col-12 col-sm-6">
                    <div class="form-group">
                        <label><?php _e('Level of expertise'); ?> <span class="required">*</span></label>
                        <label for="entry-level-pro" class="radio">
                            <input type="radio" name="entrylevel" id="entry-level-pro"
                                   value="pro" <?php echo (isset($_POST['entrylevel']) && $_POST['entrylevel'] == 'pro') ? 'checked' : 'checked'; ?>> <?php _e('Professional', 'awards'); ?>
                        </label>
                        <label for="entry-level-nonpro" class="radio">
                            <input type="radio" name="entrylevel" id="entry-level-nonpro"
                                   value="nonpro" <?php echo (isset($_POST['entrylevel']) && $_POST['entrylevel'] == 'nonpro') ? 'checked' : ''; ?>> <?php _e('Non-Professional', 'awards'); ?>
                        </label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="entry-title"><?php _e('Entry title', 'awards'); ?> <span class="required">*</span></label>
            <input type="text" name="entrytitle" id="entry-title" maxlength="110"
                   placeholder="<?php _e('Enter your entry title for view in your dashboard', 'awards'); ?>"
                   value="<?php echo isset($_POST['entrytitle']) ? $_POST['entrytitle'] : ''; ?>"
                   class="form-control">
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
                        if (isset($_POST['entrytype'])) {
                            $currentfee = ($_POST['entrytype'] == 'single') ? get_award_basefee('single') : get_award_basefee('series');
                        } else {
                            $currentfee = get_award_basefee('single');
                        }

                        if (isset($_POST['category'])) {
                            foreach ($_POST['category'] as $term) {
//                        $term_meta = get_option("taxonomy_term_$term"); // Do the check
//                        print_r($term_meta);
                                $currentfee = $currentfee + get_award_term_fee($term);
                            }
                        }
                        ?>
                        <div id="entryfee" data-catfee-single="<?php echo get_add_categories_basefee('single'); ?>" data-catfee-series="<?php echo get_add_categories_basefee('series'); ?>"><?php _e('Entry fee:', 'awards'); ?> <span
                                    class="entryfee-num strong">0</span><span
                                    class="strong"><?php echo get_awards_price_symbol() ?></span>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="row align-items-center">
                        <?php
                        $entry_Cats = get_terms('entrycat', array(
                            'hide_empty' => false,
                        ));
                        ?>
                        <?php foreach ($entry_Cats as $cat):
                            $t_id = $cat->term_id; // Get the ID of the term you're editing
                            $term_meta_price = get_award_term_fee($t_id); // Do the check
                            ?>
                            <div class="col-6 col-sm-4 col-md-3">
                                <div class="entryfeeitem"
                                     data-price="<?php echo $term_meta_price ? $term_meta_price : '0'; ?>">
                                    <label for="cat-<?php echo $cat->term_id; ?>" class="checkbox">
                                        <input type="checkbox"
                                               name="category[]" <?php echo (isset($_POST['category']) && is_array($_POST['category']) && in_array($cat->term_id, $_POST['category'])) ? 'checked' : ''; ?>
                                               id="cat-<?php echo $cat->term_id; ?>"
                                               value="<?php echo $cat->term_id; ?>"> <?php echo $cat->name; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="entry-description"><?php _e('Entry description', 'awards'); ?> <span class="required">*</span>
                <small><?php _e('(anything important about the work, such as: when, where or why, subject, context, etc. )', 'awards'); ?></small>
            </label>
            <textarea name="entry-description" id="entry-description" rows="5"
                      class="form-control"><?php echo (isset($_POST['entry-description'])) ? $_POST['entry-description'] : ''; ?></textarea>
        </div>
        <div class="form-group">
            <div class="row align-items-center">
                <div class="col"><label><?php _e('Upload your entry', 'awards'); ?> <span class="required">*</span>
                        <small><?php _e('(1600 pixels on the longest side, 72 dpi, sRGB, jpg)', 'awards'); ?></small>
                    </label></div>
            </div>
            <div id="entryfiles">
                <div id="file-single"
                     style="<?php echo ((!awards_options('select_entry_type') && awards_options('default_entry_type') == 'single') || (!isset($_POST['entrytype']) || isset($_POST['entrytype']) && $_POST['entrytype'] == 'single')) ? '' : 'display: none;'; ?>">
                    <div class="form-group-file">
                        <input type="file" name="attachment[]" class="form-control-file"/>
                    </div>
                </div>
                <div id="file-series" style="<?php echo ((!awards_options('select_entry_type') && awards_options('default_entry_type') != 'single') || (isset($_POST['entrytype']) && $_POST['entrytype'] == 'series')) ? '' : 'display: none;'; ?>">
                    <?php for ($i = 1; $i <= awards_options('max_image_upload'); $i++): ?>
                        <div class="form-group-file">
                            <input type="file" name="attachment[]" class="form-control-file"/>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="terms" class="checkbox">
                <input type="checkbox" id="terms" name="terms"
                       value="agree"> <?php _e(sprintf('I have read and agreed to the <a href="%s">Terms & Conditions</a>', get_permalink(awards_options('page_terms'))), 'awards'); ?>
            </label>
        </div>
        <input type="submit" name="wp-awards-submit" class="btn btn-primary button submit primary"
               value="<?php _e('Add entry', 'awards'); ?>">
    </form>
    </div><!-- .entry-add-container -->
    <script>
        function deletpersonitem(id) {
            (function ($) {
                $("#" + id).remove();
            })(jQuery);
        }
    </script>
<?php else: ?>
    <div class="awards-result result-danger"><?php _e('Before add entry must verify your email address', 'awards'); ?></div>
<?php endif; ?>