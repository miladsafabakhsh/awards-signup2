<?php
/**
 * Created by PhpStorm.
 * User: Mehran
 * Date: 2018/07/21
 * Time: 12:36
 */

function awards_posttypes()
{
    register_post_type('entry',
        array(
            'labels' => array(
                'name' => __('Entries', 'awards'),
                'singular_name' => __('Entry', 'awards'),
                'new_item' => __('Add entry', 'awards'),
                'add_new' => __('Add entry', 'awards'),
                'add_new_item' => __('Add entry', 'awards'),
                'edit_item' => __('Edit entry', 'awards'),
            ),
            'public' => true,
            'can_export' => true,
            'publicly_queryable' => false,
            'has_archive' => false,
            'menu_icon' => 'dashicons-welcome-view-site',
            'exclude_from_search' => true,
            'rewrite' => array('slug' => 'entry', 'with_front' => false),
            'taxonomies' => array('entrycat'),
            'supports' => array('title', 'thumbnail', 'excerpt')
        )
    );

    register_post_type('awards_copon',
        array(
            'labels' => array(
                'name' => __('Awards Copons', 'awards'),
                'singular_name' => __('Awards Copons', 'awards'),
                'new_item' => __('Add copon', 'awards'),
                'add_new' => __('Add copon', 'awards'),
                'add_new_item' => __('Add copon', 'awards'),
                'edit_item' => __('Edit copon', 'awards'),
            ),
            'public' => true,
            'can_export' => true,
            'publicly_queryable' => false,
            'has_archive' => false,
            'menu_icon' => 'dashicons-welcome-view-site',
            'exclude_from_search' => true,
            'supports' => array('title', 'excerpt')
        )
    );
}

add_action('init', 'awards_posttypes');

function awards_taxonomy()
{
    register_taxonomy('entrycat', 'entry',
        array(
            'label' => __('Categories', 'awards'),
            'rewrite' => array('slug' => 'entrycat'),
            'show_tagcloud' => false,
            'show_admin_column' => true,
            'hierarchical' => true,
            'show_in_nav_menus' => true
        )
    );
}

add_action('init', 'awards_taxonomy');

function awards_taxonomy_register()
{
    register_taxonomy_for_object_type('entrycat', 'entry');
}

add_action('init', 'awards_taxonomy_register');

/**
 *
 * entry post metabox
 */
function awards_metabox_add()
{
    add_meta_box('awards_postdata', __('Entry details', 'awards'), 'awards_postdata_metabox_cb', 'entry', 'normal', 'high');
    add_meta_box('awards_copon_details', __('Copon details', 'awards'), 'awards_copons_metabox_cb', 'awards_copon', 'normal', 'high');
}

add_action('add_meta_boxes', 'awards_metabox_add');

function awards_postdata_metabox_cb($post)
{
    $screen = get_current_screen();
    if ($screen->action === 'add') {
        require_once('template/metabox_entrydata_add.php');
    } else {
        require_once('template/metabox_entrydata.php');
    }
}

function awards_copons_metabox_cb($post)
{
    require_once('template/metabox_copons.php');
}

function awards_postdata_metabox_save($post_id)
{

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

//    if (!current_user_can('edit_post')) return;

    if (isset($_POST['entrystatus']) && !empty($_POST['entrystatus'])) {

        $current_entry_status = get_post_meta($post_id, 'status', true);

        if ($_POST['entrystatus'] != $current_entry_status) {
            $wp_awards = new wp_awards();
            $email_body = sprintf(__('Your entry status changer to <strong>%s</strong>', 'awards'), get_award_status_title($_POST['entrystatus']));
            $email_body = $wp_awards->email_body($email_body);
            $post_object = get_post($post_id);
        }

        update_post_meta($post_id, 'status', $_POST['entrystatus']);
    }

    if (isset($_POST['person'])){
        update_post_meta($post_id, 'persons', $_POST['person']);
    }

    if (isset($_POST['new_entry_files']) && !empty($_POST['new_entry_files'])) {
        $new_entries = explode(',', $_POST['new_entry_files']);
        $current_entries = get_post_meta($post_id, 'entryfiles', true);

        if(!$current_entries) $current_entries = array();

        $new_entries_final = array();
        foreach ($new_entries as $new_entry) {
            if ($new_entry == '') continue;
            $new_entries_final[] = $new_entry;
        }
        $new_entries_final = array_merge($new_entries_final, $current_entries);
        update_post_meta($post_id, 'entryfiles', $new_entries_final);
    }

    if (isset($_POST['winnerstatus']) && !empty($_POST['winnerstatus'])) {
        update_post_meta($post_id, 'winner_status', $_POST['winnerstatus']);
    }

    if (isset($_POST['adminnote']) && !empty($_POST['adminnote'])) {
        update_post_meta($post_id, 'adminnote', $_POST['adminnote']);
    }

    if (isset($_POST['certificate']) && !empty($_POST['certificate'])) {
        update_post_meta($post_id, 'certificate', $_POST['certificate']);
    }

    if (isset($_POST['code']) && !empty($_POST['code'])) {
        update_post_meta($post_id, 'code', $_POST['code']);
    }

    if (isset($_POST['value']) && !empty($_POST['value'])) {
        update_post_meta($post_id, 'value', $_POST['value']);
    }

    if (isset($_POST['type']) && !empty($_POST['type'])) {
        update_post_meta($post_id, 'type', $_POST['type']);
    }

//    if(isset($_POST['author'])){
//        $arg = array(
//            'ID' => $post_id,
//            'post_author' => $_POST['author'],
//        );
//        wp_update_post( $arg );
//    }
}

