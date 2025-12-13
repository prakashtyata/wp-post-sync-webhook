<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://softart.com.np
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'wpsw_settings' );

// Delete post meta created by the plugin.
global $wpdb;

$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpsw_source_url', '_wpsw_file_hash', '_remote_source_site', '_remote_post_id')"
);

// Clear any transients.
delete_transient( 'wpsw_health_check' );