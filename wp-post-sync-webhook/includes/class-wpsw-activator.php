<?php
/**
 * Fired during plugin activation.
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
 * Fired during plugin activation.
 */
class WPSW_Activator {

    /**
     * Activate the plugin.
     *
     * @since 1.0.0
     */
    public static function activate() {
        $defaults = array(
            'mode'          => 'sender',
            'receiver_url'  => '',
            'secret_key'    => wp_generate_password( 32, false ),
            'post_types'    => array( 'post' ),
            'sync_count'    => 0,
            'last_sync'     => '',
            'version'       => WPSW_VERSION,
        );

        $current = get_option( 'wpsw_settings', array() );

        if ( empty( $current ) ) {
            add_option( 'wpsw_settings', $defaults );
        } else {
            update_option( 'wpsw_settings', wp_parse_args( $current, $defaults ) );
        }

        flush_rewrite_rules();
    }
}