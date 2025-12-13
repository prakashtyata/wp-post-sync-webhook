<?php
/**
 * Settings page functionality.
 *
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 * @subpackage WP_Post_Sync_Webhook/admin
 * @author     Prakash Tyata <developer@softart.com.np>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Settings page functionality.
 */
class WPSW_Settings {

    /**
     * Add settings page to admin menu.
     *
     * @since 1.0.0
     */
    public function add_settings_page() {
        add_options_page(
            esc_html__( 'Post Sync Webhook', 'wp-post-sync-webhook' ),
            esc_html__( 'Post Sync Webhook', 'wp-post-sync-webhook' ),
            'manage_options',
            'wp-post-sync-webhook',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'wpsw_settings_group',
            'wpsw_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize settings.
     *
     * @since 1.0.0
     * @param array $input Input values.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $defaults = array(
            'mode'          => 'sender',
            'receiver_url'  => '',
            'secret_key'    => '',
            'post_types'    => array( 'post' ),
            'sync_count'    => 0,
            'last_sync'     => '',
        );

        $output = get_option( 'wpsw_settings', $defaults );

        if ( isset( $input['mode'] ) && in_array( $input['mode'], array( 'sender', 'receiver', 'both' ), true ) ) {
            $output['mode'] = $input['mode'];
        }

        if ( isset( $input['receiver_url'] ) ) {
            $output['receiver_url'] = esc_url_raw( trim( $input['receiver_url'] ) );
        }

        if ( isset( $input['secret_key'] ) && ! empty( $input['secret_key'] ) ) {
            $output['secret_key'] = sanitize_text_field( $input['secret_key'] );
        } elseif ( empty( $output['secret_key'] ) ) {
            $output['secret_key'] = wp_generate_password( 32, false );
        }

        if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            $output['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
        } else {
            $output['post_types'] = array( 'post' );
        }

        return $output;
    }

    /**
     * Render settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        include WPSW_PLUGIN_PATH . 'admin/partials/wpsw-admin-display.php';
    }
}