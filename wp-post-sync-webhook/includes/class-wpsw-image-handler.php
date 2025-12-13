<?php
/**
 * Image handling functionality.
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
 * Image handling functionality.
 */
class WPSW_Image_Handler {

    /**
     * Log a message.
     *
     * @since 1.0.0
     * @param string $message Message to log.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WPSW Image] ' . $message );
        }
    }

    /**
     * Get featured image data for a post.
     *
     * @since 1.0.0
     * @param int $post_id Post ID.
     * @return array
     */
    public function get_featured_image_data( $post_id ) {
        $thumb_id = get_post_thumbnail_id( $post_id );

        if ( ! $thumb_id ) {
            return array();
        }

        $src = wp_get_attachment_image_src( $thumb_id, 'full' );

        if ( ! $src ) {
            return array();
        }

        $file_path = get_attached_file( $thumb_id );
        $file_hash = '';
        $file_size = 0;

        if ( $file_path && file_exists( $file_path ) ) {
            $file_hash = md5_file( $file_path );
            $file_size = filesize( $file_path );
        }

        return array(
            'url'       => $src[0],
            'alt'       => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
            'title'     => get_the_title( $thumb_id ),
            'caption'   => wp_get_attachment_caption( $thumb_id ),
            'filename'  => basename( get_attached_file( $thumb_id ) ),
            'file_hash' => $file_hash,
            'file_size' => $file_size,
        );
    }

