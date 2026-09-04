<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://softart.com.np
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'wpsw_settings' );
delete_option( 'wpsw_log' );
delete_transient( 'wpsw_health_check' );

global $wpdb;

$meta_keys = array(
    '_wpsw_source_url',
    '_wpsw_file_hash',
    '_remote_source_site',
    '_remote_post_id',
    '_elementor_css',
    '_elementor_page_assets',
    '_elementor_controls_usage',
    '_elementor_conditions',
);

foreach ( $meta_keys as $key ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $key
        )
    );
}
