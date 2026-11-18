=== SoftArt Post Sync Webhook ===
Contributors: prakashtyata
Tags: sync, webhook, post sync, multisite, content sync
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 2.1.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync posts, pages, and Elementor-built content between WordPress sites using secure webhooks.

== Description ==

**SoftArt Post Sync Webhook** allows you to automatically sync content between two or more WordPress sites. When you publish or update a post on the sender site, it automatically appears on the receiver site.

One site acts as the sender and any number of sites can act as receivers. Each receiver has its own webhook URL, secret key, enabled post types and sync status, so content can be distributed to several destinations from a single settings screen.

= ✨ Features =

* **Automatic Sync** - Posts sync automatically when published or updated
* **Featured Images** - Featured images are synced with alt text and captions
* **Content Images** - All images within post content are synced
* **Smart Image Matching** - Avoids duplicate downloads by matching existing images
* **Categories & Tags** - Taxonomies are synced and created if needed
* **Custom Fields** - Post meta data is synced
* **Secure** - Uses HMAC-SHA256 signatures for webhook verification
* **Custom Post Types** - Sync any public post type
* **Manual Sync** - Manually sync specific posts when needed
* **Health Check** - Verify connection status between sites
* **Guided Setup** - Tabbed settings screen with step-by-step connection instructions

= 🎯 Use Cases =

* Sync content from a main site to satellite sites
* Create a staging to production workflow
* Mirror content across multiple regional sites
* Backup posts to another WordPress installation
* Distribute content to partner websites

= 🚀 How It Works =

1. Install the plugin on both sites
2. Configure one site as "Sender" and one as "Receiver"
3. Set the same secret key on both sites
4. Publish content on the sender - it appears on the receiver!

= 🔒 Security =

* All webhooks are signed with HMAC-SHA256
* Secret key verification prevents unauthorized access
* No sensitive data is exposed

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/softart-post-sync-webhook/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Post Sync Webhook to configure

= Sender Site Setup (Site A) =

1. Go to Settings → Post Sync Webhook
2. Set Sync Mode to "Sender"
3. Click "Generate Key" and "Copy Key" (save this to paste into Site B)
4. Paste the Receiver Webhook URL (copied from Site B's endpoint green box)
5. Select post types to sync (Posts, Pages, CPTs) and click "Save Settings"

= Receiving Site Setup (Site B) =

1. Go to Settings → Post Sync Webhook
2. Set Sync Mode to "Receiver"
3. Paste the exact same Secret Key copied from Site A
4. Click "Copy Endpoint URL" in the green box and paste it into Site A's Receiver URL
5. Click "Save Settings"

== Frequently Asked Questions ==

= Can I sync between more than two sites? =

Yes! You can set up multiple sender sites pointing to one receiver, or configure a site as "Both" to send and receive.

= Does it sync existing posts? =

By default, only new or updated posts are synced. Use the "Manual Sync" feature to sync existing posts by entering their post ID.

= What happens if the receiver site is down? =

The sync will fail silently. Check the debug log for error messages. The post will sync on the next update.

= Is it secure? =

Yes. All webhooks are signed with HMAC-SHA256 and verified using a shared secret key. Without the correct secret key, webhooks are rejected.

== External services ==

This plugin does not send any data to SoftArt. It only communicates with the sites and URLs you configure yourself.

= Webhook requests =

When a post is published or updated on a sender site, its content, post meta and taxonomy terms are sent to the receiver webhook URL entered in the settings. The request is signed with the secret key you generate.

= Image downloads =

A receiver site downloads the images referenced in received content from the URLs contained in that content, so they can be imported into its media library.

= Update checks =

The GitHub Plugin URI header is used by the GitHub Updater plugin, when installed, to check this plugin's GitHub repository for new releases.

= Service used =

GitHub (https://github.com/prakashtyata/wp-post-sync-webhook) hosts the plugin source code and release packages. Terms of service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service Privacy policy: https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement

= Does it work with page builders? =

Yes, the post content is synced as-is, including page builder shortcodes and blocks. Images are also imported and URLs are updated.

= Will it create duplicate images? =

No! The smart image matching system checks if an image already exists before downloading. It matches by source URL, file hash, filename, and file size.

= Can I sync custom post types? =

Yes, you can select which post types to sync in the settings panel.

== Screenshots ==

1. Dashboard with sync statistics and receiver status
2. Connection settings with webhook URL and secret key

== Changelog ==

= 2.1.4 =
* Escaped the remaining dynamic attribute output in the post type checkboxes
* Fixed the admin styles and scripts not loading on the settings page
* Fixed the Settings link on the Plugins screen
* Removed the bundled translation loader; WordPress now loads translations automatically
* Removed the plugin icon, banner and screenshots from the plugin package; they are distributed through the directory
* Updated the plugin and documentation URLs, the readme tags and the external services disclosure

= 2.1.3 =
* Escaped dynamic output in the admin settings screen
* Fixed text domain usage across admin and receiver classes

= 2.1.2 =
* Renamed the plugin and text domain to follow WordPress.org guidelines

= 2.1.1 =
* Manual Sync: shows full scrollable list of posts/pages with search, plus option to manually enter comma-separated post/page IDs

= 2.1.0 =
* Manual Sync now supports syncing multiple posts/pages at once via a searchable multi-select picker (by post type)
* Added "All Receivers" and per-receiver bulk sync with per-post result reporting

= 2.0.1 =
* Added field-level tooltips on Webhook URL and Secret Key explaining where to get and where to put each value

= 2.0.0 =
* Multiple receiver sites support with repeater UI
* Elementor page builder sync - detects and syncs all _elementor_* meta data
* Divi builder sync - detects and syncs all _et_pb_* meta data
* Smart image replacement in Elementor JSON data structures
* Auto Elementor/Divi CSS cache clearing on receiver after sync
* Tab-based settings page (Dashboard, Connections, Sync Rules, Tools)
* Per-receiver post type selection
* Per-receiver enable/disable toggle
* Per-receiver sync stats and status tracking
* Activity log viewer in admin
* Sync rules configuration (choose what to sync)
* Meta key exclusion list
* Default post status for received posts
* Auto-migration from v1.x settings format
* Improved category and tag creation on receiver
* Added clear side-by-side "Quick Copy & Paste Cheat Sheet" and summary matrix
* Added One-Click Secret Key Generator, Copy button, and Show/Hide toggle
* Added One-Click Endpoint URL Copy button with visual flash feedback
* Added real-time Receiver URL validation helper
* Added Select All / Deselect All controls for post types
* Enhanced diagnostic and connection testing tools

= 1.7.0 =
* Added smart image matching to avoid duplicate downloads
* New modern admin UI with statistics dashboard
* Added manual sync tool
* Added health check functionality
* Added connection test feature
* Improved image handling with hash-based matching
* Added sync statistics tracking

= 1.6.0 =
* Fixed Gutenberg featured image timing issue
* Added shutdown hook for reliable webhook sending
* Added thumbnail meta hooks for better detection

= 1.5.0 =
* Added content image syncing
* Added tags support
* Improved error logging
* Better payload structure

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major upgrade with multiple receiver support, Elementor/Divi page builder sync, and new tabbed settings UI. Auto-migrates from v1.x. Recommended for all users.