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

class WPSW_Settings {

    public function add_settings_page() {
        add_options_page(
            esc_html__( 'Post Sync Webhook', 'softart-post-sync-webhook' ),
            esc_html__( 'Post Sync Webhook', 'softart-post-sync-webhook' ),
            'manage_options',
            'softart-post-sync-webhook',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            'wpsw_settings_group',
            'wpsw_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    public function sanitize_settings( $input ) {
        $output = $this->get_defaults();

        if ( isset( $input['mode'] ) && in_array( $input['mode'], array( 'sender', 'receiver', 'both' ), true ) ) {
            $output['mode'] = $input['mode'];
        }

        if ( isset( $input['secret_key'] ) ) {
            $output['secret_key'] = sanitize_text_field( $input['secret_key'] );
        }

        if ( isset( $input['receivers'] ) && is_array( $input['receivers'] ) ) {
            $output['receivers'] = array();

            foreach ( $input['receivers'] as $raw ) {
                $receiver = array(
                    'id'         => sanitize_text_field( $raw['id'] ?? '' ),
                    'name'       => sanitize_text_field( $raw['name'] ?? '' ),
                    'url'        => esc_url_raw( trim( $raw['url'] ?? '' ) ),
                    'secret_key' => sanitize_text_field( $raw['secret_key'] ?? '' ),
                    'enabled'    => ! empty( $raw['enabled'] ),
                    'post_types' => array( 'post' ),
                );

                if ( empty( $receiver['id'] ) ) {
                    $receiver['id'] = 'rcv_' . wp_generate_password( 8, false );
                }

                if ( empty( $receiver['secret_key'] ) ) {
                    $receiver['secret_key'] = wp_generate_password( 32, false );
                }

                if ( isset( $raw['post_types'] ) && is_array( $raw['post_types'] ) ) {
                    $receiver['post_types'] = array_map( 'sanitize_key', $raw['post_types'] );
                }

                if ( ! empty( $receiver['url'] ) || ! empty( $receiver['name'] ) ) {
                    $output['receivers'][] = $receiver;
                }
            }
        }

        if ( ! empty( $input['sync_rules'] ) && is_array( $input['sync_rules'] ) ) {
            $rules = array(
                'sync_title'         => ! empty( $input['sync_rules']['sync_title'] ),
                'sync_content'       => ! empty( $input['sync_rules']['sync_content'] ),
                'sync_excerpt'       => ! empty( $input['sync_rules']['sync_excerpt'] ),
                'sync_slug'          => ! empty( $input['sync_rules']['sync_slug'] ),
                'sync_status'        => ! empty( $input['sync_rules']['sync_status'] ),
                'sync_categories'    => ! empty( $input['sync_rules']['sync_categories'] ),
                'sync_tags'          => ! empty( $input['sync_rules']['sync_tags'] ),
                'sync_featured_img'  => ! empty( $input['sync_rules']['sync_featured_img'] ),
                'sync_content_imgs'  => ! empty( $input['sync_rules']['sync_content_imgs'] ),
                'sync_meta'          => ! empty( $input['sync_rules']['sync_meta'] ),
                'sync_builder_data'  => ! empty( $input['sync_rules']['sync_builder_data'] ),
                'default_status'     => in_array( $input['sync_rules']['default_status'] ?? '', array( 'publish', 'draft', 'pending' ), true )
                    ? $input['sync_rules']['default_status'] : 'publish',
                'meta_exclude'       => '',
            );

            if ( isset( $input['sync_rules']['meta_exclude'] ) ) {
                $rules['meta_exclude'] = sanitize_text_field( $input['sync_rules']['meta_exclude'] );
            }

            $output['sync_rules'] = $rules;
        }

        $output['sync_count'] = intval( $input['sync_count'] ?? $output['sync_count'] ?? 0 );
        $output['last_sync']  = $input['last_sync'] ?? $output['last_sync'] ?? '';

        return $output;
    }

    public function render_settings_page() {
        include WPSW_PLUGIN_PATH . 'admin/partials/wpsw-admin-display.php';
    }

    private static function get_defaults() {
        return array(
            'mode'        => 'sender',
            'receivers'   => array(),
            'sync_rules'  => self::get_default_rules(),
            'sync_count'  => 0,
            'last_sync'   => '',
            'version'     => WPSW_VERSION,
            'secret_key'  => '',
        );
    }

    public static function get_default_rules() {
        return array(
            'sync_title'        => true,
            'sync_content'      => true,
            'sync_excerpt'      => true,
            'sync_slug'         => true,
            'sync_status'       => true,
            'sync_categories'   => true,
            'sync_tags'         => true,
            'sync_featured_img' => true,
            'sync_content_imgs' => true,
            'sync_meta'         => true,
            'sync_builder_data' => true,
            'default_status'    => 'publish',
            'meta_exclude'      => '_edit_lock,_edit_last,_thumbnail_id,_wp_old_slug',
        );
    }

    public static function migrate_settings() {
        $settings = get_option( 'wpsw_settings', array() );

        if ( empty( $settings ) ) {
            return false;
        }

        if ( isset( $settings['receivers'] ) && is_array( $settings['receivers'] ) ) {
            return false;
        }

        $migrated = array(
            'mode'        => $settings['mode'] ?? 'sender',
            'receivers'   => array(),
            'sync_rules'  => self::get_default_rules(),
            'sync_count'  => intval( $settings['sync_count'] ?? 0 ),
            'last_sync'   => $settings['last_sync'] ?? '',
            'version'     => WPSW_VERSION,
            'secret_key'  => $settings['secret_key'] ?? '',
        );

        if ( ! empty( $settings['receiver_url'] ) || ! empty( $settings['secret_key'] ) ) {
            $migrated['receivers'][] = array(
                'id'         => 'rcv_' . wp_generate_password( 8, false ),
                'name'       => esc_html__( 'Default Receiver', 'softart-post-sync-webhook' ),
                'url'        => $settings['receiver_url'] ?? '',
                'secret_key' => $settings['secret_key'] ?? wp_generate_password( 32, false ),
                'enabled'    => true,
                'post_types' => $settings['post_types'] ?? array( 'post' ),
            );
        }

        update_option( 'wpsw_settings', $migrated );

        return true;
    }

    public static function get_settings() {
        self::migrate_settings();

        $defaults = self::get_defaults();
        $settings = wp_parse_args( get_option( 'wpsw_settings', array() ), $defaults );

        if ( empty( $settings['sync_rules'] ) ) {
            $settings['sync_rules'] = self::get_default_rules();
        }

        if ( ! is_array( $settings['receivers'] ) ) {
            $settings['receivers'] = array();
        }

        return $settings;
    }

    public static function get_receiver( $receiver_id ) {
        $settings = self::get_settings();

        foreach ( $settings['receivers'] as $receiver ) {
            if ( $receiver['id'] === $receiver_id ) {
                return $receiver;
            }
        }

        return false;
    }

    public static function update_receiver_stats( $receiver_id, $success ) {
        $settings = self::get_settings();

        foreach ( $settings['receivers'] as &$receiver ) {
            if ( $receiver['id'] === $receiver_id ) {
                $receiver['sync_count']  = intval( $receiver['sync_count'] ?? 0 ) + ( $success ? 1 : 0 );
                $receiver['last_sync']   = $success ? current_time( 'mysql' ) : ( $receiver['last_sync'] ?? '' );
                $receiver['last_status'] = $success ? 'ok' : 'error';
                break;
            }
        }
        unset( $receiver );

        $settings['sync_count'] = intval( $settings['sync_count'] ) + ( $success ? 1 : 0 );
        $settings['last_sync']  = $success ? current_time( 'mysql' ) : $settings['last_sync'];

        update_option( 'wpsw_settings', $settings );
    }
}
