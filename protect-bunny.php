<?php
/**
 * Plugin Name: Bunny Stream Safed
 * Description: Securely embeds Bunny.net media using SHA256 Token Authentication.
 * Version: 1.1.0
 * Author: Senior WordPress Developer
 * Text Domain: bunny-stream-safed
 */

if (!defined('ABSPATH')) exit;

/**
 * Constants & Namespacing
 */
define('BUNNY_STREAM_SAFE_VERSION', '1.1.0');
define('BUNNY_STREAM_SAFE_PATH', plugin_dir_path(__FILE__));
define('BUNNY_STREAM_SAFE_URL', plugin_dir_url(__FILE__));
define('BUNNY_STREAM_SAFE_OPTION', 'bunny_stream_safe_settings');

/**
 * Main Plugin Class
 */
class BunnyStreamSafe {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_shortcode('bunny_video', [$this, 'render_shortcode']);
        add_action('wp_ajax_bunny_stream_safe_save_settings', [$this, 'ajax_save_settings']);
        
        $this->init_update_checker();
    }

    /**
     * Yahnis Elsts Plugin Update Checker Integration
     */
    private function init_update_checker() {
        $puc_path = BUNNY_STREAM_SAFE_PATH . 'plugin-update-checker/plugin-update-checker.php';
        if (file_exists($puc_path)) {
            require $puc_path;
            $updateChecker = YahnisElsts\PluginUpdateChecker\V5\PucFactory::buildUpdateChecker(
                'https://github.com/your-username/bunny-stream-safed/', // Replace with your repository URL
                __FILE__,
                'bunny-stream-safed'
            );
            $updateChecker->setBranch('main');
        }
    }

    public function register_settings_page() {
        add_options_page(
            'Bunny Stream Safe',
            'Bunny Stream Safe',
            'manage_options',
            'bunny-stream-safe',
            [$this, 'render_admin_ui']
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_bunny-stream-safe' !== $hook) return;

        wp_enqueue_style('bunny-stream-safe-admin-css', BUNNY_STREAM_SAFE_URL . 'admin.css', [], BUNNY_STREAM_SAFE_VERSION);
        wp_enqueue_script('bunny-stream-safe-admin-js', BUNNY_STREAM_SAFE_URL . 'admin.js', ['jquery'], BUNNY_STREAM_SAFE_VERSION, true);

        $settings = get_option(BUNNY_STREAM_SAFE_OPTION, [
            'libraries' => [],
            'default_lib' => '',
            'expiry' => 3600
        ]);

        wp_localize_script('bunny-stream-safe-admin-js', 'streamSafeData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bunny_stream_safe_secure_nonce'),
            'settings' => $settings
        ]);
    }

    /**
     * Generate SHA256 Token
     */
    private function generate_token($item_id, $secret, $expiry_seconds) {
        $expiration = time() + (int)$expiry_seconds;
        $signature = hash('sha256', $secret . $item_id . $expiration);
        return [
            'token' => $signature,
            'expires' => $expiration
        ];
    }

    /**
     * Shortcode: [bunny_video id="VIDEO_ID" lib="OPTIONAL_KEY"]
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'lib' => '',
            'expiry' => ''
        ], $atts, 'bunny_video');

        if (empty($atts['id'])) return '<!-- Bunny Stream Safe: Missing ID -->';

        $settings = get_option(BUNNY_STREAM_SAFE_OPTION);
        $libraries = $settings['libraries'] ?? [];
        
        $lib_key = !empty($atts['lib']) ? $atts['lib'] : ($settings['default_lib'] ?? '');
        $selected_lib = null;

        foreach ($libraries as $l) {
            if ($l['key'] === $lib_key) {
                $selected_lib = $l;
                break;
            }
        }

        if (!$selected_lib && !empty($libraries)) {
            $selected_lib = $libraries[0]; 
        }

        if (!$selected_lib) return '<!-- Bunny Stream Safe: No Library Found -->';

        $expiry = !empty($atts['expiry']) ? (int)$atts['expiry'] : (int)($settings['expiry'] ?? 3600);
        $token_data = $this->generate_token($atts['id'], $selected_lib['secret'], $expiry);
        
        $iframe_url = sprintf(
            'https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%s',
            esc_attr($selected_lib['id']),
            esc_attr($atts['id']),
            $token_data['token'],
            $token_data['expires']
        );

        return sprintf(
            '<div class="bunny-stream-wrapper" style="position:relative;padding-top:56.25%%;width:100%%;background:#000;border-radius:8px;overflow:hidden;">
                <iframe src="%s" loading="lazy" style="border:none;position:absolute;top:0;left:0;width:100%%;height:100%%;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen></iframe>
            </div>',
            esc_url($iframe_url)
        );
    }

    public function render_admin_ui() {
        ?>
        <div class="wrap bunny-stream-admin">
            <h1 class="wp-heading-inline">Bunny Stream Safe Security</h1>
            <hr class="wp-header-end">

            <div id="bunny-stream-app" class="bunny-card">
                <div class="card-section">
                    <h2>Library Management</h2>
                    <p class="description">Add your Bunny.net Library IDs and API Secrets below.</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:70px">Default</th>
                                <th>Name (Key)</th>
                                <th>Library ID</th>
                                <th>Secret Key</th>
                                <th>Shortcode Helper</th>
                                <th style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="library-rows">
                            <!-- Injected by admin.js -->
                        </tbody>
                    </table>
                    <button type="button" class="button button-secondary" id="add-library" style="margin-top:15px;">
                        <span class="dashicons dashicons-plus-alt2" style="margin-top:4px"></span> Add Library
                    </button>
                </div>

                <div class="card-section" style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
                    <h2>Global Configuration</h2>
                    <div class="settings-grid">
                        <p>
                            <label><strong>Default Expiry (seconds):</strong></label><br>
                            <input type="number" id="global-expiry" class="regular-text" style="max-width:150px;">
                        </p>
                    </div>
                </div>

                <div class="card-footer" style="margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                    <button type="button" id="save-stream-safe" class="button button-primary button-large">Save All Settings</button>
                    <span id="save-status" style="margin-left:15px; font-weight:600;"></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_save_settings() {
        check_ajax_referer('bunny_stream_safe_secure_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden');

        $libraries = isset($_POST['libraries']) ? $_POST['libraries'] : [];
        $sanitized_libraries = [];

        foreach ($libraries as $lib) {
            $sanitized_libraries[] = [
                'key'    => sanitize_text_field($lib['key']),
                'id'     => sanitize_text_field($lib['id']),
                'secret' => sanitize_text_field($lib['secret'])
            ];
        }

        $settings = [
            'libraries'    => $sanitized_libraries,
            'default_lib'  => sanitize_text_field($_POST['default_lib']),
            'expiry'       => intval($_POST['expiry'])
        ];

        update_option(BUNNY_STREAM_SAFE_OPTION, $settings);
        wp_send_json_success();
    }
}

BunnyStreamSafe::get_instance();