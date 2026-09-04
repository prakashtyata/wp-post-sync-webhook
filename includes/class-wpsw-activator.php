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

class WPSW_Activator {

    public static function activate() {
        require_once WPSW_PLUGIN_PATH . 'admin/class-wpsw-settings.php';

        WPSW_Settings::migrate_settings();

        $current = get_option( 'wpsw_settings', array() );

        if ( empty( $current ) ) {
            add_option( 'wpsw_settings', array(
                'mode'        => 'sender',
                'receivers'   => array(),
                'sync_rules'  => WPSW_Settings::get_default_rules(),
                'sync_count'  => 0,
                'last_sync'   => '',
                'version'     => WPSW_VERSION,
            ) );
        } else {
            $defaults = array(
                'mode'        => 'sender',
                'receivers'   => array(),
                'sync_rules'  => WPSW_Settings::get_default_rules(),
                'sync_count'  => 0,
                'last_sync'   => '',
                'version'     => WPSW_VERSION,
            );

            $current = wp_parse_args( $current, $defaults );

            if ( empty( $current['receivers'] ) ) {
                $current['receivers'] = array();
            }

            if ( empty( $current['sync_rules'] ) ) {
                $current['sync_rules'] = WPSW_Settings::get_default_rules();
            }

            update_option( 'wpsw_settings', $current );
        }

        flush_rewrite_rules();
    }
}
