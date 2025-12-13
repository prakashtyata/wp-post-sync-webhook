<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://softart.com.np
 * @since             1.0.0
 * @package           WP_Post_Sync_Webhook
 *
 * @wordpress-plugin
 * Plugin Name:       WP Post Sync Webhook
 * Plugin URI:        https://softart.com.np/plugins/wp-post-sync-webhook
 * Description:       Sync posts, pages, and custom post types between WordPress sites using secure webhooks. Includes featured images, content images, categories, tags, and custom fields.
 * Version:           1.7.0
 * Author:            Prakash Tyata
 * Author URI:        https://softart.com.np/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-post-sync-webhook
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Current plugin version.
 */
define( 'WPSW_VERSION', '1.7.0' );

/**
 * Plugin base path.
 */
define( 'WPSW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin base URL.
 */
define( 'WPSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'WPSW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function wpsw_activate() {
    require_once WPSW_PLUGIN_PATH . 'includes/class-wpsw-activator.php';
    WPSW_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function wpsw_deactivate() {
    require_once WPSW_PLUGIN_PATH . 'includes/class-wpsw-deactivator.php';
    WPSW_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'wpsw_activate' );
register_deactivation_hook( __FILE__, 'wpsw_deactivate' );

/**
 * The core plugin class.
 */
require WPSW_PLUGIN_PATH . 'includes/class-wpsw-loader.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function wpsw_run() {
    $plugin = new WPSW_Loader();
    $plugin->run();
}
wpsw_run();