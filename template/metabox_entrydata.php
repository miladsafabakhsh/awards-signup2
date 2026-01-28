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

    .loadingarea{
        position: relative;
    }

    .loadingarea:before{
        position: absolute;
        left: 0;
        right: 0;top: 0;
        bottom: 0;
        z-index: 3;
        content: "";
        background-color: rgba(255, 255, 255, 0.5);
        display: none;
    }

    .loadingarea.loading:before{
        display: block;
    }

</style>
<table class="form-table wp-awards wp-awards-table wp-awards">
    <tbody>
    <tr>
        <th><?php _e('Persons:', 'awards'); ?></th>
        <td>
            <?php
            $persons = get_post_meta($post->ID, 'persons', true);
            if (is_array($persons)) { // Added check for safety
                foreach ($persons as $person) {
                    echo '<div>';
                    if (is_array($person)) { // Added check for safety
                        foreach ($person as $item) {
                            echo $item . ' ';
                        }
                    }
                    echo '</div>';
                }
            }
            ?>
        </td>
    </tr>
    <tr>
        <th><?php _e('Entry type:', 'awards'); ?></th>
        <td>
            <?php
            echo get_post_meta($post->ID, 'entrytype', true);
            ?>
        </td>
    </tr>
    <?php if (awards_options('level_expertise') == 'enable'): ?>
        <tr>
            <th><?php _e('Level of expertise:', 'awards'); ?></th>
            <td>
                <?php
                $entrylevel = get_post_meta($post->ID, 'entrylevel', true);
                switch ($entrylevel) {
                    case 'nonpro':
                        echo __('Non-Professional', 'awards');
                        break;
                    case 'pro':
                        echo __('Professional', 'awards');
                        break;
                }
                ?>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <th>
            <?php _e('Entry files:', 'awards'); ?>
            <br>
            <br>
            <div><input type="button" id="dl-selected-btn" class="button primary main" value="Download selected entries"></div>
            <script>
                jQuery(document).ready(function($) { // Encapsulate in jQuery ready
                    $('#dl-selected-btn').click(function () {
                        var selected_items = $('label.dl-select input:checked');
                        var selected_items_text = '';
                        $.each( selected_items, function( key, value ) {
                            selected_items_text = selected_items_text + '-' + $(value).val();
                        });

                        if(selected_items_text == '') return;

                        var request_url = "<?php echo site_url('wp-admin/?download-entry-files=true&ajax=true&images=')?>" + selected_items_text;
                        console.log(request_url);
                        $.ajax({
                            url: request_url,
                            type:'GET',
                            cache: false,
                            dataType: 'json',
                            beforeSend: function( xhr ) {
                                $('.loadingarea').addClass('loading');
                            },
                            success: function( data ) {
                                console.log(data);
                                if (data.url) { // Check if URL exists
                                    window.location.replace(data.url);
                                }
                            },
                            error: function (err) {
                                console.error('Error: ', err);
                            },
                            complete: function() {
                                $('.loadingarea').removeClass('loading');
                            }
                        });
                    });
                });
            </script>
        </th>
        <td class="loadingarea">
            <?php
            // ================== FIX #1 Start ==================
            // Ensure $entryfiles is an array to prevent errors in foreach and implode
            $entryfiles = get_post_meta($post->ID, 'entryfiles', true);
            if ( ! is_array($entryfiles) ) {
                $entryfiles = array();
            }
            // ================== FIX #1 End ====================
            
            foreach ($entryfiles as $img) {
                echo '<div class="entryfile-item-holder">';
                echo '<a href="' . site_url('/?remove-entry-file=true&image=' . $img . '&post_id=' . $post->ID) . '" class="remove" id="remove-entryfile-' . $img . '">' . __('Delete', 'awards') . '</a>';
                echo '<a href="' . wp_get_attachment_image_url($img, 'full') . '" target="_blank" class="entryfile-item">' . wp_get_attachment_image($img, 'thumbnail', '', array('class' => 'img-thumbnail thumbnail award-thumbnail')) . '</a>';
                echo '<label class="dl-select"><input type="checkbox" name="dl-select[]" value="'.$img.'">Select</label>';
                echo '</div>';
                ?>
                <script>
                    jQuery(document).ready(function($) { // Encapsulate in jQuery ready
                        $('#remove-entryfile-<?php echo $img; ?>').click(function (e) {
                            e.preventDefault();
                            if (confirm("<?php _e('Are you sure you want to delete this image?', 'awards'); ?>")) {
                                window.location.assign($(this).attr('href'));
                            }
                        });
                    });
                </script>
                <?php
            }
            ?>
            
            <input type="hidden" name="entry_files" id="entry_files" value="<?php echo implode(',', $entryfiles); ?>">
            <div>
                <h4 style="color:red;"><?php _e('Add new file to entry:', 'awards'); ?></h4>
                <div class="new_entry_files-holder"></div>
                <input type="hidden" name="new_entry_files" id="new_entry_files" value="">
                <input type="button" id="select_new_entry_files" value="<?php _e('Select entry file', 'awards'); ?>">
                <script>
                    jQuery(document).ready(function () {
                        var $ = jQuery;
                        if ($('#select_new_entry_files').length > 0) {
                            if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                                $(document).on('click', '#select_new_entry_files', function (e) {
                                    e.preventDefault();
                                    var button = $(this);
                                    var id = button.prev();
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
                                        // These lines might be problematic, but I'll leave them
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
                <div style="margin-top: 10px;"><code><?php _e('Note: Save post after add entry', 'awards'); ?></code></div>
            </div>
        </td>
    </tr>
    <tr>
        <th><?php _e('Entry Status:', 'awards'); ?></th>
        <td>
            <?php
            echo get_award_status_title(get_post_meta($post->ID, 'status', true), true);
            ?>
            <div>
                <br>
                <div><label for="entrystatus"><?php _e('Change To:', 'awards'); ?></label></div>
                <select name="entrystatus" id="entrystatus">
                    <option value=""><?php _e('-- No Change --', 'awards'); ?></option>
                    <option value="pending_payment"><?php _e('Pending Payment', 'awards'); ?></option>
                    <option value="pending_review"><?php _e('Pending Review', 'awards'); ?></option>
                    <option value="approved"><?php _e('Approved', 'awards'); ?></option>
                    <option value="rejected"><?php _e('Rejected', 'awards'); ?></option>
                    <option value="winner"><?php _e('Winner', 'awards'); ?></option>
                </select>
            </div>
            <div>
                <br>
                <?php
                echo get_award_winnerstatus_title(get_post_meta($post->ID, 'winner_status', true), true);
                ?>
                <br>
                <select name="winnerstatus" id="winnerstatus">
                    <option value=""><?php _e('-- No Change --', 'awards'); ?></option>
                    <option value="none"><?php _e('None', 'awards'); ?></option>
                    <option value="firstplace"><?php _e('First place', 'awards'); ?></option>
                    <option value="secondplace"><?php _e('second place', 'awards'); ?></option>
                    <option value="thirplace"><?php _e('third place', 'awards'); ?></option>
                    <option value="honorable"><?php _e('Honorable mention', 'awards'); ?></option>
                </select>
            </div>
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
        <th><?php _e('Certificate:', 'awards'); ?></th>
        <td>
            <?php
            wp_enqueue_media();
            $certificate = get_post_meta($post->ID, 'certificate', true);
            ?>
            <input name="certificate" value="<?php echo $certificate; ?>" type="text"
                   class="regular-text ltr"/>
            <button id="select_new_entry_files" type="button"
                    class="button"><?php _e('Select File', 'awards'); ?></button>
            <div class="custom-img-container"></div>
        </td>
        <script>
            jQuery(document).ready(function () {
                var $ = jQuery; // Ensure $ is jQuery
                // Set all variables to be used in scope
                var frame,
                    metaBox = $('#poststuff'), // More reliable parent
                    addImgLink = metaBox.find('button#select_new_entry_files'), // Be more specific
                    // delImgLink = metaBox.find( '.delete-custom-img'),
                    imgContainer = metaBox.find('.custom-img-container'),
                    imgIdInput = metaBox.find('input[name="certificate"]');

                addImgLink.on('click', function (event) {
                    event.preventDefault();
                    // If the media frame already exists, reopen it.
                    if (frame) {
                        frame.open();
                        return;
                    }
                    // Create a new media frame
                    frame = wp.media({
                        title: 'Select or Upload Media Of Your Chosen Persuasion',
                        button: {
                            text: 'Use this media'
                        },
                        multiple: false  // Set to true to allow multiple files to be selected
                    });

                    // When an image is selected in the media frame...
                    frame.on('select', function () {

                        // Get media attachment details from the frame state
                        var attachment = frame.state().get('selection').first().toJSON();
                        // Send the attachment URL to our custom image input field.
                        imgContainer.html('<img src="' + attachment.url + '" alt="" style="max-width:100%;"/>'); // Use .html to replace
                        // Send the attachment id to our hidden input
                        imgIdInput.val(attachment.url);
                    });

                    // Finally, open the modal on click
                    frame.open();
                });
            });
        </script>
        </td>
    </tr>
    <tr>
        <th><?php _e('Author:', 'awards'); ?></th>
        <td><a href="<?php echo site_url('wp-admin/user-edit.php?user_id=' . $post->post_author); ?>"
               target="_blank"><?php echo get_the_author_meta('display_name', $post->post_author); ?></a></td>
    </tr>
    <?php
    $currentmeta = get_post_meta($post->ID, 'payment', true);
    if ($currentmeta):
        ?>
        <tr>
            <th><?php _e('Payment:', 'awards'); ?></th>
            <td>
                <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><?php _e('Gateway', 'awards'); ?>:</td>
                        <td><?php echo isset($currentmeta['gateway']) ? $currentmeta['gateway'] : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Amount', 'awards'); ?>:</td>
                        <td><?php echo isset($currentmeta['Amount']) ? $currentmeta['Amount'] : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('RefID', 'awards'); ?>:</td>
                        <td><?php echo isset($currentmeta['RefID']) ? $currentmeta['RefID'] : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Authority', 'awards'); ?>:</td>
                        <td><?php echo isset($currentmeta['Authority']) ? $currentmeta['Authority'] : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Pay Time', 'awards'); ?>:</td>
                        <td><?php echo isset($currentmeta['time']) ? $currentmeta['time'] : 'N/A'; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
<?php
// Add invoice section if payment exists
// Note: This logic was already in the file, just leaving it as-is
if ($currentmeta):
    $invoice_url = get_post_meta($post->ID, 'invoice_url', true);
    
    // If no invoice but payment exists, try to generate one
    if (!$invoice_url && function_exists('generate_entry_invoice')) {
        // Fix any missing fields in payment details to prevent errors
        if (!isset($currentmeta['time'])) {
            $currentmeta['time'] = get_the_modified_date('Y-m-d H:i:s', $post->ID);
        }
        
        if (!isset($currentmeta['gateway'])) {
            $currentmeta['gateway'] = 'unknown';
        }
        
        if (!isset($currentmeta['RefID'])) {
            $currentmeta['RefID'] = 'generated-' . $post->ID;
        }
        
        if (!isset($currentmeta['Authority'])) {
            $currentmeta['Authority'] = 'generated-auth-' . $post->ID;
        }
        
        $invoice_path = generate_entry_invoice($post->ID, $currentmeta);
        if ($invoice_path) {
            $invoice_url = get_post_meta($post->ID, 'invoice_url', true);
        }
    }
    ?>
    <tr>
        <th><?php _e('Invoice:', 'awards'); ?></th>
        <td>
            <?php if ($invoice_url): ?>
                <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button">
                    <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Download Invoice', 'awards'); ?>
                </a>
                
                <?php if (function_exists('generate_entry_invoice')): ?>
                <button type="button" class="button regenerate-invoice" data-entry-id="<?php echo $post->ID; ?>">
                    <?php _e('Regenerate Invoice', 'awards'); ?>
                </button>
                <span id="regenerate-invoice-spinner" class="spinner" style="float: none; margin-top: 2px;"></span>
                <div id="regenerate-invoice-result"></div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (function_exists('generate_entry_invoice')): ?>
                <button type="button" class="button button-primary generate-invoice" data-entry-id="<?php echo $post->ID; ?>">
                    <?php _e('Generate Invoice', 'awards'); ?>
                </button>
                <span id="generate-invoice-spinner" class="spinner" style="float: none; margin-top: 2px;"></span>
                <div id="generate-invoice-result"></div>
                <?php else: ?>
                <span class="description"><?php _e('Invoice generation function not available.', 'awards'); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </td>
    </tr>
<?php endif; ?>
    <?php endif; ?>
    </tbody>
</table>