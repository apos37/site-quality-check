<?php
/**
 * AUDITS
 *
 * Chunked scanning engine for content audits (orphaned pages, alt text,
 * SEO meta, mixed content), storing results in a custom table with omit support.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Audits {

    /**
     * Registered audit types and their scan callbacks.
     */
    public const TYPES = [ 'orphaned', 'alt_text', 'seo_meta', 'mixed_content' ];


    /**
     * Posts scanned per AJAX chunk.
     */
    public const CHUNK_SIZE = 25;


    /**
     * @var Audits|null Singleton instance
     */
    private static ?Audits $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'wp_ajax_sqc_scan_chunk', [ $this, 'ajax_scan_chunk' ] );
        add_action( 'wp_ajax_sqc_omit_audit_result', [ $this, 'ajax_omit_result' ] );
        add_action( 'wp_ajax_sqc_unomit_audit_result', [ $this, 'ajax_unomit_result' ] );
    } // End __construct()


    /**
     * Get the table name.
     *
     * @return string
     */
    private static function table() : string {
        global $wpdb;
        return $wpdb->prefix . 'sqc_audit_results';
    } // End table()


    /**
     * Get the full list of post IDs eligible for scanning (respecting included post types and omits).
     *
     * @param string $audit_type
     * @return array
     */
    public static function get_scan_queue( string $audit_type ) : array {
        global $wpdb;

        $post_types = StaleContent::get_included_post_types();
        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        $omitted_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM " . self::table() . " WHERE audit_type = %s AND omitted = 1",
            $audit_type
        ) );

        $query = "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})";
        $params = $post_types;

        if ( ! empty( $omitted_ids ) ) {
            $exclude_placeholders = implode( ',', array_fill( 0, count( $omitted_ids ), '%d' ) );
            $query .= " AND ID NOT IN ({$exclude_placeholders})";
            $params = array_merge( $params, $omitted_ids );
        }

        return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( $query, $params ) ) );
    } // End get_scan_queue()


    /**
     * Check if a post/page is linked from any navigation menu — classic menus
     * (via postmeta), or block-based Navigation blocks referenced by ID or by URL.
     *
     * @param int $post_id
     * @return bool
     */
    private static function is_in_nav_menus( int $post_id ) : bool {
        static $linked_ids = null;
        static $nav_content = null;

        if ( null === $linked_ids ) {
            global $wpdb;

            $linked_ids = $wpdb->get_col(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id'"
            );
            $linked_ids = array_map( 'absint', $linked_ids );
        }

        if ( in_array( $post_id, $linked_ids, true ) ) {
            return true;
        }

        if ( null === $nav_content ) {
            global $wpdb;

            $nav_content = implode( ' ', $wpdb->get_col( $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                'wp_navigation'
            ) ) );
        }

        if ( false !== strpos( $nav_content, '"id":' . $post_id )
            || false !== strpos( $nav_content, 'id="' . $post_id . '"' ) ) {
            return true;
        }

        $path = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH );

        return $path && false !== strpos( $nav_content, $path );
    } // End is_in_nav_menus()


    /**
     * Scan a single post for a given audit type, returning details if a finding exists, or null.
     *
     * @param string $audit_type
     * @param int $post_id
     * @param string $all_content Precomputed full-site content for orphan-checking (only used by 'orphaned').
     * @return array|null
     */
    public static function scan_post( string $audit_type, int $post_id, string $all_content = '' ) : ?array {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return null;
        }

        switch ( $audit_type ) {
            case 'orphaned':
                $home_id = (int) get_option( 'page_on_front' );
                $blog_id = (int) get_option( 'page_for_posts' );

                if ( $post->ID === $home_id || $post->ID === $blog_id ) {
                    return null;
                }

                if ( self::is_in_nav_menus( $post->ID ) ) {
                    return null;
                }

                $path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );

                if ( ! $path || false !== strpos( $all_content, $path ) ) {
                    return null;
                }

                return [];

            case 'alt_text':
                $missing = [];
                $featured_id = get_post_thumbnail_id( $post );

                if ( $featured_id ) {
                    $alt = get_post_meta( $featured_id, '_wp_attachment_image_alt', true );

                    if ( '' === trim( (string) $alt ) ) {
                        $missing[] = [
                            'src'    => wp_get_attachment_url( $featured_id ),
                            'source' => 'featured',
                        ];
                    }
                }

                if ( preg_match_all( '/<img[^>]+>/i', $post->post_content, $tags ) ) {
                    foreach ( $tags[ 0 ] as $tag ) {
                        if ( preg_match( '/alt=["\']([^"\']*)["\']/i', $tag, $alt_match ) && '' !== trim( $alt_match[ 1 ] ) ) {
                            continue;
                        }

                        preg_match( '/src=["\']([^"\']*)["\']/i', $tag, $src_match );
                        $missing[] = [
                            'src'    => $src_match[ 1 ] ?? '',
                            'source' => 'content',
                        ];
                    }
                }

                return empty( $missing ) ? null : [ 'images' => $missing ];

            case 'seo_meta':
                if ( ! Integrations::is_yoast_active() ) {
                    return null;
                }

                $title = Integrations::get_yoast_title( $post->ID );
                $description = Integrations::get_yoast_meta_description( $post->ID );

                $missing = [];

                if ( '' === trim( $title ) ) {
                    $missing[] = 'title';
                }

                if ( '' === trim( $description ) ) {
                    $missing[] = 'description';
                }

                return empty( $missing ) ? null : [ 'missing' => $missing ];

            case 'mixed_content':
                if ( 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
                    return null;
                }

                $urls = [];

                if ( preg_match_all( '/(src|href)=["\']http:\/\/[^"\']+["\']/i', $post->post_content, $matches ) ) {
                    $urls = $matches[ 0 ];
                }

                $featured_id = get_post_thumbnail_id( $post );

                if ( $featured_id ) {
                    $featured_url = wp_get_attachment_url( $featured_id );

                    if ( $featured_url && 0 === strpos( $featured_url, 'http://' ) ) {
                        $urls[] = $featured_url;
                    }
                }

                return empty( $urls ) ? null : [ 'urls' => array_unique( $urls ) ];

            default:
                return null;
        }
    } // End scan_post()


    /**
     * AJAX: scan one chunk of posts, save findings, return progress.
     *
     * @return void
     */
    public function ajax_scan_chunk() : void {
        check_ajax_referer( 'sqc_audits_nonce', 'nonce' );

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'site-quality-check' ) ], 403 );
        }

        $audit_type = sanitize_key( wp_unslash( $_POST[ 'audit_type' ] ?? '' ) );
        $offset = absint( $_POST[ 'offset' ] ?? 0 );

        if ( ! in_array( $audit_type, self::TYPES, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid audit type.', 'site-quality-check' ) ] );
        }

        if ( 0 === $offset ) {
            self::clear_results( $audit_type );
        }

        $queue = get_transient( 'sqc_scan_queue_' . $audit_type );

        if ( false === $queue || 0 === $offset ) {
            $queue = self::get_scan_queue( $audit_type );
            set_transient( 'sqc_scan_queue_' . $audit_type, $queue, HOUR_IN_SECONDS );
        }

        $all_content = '';

        if ( 'orphaned' === $audit_type && 0 === $offset ) {
            global $wpdb;
            $post_types = StaleContent::get_included_post_types();
            $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
            $all_content = implode( ' ', $wpdb->get_col( $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})",
                $post_types
            ) ) );
            set_transient( 'sqc_scan_all_content', $all_content, HOUR_IN_SECONDS );
        } elseif ( 'orphaned' === $audit_type ) {
            $all_content = get_transient( 'sqc_scan_all_content' ) ?: '';
        }

        $chunk = array_slice( $queue, $offset, self::CHUNK_SIZE );
        $last_title = '';

        foreach ( $chunk as $post_id ) {
            $result = self::scan_post( $audit_type, $post_id, $all_content );
            $last_title = get_the_title( $post_id );

            if ( null !== $result ) {
                self::save_result( $audit_type, $post_id, $result );
            }
        }

        $new_offset = $offset + count( $chunk );
        $done = $new_offset >= count( $queue );

        if ( $done ) {
            update_option( 'sqc_audit_last_checked_' . $audit_type, time() );
            delete_transient( 'sqc_scan_queue_' . $audit_type );
            delete_transient( 'sqc_scan_all_content' );
        }

        wp_send_json_success( [
            'done'        => $done,
            'offset'      => $new_offset,
            'total'       => count( $queue ),
            'last_title'  => $last_title,
        ] );
    } // End ajax_scan_chunk()


    /**
     * Save a single finding to the database.
     *
     * @param string $audit_type
     * @param int $post_id
     * @param array $details
     * @return void
     */
    private static function save_result( string $audit_type, int $post_id, array $details ) : void {
        global $wpdb;

        $wpdb->insert( self::table(), [
            'audit_type' => $audit_type,
            'post_id'    => $post_id,
            'details'    => wp_json_encode( $details ),
            'omitted'    => 0,
            'found_at'   => current_time( 'mysql' ),
        ] );
    } // End save_result()


    /**
     * Clear non-omitted results for an audit type before a fresh scan.
     *
     * @param string $audit_type
     * @return void
     */
    private static function clear_results( string $audit_type ) : void {
        global $wpdb;

        $wpdb->delete( self::table(), [ 'audit_type' => $audit_type, 'omitted' => 0 ] );
    } // End clear_results()


    /**
     * Get stored results for an audit type.
     *
     * @param string $audit_type
     * @param bool $omitted
     * @return array
     */
    public static function get_results( string $audit_type, bool $omitted = false ) : array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE audit_type = %s AND omitted = %d ORDER BY found_at DESC",
            $audit_type,
            $omitted ? 1 : 0
        ), ARRAY_A );
    } // End get_results()


    /**
     * Get the last-checked timestamp for an audit type, in site timezone.
     *
     * @param string $audit_type
     * @return string
     */
    public static function get_last_checked_display( string $audit_type ) : string {
        $timestamp = get_option( 'sqc_audit_last_checked_' . $audit_type, 0 );

        if ( ! $timestamp ) {
            return __( 'Never', 'site-quality-check' );
        }

        return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    } // End get_last_checked_display()


    /**
     * AJAX: omit a single result row.
     *
     * @return void
     */
    public function ajax_omit_result() : void {
        check_ajax_referer( 'sqc_audits_nonce', 'nonce' );

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'site-quality-check' ) ], 403 );
        }

        global $wpdb;
        $id = absint( $_POST[ 'id' ] ?? 0 );

        $wpdb->update( self::table(), [ 'omitted' => 1 ], [ 'id' => $id ] );

        wp_send_json_success();
    } // End ajax_omit_result()


    /**
     * AJAX: un-omit a single result row.
     *
     * @return void
     */
    public function ajax_unomit_result() : void {
        check_ajax_referer( 'sqc_audits_nonce', 'nonce' );

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'site-quality-check' ) ], 403 );
        }

        global $wpdb;
        $id = absint( $_POST[ 'id' ] ?? 0 );

        $wpdb->update( self::table(), [ 'omitted' => 0 ], [ 'id' => $id ] );

        wp_send_json_success();
    } // End ajax_unomit_result()

} // End class Audits

Audits::instance();