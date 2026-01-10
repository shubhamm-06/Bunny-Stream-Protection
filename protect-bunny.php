<?php
/**
 * Plugin Name:       Protect Bunny – By Shubham Singh
 * Description:       Securely embeds Bunny.net Stream videos using URL Token Authentication (SHA256) and supports multiple libraries.
 * Version:           0.1
 * Author:            Shubham Kumar Singh
 * Author URI:        https://github.com/shubhamm-06
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

define('PROTECT_BUNNY_VERSION', '0.1');
define('PROTECT_BUNNY_PATH', plugin_dir_path(__FILE__));
define('PROTECT_BUNNY_URL', plugin_dir_url(__FILE__));

/**
 * Add Action Links to Plugin Page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pb_add_plugin_action_links');
function pb_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=protect-bunny') . '">Settings</a>';
    $update_link = '<a href="' . admin_url('options-general.php?page=protect-bunny&force-check=1') . '" style="color: #d63638; font-weight: bold;">Check for Update</a>';
    array_unshift($links, $settings_link, $update_link);
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
    if ($hook !== 'settings_page_protect-bunny') return;

    wp_enqueue_style('pb-admin-css', PROTECT_BUNNY_URL . 'admin.css', [], PROTECT_BUNNY_VERSION);
    wp_enqueue_script('pb-admin-js', PROTECT_BUNNY_URL . 'admin.js', ['jquery'], PROTECT_BUNNY_VERSION, true);
}

/**
 * Plugin Update Logic (Public GitHub Repo)
 */
add_filter('site_transient_update_plugins', 'pb_check_for_update');
function pb_check_for_update($transient) {
    if (empty($transient->checked)) return $transient;

    $repo_url = 'https://api.github.com/repos/shubhamm-06/Bunny-Stream-Protection/releases/latest';
    $response = wp_remote_get($repo_url, [
        'timeout' => 15,
        'headers' => [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        ]
    ]);

    if (is_wp_error($response)) return $transient;

    $release_data = json_decode(wp_remote_retrieve_body($response));
    if (isset($release_data->tag_name)) {
        $new_version = ltrim($release_data->tag_name, 'v');
        if (version_compare(PROTECT_BUNNY_VERSION, $new_version, '<')) {
            $plugin_slug = plugin_basename(__FILE__);
            $obj = new stdClass();
            $obj->slug = $plugin_slug;
            $obj->new_version = $new_version;
            $obj->url = 'https://github.com/shubhamm-06/Bunny-Stream-Protection';
            $obj->package = $release_data->zipball_url;
            $transient->response[$plugin_slug] = $obj;
        }
    }
    return $transient;
}

/**
 * Render Settings Page
 */
function pb_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    // Handle Force Update Check
    if (isset($_GET['force-check']) && $_GET['force-check'] === '1') {
        delete_site_transient('update_plugins');
        echo '<div class="updated"><p>Update cache cleared. WordPress will now re-check the GitHub repository for updates.</p></div>';
    }

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
        <h1 style="display: flex; align-items: center; gap: 15px;">
            Protect Bunny Settings 
            <span style="font-size: 12px; background: #eee; padding: 2px 8px; border-radius: 4px; color: #666; font-weight: normal;">v<?php echo PROTECT_BUNNY_VERSION; ?></span>
            <a href="<?php echo admin_url('options-general.php?page=protect-bunny&force-check=1'); ?>" class="page-title-action">Check for Update</a>
        </h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('pb_action_nonce', 'pb_nonce'); ?>
            
            <div class="pb-section">
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
                                <td><code class="pb-copy-code" title="Click to copy">[bunny_video video="ID" lib="<?php echo esc_attr($key); ?>"]</code></td>
                                <td><button type="button" class="button pb-remove-row">Remove</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p><button type="button" id="pb-add-library" class="button">Add Library</button></p>
            </div>

            <div class="pb-section">
                <h2>2. Global Configuration & Timing</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Default Token Expiry</th>
                        <td>
                            <input type="number" name="pb_expiry" value="<?php echo esc_attr($globals['token_expiry']); ?>" class="small-text"> 
                            <span class="description">seconds (e.g., 3600 for 1 hour). Controls link validity in the network tab.</span>
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
    $token = hash('sha256', $library['sec_key'] . $video_id . $expires);

    $host = ($globals['use_cdn'] === 'yes' && !empty($globals['cdn_hostname'])) ? $globals['cdn_hostname'] : 'iframe.mediadelivery.net';
    $embed_url = "https://{$host}/embed/{$library['lib_id']}/{$video_id}?token={$token}&expires={$expires}";

    return sprintf(
        '<div class="pb-video-container" style="position:relative;padding-bottom:56.25%%;height:0;overflow:hidden;border-radius:8px;background:#000;">
            <iframe src="%s" loading="lazy" style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;" allow="autoplay;fullscreen;picture-in-picture;encrypted-media" allowfullscreen referrerpolicy="origin"></iframe>
        </div>',
        esc_url($embed_url)
    );
}