    /**
     * Extract images from post content.
     *
     * @since 1.0.0
     * @param string $content Post content.
     * @return array
     */
    public function extract_content_images( $content ) {
        $images = array();

        if ( empty( $content ) ) {
            return $images;
        }

        preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

        if ( ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $url ) {
                $url = html_entity_decode( trim( $url ) );

                if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    continue;
                }

                $attach_id = attachment_url_to_postid( $url );
                $file_hash = '';
                $file_size = 0;
                $filename  = basename( wp_parse_url( $url, PHP_URL_PATH ) );

                if ( $attach_id ) {
                    $file_path = get_attached_file( $attach_id );

                    if ( $file_path && file_exists( $file_path ) ) {
                        $file_hash = md5_file( $file_path );
                        $file_size = filesize( $file_path );
                    }
                }

                $images[] = array(
                    'url'       => $url,
                    'filename'  => $filename,
                    'file_hash' => $file_hash,
                    'file_size' => $file_size,
                );
            }
        }

        return $images;
    }

    /**
     * Smart import image with duplicate detection.
     *
     * @since 1.0.0
     * @param array|string $image Image data or URL.
     * @return array|false
     */
    public function smart_import( $image ) {
        $url       = is_array( $image ) ? ( $image['url'] ?? '' ) : $image;
        $file_hash = is_array( $image ) ? ( $image['file_hash'] ?? '' ) : '';
        $filename  = is_array( $image ) ? ( $image['filename'] ?? '' ) : basename( wp_parse_url( $url, PHP_URL_PATH ) );
        $file_size = is_array( $image ) ? ( $image['file_size'] ?? 0 ) : 0;

        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // 1. Check by source URL.
        $existing = $this->find_by_source_url( $url );
        if ( $existing ) {
            return $existing;
        }

        // 2. Check by file hash.
        if ( ! empty( $file_hash ) ) {
            $existing = $this->find_by_hash( $file_hash, $url );
            if ( $existing ) {
                return $existing;
            }
        }

        // 3. Check by filename and size.
        if ( ! empty( $filename ) && $file_size > 0 ) {
            $existing = $this->find_by_filename_and_size( $filename, $file_size, $url, $file_hash );
            if ( $existing ) {
                return $existing;
            }
        }

        // 4. Check by filename only.
        if ( ! empty( $filename ) ) {
            $existing = $this->find_by_filename( $filename, $url );
            if ( $existing ) {
                return $existing;
            }
        }

        // 5. Download the image.
        return $this->download_and_import( $url, $filename, $file_hash );
    }

    /**
     * Find image by source URL.
     *
     * @since  1.0.0
     * @access private
     * @param  string $url Image URL.
     * @return array|false
     */
    private function find_by_source_url( $url ) {
        global $wpdb;

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpsw_source_url' AND meta_value = %s LIMIT 1",
                $url
            )
        );

        if ( $existing ) {
            $this->log( 'Found by source URL: ' . $existing );
            return array(
                'id'     => (int) $existing,
                'url'    => wp_get_attachment_url( $existing ),
                'reused' => true,
            );
        }

        return false;
    }

    /**
     * Find image by hash.
     *
     * @since  1.0.0
     * @access private
     * @param  string $file_hash File hash.
     * @param  string $url       Image URL.
     * @return array|false
     */
    private function find_by_hash( $file_hash, $url ) {
        global $wpdb;

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpsw_file_hash' AND meta_value = %s LIMIT 1",
                $file_hash
            )
        );

        if ( $existing ) {
            $this->log( 'Found by hash: ' . $existing );
            update_post_meta( $existing, '_wpsw_source_url', $url );
            return array(
                'id'     => (int) $existing,
                'url'    => wp_get_attachment_url( $existing ),
                'reused' => true,
            );
        }

        return false;
    }

    /**
     * Find image by filename and size.
     *
     * @since  1.0.0
     * @access private
     * @param  string $filename  Filename.
     * @param  int    $file_size File size.
     * @param  string $url       Image URL.
     * @param  string $file_hash File hash.
     * @return array|false
     */
    private function find_by_filename_and_size( $filename, $file_size, $url, $file_hash ) {
        global $wpdb;

        $attachments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title = %s",
                pathinfo( $filename, PATHINFO_FILENAME )
            )
        );

        foreach ( $attachments as $att ) {
            $file_path = get_attached_file( $att->ID );

            if ( $file_path && file_exists( $file_path ) && filesize( $file_path ) === $file_size ) {
                $this->log( 'Found by filename+size: ' . $att->ID );
                update_post_meta( $att->ID, '_wpsw_source_url', $url );

                if ( $file_hash ) {
                    update_post_meta( $att->ID, '_wpsw_file_hash', $file_hash );
                }

                return array(
                    'id'     => (int) $att->ID,
                    'url'    => wp_get_attachment_url( $att->ID ),
                    'reused' => true,
                );
            }
        }

        return false;
    }

    /**
     * Find image by filename.
     *
     * @since  1.0.0
     * @access private
     * @param  string $filename Filename.
     * @param  string $url      Image URL.
     * @return array|false
     */
    private function find_by_filename( $filename, $url ) {
        global $wpdb;

        $clean_filename = pathinfo( $filename, PATHINFO_FILENAME );
        $clean_filename = preg_replace( '/-\d+x\d+$/', '', $clean_filename );

        $attachment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s LIMIT 1",
                $wpdb->esc_like( $clean_filename ) . '%'
            )
        );

        if ( $attachment ) {
            $this->log( 'Found by filename: ' . $attachment->ID );
            update_post_meta( $attachment->ID, '_wpsw_source_url', $url );
            return array(
                'id'     => (int) $attachment->ID,
                'url'    => wp_get_attachment_url( $attachment->ID ),
                'reused' => true,
            );
        }

        return false;
    }

    /**
     * Download and import image.
     *
     * @since  1.0.0
     * @access private
     * @param  string $url       Image URL.
     * @param  string $filename  Filename.
     * @param  string $file_hash File hash.
     * @return array|false
     */
    private function download_and_import( $url, $filename, $file_hash ) {
        $this->log( 'Downloading: ' . $url );

        $response = wp_remote_get(
            $url,
            array(
                'timeout'   => 60,
                'sslverify' => false,
                'headers'   => array( 'Accept' => 'image/*' ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->log( 'Download failed' );
            return false;
        }

        $data = wp_remote_retrieve_body( $response );

        if ( strlen( $data ) < 100 ) {
            return false;
        }

        $mime = wp_remote_retrieve_header( $response, 'content-type' );
        $ext  = $this->mime_to_extension( $mime );

        if ( ! $ext ) {
            $ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        }

        if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
            $ext = 'jpg';
        }

        $name           = ! empty( $filename ) ? pathinfo( $filename, PATHINFO_FILENAME ) : 'img-' . time();
        $name           = sanitize_file_name( $name );
        $final_filename = $name . '.' . $ext;

        $upload = wp_upload_bits( $final_filename, null, $data );

        if ( ! empty( $upload['error'] ) ) {
            $this->log( 'Upload error: ' . $upload['error'] );
            return false;
        }

        $filetype  = wp_check_filetype( $upload['file'] );
        $attach_id = wp_insert_attachment(
            array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => $name,
                'post_status'    => 'inherit',
            ),
            $upload['file']
        );

        if ( is_wp_error( $attach_id ) ) {
            return false;
        }

        wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );

        update_post_meta( $attach_id, '_wpsw_source_url', $url );

        if ( ! empty( $file_hash ) ) {
            update_post_meta( $attach_id, '_wpsw_file_hash', $file_hash );
        } else {
            $new_hash = md5_file( $upload['file'] );
            update_post_meta( $attach_id, '_wpsw_file_hash', $new_hash );
        }

        $this->log( 'Imported: ID ' . $attach_id );

        return array(
            'id'     => $attach_id,
            'url'    => wp_get_attachment_url( $attach_id ),
            'reused' => false,
        );
    }

    /**
     * Convert MIME type to file extension.
     *
     * @since  1.0.0
     * @access private
     * @param  string $mime MIME type.
     * @return string|false
     */
    private function mime_to_extension( $mime ) {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );

        $mime = strtolower( explode( ';', $mime )[0] );

        return isset( $map[ $mime ] ) ? $map[ $mime ] : false;
    }
}