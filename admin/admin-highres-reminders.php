<?php
/**
 * Admin Page: High-Res Upload Reminder System
 * Add this to your Awards Winners Manager or Signup plugin
 * 
 * Creates admin menu page for sending reminder emails
 */

if (!defined('ABSPATH')) exit;

class Awards_HighRes_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=entry',
            'High-Res Reminders',
            'High-Res Status',
            'manage_options',
            'highres-reminders',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        // Get all categories
        $categories = get_terms(array(
            'taxonomy' => 'entrycat',
            'hide_empty' => false
        ));
        
        ?>
        <div class="wrap">
            <h1>🏆 High-Res Upload Status & Reminders</h1>
            <p>Monitor Top 3 winners and send reminder emails to those who haven't uploaded high-resolution images.</p>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Filter by Category</h2>
                <form method="get" action="">
                    <input type="hidden" name="post_type" value="entry">
                    <input type="hidden" name="page" value="highres-reminders">
                    
                    <select name="category_filter" id="category-filter" style="min-width: 250px;">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>" 
                                    <?php selected(isset($_GET['category_filter']) ? $_GET['category_filter'] : 0, $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <?php
            $selected_category = isset($_GET['category_filter']) ? intval($_GET['category_filter']) : 0;
            $this->display_winners_table($selected_category);
            ?>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>📧 Send Raw File Request to Finalists</h2>
                <p>Send emails to finalists requesting raw/unedited images for AI verification.</p>
                
                <div id="finalist-email-results" style="margin: 15px 0; padding: 10px; display: none;"></div>
                
                <div id="finalist-recipients">
                    <div class="finalist-recipient-row" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                        <div style="display: flex; gap: 15px; align-items: flex-start; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Email:</label>
                                <input type="email" class="finalist-email" style="width: 100%; padding: 8px;" placeholder="photographer@example.com" required>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Entry Title:</label>
                                <input type="text" class="finalist-entry-title" style="width: 100%; padding: 8px;" placeholder="Entry Title" required>
                            </div>
                            <div style="padding-top: 28px;">
                                <button type="button" class="button remove-recipient-btn" style="color: #dc3545;">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" id="add-recipient-btn" class="button" style="margin-bottom: 15px;">
                    ➕ Add Another Recipient
                </button>
                
                <div style="margin: 20px 0; padding: 15px; background: #f0f0f0; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;">Email Template:</h4>
                        <button type="button" id="edit-email-template-btn" class="button">
                            ✏️ Edit Template
                        </button>
                    </div>
                    
                    <!-- Preview Mode -->
                    <div id="email-template-preview" style="font-size: 13px; color: #666; line-height: 1.6;">
                        <strong>Subject:</strong> <span id="preview-subject">Finalist Notification - Raw Image Required for Verification</span><br><br>
                        <strong>Message:</strong><br>
                        <div id="preview-message" style="white-space: pre-line;">Dear Photographer,

Congratulations! Your entry "[Entry Title]" has been selected as a finalist in the Minimalist Photography Awards.

ACTION REQUIRED:

To ensure the integrity of our competition and verify that images are not AI-generated, we require all finalists to submit raw or unedited versions of their images.

SUBMISSION INSTRUCTIONS:
• Reply to this email with your raw/unedited image file
• If file size exceeds 20MB, please use WeTransfer (wetransfer.com) or Google Drive and share the link
• DEADLINE: [10 days from today]

ACCEPTABLE FORMATS:
• RAW files (.CR2, .NEF, .ARW, .DNG, .RAF, etc.)
• Original unedited JPG files

If you encounter any issues meeting the deadline, please contact us immediately.

Thank you for your participation in the Minimalist Photography Awards!

Best regards,
Minimalist Photography Awards Team</div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div id="email-template-editor" style="display: none;">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Subject Line:</label>
                            <input type="text" id="email-subject" style="width: 100%; padding: 8px;" value="Finalist Notification - Raw Image Required for Verification">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Message Body:</label>
                            <p style="font-size: 12px; color: #666; margin: 5px 0;">Use <code>[ENTRY_TITLE]</code> and <code>[DEADLINE]</code> as placeholders</p>
                            <textarea id="email-message" rows="20" style="width: 100%; padding: 8px; font-family: monospace; font-size: 13px;">Dear Photographer,

Congratulations! Your entry "[ENTRY_TITLE]" has been selected as a finalist in the Minimalist Photography Awards.

ACTION REQUIRED:

To ensure the integrity of our competition and verify that images are not AI-generated, we require all finalists to submit raw or unedited versions of their images.

SUBMISSION INSTRUCTIONS:
• Reply to this email with your raw/unedited image file
• If file size exceeds 20MB, please use WeTransfer (wetransfer.com) or Google Drive and share the link
• DEADLINE: [DEADLINE]

ACCEPTABLE FORMATS:
• RAW files (.CR2, .NEF, .ARW, .DNG, .RAF, etc.)
• Original unedited JPG files

If you encounter any issues meeting the deadline, please contact us immediately.

Thank you for your participation in the Minimalist Photography Awards!

Best regards,
Minimalist Photography Awards Team</textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" id="save-template-btn" class="button button-primary">
                                💾 Save Template
                            </button>
                            <button type="button" id="cancel-edit-btn" class="button">
                                Cancel
                            </button>
                            <button type="button" id="reset-template-btn" class="button" style="margin-left: auto;">
                                🔄 Reset to Default
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="button" 
                        id="send-finalist-emails-btn" 
                        class="button button-primary button-large">
                    📤 Send Raw File Requests
                </button>
                
                <p class="description" style="margin-top: 10px;">
                    This will send personalized emails to each finalist requesting raw/unedited images for AI verification.
                </p>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>📧 Send Reminder Emails</h2>
                <p>Send email reminders to winners who haven't uploaded their high-resolution images yet.</p>
                
                <div id="reminder-results" style="margin: 15px 0; padding: 10px; display: none;"></div>
                <div id="test-email-results" style="margin: 15px 0; padding: 10px; display: none;"></div>
                
                <button type="button" 
                        id="send-test-email-btn" 
                        class="button button-secondary"
                        style="margin-right: 10px;">
                    ✉️ Send Test Email to Admin
                </button>
                
                <button type="button" 
                        id="send-reminders-btn" 
                        class="button button-primary button-large"
                        data-category="<?php echo esc_attr($selected_category); ?>">
                    📤 Send Reminders to Winners Missing High-Res
                </button>
                
                <p class="description" style="margin-top: 10px;">
                    <strong>Test email</strong> will be sent to: <?php echo get_option('admin_email'); ?><br>
                    <strong>Reminder emails</strong> will be sent to each Top 3 winner who hasn't uploaded high-res images yet 
                    <?php echo $selected_category ? '(in selected category only)' : '(across all categories)'; ?>.
                </p>
            </div>
        </div>
        
        <style>
            .highres-status-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            .highres-status-table th {
                background: #f5f5f5;
                padding: 10px;
                text-align: left;
                border-bottom: 2px solid #ddd;
            }
            .highres-status-table td {
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            .status-complete {
                color: #28a745;
                font-weight: bold;
            }
            .status-missing {
                color: #dc3545;
                font-weight: bold;
            }
            .status-partial {
                color: #ffc107;
                font-weight: bold;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox
            $('#select-all-highres').on('change', function() {
                $('.highres-checkbox').prop('checked', $(this).prop('checked'));
                toggleBulkDeleteButton();
            });
            
            // Individual checkboxes
            $(document).on('change', '.highres-checkbox', function() {
                toggleBulkDeleteButton();
            });
            
            function toggleBulkDeleteButton() {
                var checked = $('.highres-checkbox:checked').length;
                if (checked > 0) {
                    $('#bulk-delete-highres-btn').text('🗑️ Delete Selected Files (' + checked + ')').show();
                } else {
                    $('#bulk-delete-highres-btn').hide();
                }
            }
            
            // Delete single file
            $(document).on('click', '.delete-single-highres', function() {
                if (!confirm('Delete this high-res file from server? This cannot be undone.')) {
                    return;
                }
                
                var $btn = $(this);
                var attachmentId = $btn.data('attachment-id');
                var entryId = $btn.data('entry-id');
                
                $btn.prop('disabled', true).text('Deleting...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'admin_delete_highres',
                        nonce: '<?php echo wp_create_nonce('awards_admin_nonce'); ?>',
                        attachment_id: attachmentId,
                        entry_id: entryId
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut(function() {
                                location.reload();
                            });
                        } else {
                            alert('Error: ' + response.data.message);
                            $btn.prop('disabled', false).text('🗑️ Delete');
                        }
                    },
                    error: function() {
                        alert('Network error. Please try again.');
                        $btn.prop('disabled', false).text('🗑️ Delete');
                    }
                });
            });
            
            // Bulk delete
            $('#bulk-delete-highres-btn').on('click', function() {
                var $checked = $('.highres-checkbox:checked');
                var count = $checked.length;
                
                if (!confirm('Delete ' + count + ' high-res file(s) from server? This cannot be undone.')) {
                    return;
                }
                
                var deletions = [];
                var $btn = $(this);
                
                // Collect all files to delete
                $checked.each(function() {
                    var entryId = $(this).data('entry-id');
                    var $row = $(this).closest('tr');
                    
                    // Find all delete buttons in this row
                    $row.find('.delete-single-highres').each(function() {
                        deletions.push({
                            attachment_id: $(this).data('attachment-id'),
                            entry_id: $(this).data('entry-id')
                        });
                    });
                });
                
                $btn.prop('disabled', true).text('Deleting...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'admin_bulk_delete_highres',
                        nonce: '<?php echo wp_create_nonce('awards_admin_nonce'); ?>',
                        deletions: JSON.stringify(deletions)
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                            $btn.prop('disabled', false).text('🗑️ Delete Selected Files (' + count + ')');
                        }
                    },
                    error: function() {
                        alert('Network error. Please try again.');
                        $btn.prop('disabled', false).text('🗑️ Delete Selected Files (' + count + ')');
                    }
                });
            });
            
            // Email template default
            var defaultSubject = 'Finalist Notification - Raw Image Required for Verification';
            var defaultMessage = `Dear Photographer,

Congratulations! Your entry "[ENTRY_TITLE]" has been selected as a finalist in the Minimalist Photography Awards.

ACTION REQUIRED:

To ensure the integrity of our competition and verify that images are not AI-generated, we require all finalists to submit raw or unedited versions of their images.

SUBMISSION INSTRUCTIONS:
• Reply to this email with your raw/unedited image file
• If file size exceeds 20MB, please use WeTransfer (wetransfer.com) or Google Drive and share the link
• DEADLINE: [DEADLINE]

ACCEPTABLE FORMATS:
• RAW files (.CR2, .NEF, .ARW, .DNG, .RAF, etc.)
• Original unedited JPG files

If you encounter any issues meeting the deadline, please contact us immediately.

Thank you for your participation in the Minimalist Photography Awards!

Best regards,
Minimalist Photography Awards Team`;
            
            // Edit template button
            $('#edit-email-template-btn').on('click', function() {
                $('#email-template-preview').hide();
                $('#email-template-editor').show();
                $(this).hide();
            });
            
            // Save template button
            $('#save-template-btn').on('click', function() {
                var subject = $('#email-subject').val();
                var message = $('#email-message').val();
                
                // Update preview
                $('#preview-subject').text(subject);
                $('#preview-message').text(message.replace('[ENTRY_TITLE]', '[Entry Title]').replace('[DEADLINE]', '[10 days from today]'));
                
                // Switch back to preview
                $('#email-template-editor').hide();
                $('#email-template-preview').show();
                $('#edit-email-template-btn').show();
                
                alert('Email template saved! It will be used for all emails sent.');
            });
            
            // Cancel edit button
            $('#cancel-edit-btn').on('click', function() {
                $('#email-template-editor').hide();
                $('#email-template-preview').show();
                $('#edit-email-template-btn').show();
            });
            
            // Reset template button
            $('#reset-template-btn').on('click', function() {
                if (confirm('Reset email template to default? This will discard your changes.')) {
                    $('#email-subject').val(defaultSubject);
                    $('#email-message').val(defaultMessage);
                    alert('Template reset to default.');
                }
            });
            
            // Add recipient button
            $('#add-recipient-btn').on('click', function() {
                var newRow = $('.finalist-recipient-row:first').clone();
                newRow.find('input').val('');
                $('#finalist-recipients').append(newRow);
            });
            
            // Remove recipient button
            $(document).on('click', '.remove-recipient-btn', function() {
                if ($('.finalist-recipient-row').length > 1) {
                    $(this).closest('.finalist-recipient-row').remove();
                } else {
                    alert('You must have at least one recipient.');
                }
            });
            
            // Send finalist emails button
            $('#send-finalist-emails-btn').on('click', function() {
                var $btn = $(this);
                var $results = $('#finalist-email-results');
                
                // Get custom template
                var emailSubject = $('#email-subject').val();
                var emailMessage = $('#email-message').val();
                
                // Collect all recipients
                var recipients = [];
                var isValid = true;
                
                $('.finalist-recipient-row').each(function() {
                    var email = $(this).find('.finalist-email').val().trim();
                    var title = $(this).find('.finalist-entry-title').val().trim();
                    
                    if (email && title) {
                        recipients.push({
                            email: email,
                            title: title
                        });
                    } else if (email || title) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    alert('Please fill in both email and entry title for all recipients, or remove empty rows.');
                    return;
                }
                
                if (recipients.length === 0) {
                    alert('Please add at least one recipient.');
                    return;
                }
                
                if (!confirm('Send raw file request emails to ' + recipients.length + ' finalist(s)?')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Sending...');
                $results.hide().html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_finalist_raw_request',
                        nonce: '<?php echo wp_create_nonce('awards_admin_nonce'); ?>',
                        recipients: JSON.stringify(recipients),
                        email_subject: emailSubject,
                        email_message: emailMessage
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>').show();
                            // Clear form
                            $('.finalist-email, .finalist-entry-title').val('');
                        } else {
                            $results.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>').show();
                        }
                        $btn.prop('disabled', false).text('📤 Send Raw File Requests');
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p>Network error. Please try again.</p></div>').show();
                        $btn.prop('disabled', false).text('📤 Send Raw File Requests');
                    }
                });
            });
            
            // Test Email Button
            $('#send-test-email-btn').on('click', function() {
                var $btn = $(this);
                var $results = $('#test-email-results');
                
                $btn.prop('disabled', true).text('Sending...');
                $results.hide().html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_test_email',
                        nonce: '<?php echo wp_create_nonce('awards_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>').show();
                        } else {
                            $results.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>').show();
                        }
                        $btn.prop('disabled', false).text('✉️ Send Test Email to Admin');
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p>Network error. Please try again.</p></div>').show();
                        $btn.prop('disabled', false).text('✉️ Send Test Email to Admin');
                    }
                });
            });
            
            // Send Reminders Button
            $('#send-reminders-btn').on('click', function() {
                var $btn = $(this);
                var $results = $('#reminder-results');
                var categoryId = $btn.data('category');
                
                if (!confirm('Send reminder emails to all winners who haven\'t uploaded high-res images?')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Sending...');
                $results.hide().html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_highres_reminder',
                        nonce: '<?php echo wp_create_nonce('awards_admin_nonce'); ?>',
                        category_id: categoryId
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>').show();
                        } else {
                            $results.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>').show();
                        }
                        $btn.prop('disabled', false).text('📤 Send Reminders to Winners Missing High-Res');
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p>Network error. Please try again.</p></div>').show();
                        $btn.prop('disabled', false).text('📤 Send Reminders to Winners Missing High-Res');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display winners table with high-res status
     */
    private function display_winners_table($category_id = 0) {
        global $wpdb;
        
        // Collect ALL Top 3 winners from BOTH systems
        $all_winners = array();
        
        // Method 1: Get winners from awm_winners table
        $where_cat = $category_id ? $wpdb->prepare("AND w.category_id = %d", $category_id) : "";
        
        $awm_winners = $wpdb->get_results("
            SELECT w.entry_id, w.category_id, w.position, w.winner_type
            FROM {$wpdb->prefix}awm_winners w
            WHERE w.winner_type = 'top3'
            AND w.position BETWEEN 1 AND 3
            {$where_cat}
            ORDER BY w.category_id, w.position
        ");
        
        foreach ($awm_winners as $winner) {
            $all_winners[$winner->entry_id] = $winner;
        }
        
        // Method 2: Get winners from winner_status meta field
        $meta_query_args = array(
            'post_type' => 'entry',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'winner_status',
                    'value' => array('firstplace', 'secondplace', 'thirplace'),
                    'compare' => 'IN'
                )
            )
        );
        
        if ($category_id) {
            $meta_query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'entrycat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            );
        }
        
        $meta_winners = new WP_Query($meta_query_args);
        
        if ($meta_winners->have_posts()) {
            while ($meta_winners->have_posts()) {
                $meta_winners->the_post();
                $entry_id = get_the_ID();
                
                // Skip if already in awm_winners
                if (isset($all_winners[$entry_id])) {
                    continue;
                }
                
                $winner_status = get_post_meta($entry_id, 'winner_status', true);
                $positions = array('firstplace' => 1, 'secondplace' => 2, 'thirplace' => 3);
                $position = isset($positions[$winner_status]) ? $positions[$winner_status] : 0;
                
                // Get category
                $categories = wp_get_post_terms($entry_id, 'entrycat');
                $cat_id = !empty($categories) ? $categories[0]->term_id : 0;
                
                $winner_obj = new stdClass();
                $winner_obj->entry_id = $entry_id;
                $winner_obj->category_id = $cat_id;
                $winner_obj->position = $position;
                $winner_obj->winner_type = 'top_winner';
                
                $all_winners[$entry_id] = $winner_obj;
            }
            wp_reset_postdata();
        }
        
        if (empty($all_winners)) {
            echo '<div class="notice notice-info"><p>No Top 3 winners found.</p></div>';
            return;
        }
        
        $position_labels = array(1 => '🥇 First', 2 => '🥈 Second', 3 => '🥉 Third');
        
        ?>
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Top 3 Winners - High-Res Upload Status</h2>
                <button type="button" id="bulk-delete-highres-btn" class="button" style="display: none;">
                    🗑️ Delete Selected Files
                </button>
            </div>
            
            <table class="highres-status-table">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="select-all-highres"></th>
                        <th>Position</th>
                        <th>Category</th>
                        <th>Entry Title</th>
                        <th>Photographer</th>
                        <th>Images</th>
                        <th>High-Res Status</th>
                        <th>Upload Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_winners as $winner): ?>
                        <?php
                        $entry = get_post($winner->entry_id);
                        if (!$entry) continue;
                        
                        $category = get_term($winner->category_id, 'entrycat');
                        $author = get_user_by('id', $entry->post_author);
                        
                        $entry_files = get_post_meta($winner->entry_id, 'entryfiles', true);
                        $is_series = is_array($entry_files) && count($entry_files) > 1;
                        $total_images = $is_series ? count($entry_files) : 1;
                        
                        $highres_files = get_post_meta($winner->entry_id, '_high_res_files', true);
                        $highres_count = is_array($highres_files) ? count($highres_files) : 0;
                        $upload_date = get_post_meta($winner->entry_id, '_high_res_upload_date', true);
                        
                        // Status
                        if ($highres_count === 0) {
                            $status = '<span class="status-missing">✗ Missing</span>';
                        } else if ($highres_count < $total_images) {
                            $status = '<span class="status-partial">⚠ Partial (' . $highres_count . '/' . $total_images . ')</span>';
                        } else {
                            $status = '<span class="status-complete">✓ Complete</span>';
                        }
                        
                        $position_label = isset($position_labels[$winner->position]) ? $position_labels[$winner->position] : 'Top 3';
                        ?>
                        <tr>
                            <td>
                                <?php if ($highres_count > 0): ?>
                                    <input type="checkbox" class="highres-checkbox" data-entry-id="<?php echo $winner->entry_id; ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo $position_label; ?></td>
                            <td><?php echo $category ? esc_html($category->name) : 'N/A'; ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($winner->entry_id); ?>">
                                    <?php echo esc_html($entry->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo esc_html($author->display_name); ?><br>
                                <small><?php echo esc_html($author->user_email); ?></small>
                            </td>
                            <td><?php echo $total_images; ?> image<?php echo $total_images > 1 ? 's' : ''; ?></td>
                            <td><?php echo $status; ?></td>
                            <td>
                                <?php if ($upload_date): ?>
                                    <?php echo date('M j, Y', strtotime($upload_date)); ?>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($highres_count > 0 && is_array($highres_files)): ?>
                                    <?php foreach ($highres_files as $hr_id): ?>
                                        <?php if ($hr_id): ?>
                                            <?php $hr_url = wp_get_attachment_url($hr_id); ?>
                                            <div style="margin-bottom: 5px;">
                                                <a href="<?php echo esc_url($hr_url); ?>" class="button button-small" target="_blank" download>
                                                    ⬇ Download
                                                </a>
                                                <button type="button" 
                                                        class="button button-small delete-single-highres" 
                                                        data-attachment-id="<?php echo $hr_id; ?>"
                                                        data-entry-id="<?php echo $winner->entry_id; ?>"
                                                        style="color: #dc3545;">
                                                    🗑️ Delete
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php
            // Summary stats
            $total_winners = count($all_winners);
            $complete_count = 0;
            $missing_count = 0;
            
            foreach ($all_winners as $winner) {
                $entry_files = get_post_meta($winner->entry_id, 'entryfiles', true);
                $is_series = is_array($entry_files) && count($entry_files) > 1;
                $total_images = $is_series ? count($entry_files) : 1;
                
                $highres_files = get_post_meta($winner->entry_id, '_high_res_files', true);
                $highres_count = is_array($highres_files) ? count($highres_files) : 0;
                
                if ($highres_count >= $total_images) {
                    $complete_count++;
                } else if ($highres_count === 0) {
                    $missing_count++;
                }
            }
            ?>
            
            <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <strong>Summary:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Total Top 3 Winners: <strong><?php echo $total_winners; ?></strong></li>
                    <li>Complete High-Res Uploads: <strong style="color: #28a745;"><?php echo $complete_count; ?></strong></li>
                    <li>Missing High-Res: <strong style="color: #dc3545;"><?php echo $missing_count; ?></strong></li>
                    <li>Completion Rate: <strong><?php echo $total_winners > 0 ? round(($complete_count / $total_winners) * 100) : 0; ?>%</strong></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'entry_page_highres-reminders') {
            return;
        }
        
        wp_enqueue_style('highres-admin', plugins_url('css/highres-admin.css', __FILE__));
    }
}

// Initialize
new Awards_HighRes_Admin();
