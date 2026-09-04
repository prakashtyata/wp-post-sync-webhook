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

class WPSW_Admin {

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
                    'enterPostId'   => esc_html__( 'Please enter a valid Post ID', 'wp-post-sync-webhook' ),
                    'noReceiverUrl' => esc_html__( 'Please enter a Receiver Webhook URL first', 'wp-post-sync-webhook' ),
                    'copied'        => esc_html__( 'Copied to clipboard!', 'wp-post-sync-webhook' ),
                    'keyCopied'     => esc_html__( 'Secret Key Copied!', 'wp-post-sync-webhook' ),
                    'urlCopied'     => esc_html__( 'Endpoint URL Copied!', 'wp-post-sync-webhook' ),
                    'generateKeyTip'=> esc_html__( 'Please generate or enter a secret key first', 'wp-post-sync-webhook' ),
                    'invalidUrlTip' => esc_html__( 'Tip: Endpoint usually ends with /wp-json/post-sync/v1/receive', 'wp-post-sync-webhook' ),
                    'confirmDelete' => esc_html__( 'Remove this receiver site?', 'wp-post-sync-webhook' ),
                    'receiverAdded' => esc_html__( 'Receiver site added!', 'wp-post-sync-webhook' ),
                    'logCleared'    => esc_html__( 'Log cleared.', 'wp-post-sync-webhook' ),
                    'webhookUrlTipTitle' => esc_html__( 'Where to get this:', 'wp-post-sync-webhook' ),
                    'webhookUrlTip' => esc_html__( 'This is the receive endpoint on the destination (receiver) site. On that site, open Post Sync Webhook &rarr; Connections and copy the "Your Webhook Endpoint" URL, then paste it here.', 'wp-post-sync-webhook' ),
                    'secretKeyTipTitle' => esc_html__( 'Where to get / put this:', 'wp-post-sync-webhook' ),
                    'secretKeyTip' => esc_html__( 'A shared password between both sites. Use the "Secret Key" shown on the receiver site (Connections tab) and paste the exact same key here, OR generate one here and paste it on the receiver site. Both sides must match.', 'wp-post-sync-webhook' ),
                    'noPostsSelected' => esc_html__( 'Please select at least one post.', 'wp-post-sync-webhook' ),
                    'noResults' => esc_html__( 'No posts found.', 'wp-post-sync-webhook' ),
                    'searchFailed' => esc_html__( 'Search failed.', 'wp-post-sync-webhook' ),
                    'syncComplete' => esc_html__( 'Sync Complete!', 'wp-post-sync-webhook' ),
                    'add' => esc_html__( 'Add', 'wp-post-sync-webhook' ),
                    'synced' => esc_html__( 'synced', 'wp-post-sync-webhook' ),
                    'failed' => esc_html__( 'failed', 'wp-post-sync-webhook' ),
                    'invalidIds' => esc_html__( 'Please enter valid post/page IDs separated by commas.', 'wp-post-sync-webhook' ),
                    'resolving' => esc_html__( 'Resolving posts...', 'wp-post-sync-webhook' ),
                    'idsNotFound' => esc_html__( 'IDs not found:', 'wp-post-sync-webhook' ),
                ),
            )
        );
    }

    public function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=wp-post-sync-webhook' ),
            esc_html__( 'Settings', 'wp-post-sync-webhook' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    public function ajax_test_connection() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $receiver_id = isset( $_POST['receiver_id'] ) ? sanitize_text_field( $_POST['receiver_id'] ) : '';
        $receiver    = WPSW_Settings::get_receiver( $receiver_id );

        if ( ! $receiver || empty( $receiver['url'] ) ) {
            wp_send_json_error( esc_html__( 'Receiver not found or URL not configured', 'wp-post-sync-webhook' ) );
        }

        $health_url = str_replace( '/receive', '/health', $receiver['url'] );

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
            wp_send_json_success( sprintf(
                esc_html__( 'Connected to %s! Receiver version: %s', 'wp-post-sync-webhook' ),
                esc_html( $receiver['name'] ),
                $body['version'] ?? 'unknown'
            ) );
        } else {
            wp_send_json_error( sprintf(
                esc_html__( '%s: Unexpected response (HTTP %d)', 'wp-post-sync-webhook' ),
                esc_html( $receiver['name'] ),
                $code
            ) );
        }
    }

    public function ajax_sync_post() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $receiver_id = isset( $_POST['receiver_id'] ) ? sanitize_text_field( $_POST['receiver_id'] ) : '';

        if ( ! $post_id ) {
            wp_send_json_error( esc_html__( 'Invalid post ID', 'wp-post-sync-webhook' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( esc_html__( 'Post not found', 'wp-post-sync-webhook' ) );
        }

        $receiver = WPSW_Settings::get_receiver( $receiver_id );
        if ( ! $receiver ) {
            wp_send_json_error( esc_html__( 'Receiver not found', 'wp-post-sync-webhook' ) );
        }

        $sender = new WPSW_Sender();
        $result = $sender->send_webhook( $post_id, $receiver );

        if ( $result ) {
            wp_send_json_success( sprintf(
                esc_html__( 'Post #%d synced to %s!', 'wp-post-sync-webhook' ),
                $post_id,
                esc_html( $receiver['name'] )
            ) );
        } else {
            wp_send_json_error( sprintf(
                esc_html__( 'Sync to %s failed. Check debug log.', 'wp-post-sync-webhook' ),
                esc_html( $receiver['name'] )
            ) );
        }
    }

    public function ajax_sync_post_all() {
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
        $payload  = $sender->build_payload( $post_id, $post );
        $settings = WPSW_Settings::get_settings();
        $results  = array();

        foreach ( $settings['receivers'] as $receiver ) {
            if ( empty( $receiver['enabled'] ) || empty( $receiver['url'] ) ) {
                continue;
            }

            $sent = $sender->send_to_receiver( $payload, $receiver );
            WPSW_Settings::update_receiver_stats( $receiver['id'], $sent );

            $results[] = array(
                'name'    => $receiver['name'],
                'success' => $sent,
            );
        }

        wp_send_json_success( $results );
    }

    public function ajax_search_posts() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
        $search    = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $exclude   = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : array();

        $post_types = get_post_types( array( 'public' => true ), 'names' );
        if ( ! in_array( $post_type, $post_types, true ) ) {
            $post_type = 'post';
        }

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish' ),
            'posts_per_page' => 50,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            's'              => $search,
            'post__not_in'   => $exclude,
        );

        if ( '' === $search ) {
            unset( $args['s'] );
        }

        $query = new WP_Query( $args );

        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title ? $post->post_title : sprintf( '(#%d)', $post->ID ),
            );
        }

        wp_send_json_success( $posts );
    }

    public function ajax_get_post_titles() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();
        $ids = array_values( array_filter( $ids ) );

        if ( empty( $ids ) ) {
            wp_send_json_error( esc_html__( 'No IDs provided', 'wp-post-sync-webhook' ) );
        }

        $titles = array();
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( $post ) {
                $titles[ $id ] = $post->post_title ? $post->post_title : sprintf( '(#%d)', $id );
            }
        }

        wp_send_json_success( $titles );
    }

    public function ajax_sync_posts() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $post_ids    = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();
        $receiver_id = isset( $_POST['receiver_id'] ) ? sanitize_text_field( $_POST['receiver_id'] ) : '';

        if ( empty( $post_ids ) ) {
            wp_send_json_error( esc_html__( 'No posts selected', 'wp-post-sync-webhook' ) );
        }

        $sender   = new WPSW_Sender();
        $settings = WPSW_Settings::get_settings();

        if ( 'all' === $receiver_id ) {
            $results = array();

            foreach ( $post_ids as $post_id ) {
                $post = get_post( $post_id );
                if ( ! $post ) {
                    $results[] = array( 'id' => $post_id, 'title' => sprintf( '(#%d)', $post_id ), 'success' => false, 'error' => 'not found' );
                    continue;
                }

                $payload = $sender->build_payload( $post_id, $post );
                $per_receiver = array();

                foreach ( $settings['receivers'] as $receiver ) {
                    if ( empty( $receiver['enabled'] ) || empty( $receiver['url'] ) ) {
                        continue;
                    }

                    if ( ! in_array( $post->post_type, $receiver['post_types'] ?? array( 'post' ), true ) ) {
                        continue;
                    }

                    $sent = $sender->send_to_receiver( $payload, $receiver );
                    WPSW_Settings::update_receiver_stats( $receiver['id'], $sent );
                    $per_receiver[] = array( 'name' => $receiver['name'], 'success' => $sent );
                }

                $results[] = array(
                    'id'       => $post_id,
                    'title'    => $post->post_title ? $post->post_title : sprintf( '(#%d)', $post_id ),
                    'success'  => ! empty( $per_receiver ) && count( array_filter( $per_receiver, function ( $r ) { return $r['success']; } ) ) > 0,
                    'receivers' => $per_receiver,
                );
            }

            wp_send_json_success( $results );
        }

        $receiver = WPSW_Settings::get_receiver( $receiver_id );
        if ( ! $receiver ) {
            wp_send_json_error( esc_html__( 'Receiver not found', 'wp-post-sync-webhook' ) );
        }

        $results = array();
        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                $results[] = array( 'id' => $post_id, 'title' => sprintf( '(#%d)', $post_id ), 'success' => false, 'error' => 'not found' );
                continue;
            }

            $sent = $sender->send_webhook( $post_id, $receiver );
            $results[] = array(
                'id'      => $post_id,
                'title'   => $post->post_title ? $post->post_title : sprintf( '(#%d)', $post_id ),
                'success' => $sent,
            );
        }

        wp_send_json_success( $results );
    }

    public function ajax_health_check() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $settings = $this->get_settings_for_display();

        wp_send_json_success( array(
            'sync_count' => $settings['sync_count'] ?? 0,
            'last_sync'  => $settings['last_sync'] ?? '',
            'receivers'  => $settings['receivers'] ?? array(),
        ) );
    }

    public function ajax_clear_log() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        delete_option( 'wpsw_log' );
        wp_send_json_success( esc_html__( 'Log cleared.', 'wp-post-sync-webhook' ) );
    }

    public function ajax_get_log() {
        check_ajax_referer( 'wpsw_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'wp-post-sync-webhook' ) );
        }

        $log = get_option( 'wpsw_log', array() );
        wp_send_json_success( array_reverse( array_slice( $log, -100 ) ) );
    }

    private function get_settings_for_display() {
        return WPSW_Settings::get_settings();
    }
}
