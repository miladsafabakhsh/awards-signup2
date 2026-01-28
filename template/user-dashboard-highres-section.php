<!-- 
    User Dashboard High-Res Upload Section
    Add this to your Awards Signup user dashboard template
    Location: awards-signup/template/user-dashboard-entries.php
    
    Insert this code in the loop where you display user's entries
-->

<?php
/**
 * Check if entry is Top 3 winner - checks BOTH systems
 */
$is_top3_winner = false;
$winner_data = null;

// Method 1: Check Awards Winners Manager table
global $wpdb;
$winner_data = $wpdb->get_row($wpdb->prepare("
    SELECT winner_type, position, category_id 
    FROM {$wpdb->prefix}awm_winners
    WHERE entry_id = %d 
    AND winner_type = 'top3'
    AND position BETWEEN 1 AND 3
", $entry->ID));

if (!empty($winner_data)) {
    $is_top3_winner = true;
}

// Method 2: Check your existing winner_status meta field
$winner_status = get_post_meta($entry->ID, 'winner_status', true);
if (in_array($winner_status, array('firstplace', 'secondplace', 'thirplace'))) {
    $is_top3_winner = true;
    
    // Create fake winner_data for display if not from awm_winners
    if (!$winner_data) {
        $winner_data = new stdClass();
        $positions = array('firstplace' => 1, 'secondplace' => 2, 'thirplace' => 3);
        $winner_data->position = $positions[$winner_status];
        
        // Get category from entry taxonomy
        $categories = wp_get_post_terms($entry->ID, 'entrycat');
        $winner_data->category_id = !empty($categories) ? $categories[0]->term_id : 0;
    }
}
?>


<?php if ($is_top3_winner): ?>
    <?php
    $category = get_term($winner_data->category_id, 'entrycat');
    $position_labels = array(1 => 'First Place', 2 => 'Second Place', 3 => 'Third Place');
    $position_label = $position_labels[$winner_data->position];
    
    $highres_files = get_post_meta($entry->ID, '_high_res_files', true);
    $entry_files = get_post_meta($entry->ID, 'entryfiles', true);
    $is_series = is_array($entry_files) && count($entry_files) > 1;
    $upload_date = get_post_meta($entry->ID, '_high_res_upload_date', true);
    ?>
    
    <!-- High-Res Upload Section -->
    <div class="highres-upload-section" style="margin: 15px 20px; padding: 20px; background: #f8f9fa; border-left: 3px solid #f99800; border-radius: 4px;">
        
        <div class="highres-header" style="margin-bottom: 15px;">
            <h3 style="margin: 0 0 5px 0; color: #333; font-size: 16px;">
                🏆 <?php echo esc_html($position_label); ?> Winner - High-Resolution Images Required
            </h3>
            <p style="margin: 0; color: #666; font-size: 13px;">
                <strong>Category:</strong> <?php echo esc_html($category->name); ?>
            </p>
        </div>
        
        <?php if ($upload_date): ?>
            <div class="highres-status-complete" style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0; color: #155724; font-size: 13px;">
                    ✓ High-resolution images uploaded on <?php echo date('F j, Y', strtotime($upload_date)); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="highres-status-pending" style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0; color: #856404; font-size: 13px;">
                    ⚠ Action Required: Please upload high-resolution versions of your winning images for the awards book.
                </p>
            </div>
        <?php endif; ?>
        
        <div class="highres-requirements" style="margin-bottom: 15px; padding: 12px; background: white; border: 1px solid #ddd; border-radius: 4px;">
            <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #333;">📸 High-Resolution Image Requirements:</h4>
            <ul style="margin: 0; padding-left: 20px; font-size: 12px; color: #666; line-height: 1.6;">
                <li>Minimum: <strong>3000 pixels</strong> on shortest side</li>
                <li>Format: <strong>JPG, TIFF, or PNG</strong></li>
                <li>Color Space: RGB or CMYK</li>
                <li>Max file size: <strong>50MB per image</strong></li>
                <li>Same image as your original submission (higher quality)</li>
            </ul>
        </div>
        
        <div class="highres-upload-grid">
            <?php
            // Determine which images to show
            $images_to_show = array();
            
            if ($is_series && is_array($entry_files)) {
                foreach ($entry_files as $index => $file_id) {
                    $images_to_show[] = array(
                        'index' => $index,
                        'id' => $file_id,
                        'label' => 'Image ' . ($index + 1)
                    );
                }
            } else {
                $thumbnail_id = get_post_thumbnail_id($entry->ID);
                $images_to_show[] = array(
                    'index' => 0,
                    'id' => $thumbnail_id,
                    'label' => 'Entry Image'
                );
            }
            ?>
            
            <?php foreach ($images_to_show as $img_data): ?>
                <?php
                $has_highres = is_array($highres_files) && isset($highres_files[$img_data['index']]);
                $standard_url = wp_get_attachment_image_url($img_data['id'], 'medium');
                ?>
                
                <div class="highres-upload-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        
                        <!-- Standard Image Preview -->
                        <div style="flex: 0 0 150px;">
                            <img src="<?php echo esc_url($standard_url); ?>" 
                                 alt="<?php echo esc_attr($img_data['label']); ?>" 
                                 style="width: 100%; height: auto; border-radius: 4px;">
                            <p style="margin: 5px 0 0 0; font-size: 12px; text-align: center; color: #666;">
                                <?php echo esc_html($img_data['label']); ?>
                            </p>
                        </div>
                        
                        <!-- Upload Interface -->
                        <div style="flex: 1;">
                            <?php if ($has_highres): ?>
                                <?php
                                $highres_id = $highres_files[$img_data['index']];
                                $highres_url = wp_get_attachment_url($highres_id);
                                $file_path = get_attached_file($highres_id);
                                
                                // Get ORIGINAL dimensions (not WordPress compressed)
                                $original_dims = get_post_meta($highres_id, '_original_dimensions', true);
                                $original_size = get_post_meta($highres_id, '_original_filesize', true);
                                
                                // Fallback to actual file if meta not set (for old uploads)
                                if (!$original_dims && file_exists($file_path)) {
                                    $image_info = getimagesize($file_path);
                                    $original_dims = $image_info[0] . '×' . $image_info[1];
                                    $original_size = filesize($file_path);
                                }
                                ?>
                                <div class="highres-uploaded">
                                    <p style="margin: 0 0 10px 0; color: green; font-weight: bold;">
                                        ✓ High-Res Uploaded
                                    </p>
                                    <p style="margin: 0 0 5px 0; font-size: 13px; color: #666;">
                                        <strong>Dimensions:</strong> <?php echo $original_dims; ?> px
                                    </p>
                                    <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                                        <strong>File Size:</strong> <?php echo size_format($original_size); ?>
                                    </p>
                                    <div style="display: flex; gap: 10px;">
                                        <a href="<?php echo esc_url($highres_url); ?>" 
                                           target="_blank" 
                                           class="button button-small"
                                           style="text-decoration: none;">
                                            View Full Size
                                        </a>
                                        <button type="button" 
                                                class="button button-small delete-highres-btn" 
                                                data-entry-id="<?php echo esc_attr($entry->ID); ?>"
                                                data-image-index="<?php echo esc_attr($img_data['index']); ?>"
                                                style="color: #dc3545;">
                                            Replace
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="highres-upload-form">
                                    <p style="margin: 0 0 10px 0; color: #856404;">
                                        ⚠ High-resolution version not uploaded
                                    </p>
                                    
                                    <input type="file" 
                                           class="highres-file-input" 
                                           id="highres-input-<?php echo $entry->ID; ?>-<?php echo $img_data['index']; ?>"
                                           accept="image/jpeg,image/jpg,image/png,image/tiff"
                                           style="display: none;">
                                    
                                    <button type="button" 
                                            class="button button-primary upload-highres-btn"
                                            data-entry-id="<?php echo esc_attr($entry->ID); ?>"
                                            data-image-index="<?php echo esc_attr($img_data['index']); ?>"
                                            style="background-color: #f99800; border-color: #f99800;">
                                        📤 Upload High-Res Image
                                    </button>
                                    
                                    <div class="upload-progress" style="display: none; margin-top: 10px;">
                                        <div style="background: #f0f0f0; border-radius: 4px; overflow: hidden; height: 20px;">
                                            <div class="progress-bar" style="background: #f99800; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                        </div>
                                        <p class="upload-status" style="margin: 5px 0 0 0; font-size: 12px; color: #666;">Uploading...</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    </div>
    
    <!-- JavaScript for High-Res Upload -->
    <script>
    jQuery(document).ready(function($) {
        
        // Upload button click - use event delegation and prevent double-trigger
        $(document).off('click', '.upload-highres-btn'); // Remove any existing handlers
        $(document).on('click', '.upload-highres-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            
            // Prevent double-click
            if ($btn.data('clicking')) {
                return false;
            }
            $btn.data('clicking', true);
            
            var entryId = $btn.data('entry-id');
            var imageIndex = $btn.data('image-index');
            var $input = $('#highres-input-' + entryId + '-' + imageIndex);
            
            // Trigger file input
            $input.trigger('click');
            
            // Reset click protection after delay
            setTimeout(function() {
                $btn.data('clicking', false);
            }, 1000);
            
            return false;
        });
        
        // File selected - remove any duplicate handlers first
        $(document).off('change', '.highres-file-input');
        $(document).on('change', '.highres-file-input', function(e) {
            var file = this.files[0];
            var $input = $(this);
            
            if (!file) return;
            
            var entryId = $input.closest('.highres-upload-form').find('.upload-highres-btn').data('entry-id');
            var imageIndex = $input.closest('.highres-upload-form').find('.upload-highres-btn').data('image-index');
            var $form = $input.closest('.highres-upload-form');
            var $progress = $form.find('.upload-progress');
            var $progressBar = $progress.find('.progress-bar');
            var $status = $progress.find('.upload-status');
            
            // Validate file size
            if (file.size > 52428800) { // 50MB
                alert('File too large! Maximum size is 50MB.');
                $input.val(''); // Clear input
                return;
            }
            
            // Show progress
            $progress.show();
            $progressBar.css('width', '10%');
            $status.text('Uploading...');
            
            // Create FormData
            var formData = new FormData();
            formData.append('action', 'upload_highres_image');
            formData.append('nonce', '<?php echo wp_create_nonce('awards_highres_nonce'); ?>');
            formData.append('entry_id', entryId);
            formData.append('image_index', imageIndex);
            formData.append('highres_file', file);
            
            // Upload via AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 100);
                            $progressBar.css('width', percent + '%');
                            $status.text('Uploading... ' + percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    $input.val(''); // Clear input immediately
                    
                    if (response.success) {
                        $progressBar.css('width', '100%');
                        $status.text('✓ Upload complete!').css('color', 'green');
                        
                        // Reload page after 1 second
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert('Upload failed: ' + response.data.message);
                        $progress.hide();
                    }
                },
                error: function() {
                    $input.val(''); // Clear input on error too
                    alert('Upload error. Please try again.');
                    $progress.hide();
                }
            });
            
            // Reset file input after upload
            $(this).val('');
        });
        
        // Delete/Replace button - use event delegation
        $(document).off('click', '.delete-highres-btn');
        $(document).on('click', '.delete-highres-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!confirm('Are you sure you want to replace this high-res image?')) {
                return false;
            }
            
            var $btn = $(this);
            var entryId = $btn.data('entry-id');
            var imageIndex = $btn.data('image-index');
            var $item = $btn.closest('.highres-upload-item');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'delete_highres_image',
                    nonce: '<?php echo wp_create_nonce('awards_highres_nonce'); ?>',
                    entry_id: entryId,
                    image_index: imageIndex
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Delete failed: ' + response.data.message);
                    }
                }
            });
        });
    });
    </script>
    
<?php endif; ?>
