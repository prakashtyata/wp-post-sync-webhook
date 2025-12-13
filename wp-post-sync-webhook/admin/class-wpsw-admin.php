<?php
/**
 * The admin-specific functionality of the plugin.
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
 * The admin-specific functionality of the plugin.
 */
class WPSW_Admin {

    /**
     * Register the stylesheets for the admin area.
     *
     * @since 1.0.0
     * @param string $hook Current admin page.
     */
    public function enqueue_styles( $hook ) {
        if ( 'settings_page_wp-post-sync-webhook' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wpsw-admin',
            WPSW_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WPSW_VERSION,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since 1.0.0
     * @param string $hook Current admin page.
     */
    public function enqueue_scripts( $hook ) {
        if ( 'settings_page_wp-post-sync-webhook' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'wpsw-admin',
            WPSW_PLUGIN_URL . 'assets/js/admin-script.js',
            array( 'jquery' ),
            WPSW_VERSION,
            true
        );

        wp_localize_script(
            'wpsw-admin',
            'wpswAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wpsw_ajax' ),
                'i18n'    => array(
                    'testing'       => esc_html__( 'Testing connection...', 'wp-post-sync-webhook' ),
                    'syncing'       => esc_html__( 'Syncing post...', 'wp-post-sync-webhook' ),
                    'checking'      => esc_html__( 'Checking health...', 'wp-post-sync-webhook' ),
                    'enterPostId'   => esc_html__( 'Please enter a post ID', 'wp-post-sync-webhook' ),
                    'noReceiverUrl' => esc_html__( 'No receiver URL configured', 'wp-post-sync-webhook' ),
                    'copied'        => esc_html__( 'Copied!', 'wp-post-sync-webhook' ),
                ),
            )
        );
    }

    /**
     * Add plugin action links.
     *
     * @since 1.0.0
     * @param array $links Existing links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=wp-post-sync-webhook' ),
            esc_html__( 'Settings', 'wp-post-sync-webhook' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * AJAX handler for testing connection.
     *
     * @since 1.0.0
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $settings = get_option( 'wpsw_settings', array() );

        if ( empty( $settings['receiver_url'] ) ) {
            wp_send_json_error( esc_html__( 'Receiver URL not configured', 'wp-post-sync-webhook' ) );
        }

        $health_url = str_replace( '/receive', '/health', $settings['receiver_url'] );

        $response = wp_remote_get(
            $health_url,
            array(
                'timeout'   => 15,
                'sslverify' => false,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $code && isset( $body['status'] ) && 'ok' === $body['status'] ) {
            /* translators: %s: receiver version */
            wp_send_json_success( sprintf( esc_html__( 'Connected! Receiver version: %s', 'wp-post-sync-webhook' ), $body['version'] ?? 'unknown' ) );
        } else {
            /* translators: %d: HTTP response code */
            wp_send_json_error( sprintf( esc_html__( 'Unexpected response (HTTP %d)', 'wp-post-sync-webhook' ), $code ) );
        }
    }

    /**
     * AJAX handler for manual sync.
     *
     * @since 1.0.0
     */
    public function ajax_sync_post() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            wp_send_json_error( esc_html__( 'Invalid post ID', 'wp-post-sync-webhook' ) );
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            wp_send_json_error( esc_html__( 'Post not found', 'wp-post-sync-webhook' ) );
        }

        $sender   = new WPSW_Sender();
        $settings = get_option( 'wpsw_settings', array() );
        $result   = $sender->send_webhook( $post_id, $settings );

        if ( $result ) {
            /* translators: %d: post ID */
            wp_send_json_success( sprintf( esc_html__( 'Post #%d synced successfully!', 'wp-post-sync-webhook' ), $post_id ) );
        } else {
            wp_send_json_error( esc_html__( 'Sync failed. Check debug log for details.', 'wp-post-sync-webhook' ) );
        }
    }

    /**
     * AJAX handler for health check.
     *
     * @since 1.0.0
     */
    public function ajax_health_check() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        $settings = get_option( 'wpsw_settings', array() );

        wp_send_json_success(
            array(
                'sync_count' => $settings['sync_count'] ?? 0,
                'last_sync'  => $settings['last_sync'] ?? '',
            )
        );
    }
}