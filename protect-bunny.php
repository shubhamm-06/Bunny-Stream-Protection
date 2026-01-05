<?php
/**
 * Plugin Name:       Protect Bunny – By Shubham Singh
 * Description:       Securely embeds Bunny.net Stream videos using URL Token Authentication (SHA256) and supports multiple libraries.
 * Version:           5.2.25
 * Author:            Shubham Kumar Singh
 * Author URI:        https://github.com/shubhamm-06
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

define('STOCK_DASHBOARD_PLUGIN_VERSION', '5.2.25');
define('STOCK_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROTECT_BUNNY_VERSION', '5.2.25');

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
 * Plugin Update Logic (Public GitHub Repo)
 */
add_filter('site_transient_update_plugins', 'pb_check_for_update');
function pb_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $repo_url = 'https://api.github.com/repos/shubhamm-06/protect-bunny/releases/latest'; // Ensure this matches your repo
    $response = wp_remote_get($repo_url, [
        'headers' => [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        ]
    ]);

    if (is_wp_error($response)) {
        return $transient;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response));

    if (isset($release_data->tag_name)) {
        $new_version = ltrim($release_data->tag_name, 'v');

        if (version_compare(PROTECT_BUNNY_VERSION, $new_version, '<')) {
            $plugin_slug = plugin_basename(__FILE__);
            $obj = new stdClass();
            $obj->slug = $plugin_slug;
            $obj->new_version = $new_version;
            $obj->url = 'https://github.com/shubhamm-06/protect-bunny';
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
                        <th>Shortcode Helper</th>
                        <th width="80">Action</th>
                    </tr>
                </thead>
                <tbody id="pb-library-rows">
                    <?php if (empty($libraries)): ?>
                        <tr class="pb-no-libs"><td colspan="6">No libraries added yet. Click "Add Library" to start.</td></tr>
                    <?php else: ?>
                        <?php foreach ($libraries as $key => $data): 
                            $shortcode_hint = '[bunny_video video="ID" lib="' . esc_attr($key) . '"]';
                        ?>
                        <tr>
                            <td><input type="radio" name="default_lib" value="<?php echo esc_attr($key); ?>" <?php checked($default_lib, $key); ?> required></td>
                            <td><input type="text" name="libs[<?php echo esc_attr($key); ?>][key]" value="<?php echo esc_attr($data['key']); ?>" placeholder="e.g. primary" class="regular-text" readonly></td>
                            <td><input type="text" name="libs[<?php echo esc_attr($key); ?>][lib_id]" value="<?php echo esc_attr($data['lib_id']); ?>" placeholder="Library ID" class="regular-text"></td>
                            <td><input type="password" name="libs[<?php echo esc_attr($key); ?>][sec_key]" value="<?php echo esc_attr($data['sec_key']); ?>" placeholder="Security Key" class="regular-text"></td>
                            <td>
                                <code class="pb-copy-code" style="cursor: pointer; display: block; padding: 5px; background: #f0f0f0; border: 1px solid #ccc; font-size: 11px;" title="Click to copy">
                                    <?php echo esc_html($shortcode_hint); ?>
                                </code>
                            </td>
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

        <hr style="margin-top: 30px;">
        <div class="pb-update-check">
            <h3>Check for Updates</h3>
            <p>Your current version: <strong><?php echo PROTECT_BUNNY_VERSION; ?></strong></p>
            <a href="<?php echo admin_url('update-core.php?force-check=1'); ?>" class="button">Force Update Check</a>
        </div>
    </div>

    <!-- Row Template -->
    <script type="text/template" id="pb-row-template">
        <tr>
            <td><input type="radio" name="default_lib" value="__KEY__" required></td>
            <td><input type="text" name="libs[__KEY__][key]" value="" placeholder="e.g. primary" class="regular-text pb-key-input" required></td>
            <td><input type="text" name="libs[__KEY__][lib_id]" value="" placeholder="Library ID" class="regular-text" required></td>
            <td><input type="password" name="libs[__KEY__][sec_key]" value="" placeholder="Security Key" class="regular-text" required></td>
            <td><small>Save to see shortcode</small></td>
            <td><button type="button" class="button pb-remove-row">Remove</button></td>
        </tr>
    </script>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.pb-copy-code').on('click', function() {
                var text = $(this).text().trim();
                var temp = $("<input>");
                $("body").append(temp);
                temp.val(text).select();
                document.execCommand("copy");
                temp.remove();
                
                var $el = $(this);
                var originalColor = $el.css('background');
                $el.css('background', '#c6ffc6').text('Copied!');
                setTimeout(function() {
                    $el.css('background', originalColor).text(text);
                }, 1000);
            });
        });
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