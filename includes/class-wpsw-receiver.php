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

class WPSW_Receiver {

    private function get_settings() {
        return WPSW_Settings::get_settings();
    }

    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( '[WPSW Receiver] ' . print_r( $message, true ) );
            } else {
                error_log( '[WPSW Receiver] ' . $message );
            }
        }
    }

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

    public function handle_receive( WP_REST_Request $request ) {
        $settings = $this->get_settings();

        $this->log( '=== Receiving webhook ===' );

        if ( ! in_array( $settings['mode'], array( 'receiver', 'both' ), true ) ) {
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Receiver mode not enabled', 'softart-post-sync-webhook' ) ),
                403
            );
        }

        $body = $request->get_body();
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Invalid JSON', 'softart-post-sync-webhook' ) ),
                400
            );
        }

        if ( ! $this->verify_signature( $request, $body ) ) {
            $this->log( 'Invalid signature' );
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Invalid signature', 'softart-post-sync-webhook' ) ),
                403
            );
        }

        $source_site = isset( $data['source_site'] ) ? esc_url_raw( $data['source_site'] ) : '';
        $remote_id   = isset( $data['post_id'] ) ? intval( $data['post_id'] ) : 0;

        if ( ! $source_site || ! $remote_id ) {
            return new WP_REST_Response(
                array( 'error' => esc_html__( 'Missing source_site or post_id', 'softart-post-sync-webhook' ) ),
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

        $this->log_entry( $data, $result );

        return new WP_REST_Response( $result, 200 );
    }

    private function verify_signature( $request, $body ) {
        $settings      = $this->get_settings();
        $header_secret = $request->get_header( 'x-webhook-secret' );
        $header_sig    = $request->get_header( 'x-webhook-signature' );

        foreach ( $settings['receivers'] as $receiver ) {
            if ( empty( $receiver['secret_key'] ) ) {
                continue;
            }

            if ( $header_secret === $receiver['secret_key'] ) {
                $calc_sig = hash_hmac( 'sha256', $body, $receiver['secret_key'] );
                return hash_equals( $calc_sig, (string) $header_sig );
            }
        }

        if ( ! empty( $settings['secret_key'] ) ) {
            $calc_sig = hash_hmac( 'sha256', $body, $settings['secret_key'] );
            return (
                $header_secret === $settings['secret_key'] &&
                hash_equals( $calc_sig, (string) $header_sig )
            );
        }

        return false;
    }

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
        $this->save_builder_data( $local_id, $data, $image_handler, $content );

        $featured_result = $this->save_featured_image( $local_id, $data, $image_handler );

        $this->clear_builder_cache( $local_id, $data );

        return array(
            'success'         => true,
            'local_id'        => $local_id,
            'images_imported' => $images_imported,
            'images_reused'   => $images_reused,
            'featured_image'  => $featured_result,
            'builder_type'    => $data['builder']['type'] ?? 'none',
            'version'         => WPSW_VERSION,
        );
    }

    private function find_existing_post( $data, $source_site, $remote_id ) {
        return get_posts(
            array(
                'post_type'      => $data['post_type'] ?? 'post',
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

    private function save_post( $data, $content, $existing, $source_site, $remote_id ) {
        $rules = $this->get_settings()['sync_rules'] ?? WPSW_Settings::get_default_rules();

        $postarr = array(
            'post_title'   => $data['title'] ?? '',
            'post_content' => $content,
            'post_excerpt' => $data['excerpt'] ?? '',
            'post_status'  => $data['status'] ?? $rules['default_status'] ?? 'publish',
            'post_name'    => $data['slug'] ?? '',
            'post_type'    => $data['post_type'] ?? 'post',
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

    private function save_taxonomies( $post_id, $data ) {
        $rules = $this->get_settings()['sync_rules'] ?? WPSW_Settings::get_default_rules();

        if ( ! empty( $rules['sync_categories'] ) && ! empty( $data['categories'] ) ) {
            $term_ids = array();

            foreach ( $data['categories'] as $slug ) {
                $term = get_term_by( 'slug', $slug, 'category' );

                if ( ! $term ) {
                    $result = wp_insert_term(
                        ucfirst( str_replace( '-', ' ', $slug ) ),
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

        if ( ! empty( $rules['sync_tags'] ) && ! empty( $data['tags'] ) ) {
            $tag_ids = array();

            foreach ( $data['tags'] as $slug ) {
                $term = get_term_by( 'slug', $slug, 'post_tag' );

                if ( ! $term ) {
                    $result = wp_insert_term(
                        ucfirst( str_replace( '-', ' ', $slug ) ),
                        'post_tag',
                        array( 'slug' => $slug )
                    );

                    if ( ! is_wp_error( $result ) ) {
                        $tag_ids[] = $result['term_id'];
                    }
                } else {
                    $tag_ids[] = $term->term_id;
                }
            }

            if ( $tag_ids ) {
                wp_set_post_terms( $post_id, $tag_ids, 'post_tag', false );
            }
        }
    }

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

    private function save_builder_data( $post_id, $data, $image_handler, $content ) {
        $builder = $data['builder'] ?? array();
        $type    = $builder['type'] ?? 'none';

        if ( 'none' === $type || empty( $builder['meta'] ) ) {
            return;
        }

        $this->log( 'Saving builder data: ' . $type );

        switch ( $type ) {
            case 'elementor':
                $this->save_elementor_data( $post_id, $builder['meta'], $image_handler );
                break;

            case 'divi':
                $this->save_divi_data( $post_id, $builder['meta'], $image_handler );
                break;
        }
    }

    private function save_elementor_data( $post_id, $meta, $image_handler ) {
        if ( ! function_exists( 'register_meta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/meta.php';
        }

        $elementor_keys = array(
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

        foreach ( $elementor_keys as $key ) {
            register_meta( 'post', $key, array(
                'show_in_rest' => true,
                'single'       => true,
                'auth_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
            ) );
        }

        foreach ( $meta as $key => $value ) {
            if ( in_array( $key, $elementor_keys, true ) && '' !== $value ) {
                update_post_meta( $post_id, $key, $value );
            }
        }

        $this->replace_elementor_image_urls( $post_id, $image_handler );
    }

    private function replace_elementor_image_urls( $post_id, $image_handler ) {
        $raw = get_post_meta( $post_id, '_elementor_data', true );

        if ( empty( $raw ) ) {
            return;
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return;
        }

        $modified = $this->walk_elementor_elements( $decoded, $image_handler );

        if ( $modified ) {
            update_post_meta( $post_id, '_elementor_data', wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        }
    }

    private function walk_elementor_elements( &$elements, $image_handler ) {
        $modified = false;

        if ( isset( $elements['content'] ) && is_array( $elements['content'] ) ) {
            foreach ( $elements['content'] as &$element ) {
                if ( $this->walk_single_element( $element, $image_handler ) ) {
                    $modified = true;
                }
            }
            unset( $element );
        } elseif ( is_array( $elements ) && isset( $elements['id'] ) ) {
            $modified = $this->walk_single_element( $elements, $image_handler );
        }

        return $modified;
    }

    private function walk_single_element( &$element, $image_handler ) {
        $modified = false;

        if ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
            $image_keys = array(
                'image', 'background_image', 'background_image_tablet',
                'background_image_mobile', 'selected_icon', 'media',
            );

            foreach ( $image_keys as $key ) {
                if ( empty( $element['settings'][ $key ] ) ) {
                    continue;
                }

                $val = $element['settings'][ $key ];

                if ( is_array( $val ) && ! empty( $val['url'] ) ) {
                    $result = $image_handler->smart_import( array(
                        'url'       => $val['url'],
                        'filename'  => $val['id'] ?? '',
                        'file_hash' => '',
                        'file_size' => 0,
                    ) );

                    if ( $result && ! $result['reused'] ) {
                        $element['settings'][ $key ]['url'] = $result['url'];
                        if ( ! empty( $val['id'] ) ) {
                            $element['settings'][ $key ]['id'] = $result['id'];
                        }
                        $modified = true;
                    } elseif ( $result ) {
                        $element['settings'][ $key ]['url'] = $result['url'];
                    }
                } elseif ( is_string( $val ) && filter_var( $val, FILTER_VALIDATE_URL ) ) {
                    $result = $image_handler->smart_import( $val );
                    if ( $result ) {
                        $element['settings'][ $key ] = $result['url'];
                        $modified = true;
                    }
                }
            }
        }

        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as &$child ) {
                if ( $this->walk_single_element( $child, $image_handler ) ) {
                    $modified = true;
                }
            }
            unset( $child );
        }

        return $modified;
    }

    private function save_divi_data( $post_id, $meta, $image_handler ) {
        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

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

    private function clear_builder_cache( $post_id, $data ) {
        $type = $data['builder']['type'] ?? 'none';

        if ( 'elementor' === $type && class_exists( '\\Elementor\\Plugin' ) ) {
            try {
                \Elementor\Plugin::instance()->files_manager->clear_cache();
                $this->log( 'Elementor CSS cache cleared' );
            } catch ( \Exception $e ) {
                $this->log( 'Failed to clear Elementor cache: ' . $e->getMessage() );
            }
        }

        if ( 'divi' === $type && function_exists( 'et_pb_flush_et_cache' ) ) {
            et_pb_flush_et_cache();
            $this->log( 'Divi CSS cache cleared' );
        }
    }

    private function log_entry( $data, $result ) {
        if ( ! is_wp_error( $result ) && ! empty( $result['success'] ) ) {
            WPSW_Settings::update_receiver_stats( '', true );
        }
    }
}
