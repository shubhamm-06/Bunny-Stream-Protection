<?php
/**
 * Plugin Name:       Protect Bunny – By Shubham Singh
 * Description:       Securely embeds Bunny.net Stream videos using URL Token Authentication (SHA256) and supports multiple libraries.
 * Version:           0.2
 * Author:            Shubham Kumar Singh
 * Author URI:        https://github.com/shubhamm-06
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

define('PROTECT_BUNNY_VERSION', '0.2');
define('PROTECT_BUNNY_PATH', plugin_dir_path(__FILE__));
define('PROTECT_BUNNY_URL', plugin_dir_url(__FILE__));

/**
 * Initialize Plugin Update Checker
 */
add_action('plugins_loaded', function() {
    if (file_exists(PROTECT_BUNNY_PATH . 'plugin-update-checker/plugin-update-checker.php')) {
        require_once PROTECT_BUNNY_PATH . 'plugin-update-checker/plugin-update-checker.php';
        if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            $pbUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/shubhamm-06/Bunny-Stream-Protection',
                __FILE__,
                'protect-bunny'
            );
            $pbUpdateChecker->setBranch('main');
            
            // Store instance for AJAX access
            $GLOBALS['pb_update_checker'] = $pbUpdateChecker;
        }
    }
});

/**
 * AJAX Handler for Manual Update Check
 */
add_action('wp_ajax_pb_force_update_check', function() {
    check_ajax_referer('pb_ajax_nonce', 'nonce');
    
    if (isset($GLOBALS['pb_update_checker'])) {
        // Trigger a check
        $update = $GLOBALS['pb_update_checker']->requestUpdate();
        $update_available = ($update !== null);
        
        wp_send_json_success([
            'message' => $update_available ? 'A new version is available!' : 'You are running the latest version.',
            'update_available' => $update_available
        ]);
    }
    
    wp_send_json_error('Update checker could not be initialized.');
});

/**
 * Add Action Links to Plugin Page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pb_add_plugin_action_links');
function pb_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=protect-bunny') . '">Settings</a>';
    $links['update_check'] = '<a href="#" class="pb-check-update-btn" style="color: #d63638; font-weight: bold;">Check for Update</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Register Admin Menu
 */
add_action('admin_menu', 'pb_register_settings_page');
function pb_register_settings_page() {
    add_options_page(
        'Protect Bunny Settings',
        'Protect Bunny',
        'manage_options',
        'protect-bunny',
        'pb_render_settings_page'
    );
}

/**
 * Enqueue Admin Assets
 */
add_action('admin_enqueue_scripts', 'pb_enqueue_admin_assets');
function pb_enqueue_admin_assets($hook) {
    if ($hook !== 'settings_page_protect-bunny' && $hook !== 'plugins.php') return;

    wp_enqueue_style('pb-admin-css', PROTECT_BUNNY_URL . 'admin.css', [], PROTECT_BUNNY_VERSION);
    wp_enqueue_script('pb-admin-js', PROTECT_BUNNY_URL . 'admin.js', ['jquery'], PROTECT_BUNNY_VERSION, true);

    wp_localize_script('pb-admin-js', 'pb_vars', [
        'nonce' => wp_create_nonce('pb_ajax_nonce')
    ]);
}

/**
 * Render Settings Page
 */
