<?php
/**
 * Plugin Name:       Stock Dashboard
 * Description:       Displays finance data dynamically from a secured private GitHub repository.
 * Version:           5.2.25
 * Author:            Shubham Kumar Singh
 * Author URI:        https://github.com/shubhamm-06
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

define('STOCK_DASHBOARD_PLUGIN_VERSION', '5.2.25');
define('STOCK_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROTECT_BUNNY_VERSION', '1.0.0');

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
    if ($hook !== 'settings_page_protect-bunny') return;

    wp_enqueue_style('pb-admin-css', plugin_dir_url(__FILE__) . 'admin.css', [], PROTECT_BUNNY_VERSION);
    wp_enqueue_script('pb-admin-js', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], PROTECT_BUNNY_VERSION, true);
}

/**
 * Render Settings Page
 */
function pb_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['pb_save_libraries']) && check_admin_referer('pb_save_action', 'pb_nonce')) {
        $raw_libs = isset($_POST['libs']) ? $_POST['libs'] : [];
        $default_key = sanitize_key($_POST['default_lib'] ?? '');
        
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
        update_option('pb_default_lib', $default_key);
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    $libraries = get_option('pb_libraries', []);
    $default_lib = get_option('pb_default_lib', '');
    ?>
    <div class="wrap">
        <h1>Protect Bunny Settings</h1>
        <p>Manage your Bunny.net Stream libraries and security keys below.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('pb_save_action', 'pb_nonce'); ?>
            
            <table class="wp-list-table widefat fixed striped pb-settings-table">
                <thead>
                    <tr>
                        <th width="50">Default</th>
                        <th>Library Name / Key</th>
                        <th>Library ID</th>
                        <th>Security Key (Pull Zone)</th>
                        <th width="80">Action</th>
                    </tr>
                </thead>
                <tbody id="pb-library-rows">
                    <?php if (empty($libraries)): ?>
                        <tr class="pb-no-libs"><td colspan="5">No libraries added yet. Click "Add Library" to start.</td></tr>
                    <?php else: ?>
                        <?php foreach ($libraries as $key => $data): ?>
                        <tr>
                            <td><input type="radio" name="default_lib" value="<?php echo esc_attr($key); ?>" <?php checked($default_lib, $key); ?> required></td>
                            <td><input type="text" name="libs[<?php echo esc_attr($key); ?>][key]" value="<?php echo esc_attr($data['key']); ?>" placeholder="e.g. primary" class="regular-text" readonly></td>
                            <td><input type="text" name="libs[<?php echo esc_attr($key); ?>][lib_id]" value="<?php echo esc_attr($data['lib_id']); ?>" placeholder="Library ID" class="regular-text"></td>
                            <td><input type="password" name="libs[<?php echo esc_attr($key); ?>][sec_key]" value="<?php echo esc_attr($data['sec_key']); ?>" placeholder="Security Key" class="regular-text"></td>
                            <td><button type="button" class="button pb-remove-row">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 15px;">
                <button type="button" id="pb-add-library" class="button">Add Library</button>
                <input type="submit" name="pb_save_libraries" class="button button-primary" value="Save Settings">
            </div>
        </form>
    </div>

    <!-- Row Template -->
    <script type="text/template" id="pb-row-template">
        <tr>
            <td><input type="radio" name="default_lib" value="__KEY__" required></td>
            <td><input type="text" name="libs[__KEY__][key]" value="" placeholder="e.g. primary" class="regular-text pb-key-input" required></td>
            <td><input type="text" name="libs[__KEY__][lib_id]" value="" placeholder="Library ID" class="regular-text" required></td>
            <td><input type="password" name="libs[__KEY__][sec_key]" value="" placeholder="Security Key" class="regular-text" required></td>
            <td><button type="button" class="button pb-remove-row">Remove</button></td>
        </tr>
    </script>
    <?php
}

/**
 * Shortcode logic
 */
add_shortcode('bunny_video', 'pb_video_shortcode');
function pb_video_shortcode($atts) {
    $atts = shortcode_atts([
        'video' => '',
        'lib'   => ''
    ], $atts, 'bunny_video');

    if (empty($atts['video'])) {
        return '<!-- Bunny Video Error: Missing video ID -->';
    }

    $libraries = get_option('pb_libraries', []);
    $default_key = get_option('pb_default_lib', '');
    
    // Resolve library
    $lib_key = !empty($atts['lib']) && isset($libraries[$atts['lib']]) ? $atts['lib'] : $default_key;
    
    if (empty($lib_key) || !isset($libraries[$lib_key])) {
        return '<!-- Bunny Video Error: No library configured -->';
    }

    $library = $libraries[$lib_key];
    $video_id = sanitize_text_field($atts['video']);
    $lib_id = $library['lib_id'];
    $security_key = $library['sec_key'];

    // Token Logic
    $expiry_seconds = apply_filters('protect_bunny_token_expiry', 3600);
    $expires = time() + $expiry_seconds;
    $token = hash('sha256', $security_key . $video_id . $expires);

    $embed_url = sprintf(
        "https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%d",
        $lib_id,
        $video_id,
        $token,
        $expires
    );

    // Responsive Output
    ob_start();
    ?>
    <div class="pb-video-container" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000; border-radius: 8px;">
        <iframe 
            src="<?php echo esc_url($embed_url); ?>" 
            loading="lazy" 
            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" 
            allow="autoplay; fullscreen; picture-in-picture; encrypted-media" 
            allowfullscreen 
            referrerpolicy="origin">
        </iframe>
    </div>
    <?php
    return ob_get_clean();
}