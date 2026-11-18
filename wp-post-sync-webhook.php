<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://softart.com.np
 * @since             1.0.0
 * @package           WP_Post_Sync_Webhook
 *
 * @wordpress-plugin
 * Plugin Name:       SoftArt Post Sync Webhook
 * Plugin URI:        https://github.com/prakashtyata/wp-post-sync-webhook
 * Description:       Sync posts, pages, and Elementor/Divi-built content between WordPress sites using secure webhooks. Supports multiple receiver sites with per-site settings.
 * Version:           2.1.4
 * Author:            Prakash Tyata
 * Author URI:        https://softart.com.np/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       softart-post-sync-webhook
 * Domain Path:       /languages
 *
 * GitHub Plugin URI:  prakashtyata/wp-post-sync-webhook
 * Primary Branch:     main
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Current plugin version.
 */
define( 'WPSW_VERSION', '2.1.4' );

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
 * Settings class (needed by sender/receiver).
 */
require WPSW_PLUGIN_PATH . 'admin/class-wpsw-settings.php';

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