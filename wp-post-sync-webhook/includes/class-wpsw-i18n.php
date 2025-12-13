<?php
/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 * @subpackage WP_Post_Sync_Webhook/includes
 * @author     Prakash Tyata <developer@softart.com.np>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Define the internationalization functionality.
 */
class WPSW_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wp-post-sync-webhook',
            false,
            dirname( WPSW_PLUGIN_BASENAME ) . '/languages/'
        );
    }
}