add_action('save_post', 'awards_postdata_metabox_save');

function save_taxonomy_custom_fields($term_id)
{
    if (isset($_POST['term_meta'])) {
        $t_id = $term_id;
        $term_meta = get_option("taxonomy_term_$t_id");
        $cat_keys = array_keys($_POST['term_meta']);
        foreach ($cat_keys as $key) {
            if (isset($_POST['term_meta'][$key])) {
                $term_meta[$key] = $_POST['term_meta'][$key];
            }
        }
        update_option("taxonomy_term_$t_id", $term_meta);
    }
}

add_action('edited_entrycat', 'save_taxonomy_custom_fields', 10, 2);

// ----------------------------- filter -------------------
add_action('restrict_manage_posts', 'awards_filter_entries_by_status');
function awards_filter_entries_by_status()
{
    $type = 'entry';
    if (isset($_GET['post_type'])) $type = $_GET['post_type'];

    if ('entry' == $type) {
        $current_v = isset($_GET['awards_status']) ? $_GET['awards_status'] : '';
        ?>
        <select name="awards_status">
            <option value=""><?php _e('Filter By Status', 'awards'); ?></option>
            <option value="pending_payment" <?php echo $current_v == 'pending_payment' ? 'selected="selected"' : ''; ?>><?php _e('Pending Payment', 'awards'); ?></option>
            <option value="pending_review" <?php echo $current_v == 'pending_review' ? 'selected="selected"' : ''; ?>><?php _e('Pending Review', 'awards'); ?></option>
            <option value="approved" <?php echo $current_v == 'approved' ? 'selected="selected"' : ''; ?>><?php _e('Approved', 'awards'); ?></option>
            <option value="rejected" <?php echo $current_v == 'rejected' ? 'selected="selected"' : ''; ?>><?php _e('Rejected', 'awards'); ?></option>
            <option value="winner" <?php echo $current_v == 'winner' ? 'selected="selected"' : ''; ?>><?php _e('Winner', 'awards'); ?></option>
        </select>
        <?php
    }
}

add_action('restrict_manage_posts', 'awards_filter_entries_by_payment');
function awards_filter_entries_by_payment()
{
    $type = 'entry';
    if (isset($_GET['post_type'])) $type = $_GET['post_type'];

    if ('entry' == $type) {
        $current_v = isset($_GET['payment']) ? $_GET['payment'] : '';
        ?>
        <select name="payment">
            <option value=""><?php _e('Filter By Payment', 'awards'); ?></option>
            <option value="yes" <?php echo $current_v == 'yes' ? 'selected="selected"' : ''; ?>><?php _e('Yes', 'awards'); ?></option>
        </select>
        <?php
    }
}

add_filter('parse_query', 'awards_filter_by_custom_meta');
function awards_filter_by_custom_meta($query)
{
    global $pagenow;
    $type = 'entry';
    if (isset($_GET['post_type'])) {
        $type = $_GET['post_type'];
    }
    if ('entry' == $type && is_admin() && $pagenow == 'edit.php' && isset($_GET['awards_status']) && $_GET['awards_status'] != '') {
        $query->query_vars['meta_key'] = 'status';
        $query->query_vars['meta_value'] = $_GET['awards_status'];
    }

    if ('entry' == $type && is_admin() && $pagenow == 'edit.php' && isset($_GET['payment']) && $_GET['payment'] == 'yes') {
        $query->query_vars['meta_key'] = 'payment';
        $query->query_vars['meta_compare'] = '!=';
        $query->query_vars['meta_value'] = '';
    }

}

// ----------------------------- end filter -------------------

add_filter('manage_entry_posts_columns', 'awards_set_custom_columns');
function awards_set_custom_columns($columns)
{
    unset($columns['author']);
    $columns['status'] = __('Status', 'awards');
    $columns['user'] = __('User', 'awards');

    return $columns;
}

// Add the data to the custom columns for the book post type:
add_action('manage_entry_posts_custom_column', 'awards_custom_column_data', 10, 2);
function awards_custom_column_data($column, $post_id)
{
    switch ($column) {
        case 'status' :
            $status = get_post_meta($post_id, 'status', true);
            echo get_award_status_title($status);
            break;
        case 'user' :
            $post = get_post($post_id);
            $user_name = get_user_by('id', $post->post_author);
            $user_name = $user_name->display_name;
            echo '<a href="' . get_edit_user_link($post->post_author) . '">' . $user_name . '</a>';
            break;
    }
}


function awards_admin_entry_css()
{
    $screen = get_current_screen();
    if ($screen->post_type == 'entry') {
        wp_register_style('bootstrap', plugin_dir_url(__FILE__) . 'css/bootstrap-grid.min.css');
        wp_enqueue_style('bootstrap');
    }

    if ($screen->post_type == 'entry' && $screen->action == 'add') {
        wp_register_script('awards', plugin_dir_url(__FILE__) . 'js/awards.js');
        wp_register_style('awards', plugin_dir_url(__FILE__) . 'css/awards.css');
        wp_enqueue_style('awards');
        wp_enqueue_script('awards');
    }
}

add_action('admin_enqueue_scripts', 'awards_admin_entry_css');
