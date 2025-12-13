<?php
/**
 * The receiver functionality of the plugin.
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
 * The receiver functionality of the plugin.
 */
class WPSW_Receiver {

    /**
     * Get plugin settings.
     *
     * @since  1.0.0
     * @access private
     * @return array
     */
    private function get_settings() {
        $defaults = array(
            'mode'          => 'sender',
            'receiver_url'  => '',
            'secret_key'    => '',
            'post_types'    => array( 'post' ),
            'sync_count'    => 0,
            'last_sync'     => '',
        );
        return wp_parse_args( get_option( 'wpsw_settings', array() ), $defaults );
    }

    /**
     * Log a message.
     *
     * @since 1.0.0
     * @param string $message Message to log.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( '[WPSW Receiver] ' . print_r( $message, true ) );
            } else {
                error_log( '[WPSW Receiver] ' . $message );
            }
        }
    }

    /**
     * Update sync statistics.
     *
     * @since 1.0.0
     */
    private function update_stats() {
        $settings               = $this->get_settings();
        $settings['sync_count'] = intval( $settings['sync_count'] ) + 1;
        $settings['last_sync']  = current_time( 'mysql' );
        update_option( 'wpsw_settings', $settings );
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     */
    public function register_routes() {
        register_rest_route(
            'post-sync/v1',
            '/receive',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_receive' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'post-sync/v1',
            '/health',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_health' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle health check request.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function handle_health( WP_REST_Request $request ) {
        $settings = $this->get_settings();

        return new WP_REST_Response(
            array(
                'status'  => 'ok',
                'version' => WPSW_VERSION,
                'mode'    => $settings['mode'],
                'time'    => current_time( 'c' ),
            ),
            200
        );
    }

    /**
     * Handle incoming webhook.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function handle_receive( WP_REST_Request $request ) {
        $settings = $this->get_settings();

        $this->log( '=== Receiving webhook ===' );

        if ( ! in_array( $settings['mode'], array( 'receiver', 'both' ), true ) ) {
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Receiver mode not enabled', 'wp-post-sync-webhook' ) ),
                403
            );
        }

        $body = $request->get_body();
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Invalid JSON', 'wp-post-sync-webhook' ) ),
                400
            );
        }

        if ( ! $this->verify_signature( $request, $body, $settings['secret_key'] ) ) {
            $this->log( 'Invalid signature' );
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Invalid signature', 'wp-post-sync-webhook' ) ),
                403
            );
        }

        $source_site = isset( $data['source_site'] ) ? esc_url_raw( $data['source_site'] ) : '';
        $remote_id   = isset( $data['post_id'] ) ? intval( $data['post_id'] ) : 0;

        if ( ! $source_site || ! $remote_id ) {
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Missing source_site or post_id', 'wp-post-sync-webhook' ) ),
                400
            );
        }

        $result = $this->process_webhook( $data, $source_site, $remote_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'error' => $result->get_error_message() ),
                500
            );
        }

        $this->update_stats();

        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Verify webhook signature.
     *
     * @since  1.0.0
     * @access private
     * @param  WP_REST_Request $request    REST request.
     * @param  string          $body       Request body.
     * @param  string          $secret_key Secret key.
     * @return bool
     */
    private function verify_signature( $request, $body, $secret_key ) {
        if ( empty( $secret_key ) ) {
            return false;
        }

        $header_secret = $request->get_header( 'x-webhook-secret' );
        $header_sig    = $request->get_header( 'x-webhook-signature' );
        $calc_sig      = hash_hmac( 'sha256', $body, $secret_key );

        return ( $header_secret === $secret_key && hash_equals( $calc_sig, (string) $header_sig ) );
    }

    /**
     * Process webhook data.
     *
     * @since  1.0.0
     * @access private
     * @param  array  $data        Webhook data.
     * @param  string $source_site Source site URL.
     * @param  int    $remote_id   Remote post ID.
     * @return array|WP_Error
     */
    private function process_webhook( $data, $source_site, $remote_id ) {
        $image_handler = new WPSW_Image_Handler();

        $existing = $this->find_existing_post( $data, $source_site, $remote_id );

        $content         = isset( $data['content'] ) ? $data['content'] : '';
        $images_imported = 0;
        $images_reused   = 0;

        if ( ! empty( $data['content_images'] ) && is_array( $data['content_images'] ) ) {
            foreach ( $data['content_images'] as $img ) {
                $result = $image_handler->smart_import( $img );

                if ( $result ) {
                    $old_url = is_array( $img ) ? $img['url'] : $img;
                    $content = str_replace( $old_url, $result['url'], $content );

                    if ( $result['reused'] ) {
                        ++$images_reused;
                    } else {
                        ++$images_imported;
                    }
                }
            }
        }

        $local_id = $this->save_post( $data, $content, $existing, $source_site, $remote_id );

        if ( is_wp_error( $local_id ) ) {
            return $local_id;
        }

        $this->log( 'Post saved: ' . $local_id );

        $this->save_taxonomies( $local_id, $data );
        $this->save_meta( $local_id, $data );

        $featured_result = $this->save_featured_image( $local_id, $data, $image_handler );

        return array(
            'success'         => true,
            'local_id'        => $local_id,
            'images_imported' => $images_imported,
            'images_reused'   => $images_reused,
            'featured_image'  => $featured_result,
            'version'         => WPSW_VERSION,
        );
    }

    /**
     * Find existing post by remote source.
     *
     * @since  1.0.0
     * @access private
     * @param  array  $data        Webhook data.
     * @param  string $source_site Source site URL.
     * @param  int    $remote_id   Remote post ID.
     * @return array
     */
    private function find_existing_post( $data, $source_site, $remote_id ) {
        return get_posts(
            array(
                'post_type'      => isset( $data['post_type'] ) ? $data['post_type'] : 'post',
                'meta_query'     => array(
                    array(
                        'key'   => '_remote_source_site',
                        'value' => $source_site,
                    ),
                    array(
                        'key'   => '_remote_post_id',
                        'value' => $remote_id,
                    ),
                ),
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'fields'         => 'ids',
            )
        );
    }

    /**
     * Save post data.
     *
     * @since  1.0.0
     * @access private
     * @param  array  $data        Webhook data.
     * @param  string $content     Processed content.
     * @param  array  $existing    Existing post IDs.
     * @param  string $source_site Source site URL.
     * @param  int    $remote_id   Remote post ID.
     * @return int|WP_Error
     */
    private function save_post( $data, $content, $existing, $source_site, $remote_id ) {
        $postarr = array(
            'post_title'   => isset( $data['title'] ) ? $data['title'] : '',
            'post_content' => $content,
            'post_excerpt' => isset( $data['excerpt'] ) ? $data['excerpt'] : '',
            'post_status'  => isset( $data['status'] ) ? $data['status'] : 'draft',
            'post_name'    => isset( $data['slug'] ) ? $data['slug'] : '',
            'post_type'    => isset( $data['post_type'] ) ? $data['post_type'] : 'post',
        );

        if ( ! empty( $existing ) ) {
            $postarr['ID'] = $existing[0];
            $local_id      = wp_update_post( $postarr, true );
        } else {
            $local_id = wp_insert_post( $postarr, true );

            if ( ! is_wp_error( $local_id ) ) {
                update_post_meta( $local_id, '_remote_source_site', $source_site );
                update_post_meta( $local_id, '_remote_post_id', $remote_id );
            }
        }

        return $local_id;
    }

    /**
     * Save taxonomies.
     *
     * @since  1.0.0
     * @access private
     * @param  int   $post_id Post ID.
     * @param  array $data    Webhook data.
     */
    private function save_taxonomies( $post_id, $data ) {
        if ( ! empty( $data['categories'] ) ) {
            $term_ids = array();

            foreach ( $data['categories'] as $slug ) {
                $term = get_term_by( 'slug', $slug, 'category' );

                if ( ! $term ) {
                    $result = wp_insert_term(
                        ucfirst( $slug ),
                        'category',
                        array( 'slug' => $slug )
                    );

                    if ( ! is_wp_error( $result ) ) {
                        $term_ids[] = $result['term_id'];
                    }
                } else {
                    $term_ids[] = $term->term_id;
                }
            }

            if ( $term_ids ) {
                wp_set_post_terms( $post_id, $term_ids, 'category', false );
            }
        }

        if ( ! empty( $data['tags'] ) ) {
            wp_set_post_terms( $post_id, $data['tags'], 'post_tag', false );
        }
    }

    /**
     * Save post meta.
     *
     * @since  1.0.0
     * @access private
     * @param  int   $post_id Post ID.
     * @param  array $data    Webhook data.
     */
    private function save_meta( $post_id, $data ) {
        if ( empty( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
            return;
        }

        $excluded = array( '_remote_source_site', '_remote_post_id', '_thumbnail_id' );

        foreach ( $data['meta'] as $key => $value ) {
            if ( in_array( $key, $excluded, true ) ) {
                continue;
            }

            delete_post_meta( $post_id, $key );

            if ( is_array( $value ) && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
                foreach ( $value as $v ) {
                    add_post_meta( $post_id, $key, $v );
                }
            } else {
                update_post_meta( $post_id, $key, $value );
            }
        }
    }

    /**
     * Save featured image.
     *
     * @since  1.0.0
     * @access private
     * @param  int                $post_id       Post ID.
     * @param  array              $data          Webhook data.
     * @param  WPSW_Image_Handler $image_handler Image handler instance.
     * @return string
     */
    private function save_featured_image( $post_id, $data, $image_handler ) {
        if ( empty( $data['featured_image'] ) || ! is_array( $data['featured_image'] ) || empty( $data['featured_image']['url'] ) ) {
            return 'none';
        }

        $result = $image_handler->smart_import( $data['featured_image'] );

        if ( ! $result ) {
            return 'failed';
        }

        set_post_thumbnail( $post_id, $result['id'] );

        if ( ! empty( $data['featured_image']['alt'] ) ) {
            update_post_meta( $result['id'], '_wp_attachment_image_alt', $data['featured_image']['alt'] );
        }

        $this->log( 'Featured image: ' . ( $result['reused'] ? 'reused' : 'imported' ) . ' (ID: ' . $result['id'] . ')' );

        return $result['reused'] ? 'reused' : 'imported';
    }
}