function pb_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    // Handle Saving
    if (isset($_POST['pb_save_all']) && check_admin_referer('pb_action_nonce', 'pb_nonce')) {
        $raw_libs = isset($_POST['libs']) ? $_POST['libs'] : [];
        $sanitized_libs = [];
        foreach ($raw_libs as $lib) {
            $key = sanitize_key($lib['key']);
            if (empty($key)) continue;
            $sanitized_libs[$key] = [
                'key' => $key,
                'lib_id' => sanitize_text_field($lib['lib_id']),
                'sec_key' => sanitize_text_field($lib['sec_key']),
            ];
        }
        update_option('pb_libraries', $sanitized_libs);
        update_option('pb_default_lib', sanitize_key($_POST['default_lib'] ?? ''));

        $global_settings = [
            'token_expiry' => absint($_POST['pb_expiry'] ?? 3600),
            'use_cdn'      => isset($_POST['pb_use_cdn']) ? 'yes' : 'no',
            'cdn_hostname' => sanitize_text_field($_POST['pb_cdn_hostname'] ?? ''),
        ];
        update_option('pb_global_settings', $global_settings);

        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    $libraries = get_option('pb_libraries', []);
    $default_lib = get_option('pb_default_lib', '');
    $globals = get_option('pb_global_settings', ['token_expiry' => 3600, 'use_cdn' => 'no', 'cdn_hostname' => '']);
    ?>
    <div class="wrap pb-admin-wrap">
        <h1 style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            Protect Bunny Settings 
            <span style="font-size: 12px; background: #eee; padding: 2px 8px; border-radius: 4px; color: #666; font-weight: normal;">v<?php echo PROTECT_BUNNY_VERSION; ?></span>
            <button type="button" class="page-title-action pb-check-update-btn">Check for Update</button>
        </h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('pb_action_nonce', 'pb_nonce'); ?>
            
            <div class="pb-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px;">
                <h2>1. Library Configuration</h2>
                <table class="wp-list-table widefat fixed striped pb-settings-table">
                    <thead>
                        <tr>
                            <th width="50">Default</th>
                            <th>Library Key</th>
                            <th>Library ID</th>
                            <th>Security Key</th>
                            <th>Shortcode Helper</th>
                            <th width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody id="pb-library-rows">
                        <?php if (empty($libraries)): ?>
                            <tr class="pb-no-libs"><td colspan="6">No libraries added yet. Click "Add Library" to start.</td></tr>
                        <?php else: ?>
                            <?php foreach ($libraries as $key => $data): ?>
                            <tr>
                                <td><input type="radio" name="default_lib" value="<?php echo esc_attr($key); ?>" <?php checked($default_lib, $key); ?> required></td>
                                <td><input type="text" name="libs[<?php echo esc_attr($key); ?>][key]" value="<?php echo esc_attr($data['key']); ?>" class="regular-text" readonly></td>
                                <td><input type="text" name="libs[<?php echo esc_attr($key); ?>][lib_id]" value="<?php echo esc_attr($data['lib_id']); ?>" class="regular-text"></td>
                                <td><input type="password" name="libs[<?php echo esc_attr($key); ?>][sec_key]" value="<?php echo esc_attr($data['sec_key']); ?>" class="regular-text"></td>
                                <td><code class="pb-copy-code" title="Click to copy" style="cursor: pointer; display: block; padding: 5px; background: #f0f0f1; border: 1px dashed #999;">[bunny_video video="ID" lib="<?php echo esc_attr($key); ?>"]</code></td>
                                <td><button type="button" class="button pb-remove-row">Remove</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p><button type="button" id="pb-add-library" class="button">Add Library</button></p>
            </div>

            <div class="pb-section" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px;">
                <h2>2. Global Configuration & Timing</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Token Expiry</th>
                        <td>
                            <input type="number" name="pb_expiry" value="<?php echo esc_attr($globals['token_expiry']); ?>" class="small-text"> 
                            <span class="description">seconds (e.g., 3600 for 1 hour). Controls how long the generated URL remains valid.</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">CDN Stream Mode</th>
                        <td>
                            <label><input type="checkbox" name="pb_use_cdn" value="yes" <?php checked($globals['use_cdn'], 'yes'); ?>> Enable Custom Hostname</label>
                            <p><input type="text" name="pb_cdn_hostname" value="<?php echo esc_attr($globals['cdn_hostname']); ?>" placeholder="e.g. video.yourdomain.com" class="regular-text"></p>
                            <p class="description">When enabled, URLs will use your custom domain. Token and Expiry are automatically handled in the URL query string.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="pb-submit-zone" style="margin-top: 20px; display: flex; align-items: center; gap: 10px;">
                <input type="submit" name="pb_save_all" class="button button-primary" value="Save All Changes">
                <a href="https://github.com/shubhamm-06/Bunny-Stream-Protection" target="_blank" class="button pb-help-link">Help & Documentation</a>
            </div>
        </form>
    </div>

    <script type="text/template" id="pb-row-template">
        <tr>
            <td><input type="radio" name="default_lib" value="__KEY__" required></td>
            <td><input type="text" name="libs[__KEY__][key]" value="" placeholder="e.g. main" class="regular-text pb-key-input" required></td>
            <td><input type="text" name="libs[__KEY__][lib_id]" value="" placeholder="Library ID" class="regular-text" required></td>
            <td><input type="password" name="libs[__KEY__][sec_key]" value="" placeholder="Security Key" class="regular-text" required></td>
            <td><small>Save to see shortcode</small></td>
            <td><button type="button" class="button pb-remove-row">Remove</button></td>
        </tr>
    </script>
    <?php
}

/**
 * Shortcode Logic
 */
add_shortcode('bunny_video', 'pb_video_shortcode');
function pb_video_shortcode($atts) {
    $globals = get_option('pb_global_settings', ['token_expiry' => 3600, 'use_cdn' => 'no', 'cdn_hostname' => '']);
    
    $atts = shortcode_atts([
        'video'  => '',
        'lib'    => '',
        'expiry' => $globals['token_expiry']
    ], $atts, 'bunny_video');

    if (empty($atts['video'])) return '<!-- Bunny Error: Missing Video ID -->';

    $libraries = get_option('pb_libraries', []);
    $default_key = get_option('pb_default_lib', '');
    $lib_key = (!empty($atts['lib']) && isset($libraries[$atts['lib']])) ? $atts['lib'] : $default_key;
    
    if (empty($lib_key) || !isset($libraries[$lib_key])) return '<!-- Bunny Error: Library not found -->';

    $library = $libraries[$lib_key];
    $video_id = sanitize_text_field($atts['video']);
    $expires = time() + absint($atts['expiry']);
    
    // Core Security Logic: SHA256( security_key + video_id + expiry )
    $token = hash('sha256', $library['sec_key'] . $video_id . $expires);

    // Hostname logic
    $host = ($globals['use_cdn'] === 'yes' && !empty($globals['cdn_hostname'])) ? $globals['cdn_hostname'] : 'iframe.mediadelivery.net';
    $embed_url = "https://{$host}/embed/{$library['lib_id']}/{$video_id}?token={$token}&expires={$expires}";

    return sprintf(
        '<div class="pb-video-container" style="position:relative;padding-bottom:56.25%%;height:0;overflow:hidden;border-radius:8px;background:#000;">
            <iframe src="%s" loading="lazy" style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;" allow="autoplay;fullscreen;picture-in-picture;encrypted-media" allowfullscreen referrerpolicy="origin"></iframe>
        </div>',
        esc_url($embed_url)
    );
}