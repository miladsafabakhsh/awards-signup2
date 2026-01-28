<style>
    .wp-awards-table td {
        font-size: 14px !important;
    }

    .entryfile-item {
        display: inline-block;
    }

    .entryfile-item img {
        border-radius: 8px;
        transition: all ease 0.3s;
    }

    .entryfile-item:hover img {
        transition: all ease 0.3s;
        opacity: 0.7;
        filter: alpha(opacity=70);
    }

    .wp-awards .badge {
        display: inline-block;
        padding: 0 5px;
        -webkit-border-radius: 5px;
        -moz-border-radius: 5px;
        border-radius: 5px;
        font-size: 13px;
    }

    .wp-awards .badge.badge-success {
        background-color: #AED581;
    }

    .wp-awards .badge.badge-light {
        background-color: #eeeeee;
    }

    .wp-awards .badge.badge-dark {
        background-color: #333333;
        color: #fff;
    }

    .wp-awards .badge.badge-danger {
        background-color: #e57373;
        color: #fff;
    }

    .wp-awards .badge.badge-warning {
        background-color: #FFE082;
        color: #333333;
    }

    .wp-awards .custom-img-container img {
        max-width: 150px;
    }

    .entryfile-item-holder {
        border-radius: 8px;
        border: 1px solid #eee;
        overflow: hidden;
        position: relative;
        margin: 5px;
        display: inline-block;
    }

    .entryfile-item-holder .remove {
        position: absolute;
        top: 0;
        right: 0;
        background-color: rgba(255, 0, 0, 0.6);
        color: #ffffff;
        cursor: pointer;
        font-size: 11px;
        padding: 2px 5px;
        display: inline-block;
        z-index: 1;
    }

    .entryfile-item-holder .remove:hover {
        background-color: rgba(255, 0, 0, 0.9);
    }

    .entryfile-item-holder .dl-select {
        position: absolute;
        top: 0;
        left: 0;
        background-color: rgba(255, 0, 0, 0.6);
        color: #ffffff;
        cursor: pointer;
        font-size: 11px;
        padding: 2px 5px;
        display: inline-block;
        z-index: 1;
    }

    .loadingarea {
        position: relative;
    }

    .loadingarea:before {
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        z-index: 3;
        content: "";
        background-color: rgba(255, 255, 255, 0.5);
        display: none;
    }

    .loadingarea.loading:before {
        display: block;
    }

