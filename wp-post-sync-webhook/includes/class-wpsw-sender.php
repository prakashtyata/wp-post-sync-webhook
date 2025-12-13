<?php
/**
 * The sender functionality of the plugin.
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
 * The sender functionality of the plugin.
 */
class WPSW_Sender {

    /**
     * Posts queued for sending.
     *
     * @since  1.0.0
     * @access private
     * @var    array
     */
    private static $queued_posts = array();

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
                error_log( '[WPSW Sender] ' . print_r( $message, true ) );
            } else {
                error_log( '[WPSW Sender] ' . $message );
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
     * Schedule webhook on save_post.
     *
     * @since 1.0.0
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     */
    public function schedule_webhook( $post_id, $post, $update ) {
        $this->maybe_queue_post( $post_id, $post );
    }

    /**
     * Schedule webhook on REST API save.
     *
     * @since 1.0.0
     * @param WP_Post         $post    Post object.
     * @param WP_REST_Request $request REST request.
     */
    public function rest_schedule_webhook( $post, $request ) {
        $this->maybe_queue_post( $post->ID, $post );
    }

    /**
     * Maybe send webhook when thumbnail is updated.
     *
     * @since 1.0.0
     * @param int    $meta_id    Meta ID.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public function maybe_send_on_thumbnail( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( '_thumbnail_id' !== $meta_key ) {
            return;
        }

        $post = get_post( $post_id );
        if ( $post && 'publish' === $post->post_status ) {
            $this->maybe_queue_post( $post_id, $post );
        }
    }

    /**
     * Queue a post for webhook sending.
     *
     * @since  1.0.0
     * @access private
     * @param  int     $post_id Post ID.
     * @param  WP_Post $post    Post object.
     */
    private function maybe_queue_post( $post_id, $post ) {
        $settings = $this->get_settings();

        if ( ! in_array( $settings['mode'], array( 'sender', 'both' ), true ) ) {
            return;
        }

        if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( 'publish' !== $post->post_status ) {
            return;
        }

        if ( get_post_meta( $post_id, '_remote_source_site', true ) ) {
            return;
        }

        self::$queued_posts[ $post_id ] = true;
        $this->log( 'Queued post ' . $post_id . ' for sync' );
    }

    /**
     * Send all pending webhooks on shutdown.
     *
     * @since 1.0.0
     */
    public function send_pending_webhooks() {
        if ( empty( self::$queued_posts ) ) {
            return;
        }

        $settings = $this->get_settings();

        foreach ( array_keys( self::$queued_posts ) as $post_id ) {
            $this->send_webhook( $post_id, $settings );
        }

        self::$queued_posts = array();
    }

    /**
     * Send webhook for a post.
     *
     * @since  1.0.0
     * @param  int   $post_id  Post ID.
     * @param  array $settings Plugin settings.
     * @return bool
     */
    public function send_webhook( $post_id, $settings = null ) {
        if ( null === $settings ) {
            $settings = $this->get_settings();
        }

        $post = get_post( $post_id );

        if ( ! $post || 'publish' !== $post->post_status ) {
            return false;
        }

        $webhook_url = $settings['receiver_url'];
        $secret      = $settings['secret_key'];

        if ( empty( $webhook_url ) || empty( $secret ) ) {
            $this->log( 'Missing webhook URL or secret key' );
            return false;
        }

        $this->log( '=== Sending post ' . $post_id . ' ===' );

        $payload   = $this->build_payload( $post_id, $post );
        $body_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $signature = hash_hmac( 'sha256', $body_json, $secret );

        $this->log( 'Payload size: ' . strlen( $body_json ) . ' bytes' );

        $response = wp_remote_post(
            $webhook_url,
            array(
                'headers'   => array(
                    'Content-Type'        => 'application/json; charset=utf-8',
                    'X-Webhook-Secret'    => $secret,
                    'X-Webhook-Signature' => $signature,
                    'X-Plugin-Version'    => WPSW_VERSION,
                ),
                'body'      => $body_json,
                'timeout'   => 120,
                'sslverify' => false,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Error: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $this->log( 'Response: HTTP ' . $response_code );

        if ( 200 === $response_code ) {
            $this->update_stats();
            return true;
        }

        return false;
    }

    /**
     * Build webhook payload.
     *
     * @since  1.0.0
     * @access private
     * @param  int     $post_id Post ID.
     * @param  WP_Post $post    Post object.
     * @return array
     */
    private function build_payload( $post_id, $post ) {
        $image_handler = new WPSW_Image_Handler();

        $featured_image = $image_handler->get_featured_image_data( $post_id );
        $content_images = $image_handler->extract_content_images( $post->post_content );

        $categories = array();
        $terms      = wp_get_post_terms( $post_id, 'category' );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $categories[] = $term->slug;
            }
        }

        $tags      = array();
        $tag_terms = wp_get_post_terms( $post_id, 'post_tag' );
        if ( ! is_wp_error( $tag_terms ) ) {
            foreach ( $tag_terms as $term ) {
                $tags[] = $term->slug;
            }
        }

        $meta_data = $this->get_post_meta_data( $post_id );

        return array(
            'version'        => WPSW_VERSION,
            'source_site'    => get_site_url(),
            'post_id'        => $post_id,
            'post_type'      => $post->post_type,
            'slug'           => $post->post_name,
            'title'          => $post->post_title,
            'content'        => $post->post_content,
            'excerpt'        => $post->post_excerpt,
            'status'         => $post->post_status,
            'author_name'    => get_the_author_meta( 'display_name', $post->post_author ),
            'modified'       => $post->post_modified_gmt,
            'featured_image' => $featured_image,
            'content_images' => $content_images,
            'categories'     => $categories,
            'tags'           => $tags,
            'meta'           => $meta_data,
        );
    }

    /**
     * Get post meta data for syncing.
     *
     * @since  1.0.0
     * @access private
     * @param  int $post_id Post ID.
     * @return array
     */
    private function get_post_meta_data( $post_id ) {
        $meta_data = array();
        $meta_raw  = get_post_meta( $post_id );

        $excluded = array(
            '_edit_lock',
            '_edit_last',
            '_remote_source_site',
            '_remote_post_id',
            '_thumbnail_id',
            '_wp_old_slug',
        );

        if ( is_array( $meta_raw ) ) {
            foreach ( $meta_raw as $key => $values ) {
                if ( in_array( $key, $excluded, true ) || 0 === strpos( $key, '_edit_' ) ) {
                    continue;
                }

                if ( 1 === count( $values ) ) {
                    $meta_data[ $key ] = maybe_unserialize( $values[0] );
                } else {
                    $meta_data[ $key ] = array_map( 'maybe_unserialize', $values );
                }
            }
        }

        return $meta_data;
    }
}