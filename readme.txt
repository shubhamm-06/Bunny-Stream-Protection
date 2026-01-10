=== Bunny Stream Safed ===
Contributors: Shubham Singh
Tags: bunny.net, security, video, streaming, sha256, secure embed, video player, drm
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely embed Bunny.net media using SHA256 Token Authentication and DRM support to prevent unauthorized hotlinking and content theft.

== Description ==

Bunny Stream Safed is a high-security integration for Bunny.net streaming. It generates dynamic, time-limited tokens for every video embed, ensuring your content is only accessible via your authorized WordPress site.

Key Features:

SHA256 Token Authentication: Implements the official Bunny.net security algorithm.

DRM & Media Security Ready: Optimized to work with Bunny.net's Digital Rights Management for encrypted streaming.

Dynamic Library Management: Manage multiple Bunny.net libraries and secrets from a single dashboard.

GitHub Auto-Updates: Integrated with plugin-update-checker for seamless version management.

Modern Admin UI: Clean, AJAX-powered settings page with live shortcode helpers.

== Installation ==

Upload the bunny-stream-safed folder to the /wp-content/plugins/ directory.

Activate the plugin through the 'Plugins' menu in WordPress.

In Bunny.net Dashboard: * Navigate to Stream Library > Security.

Enable Token Authentication.

Enable Media Security & DRM for maximum content protection.

Copy your Library API Key.

Navigate to Settings > Bunny Stream Safe in your WordPress admin.

Add your Library ID and the API Key (Secret) you just copied.

Set your default library and global token expiry time.

== Shortcode Usage ==

Embed videos anywhere using the shortcode:
[bunny_video id="YOUR_VIDEO_ID"]

To use a specific library other than the default:
[bunny_video id="YOUR_VIDEO_ID" lib="YOUR_LIB_KEY"]

== Frequently Asked Questions ==

= Why should I enable DRM? =
While Token Authentication prevents unauthorized domains from embedding your video, DRM (Digital Rights Management) encrypts the actual video chunks. This prevents users from using browser extensions or tools to download your content. For "full safety," both should be enabled.

= Where do I find my API Secret? =
In your Bunny.net dashboard, navigate to your Stream Library > Security. It is labeled as the Library API Key.

= Does this work with any Bunny.net plan? =
Token authentication is standard, but check your Bunny.net plan to ensure DRM features are active for your specific library.

== Screenshots ==

Dashboard Overview: The dynamic Library Management table where you manage IDs and secrets.

Shortcode Implementation: Example of the live shortcode helper updating in real-time.

Front-end Player: A securely embedded Bunny.net player with a 16:9 responsive wrapper.

== Changelog ==

= 1.1.0 =

Added DRM configuration guidance for enhanced security.

Optimized Admin UI with live shortcode helpers.

Integrated GitHub repository for automatic updates.

= 1.0.0 =

Initial release with SHA256 security logic.