</style>
<table class="form-table wp-awards-table wp-awards">
    <tbody>
    <tr>
        <th><?php _e('Persons:', 'awards'); ?></th>
        <td>
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

            <div class="personitem-area">
                <div class="personitem label-group">
                    <div class="row align-items-center">
                        <div class="col-12 col-sm-auto">
                            <div class="form-group">
                                <label for="person[0][title]"><?php _e('Name', 'awards'); ?> <span
                                            class="required">*</span></label>
                                <select name="person[0][title]" class="form-control">
                                    <option value="mr" <?php selected( $_POST['person'][0]['title'] ?? 'mr', 'mr' ); ?>>
                                        Mr
                                    </option>
                                    <option value="mrs" <?php selected( $_POST['person'][0]['title'] ?? '', 'mrs' ); ?>>
                                        Mrs
                                    </option>
                                    <option value="ms" <?php selected( $_POST['person'][0]['title'] ?? '', 'ms' ); ?>>
                                        Ms
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-sm">
                            <div class="form-group">
                                <label for="person[0][firstname]"
                                       class="small"><?php _e('First name', 'awards'); ?></label>
                                <input type="text" name="person[0][firstname]" id="person[0][firstname]"
                                       value="<?php echo esc_attr($_POST['person'][0]['firstname'] ?? ''); ?>"
                                       placeholder="<?php _e('Enter your first name', 'awards'); ?>"
                                       class="form-control">
                            </div>
                        </div>
                        <div class="col-6 col-sm">
                            <div class="form-group">
                                <label for="person[0][lastname]"
                                       class="small"><?php _e('Last name', 'awards'); ?></label>
                                <input type="text" name="person[0][lastname]" id="person[0][lastname]"
                                       value="<?php echo esc_attr($_POST['person'][0]['lastname'] ?? ''); ?>"
                                       placeholder="<?php _e('Enter your last name', 'awards'); ?>"
                                       class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                // Check if $_POST['person'] is set and is an array
                if (isset($_POST['person']) && is_array($_POST['person'])):
                    for ($i = 1; $i < count($_POST['person']); $i++):
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
                                        <option value="mr" <?php selected( $_POST['person'][$i]['title'] ?? 'mr', 'mr' ); ?>>
                                            Mr
                                        </option>
                                        <option value="mrs" <?php selected( $_POST['person'][$i]['title'] ?? '', 'mrs' ); ?>>
                                            Mrs
                                        </option>
                                        <option value="ms" <?php selected( $_POST['person'][$i]['title'] ?? '', 'ms' ); ?>>
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
                                           value="<?php echo esc_attr($_POST['person'][$i]['firstname'] ?? ''); ?>"
                                           placeholder="<?php _e('Enter your first name', 'awards'); ?>"
                                           class="form-control">
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
                                           value="<?php echo esc_attr($_POST['person'][$i]['lastname'] ?? ''); ?>"
                                           placeholder="<?php _e('Enter your last name', 'awards'); ?>"
                                           class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                    endfor; // End of for loop
                endif; // End of if check
                ?>
            </div>
            <a href="#!"
               id="add-new-person-field"><?php _e('+ add another person for collective work', 'awards'); ?></a>
            <br/>
            <script>
                function deletpersonitem(id) {
                    (function ($) {
                        $("#" + id).remove();
                    })(jQuery);
                }
            </script>
        </td>
    </tr>
    <tr>
        <th><?php _e('Entry type:', 'awards'); ?></th>
        <td>
            <div class="form-group">
                <label for="entry-type-single" class="radio">
                    <input type="radio" name="entrytype" data-price="<?php echo get_award_basefee('single'); ?>"
                           id="entry-type-single"
                           value="single" <?php checked( $_POST['entrytype'] ?? 'single', 'single' ); ?>> <?php _e('Single', 'awards'); ?>
                </label>
                <label for="entry-type-series" class="radio">
                    <input type="radio" name="entrytype" data-price="<?php echo get_award_basefee('series'); ?>"
                           id="entry-type-series"
                           value="series" <?php checked( $_POST['entrytype'] ?? '', 'series' ); ?>> <?php _e('Series', 'awards'); ?>
                </label>
            </div>
        </td>
    </tr>
    <?php if (awards_options('level_expertise') == 'enable'): ?>
        <tr>
            <th><?php _e('Level of expertise:', 'awards'); ?></th>
            <td>
                <div class="form-group">
                    <label><?php _e('Level of expertise'); ?> <span class="required">*</span></label>
                    <label for="entry-level-pro" class="radio">
                        <input type="radio" name="entrylevel" id="entry-level-pro"
                               value="pro" <?php checked( $_POST['entrylevel'] ?? 'pro', 'pro' ); ?>> <?php _e('Professional', 'awards'); ?>
                    </label>
                    <label for="entry-level-nonpro" class="radio">
                        <input type="radio" name="entrylevel" id="entry-level-nonpro"
                               value="nonpro" <?php checked( $_POST['entrylevel'] ?? '', 'nonpro' ); ?>> <?php _e('Non-Professional', 'awards'); ?>
                    </label>
                </div>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <th>
            <?php _e('Entry files:', 'awards'); ?>
        </th>
        <td class="loadingarea">
            <div>
                <div class="new_entry_files-holder"></div>
                <input type="hidden" name="new_entry_files" id="new_entry_files" value="">
                <input type="button" id="select_new_entry_files" class="button" value="<?php _e('Select entry files', 'awards'); ?>">
                <script>
                    jQuery(document).ready(function () {
                        var $ = jQuery;
                        if ($('#select_new_entry_files').length > 0) {
                            if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                                $(document).on('click', '#select_new_entry_files', function (e) {
                                    e.preventDefault();
                                    var button = $(this);
                                    var id = $('#new_entry_files');
                                    var holder = $('.new_entry_files-holder');
                                    var edit = wp.media({
                                        title: 'Select new entry files to add...',
                                        button: {
                                            text: 'Add to entry'
                                        },
                                        multiple: true  // Set to true to allow multiple files to be selected
                                    });
                                    edit.on('select', function () {
                                        console.log(edit.state().get('selection').toJSON());
                                        var attachment = edit.state().get('selection').toJSON();
                                        var attachment_ids = '';
                                        holder.html('');
                                        $.each(attachment, function (i, item) {
                                            holder.append('<img src="' + item.url + '" alt="" style="max-width:150px;height:auto;"/>');
                                            attachment_ids = attachment_ids + ',' + item.id
                                        })
                                        id.val(attachment_ids);
                                        edit.close();
                                        $('#__wp-uploader-id-0').hide();
                                        $('.supports-drag-drop').hide();
                                    });

                                    edit.open();
                                    return false;
                                });
                            }
                        }
                    });
                </script>
            </div>
        </td>
    </tr>
    <tr>
        <th><?php _e('Entry Status:', 'awards'); ?></th>
        <td>
            <select name="entrystatus" id="entrystatus">
                <option value="pending_payment"><?php _e('Pending Payment', 'awards'); ?></option>
                <option value="pending_review"><?php _e('Pending Review', 'awards'); ?></option>
                <option value="approved"><?php _e('Approved', 'awards'); ?></option>
                <option value="rejected"><?php _e('Rejected', 'awards'); ?></option>
                <option value="winner"><?php _e('Winner', 'awards'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php _e('Winner Status:', 'awards'); ?></th>
        <td>
            <select name="winnerstatus" id="winnerstatus">
                <option value=""><?php _e('-- No Select --', 'awards'); ?></option>
                <option value="none"><?php _e('None', 'awards'); ?></option>
                <option value="firstplace"><?php _e('First place', 'awards'); ?></option>
                <option value="secondplace"><?php _e('second place', 'awards'); ?></option>
                <option value="thirplace"><?php _e('third place', 'awards'); ?></option>
                <option value="honorable"><?php _e('Honorable mention', 'awards'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php _e('Admin Note:', 'awards'); ?></th>
        <td>
            <textarea name="adminnote" id="adminnote" rows="5"
                      style="width: 100%"><?php echo get_post_meta($post->ID, 'adminnote', true); ?></textarea>
            <small><?php _e('the reason or custom note for notice to user', 'awards'); ?></small>
        </td>
    </tr>
    <tr>
        <th><?php _e('Author:', 'awards'); ?></th>
        <td>
            <?php
                $users = get_users(array());
            ?>
            <select name="author" id="author">
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user->ID; ?>"><?php echo $user->display_name; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    </tbody>
</table>