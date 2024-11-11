<?php
/**
 * Plugin Name: Henry's Project
 * Description: PixelPeople side project/test.
 * Version: 1.0.0
 * Author: Henry Newcomer
 * Text Domain: henry-project
 */

if (!defined('ABSPATH')) {
    exit;
}

class HenryProject {
    private $settings;
    private $page_ids;

    // Role hierarchy (core roles with explicit viewing weights)
    // NOTE: If this plugin were to be extended, I'd place these into the admin area, too, to be sorted.
    private static $ROLE_HIERARCHY = [
        'administrator' => 100, // Can see everything
        'editor' => 60,
        'subscriber' => 20
    ];

    public function __construct() {
        $this->settings = get_option('henry_project_settings', [
            'per_page' => 10,
        ]);
        $this->page_ids = get_option('henry_project_pages', []);

        // Shortcode for displaying entries
        add_shortcode('henry_project_entries', [$this, 'render_entries_page']);

        // Admin hooks
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);

        // Core hooks
        add_action('init', [$this, 'register_post_type']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);

        // AJAX handlers
        add_action('wp_ajax_henry_project_get_entries', [$this, 'ajax_get_entries']);
        add_action('wp_ajax_henry_project_create_entry', [$this, 'ajax_create_entry']);
        add_action('wp_ajax_henry_project_update_entry', [$this, 'ajax_update_entry']);
        add_action('wp_ajax_henry_project_delete_entry', [$this, 'ajax_delete_entry']);
    }

    public function add_menu_pages() {
        // Main menu and settings page
        add_menu_page(
            __('Henry Project', 'henry-project'),
            __('Henry Project', 'henry-project'),
            'manage_options',
            'henry-project-settings',
            [$this, 'render_settings_page'],
            'dashicons-list-view'
        );

        remove_submenu_page('henry-project-settings', 'henry-project-settings');

        // Add settings as submenu
        add_submenu_page(
            'henry-project-settings',
            __('Settings', 'henry-project'),
            __('Settings', 'henry-project'),
            'manage_options',
            'henry-project-settings'
        );
    }

    public function register_settings() {
        register_setting(
            'henry_project_settings', // Option group
            'henry_project_settings', // Option name in wp_options
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => ['per_page' => 10]
            ]
        );

        add_settings_section(
            'henry_project_main',
            __('Display Settings', 'henry-project'),
            function() {
                echo '<p>' . __('Configure how entries are displayed on the front end.', 'henry-project') . '</p>';
            },
            'henry-project-settings' // Match the menu slug
        );

        add_settings_field(
            'per_page',
            __('Entries per page', 'henry-project'),
            [$this, 'render_per_page_field'],
            'henry-project-settings', // Match the menu slug
            'henry_project_main'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        if (isset($input['per_page'])) {
            $sanitized['per_page'] = absint($input['per_page']);
            if ($sanitized['per_page'] < 1) {
                $sanitized['per_page'] = 10;
            }
        }
        return $sanitized;
    }

    public function render_entries_page() {
        ob_start();
        require plugin_dir_path(__FILE__) . 'templates/page-entries.php';
        return ob_get_clean();
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for settings update
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'henry_project_messages',
                'henry_project_message',
                __('Settings Saved', 'henry-project'),
                'updated'
            );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('henry_project_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('henry_project_settings');
                do_settings_sections('henry-project-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function render_per_page_field() {
        $value = $this->settings['per_page'] ?? 10;
        ?>
        <input type="number"
            name="henry_project_settings[per_page]"
            value="<?php echo esc_attr($value); ?>"
            min="1"
            max="100"
            class="small-text">
        <p class="description">
            <?php _e('Number of entries to display per page on the front end.', 'henry-project'); ?>
        </p>
        <?php
    }

    public function register_post_type() {
        register_post_type('henry_project_entry', [
            'labels' => [
                'name' => __('Entries', 'henry-project'),
                'singular_name' => __('Entry', 'henry-project')
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'author'],
            'has_archive' => true,
            'show_in_menu' => 'henry-project-settings',
            'capability_type' => 'post', // Just use default posting capabilities
            'map_meta_cap' => true
        ]);
    }

    public function enqueue_assets() {
        if (!is_page($this->page_ids['entries'])) {
            return;
        }

        wp_enqueue_style(
            'henry-project-bootstrap',
            plugin_dir_url(__FILE__) . 'css/bootstrap.min.css',
            [],
            '5.3.2'
        );

        wp_enqueue_style(
            'bootstrap-icons',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
            ['henry-project-bootstrap'],
            '1.11.3'
        );

        wp_enqueue_style(
            'henry-project-styles',
            plugin_dir_url(__FILE__) . 'css/style.css',
            ['henry-project-bootstrap', 'bootstrap-icons'],
            filemtime(plugin_dir_path(__FILE__) . 'css/style.css')
        );

        // Determine view type (REST is the default since that's the modern standard)
        $view_type = isset($_GET['view']) && $_GET['view'] === 'ajax' ? 'ajax' : 'rest';

        // Enqueue view-specific script
        if ($view_type === 'ajax') {
            wp_enqueue_script(
                'henry-project-ajax',
                plugin_dir_url(__FILE__) . 'js/ajax.min.js',
                ['jquery'],
                filemtime(plugin_dir_path(__FILE__) . 'js/ajax.min.js'),
                true
            );
            wp_localize_script('henry-project-ajax', 'henryProject', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('henry_project_nonce'),
                'perPage' => $this->settings['per_page']
            ]);
        } else {
            wp_enqueue_script(
                'henry-project-rest',
                plugin_dir_url(__FILE__) . 'js/rest.min.js',
                ['wp-api-fetch'],
                filemtime(plugin_dir_path(__FILE__) . 'js/rest.min.js'),
                true
            );
            wp_localize_script('henry-project-rest', 'henryProject', [
                'restUrl' => rest_url('henry-project/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'perPage' => $this->settings['per_page']
            ]);
        }
    }

    // REST API Endpoints
    public function register_rest_endpoints() {
        register_rest_route('henry-project/v1', '/entries', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_entries'],
                'permission_callback' => 'is_user_logged_in'
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_entry'],
                'permission_callback' => 'is_user_logged_in'
            ]
        ]);

        register_rest_route('henry-project/v1', '/entries/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_entry'],
                'permission_callback' => [$this, 'can_edit_entry']
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_entry'],
                'permission_callback' => [$this, 'can_edit_entry']
            ]
        ]);
    }

    // REST API Methods
    public function get_entries($request) {
        $args = $this->get_query_args(
            $request->get_param('page') ?: 1,
            $request->get_param('order') ?: 'DESC'
        );

        $query = new WP_Query($args);
        $entries = array_map([$this, 'prepare_entry'], $query->posts);

        return new WP_REST_Response([
            'entries' => $entries,
            'total_pages' => $query->max_num_pages
        ], 200);
    }

    public function create_entry($request) {
        $content = sanitize_text_field($request->get_param('content'));

        if (empty($content)) {
            return new WP_Error('empty_content', __('Content cannot be empty', 'henry-project'), ['status' => 400]);
        }

        $post_id = $this->create_entry_post($content);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return new WP_REST_Response($this->prepare_entry(get_post($post_id)), 201); // 201: Created
    }

    public function update_entry($request) {
        $post_id = $request->get_param('id');
        $content = sanitize_text_field($request->get_param('content'));

        if (empty($content)) {
            return new WP_Error('empty_content', __('Content cannot be empty', 'henry-project'), ['status' => 400]);
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => $content
        ]);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return new WP_REST_Response($this->prepare_entry(get_post($post_id)), 200);
    }

    public function delete_entry($request) {
        $deleted = wp_delete_post($request->get_param('id'), true);

        if (!$deleted) {
            return new WP_Error('delete_failed', __('Failed to delete entry', 'henry-project'), ['status' => 500]);
        }

        return new WP_REST_Response(null, 204); // 204: No Content
    }

    // AJAX Methods
    public function ajax_get_entries() {
        error_log('AJAX get_entries called');
        error_log('REQUEST: ' . print_r($_REQUEST, true));
        error_log('GET: ' . print_r($_GET, true));

        // Verify nonce first
        if (!check_ajax_referer('henry_project_nonce', 'nonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        error_log('AJAX Get Entries Request: ' . print_r($_REQUEST, true));

        // Verify this is a GET request
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            wp_send_json_error(['message' => 'Invalid request method'], 400);
        }

        try {
            // Check nonce from GET parameters
            check_ajax_referer('henry_project_nonce', 'nonce');

            $args = $this->get_query_args(
                isset($_GET['page']) ? (int)$_GET['page'] : 1,
                isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC'
            );

            $query = new WP_Query($args);
            $entries = array_map([$this, 'prepare_entry'], $query->posts);

            wp_send_json_success([
                'entries' => $entries,
                'total_pages' => $query->max_num_pages
            ]);
        } catch (Exception $e) {
            error_log('AJAX Get Entries Error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajax_create_entry() {
        error_log('Create entry attempt by user: ' . get_current_user_id()); // DEBUG

        check_ajax_referer('henry_project_nonce', 'nonce');

        if (!is_user_logged_in()) {
            error_log('User not logged in');
            wp_send_json_error(['message' => __('You must be logged in to create entries', 'henry-project')]);
            return;
        }

        $content = sanitize_text_field($_POST['content']);

        if (empty($content)) {
            wp_send_json_error(['message' => __('Content cannot be empty', 'henry-project')]);
        }

        $post_id = $this->create_entry_post($content);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        wp_send_json_success($this->prepare_entry(get_post($post_id)));
    }

    public function ajax_update_entry() {
        check_ajax_referer('henry_project_nonce', 'nonce');

        $post_id = (int)$_POST['id'];
        $post = get_post($post_id);

        // Allow only administrators and the current user to update their own entries
        if (!$post || (!current_user_can('administrator') && $post->post_author != get_current_user_id())) {
            wp_send_json_error(['message' => __('Permission denied', 'henry-project')]);
        }

        $content = sanitize_text_field($_POST['content']);

        if (empty($content)) {
            wp_send_json_error(['message' => __('Content cannot be empty', 'henry-project')]);
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => $content
        ]);

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => $updated->get_error_message()]);
        }

        wp_send_json_success($this->prepare_entry(get_post($post_id)));
    }

    public function ajax_delete_entry() {
        check_ajax_referer('henry_project_nonce', 'nonce');

        $post_id = (int)$_POST['id'];
        $post = get_post($post_id);

        if (!$post || (!current_user_can('administrator') && $post->post_author != get_current_user_id())) {
            wp_send_json_error(['message' => __('Permission denied', 'henry-project')]);
        }

        if (!wp_delete_post($post_id, true)) {
            wp_send_json_error(['message' => __('Failed to delete entry', 'henry-project')]);
        }

        wp_send_json_success();
    }

    private function get_query_args($page, $order) {
        $args = [
            'post_type' => 'henry_project_entry',
            'posts_per_page' => $this->settings['per_page'],
            'paged' => $page,
            'orderby' => 'date',
            'order' => $order
        ];

        if (!current_user_can('administrator')) {
            add_filter('posts_where', [$this, 'filter_posts_by_role_level']);
        }

        return $args;
    }

    // BUGFIX: Switched to public since it's being used as a filter callback
    public function filter_posts_by_role_level($where) {
        global $wpdb;
        $current_user_level = $this->get_user_role_level();
        $current_user_id = get_current_user_id();

        // Build the LIKE conditions for roles based on current user's level
        $role_conditions = [];

        // Get all WordPress roles
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        // For each WordPress role, determine if it should be visible
        foreach ($wp_roles->roles as $role_name => $role_info) {
            // Get the role's level (default to subscriber level if not explicitly defined)
            $role_level = isset(self::$ROLE_HIERARCHY[$role_name])
                ? self::$ROLE_HIERARCHY[$role_name]
                : self::$ROLE_HIERARCHY['subscriber'];

            // If this role's level is <= current user's level, include it
            if ($role_level <= $current_user_level) {
                $role_conditions[] = $wpdb->prepare("um.meta_value LIKE %s", '%"' . $role_name . '"%');
            }
        }

        $role_sql = !empty($role_conditions) ? implode(' OR ', $role_conditions) : '1=0'; // Sets query to false if no roles match

        // Filter posts where the author's role level is less than or equal to current user's level
        // Also ensure that user's see their own posts regardless (I was unsure if they should *always* see their own or not, but this seemed logical).
        $where .= $wpdb->prepare("
            AND {$wpdb->posts}.post_author IN (
                SELECT u.ID
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = '{$wpdb->prefix}capabilities'
                AND (
                    u.ID = %d  /* Author's own posts */
                    OR
                    (
                        /* Check if user has no roles (treat as lowest level) */
                        (um.meta_value = 'a:0:{}' AND %d > 0)
                        OR
                        /* Check for roles at or below current user's level */
                        ({$role_sql})
                    )
                )
            )",
            $current_user_id,
            $current_user_level
        );

        // Clean up (remove the filter after use to avoid affecting other queries)
        remove_filter('posts_where', [$this, 'filter_posts_by_role_level']);

        return $where;
    }

    private function create_entry_post($content) {
        if (!is_user_logged_in()) {
            return new WP_Error('permission_denied', __('You must be logged in to create entries', 'henry-project'));
        }

        return wp_insert_post([
            'post_title' => $content,
            'post_type' => 'henry_project_entry',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ]);
    }

    private function prepare_entry($post) {
        $author = get_user_by('id', $post->post_author);
        $current_user_level = $this->get_user_role_level();
        $author_level = $this->get_user_role_level($author);
        $current_user_id = get_current_user_id();

        $is_admin = current_user_can('administrator');  // Check if user is admin
        $is_author = $post->post_author == $current_user_id;  // Check if user is the author

        return [
            'id' => $post->ID,
            'content' => $post->post_title,
            'author' => [
                'name' => $author->display_name,
                'roles' => array_map('ucfirst', $author->roles),
                'level' => $author_level
            ],
            'date' => get_the_date('c', $post),
            'can_edit' => $is_admin || $is_author,  // Only Admin or author can edit (for this demo)
            'can_view' => $current_user_level >= $author_level
        ];
    }

    public function can_edit_entry($request) {
        $post = get_post($request->get_param('id'));
        // Only allow editing if the user is an Admin or the author of the entry
        return $post && (current_user_can('administrator') || $post->post_author == get_current_user_id());
    }

    public static function show_current_role() {
        $user = wp_get_current_user();
        $roles = array_map('ucfirst', $user->roles);
        echo '<div class="alert alert-info">Currently viewing as: ' . implode(', ', $roles) . '</div>';
    }

    private function get_user_role_level($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }

        // Get the user's highest role
        $user_roles = $user->roles;
        $highest_level = 0;

        foreach ($user_roles as $role) {
            // If role is explicitly defined in hierarchy, use that level
            if (isset(self::$ROLE_HIERARCHY[$role])) {
                $level = self::$ROLE_HIERARCHY[$role];
                if ($level > $highest_level) {
                    $highest_level = $level;
                }
            } else {
                // For any other role (like Author, Contributor, or new/custom roles),
                // treat them like Subscribers for viewing purposes.
                $level = self::$ROLE_HIERARCHY['subscriber'];
                if ($level > $highest_level) {
                    $highest_level = $level;
                }
            }
        }


        return $highest_level;
    }

    private function add_role_capabilities() {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        // Basic read capability for all roles
        foreach ($wp_roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                // Everyone gets basic read capability
                $role->add_cap('read');

                // If role can 'edit_posts', grant our entry capabilities
                if (isset($role_info['capabilities']['edit_posts']) && $role_info['capabilities']['edit_posts']) {
                    $role->add_cap('publish_posts');
                    $role->add_cap('edit_posts');
                    $role->add_cap('delete_posts');
                }

                // Administrator gets all capabilities
                if ($role_name === 'administrator') {
                    $role->add_cap('edit_others_posts');
                    $role->add_cap('delete_others_posts');
                    $role->add_cap('read_private_posts');
                }
            }
        }
    }

    private function can_user_view_content($content_author_id) {
        $current_user_level = $this->get_user_role_level();
        $content_author = get_user_by('id', $content_author_id);
        $content_author_level = $this->get_user_role_level($content_author);

        return $current_user_level >= $content_author_level;
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    new HenryProject();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Add default settings
    add_option('henry_project_settings', [
        'per_page' => 10,
    ]);

    // Create the entries page if it doesn't exist
    $page_ids = get_option('henry_project_pages', []);

    if (empty($page_ids['entries'])) {
        $page_id = wp_insert_post([
            'post_title'    => __('Entries', 'henry-project'),
            'post_content'  => '[henry_project_entries]', // Uses shortcode to display entries
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'henry-entries'
        ]);

        if (!is_wp_error($page_id)) {
            $page_ids['entries'] = $page_id;
            update_option('henry_project_pages', $page_ids);
        }
    }

    $plugin = new HenryProject();
    $plugin->add_role_capabilities();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    $page_ids = get_option('henry_project_pages', []);
    foreach ($page_ids as $page_id) {
        wp_delete_post($page_id, true);
    }
    delete_option('henry_project_pages');
});
