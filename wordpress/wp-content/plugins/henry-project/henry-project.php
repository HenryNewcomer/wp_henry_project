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

        // AJAX handlers (non-admin only)
        if (!is_admin()) {
            add_action('wp_ajax_henry_project_get_entries', [$this, 'ajax_get_entries']);
            add_action('wp_ajax_henry_project_create_entry', [$this, 'ajax_create_entry']);
            add_action('wp_ajax_henry_project_update_entry', [$this, 'ajax_update_entry']);
            add_action('wp_ajax_henry_project_delete_entry', [$this, 'ajax_delete_entry']);
        }
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
            'capability_type' => 'post',
            'map_meta_cap' => true
        ]);
    }

    // public function add_page_templates($templates) {
    //     $templates['henry-project-entries'] = __('Henry Project Entries', 'henry-project');
    //     return $templates;
    // }

    // public function load_page_template($template) {
    //     if (is_page()) {
    //         $template_file = get_post_meta(get_the_ID(), '_wp_page_template', true);

    //         if ('henry-project-entries' === $template_file) {
    //             return plugin_dir_path(__FILE__) . 'templates/page-entries.php';
    //         }
    //     }

    //     return $template;
    // }

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
            'henry-project-styles',
            plugin_dir_url(__FILE__) . 'css/style.css',
            ['henry-project-bootstrap'],
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
    }

    public function ajax_create_entry() {
        check_ajax_referer('henry_project_nonce', 'nonce');

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
        $content = sanitize_text_field($_POST['content']);

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied', 'henry-project')]);
        }

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

        if (!current_user_can('delete_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied', 'henry-project')]);
        }

        if (!wp_delete_post($post_id, true)) {
            wp_send_json_error(['message' => __('Failed to delete entry', 'henry-project')]);
        }

        wp_send_json_success();
    }

    // Helper Methods
    private function get_query_args($page, $order) {
        $args = [
            'post_type' => 'henry_project_entry',
            'posts_per_page' => $this->settings['per_page'],
            'paged' => $page,
            'orderby' => 'date',
            'order' => $order
        ];

        if (!current_user_can('administrator')) {
            $args['author'] = get_current_user_id();
        }

        return $args;
    }

    private function create_entry_post($content) {
        return wp_insert_post([
            'post_title' => $content,
            'post_type' => 'henry_project_entry',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ]);
    }

    private function prepare_entry($post) {
        $author = get_user_by('id', $post->post_author);

        return [
            'id' => $post->ID,
            'content' => $post->post_title,
            'author' => [
                'name' => $author->display_name,
                'roles' => array_map('ucfirst', $author->roles)
            ],
            'date' => get_the_date('c', $post),
            'can_edit' => current_user_can('edit_post', $post->ID)
        ];
    }

    public function can_edit_entry($request) {
        return current_user_can('edit_post', $request->get_param('id'));
    }

    public static function show_current_role() {
        $user = wp_get_current_user();
        $roles = array_map('ucfirst', $user->roles);
        echo '<div class="alert alert-info">Currently viewing as: ' . implode(', ', $roles) . '</div>';
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
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    $page_ids = get_option('henry_project_pages', []);
    foreach ($page_ids as $page_id) {
        wp_delete_post($page_id, true);
    }
    delete_option('henry_project_pages');
});
