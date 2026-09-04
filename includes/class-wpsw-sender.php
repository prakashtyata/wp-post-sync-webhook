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

class WPSW_Sender {

    private static $queued_posts = array();

    private function get_settings() {
        return WPSW_Settings::get_settings();
    }

    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( '[WPSW Sender] ' . print_r( $message, true ) );
            } else {
                error_log( '[WPSW Sender] ' . $message );
            }
        }
    }

    public function schedule_webhook( $post_id, $post, $update ) {
        $this->maybe_queue_post( $post_id, $post );
    }

    public function rest_schedule_webhook( $post, $request ) {
        $this->maybe_queue_post( $post->ID, $post );
    }

    public function maybe_send_on_thumbnail( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( '_thumbnail_id' !== $meta_key ) {
            return;
        }

        $post = get_post( $post_id );
        if ( $post && 'publish' === $post->post_status ) {
            $this->maybe_queue_post( $post_id, $post );
        }
    }

    private function maybe_queue_post( $post_id, $post ) {
        $settings = $this->get_settings();

        if ( ! in_array( $settings['mode'], array( 'sender', 'both' ), true ) ) {
            return;
        }

        $enabled_post_types = $this->get_enabled_post_types();

        if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
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

    private function get_enabled_post_types() {
        $settings = $this->get_settings();
        $types    = array();

        foreach ( $settings['receivers'] as $receiver ) {
            if ( ! empty( $receiver['enabled'] ) && ! empty( $receiver['url'] ) ) {
                $types = array_merge( $types, $receiver['post_types'] ?? array( 'post' ) );
            }
        }

        return array_unique( $types );
    }

    public function send_pending_webhooks() {
        if ( empty( self::$queued_posts ) ) {
            return;
        }

        $settings = $this->get_settings();

        foreach ( array_keys( self::$queued_posts ) as $post_id ) {
            $this->send_to_all_receivers( $post_id, $settings );
        }

        self::$queued_posts = array();
    }

    private function send_to_all_receivers( $post_id, $settings ) {
        $payload = null;

        foreach ( $settings['receivers'] as $receiver ) {
            if ( empty( $receiver['enabled'] ) || empty( $receiver['url'] ) || empty( $receiver['secret_key'] ) ) {
                continue;
            }

            if ( ! in_array( $settings['mode'], array( 'sender', 'both' ), true ) ) {
                continue;
            }

            $post = get_post( $post_id );
            if ( ! $post || 'publish' !== $post->post_status ) {
                continue;
            }

            if ( ! in_array( $post->post_type, $receiver['post_types'] ?? array( 'post' ), true ) ) {
                continue;
            }

            if ( null === $payload ) {
                $payload = $this->build_payload( $post_id, $post );
            }

            $result = $this->send_to_receiver( $payload, $receiver );
            WPSW_Settings::update_receiver_stats( $receiver['id'], $result );
        }
    }

    public function send_webhook( $post_id, $receiver = null, $settings = null ) {
        if ( null === $settings ) {
            $settings = $this->get_settings();
        }

        $post = get_post( $post_id );

        if ( ! $post || 'publish' !== $post->post_status ) {
            return false;
        }

        if ( null === $receiver ) {
            $enabled = array_filter( $settings['receivers'], function ( $r ) {
                return ! empty( $r['enabled'] ) && ! empty( $r['url'] ) && ! empty( $r['secret_key'] );
            } );

            $receiver = reset( $enabled );
            if ( ! $receiver ) {
                $this->log( 'No enabled receivers found' );
                return false;
            }
        }

        $payload  = $this->build_payload( $post_id, $post );
        $result   = $this->send_to_receiver( $payload, $receiver );
        WPSW_Settings::update_receiver_stats( $receiver['id'], $result );

        return $result;
    }

    public function send_to_receiver( $payload, $receiver ) {
        $body_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $signature = hash_hmac( 'sha256', $body_json, $receiver['secret_key'] );

        $this->log( 'Sending to ' . ( $receiver['name'] ?? $receiver['url'] ) . ' (' . strlen( $body_json ) . ' bytes)' );

        $response = wp_remote_post(
            $receiver['url'],
            array(
                'headers'   => array(
                    'Content-Type'        => 'application/json; charset=utf-8',
                    'X-Webhook-Secret'    => $receiver['secret_key'],
                    'X-Webhook-Signature' => $signature,
                    'X-Plugin-Version'    => WPSW_VERSION,
                    'X-Receiver-Id'       => $receiver['id'],
                ),
                'body'      => $body_json,
                'timeout'   => 120,
                'sslverify' => false,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Error sending to ' . ( $receiver['name'] ?? '' ) . ': ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $this->log( 'Response from ' . ( $receiver['name'] ?? '' ) . ': HTTP ' . $response_code );

        return 200 === $response_code;
    }

    public function build_payload( $post_id, $post ) {
        $image_handler = new WPSW_Image_Handler();
        $rules         = $this->get_settings()['sync_rules'] ?? WPSW_Settings::get_default_rules();

        $featured_image = ! empty( $rules['sync_featured_img'] )
            ? $image_handler->get_featured_image_data( $post_id )
            : array();

        $content_images = ! empty( $rules['sync_content_imgs'] )
            ? $image_handler->extract_content_images( $post->post_content )
            : array();

        $categories = array();
        if ( ! empty( $rules['sync_categories'] ) ) {
            $terms = wp_get_post_terms( $post_id, 'category' );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $categories[] = $term->slug;
                }
            }
        }

        $tags = array();
        if ( ! empty( $rules['sync_tags'] ) ) {
            $tag_terms = wp_get_post_terms( $post_id, 'post_tag' );
            if ( ! is_wp_error( $tag_terms ) ) {
                foreach ( $tag_terms as $term ) {
                    $tags[] = $term->slug;
                }
            }
        }

        $meta_data = ! empty( $rules['sync_meta'] )
            ? $this->get_post_meta_data( $post_id )
            : array();

        $builder_data = ! empty( $rules['sync_builder_data'] )
            ? $this->get_builder_data( $post_id, $post )
            : array();

        $payload = array(
            'version'        => WPSW_VERSION,
            'source_site'    => get_site_url(),
            'post_id'        => $post_id,
            'post_type'      => $post->post_type,
            'title'          => ! empty( $rules['sync_title'] ) ? $post->post_title : '',
            'content'        => ! empty( $rules['sync_content'] ) ? $post->post_content : '',
            'excerpt'        => ! empty( $rules['sync_excerpt'] ) ? $post->post_excerpt : '',
            'slug'           => ! empty( $rules['sync_slug'] ) ? $post->post_name : '',
            'status'         => ! empty( $rules['sync_status'] ) ? $post->post_status : ( $rules['default_status'] ?? 'publish' ),
            'author_name'    => get_the_author_meta( 'display_name', $post->post_author ),
            'modified'       => $post->post_modified_gmt,
            'featured_image' => $featured_image,
            'content_images' => $content_images,
            'categories'     => $categories,
            'tags'           => $tags,
            'meta'           => $meta_data,
            'builder'        => $builder_data,
        );

        return $payload;
    }

    private function get_builder_data( $post_id, $post ) {
        $elementor_meta = $this->get_elementor_meta( $post_id );

        if ( ! empty( $elementor_meta ) ) {
            return array(
                'type'    => 'elementor',
                'version' => $elementor_meta['_elementor_version'] ?? '',
                'meta'    => $elementor_meta,
            );
        }

        $divi_meta = $this->get_divi_meta( $post_id );
        if ( ! empty( $divi_meta ) ) {
            return array(
                'type'    => 'divi',
                'version' => $divi_meta['_et_builder_version'] ?? '',
                'meta'    => $divi_meta,
            );
        }

        return array(
            'type' => 'gutenberg',
            'meta' => array(),
        );
    }

    private function get_elementor_meta( $post_id ) {
        $edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
        if ( 'builder' !== $edit_mode ) {
            return array();
        }

        $keys = array(
            '_elementor_data',
            '_elementor_version',
            '_elementor_edit_mode',
            '_elementor_template_type',
            '_elementor_page_settings',
            '_elementor_css',
            '_elementor_page_assets',
            '_elementor_conditions',
            '_elementor_controls_usage',
            '_wp_page_template',
        );

        $meta = array();
        foreach ( $keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( '' !== $value && false !== $value ) {
                $meta[ $key ] = $value;
            }
        }

        return $meta;
    }

    private function get_divi_meta( $post_id ) {
        $use_builder = get_post_meta( $post_id, '_et_pb_use_builder', true );
        if ( 'on' !== $use_builder ) {
            return array();
        }

        $raw = get_post_meta( $post_id );
        $meta = array();

        $prefixes = array( '_et_pb_', '_et_' );
        $excluded = array(
            '_edit_lock',
            '_edit_last',
            '_thumbnail_id',
            '_remote_source_site',
            '_remote_post_id',
        );

        if ( is_array( $raw ) ) {
            foreach ( $raw as $key => $values ) {
                if ( in_array( $key, $excluded, true ) ) {
                    continue;
                }

                $is_divi = false;
                foreach ( $prefixes as $prefix ) {
                    if ( 0 === strpos( $key, $prefix ) ) {
                        $is_divi = true;
                        break;
                    }
                }

                if ( $is_divi ) {
                    $meta[ $key ] = ( 1 === count( $values ) )
                        ? maybe_unserialize( $values[0] )
                        : array_map( 'maybe_unserialize', $values );
                }
            }
        }

        return $meta;
    }

    private function get_post_meta_data( $post_id ) {
        $meta_data = array();
        $meta_raw  = get_post_meta( $post_id );

        $rules = $this->get_settings()['sync_rules'] ?? array();
        $extra_exclude = array();
        if ( ! empty( $rules['meta_exclude'] ) ) {
            $extra_exclude = array_map( 'trim', explode( ',', $rules['meta_exclude'] ) );
        }

        $excluded = array_merge(
            array(
                '_edit_lock',
                '_edit_last',
                '_remote_source_site',
                '_remote_post_id',
                '_thumbnail_id',
                '_wp_old_slug',
            ),
            $extra_exclude
        );

        $builder_prefixes = array( '_elementor_', '_et_pb_', '_et_', '_wp_' );
        $builder_keep     = array(
            '_wp_page_template',
            '_wp_attachment_metadata',
        );

        if ( is_array( $meta_raw ) ) {
            foreach ( $meta_raw as $key => $values ) {
                if ( in_array( $key, $excluded, true ) || 0 === strpos( $key, '_edit_' ) ) {
                    continue;
                }

                $is_builder = false;
                foreach ( $builder_prefixes as $prefix ) {
                    if ( 0 === strpos( $key, $prefix ) && ! in_array( $key, $builder_keep, true ) ) {
                        $is_builder = true;
                        break;
                    }
                }

                if ( $is_builder ) {
                    continue;
                }

                $meta_data[ $key ] = ( 1 === count( $values ) )
                    ? maybe_unserialize( $values[0] )
                    : array_map( 'maybe_unserialize', $values );
            }
        }

        return $meta_data;
    }
}
