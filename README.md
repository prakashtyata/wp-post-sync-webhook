=== WP Post Sync Webhook ===
Contributors: prakashtyata
Donate link: https://softart.com.np/donate
Tags: sync, webhook, post sync, multisite, content sync, migration, duplicate posts
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.7.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync posts, pages, and custom post types between WordPress sites using secure webhooks.

== Description ==

**WP Post Sync Webhook** allows you to automatically sync content between two or more WordPress sites. When you publish or update a post on the sender site, it automatically appears on the receiver site.

Perfect for agencies, content networks, and multi-site publishers who need to distribute content across multiple WordPress installations.

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
* **Beautiful Admin UI** - Modern, intuitive settings panel

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

= Sender Site Setup =

1. Set mode to "Sender"
2. Enter the receiver site's webhook URL
3. Copy the secret key
4. Select post types to sync
5. Save settings

= Receiver Site Setup =

1. Set mode to "Receiver"
2. Paste the same secret key from the sender
3. Save settings

== Frequently Asked Questions ==

= Can I sync between more than two sites? =

Yes! You can set up multiple sender sites pointing to one receiver, or configure a site as "Both" to send and receive.

= Does it sync existing posts? =

By default, only new or updated posts are synced. Use the "Manual Sync" feature to sync existing posts by entering their post ID.

= What happens if the receiver site is down? =

The sync will fail silently. Check the debug log for error messages. The post will sync on the next update.

= Is it secure? =

Yes. All webhooks are signed with HMAC-SHA256 and verified using a shared secret key. Without the correct secret key, webhooks are rejected.

= Does it work with page builders? =

Yes, the post content is synced as-is, including page builder shortcodes and blocks. Images are also imported and URLs are updated.

= Will it create duplicate images? =

No! The smart image matching system checks if an image already exists before downloading. It matches by source URL, file hash, filename, and file size.

= Can I sync custom post types? =

Yes, you can select which post types to sync in the settings panel.

== Screenshots ==

1. Dashboard with sync statistics and status cards
2. Mode selection with visual cards
3. Connection settings panel
4. Tools panel with test connection and manual sync
5. Quick setup guide

== Changelog ==

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

= 1.7.0 =
Major update with new admin UI and smart image matching. Recommended for all users. No breaking changes.
