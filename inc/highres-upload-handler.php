<?php
/**
 * High-Resolution Image Upload Handler
 * Add this file to your Awards Signup plugin
 * 
 * Location: awards-signup/inc/highres-upload-handler.php
 */

if (!defined('ABSPATH')) exit;

class Awards_HighRes_Upload {
    
    private $min_dimension = 3000; // Minimum pixels on shortest side
    private $max_filesize = 52428800; // 50MB in bytes
    private $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/tiff');
    
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_upload_highres_image', array($this, 'ajax_upload_highres'));
        add_action('wp_ajax_delete_highres_image', array($this, 'ajax_delete_highres'));
        add_action('wp_ajax_send_highres_reminder', array($this, 'ajax_send_reminder'));
        add_action('wp_ajax_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_send_finalist_raw_request', array($this, 'ajax_send_finalist_raw_request'));
        add_action('wp_ajax_admin_delete_highres', array($this, 'ajax_admin_delete_highres'));
        add_action('wp_ajax_admin_bulk_delete_highres', array($this, 'ajax_admin_bulk_delete_highres'));
        
        // Admin hooks
        add_action('add_meta_boxes', array($this, 'add_highres_metabox'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Cleanup: Remove high-res requirement when entry is demoted from Top 3
        add_action('save_post_entry', array($this, 'check_winner_status_change'), 20);
    }
    
    /**
     * Check if winner status changed and clean up if demoted from Top 3
     */
    public function check_winner_status_change($post_id) {
        // Only run on entry posts
        if (get_post_type($post_id) !== 'entry') {
            return;
        }
        
        // Check if currently Top 3
        $is_top3 = $this->is_top3_winner($post_id);
        $had_highres = get_post_meta($post_id, '_high_res_files', true);
        
        // If they were Top 3 before (had upload requirement) but not anymore, log it
        if (!$is_top3 && $had_highres) {
            // Add a note that they were demoted (for admin reference)
            update_post_meta($post_id, '_high_res_status_note', 'Entry was demoted from Top 3 on ' . current_time('mysql'));
        }
        
        // Note: We don't delete the high-res files in case admin wants to restore them
        // They're just hidden from the user dashboard automatically
    }
    
    /**
     * Disable image compression by returning empty editors array
     */
    public function disable_image_compression($editors) {
        return array();
    }
    
    /**
     * AJAX: Upload high-res image
     */
    public function ajax_upload_highres() {
        check_ajax_referer('awards_highres_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }
        
        $entry_id = intval($_POST['entry_id']);
        $image_index = intval($_POST['image_index']); // Which image in the series (0-based)
        
        // Verify ownership
        $entry = get_post($entry_id);
        if (!$entry || $entry->post_author != get_current_user_id()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        // Verify entry is a Top 3 winner
        if (!$this->is_top3_winner($entry_id)) {
            wp_send_json_error(array('message' => 'Only Top 3 winners can upload high-res images'));
        }
        
        // Validate file
        if (empty($_FILES['highres_file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }
        
        $file = $_FILES['highres_file'];
        $validation = $this->validate_file($file);
        
        if (!$validation['valid']) {
            wp_send_json_error(array('message' => $validation['error']));
        }
        
        // Upload to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // DISABLE WordPress image compression for high-res uploads
        add_filter('big_image_size_threshold', '__return_false'); // Disable 2560px limit
        add_filter('wp_image_editors', array($this, 'disable_image_compression'), 10, 1);
        
        $attachment_id = media_handle_upload('highres_file', $entry_id);
        
        // Re-enable compression after upload
        remove_filter('big_image_size_threshold', '__return_false');
        remove_filter('wp_image_editors', array($this, 'disable_image_compression'), 10);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }
        
        // Store ORIGINAL file dimensions and size, not WordPress metadata
        $file_path = get_attached_file($attachment_id);
        $image_info = getimagesize($file_path);
        $file_size = filesize($file_path);
        
        // Store original specs
        update_post_meta($attachment_id, '_original_dimensions', $image_info[0] . 'x' . $image_info[1]);
        update_post_meta($attachment_id, '_original_filesize', $file_size);
        
        // Store in meta array
        $highres_files = get_post_meta($entry_id, '_high_res_files', true);
        if (!is_array($highres_files)) {
            $highres_files = array();
        }
        
        $highres_files[$image_index] = $attachment_id;
        update_post_meta($entry_id, '_high_res_files', $highres_files);
        update_post_meta($entry_id, '_high_res_upload_date', current_time('mysql'));
        
        // Get image data for response - use ORIGINAL dimensions we just stored
        $image_url = wp_get_attachment_url($attachment_id);
        
        wp_send_json_success(array(
            'message' => 'High-res image uploaded successfully',
            'attachment_id' => $attachment_id,
            'url' => $image_url,
            'dimensions' => $image_info[0] . '×' . $image_info[1],
            'filesize' => size_format($file_size)
        ));
    }
    
    /**
     * AJAX: Delete high-res image
     */
    public function ajax_delete_highres() {
        check_ajax_referer('awards_highres_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }
        
        $entry_id = intval($_POST['entry_id']);
        $image_index = intval($_POST['image_index']);
        
        // Verify ownership
        $entry = get_post($entry_id);
        if (!$entry || $entry->post_author != get_current_user_id()) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $highres_files = get_post_meta($entry_id, '_high_res_files', true);
        if (isset($highres_files[$image_index])) {
            $attachment_id = $highres_files[$image_index];
            wp_delete_attachment($attachment_id, true);
            unset($highres_files[$image_index]);
            update_post_meta($entry_id, '_high_res_files', $highres_files);
        }
        
        wp_send_json_success(array('message' => 'High-res image deleted'));
    }
    
    /**
     * AJAX: Send reminder email to winners who haven't uploaded (ADMIN ONLY)
     */
    public function ajax_send_reminder() {
        check_ajax_referer('awards_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        // Get Top 3 winners without high-res images
        $winners_missing = $this->get_winners_missing_highres($category_id);
        
        $sent_count = 0;
        foreach ($winners_missing as $winner) {
            $sent = $this->send_reminder_email($winner->entry_id);
            if ($sent) $sent_count++;
        }
        
        wp_send_json_success(array(
            'message' => "Reminder emails sent to {$sent_count} winners",
            'count' => $sent_count
        ));
    }
    
    /**
     * AJAX: Send raw file request to finalists (ADMIN ONLY)
     */
    public function ajax_send_finalist_raw_request() {
        check_ajax_referer('awards_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $recipients_json = isset($_POST['recipients']) ? $_POST['recipients'] : '';
        $recipients = json_decode(stripslashes($recipients_json), true);
        
        $custom_subject = isset($_POST['email_subject']) ? sanitize_text_field($_POST['email_subject']) : '';
        $custom_message = isset($_POST['email_message']) ? $_POST['email_message'] : '';
        
        if (empty($recipients) || !is_array($recipients)) {
            wp_send_json_error(array('message' => 'No recipients provided'));
        }
        
        $sent_count = 0;
        $failed = array();
        
        foreach ($recipients as $recipient) {
            if (empty($recipient['email']) || empty($recipient['title'])) {
                continue;
            }
            
            $sent = $this->send_finalist_raw_email($recipient['email'], $recipient['title'], $custom_subject, $custom_message);
            if ($sent) {
                $sent_count++;
            } else {
                $failed[] = $recipient['email'];
            }
        }
        
        if ($sent_count > 0) {
            $message = "Raw file request sent to {$sent_count} finalist(s)";
            if (!empty($failed)) {
                $message .= ". Failed to send to: " . implode(', ', $failed);
            }
            wp_send_json_success(array('message' => $message, 'count' => $sent_count));
        } else {
            wp_send_json_error(array('message' => 'Failed to send emails'));
        }
    }
    
    /**
     * Send finalist raw file request email
     */
    private function send_finalist_raw_email($email, $entry_title, $custom_subject = '', $custom_message = '') {
        // Use custom template if provided, otherwise use default
        $subject = !empty($custom_subject) ? $custom_subject : 'Finalist Notification - Raw Image Required for Verification';
        
        $deadline_date = date('F j, Y', strtotime('+10 days'));
        
        if (!empty($custom_message)) {
            // Use custom message with placeholder replacement
            $message = str_replace('[ENTRY_TITLE]', $entry_title, $custom_message);
            $message = str_replace('[DEADLINE]', $deadline_date, $message);
        } else {
            // Default message
            $message = "Dear Photographer,\n\n";
            $message .= "Congratulations! Your entry \"{$entry_title}\" has been selected as a finalist in the Minimalist Photography Awards.\n\n";
            $message .= "ACTION REQUIRED:\n\n";
            $message .= "To ensure the integrity of our competition and verify that images are not AI-generated, we require all finalists to submit raw or unedited versions of their images.\n\n";
            $message .= "SUBMISSION INSTRUCTIONS:\n";
            $message .= "• Reply to this email with your raw/unedited image file\n";
            $message .= "• If file size exceeds 20MB, please use WeTransfer (wetransfer.com) or Google Drive and share the link\n";
            $message .= "• DEADLINE: {$deadline_date} (10 days from today)\n\n";
            $message .= "ACCEPTABLE FORMATS:\n";
            $message .= "• RAW files (.CR2, .NEF, .ARW, .DNG, .RAF, etc.)\n";
            $message .= "• Original unedited JPG files\n\n";
            $message .= "If you encounter any issues meeting the deadline, please contact us immediately.\n\n";
            $message .= "Thank you for your participation in the Minimalist Photography Awards!\n\n";
            $message .= "Best regards,\n";
            $message .= "Minimalist Photography Awards Team";
        }
        
        $message .= "\n\n---\n";
        $message .= "Please reply to: " . get_option('admin_email');
        
        return wp_mail($email, $subject, $message);
    }
    
    /**
     * AJAX: Admin delete single high-res file
     */
    public function ajax_admin_delete_highres() {
        check_ajax_referer('awards_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $entry_id = intval($_POST['entry_id']);
        
        // Delete the attachment from server
        $deleted = wp_delete_attachment($attachment_id, true);
        
        if ($deleted) {
            // Remove from meta array
            $highres_files = get_post_meta($entry_id, '_high_res_files', true);
            if (is_array($highres_files)) {
                $highres_files = array_filter($highres_files, function($id) use ($attachment_id) {
                    return $id != $attachment_id;
                });
                update_post_meta($entry_id, '_high_res_files', $highres_files);
            }
            
            wp_send_json_success(array('message' => 'High-res file deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete file'));
        }
    }
    
    /**
     * AJAX: Admin bulk delete high-res files
     */
    public function ajax_admin_bulk_delete_highres() {
        check_ajax_referer('awards_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $deletions_json = isset($_POST['deletions']) ? $_POST['deletions'] : '';
        $deletions = json_decode(stripslashes($deletions_json), true);
        
        if (empty($deletions) || !is_array($deletions)) {
            wp_send_json_error(array('message' => 'No files selected'));
        }
        
        $deleted_count = 0;
        $failed = array();
        
        foreach ($deletions as $deletion) {
            $attachment_id = intval($deletion['attachment_id']);
            $entry_id = intval($deletion['entry_id']);
            
            $deleted = wp_delete_attachment($attachment_id, true);
            
            if ($deleted) {
                // Remove from meta array
                $highres_files = get_post_meta($entry_id, '_high_res_files', true);
                if (is_array($highres_files)) {
                    $highres_files = array_filter($highres_files, function($id) use ($attachment_id) {
                        return $id != $attachment_id;
                    });
                    update_post_meta($entry_id, '_high_res_files', $highres_files);
                }
                $deleted_count++;
            } else {
                $failed[] = $attachment_id;
            }
        }
        
        if ($deleted_count > 0) {
            $message = "Deleted {$deleted_count} high-res file(s) from server";
            if (!empty($failed)) {
                $message .= ". Failed: " . count($failed);
            }
            wp_send_json_success(array('message' => $message, 'count' => $deleted_count));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete files'));
        }
    }
    
    /**
     * AJAX: Send test email (ADMIN ONLY)
     */
    public function ajax_send_test_email() {
        check_ajax_referer('awards_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }
        
        $admin_email = get_option('admin_email');
        $admin_user = wp_get_current_user();
        
        // Send EXACT same email that winners receive
        $subject = 'Action Required: Upload High-Resolution Images - Minimalist Photography Awards';
        
        $message = "Dear {$admin_user->display_name},\n\n";
        $message .= "Congratulations again on winning a Top 3 award in the Minimalist Photography Awards!\n\n";
        $message .= "To include your winning entry \"[Entry Title Example]\" in the official awards book, we need you to upload high-resolution versions of your images.\n\n";
        $message .= "Requirements:\n";
        $message .= "• Minimum 3000 pixels on the shortest side\n";
        $message .= "• JPG, PNG, or TIFF format\n";
        $message .= "• Maximum 50MB per file\n\n";
        $message .= "Please upload your high-res images here:\n";
        $message .= home_url('/dashboard/?dash-page=entry-list') . "\n\n";
        $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
        $message .= "Best regards,\n";
        $message .= "Minimalist Photography Awards Team\n\n";
        $message .= "---\n";
        $message .= "This is a TEST EMAIL sent to admin ({$admin_email})\n";
        $message .= "Winners will receive the same message with their actual entry title.";
        
        $sent = wp_mail($admin_email, $subject, $message);
        
        if ($sent) {
            wp_send_json_success(array(
                'message' => "Test email sent to {$admin_email} - Check your inbox to see what winners receive!",
                'email' => $admin_email
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to send test email. Check your email configuration.',
                'email' => $admin_email
            ));
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        $result = array('valid' => false, 'error' => '');
        
        // Check file size
        if ($file['size'] > $this->max_filesize) {
            $result['error'] = 'File too large. Maximum 50MB.';
            return $result;
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            $result['error'] = 'Invalid file type. Only JPG, PNG, and TIFF allowed.';
            return $result;
        }
        
        // Check dimensions
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            $result['error'] = 'Invalid image file.';
            return $result;
        }
        
        list($width, $height) = $image_info;
        $shortest_side = min($width, $height);
        
        if ($shortest_side < $this->min_dimension) {
            $result['error'] = "Image too small. Minimum {$this->min_dimension}px on shortest side. Your image: {$shortest_side}px.";
            return $result;
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    /**
     * Check if entry is a Top 3 winner in any category
     * Checks BOTH Awards Winners Manager table AND your old winner_status meta field
     */
    private function is_top3_winner($entry_id) {
        // Method 1: Check Awards Winners Manager table
        global $wpdb;
        $winner = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}awm_winners
            WHERE entry_id = %d 
            AND winner_type = 'top3'
            AND position BETWEEN 1 AND 3
        ", $entry_id));
        
        if ($winner > 0) {
            return true;
        }
        
        // Method 2: Check your existing winner_status meta field
        $winner_status = get_post_meta($entry_id, 'winner_status', true);
        if (in_array($winner_status, array('firstplace', 'secondplace', 'thirplace'))) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get winners missing high-res images
     */
    private function get_winners_missing_highres($category_id = 0) {
        global $wpdb;
        
        $where_cat = $category_id ? $wpdb->prepare("AND category_id = %d", $category_id) : "";
        
        $winners = $wpdb->get_results("
            SELECT entry_id, category_id, position 
            FROM {$wpdb->prefix}awm_winners
            WHERE winner_type = 'top3'
            AND position BETWEEN 1 AND 3
            {$where_cat}
            ORDER BY category_id, position
        ");
        
        $missing = array();
        foreach ($winners as $winner) {
            $highres = get_post_meta($winner->entry_id, '_high_res_files', true);
            if (empty($highres)) {
                $missing[] = $winner;
            }
        }
        
        return $missing;
    }
    
    /**
     * Send reminder email to winner
     */
    private function send_reminder_email($entry_id) {
        $entry = get_post($entry_id);
        $user = get_user_by('id', $entry->post_author);
        
        if (!$user) return false;
        
        $subject = 'Action Required: Upload High-Resolution Images - Minimalist Photography Awards';
        
        $message = "Dear {$user->display_name},\n\n";
        $message .= "Congratulations again on winning a Top 3 award in the Minimalist Photography Awards!\n\n";
        $message .= "To include your winning entry \"{$entry->post_title}\" in the official awards book, we need you to upload high-resolution versions of your images.\n\n";
        $message .= "Requirements:\n";
        $message .= "• Minimum 3000 pixels on the shortest side\n";
        $message .= "• JPG, PNG, or TIFF format\n";
        $message .= "• Maximum 50MB per file\n\n";
        $message .= "Please upload your high-res images here:\n";
        $message .= home_url('/my-account/entries/') . "\n\n";
        $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
        $message .= "Best regards,\n";
        $message .= "Minimalist Photography Awards Team";
        
        return wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Add meta box to admin entry edit screen
     */
    public function add_highres_metabox() {
        add_meta_box(
            'highres_images',
            '🏆 High-Resolution Images (Top 3 Winners Only)',
            array($this, 'render_admin_metabox'),
            'entry',
            'normal',
            'high'
        );
    }
    
    /**
     * Render admin meta box
     */
    public function render_admin_metabox($post) {
        if (!$this->is_top3_winner($post->ID)) {
            echo '<p>This entry is not a Top 3 winner. High-res uploads are only for Top 3 winners.</p>';
            return;
        }
        
        $highres_files = get_post_meta($post->ID, '_high_res_files', true);
        $upload_date = get_post_meta($post->ID, '_high_res_upload_date', true);
        $entry_files = get_post_meta($post->ID, 'entryfiles', true);
        $is_series = is_array($entry_files) && count($entry_files) > 1;
        
        echo '<div class="highres-admin-box">';
        
        if ($upload_date) {
            echo '<p><strong>✓ High-res uploaded:</strong> ' . date('F j, Y g:i a', strtotime($upload_date)) . '</p>';
        } else {
            echo '<p><strong>⚠ High-res not uploaded yet</strong></p>';
        }
        
        echo '<table class="widefat">';
        echo '<thead><tr><th>Image</th><th>Standard Version</th><th>High-Res Version</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        
        if ($is_series) {
            foreach ($entry_files as $index => $file_id) {
                $this->render_highres_row($index, $file_id, $highres_files);
            }
        } else {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $this->render_highres_row(0, $thumbnail_id, $highres_files);
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Render single high-res row in admin
     */
    private function render_highres_row($index, $standard_id, $highres_files) {
        $standard_url = wp_get_attachment_image_url($standard_id, 'thumbnail');
        $has_highres = isset($highres_files[$index]);
        
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td><img src="' . esc_url($standard_url) . '" style="max-width:100px;"></td>';
        
        if ($has_highres) {
            $highres_id = $highres_files[$index];
            $highres_url = wp_get_attachment_url($highres_id);
            $metadata = wp_get_attachment_metadata($highres_id);
            
            echo '<td>';
            echo '<a href="' . esc_url($highres_url) . '" target="_blank">View Full Size</a><br>';
            echo '<small>' . $metadata['width'] . 'x' . $metadata['height'] . 'px</small><br>';
            echo '<small>' . size_format($metadata['filesize']) . '</small>';
            echo '</td>';
            echo '<td><span style="color:green;">✓ Uploaded</span></td>';
        } else {
            echo '<td>—</td>';
            echo '<td><span style="color:orange;">⚠ Missing</span></td>';
        }
        
        echo '</tr>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            global $post;
            if ($post && $post->post_type === 'entry') {
                wp_enqueue_style('highres-admin', plugins_url('css/highres-admin.css', __FILE__));
            }
        }
    }
}

// Initialize
new Awards_HighRes_Upload();
