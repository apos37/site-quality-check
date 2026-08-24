<?php
/**
 * STALE CONTENT
 *
 * Lists content by staleness tier (warning/danger/critical) based on
 * post_modified, with permanent per-post omit and configurable thresholds.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class StaleContent {

    /**
     * Meta key marking a post as permanently omitted from stale content views.
     */
    public const OMIT_META_KEY = '_sqc_stale_omitted';


    /**
     * Default thresholds in days, overridable via Settings.
     */
    public const DEFAULT_THRESHOLDS = [
        'warning'  => 180,
        'danger'   => 365,
        'critical' => 730,
    ];


    /**
     * @var StaleContent|null Singleton instance
     */
    private static ?StaleContent $instance = null;


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
        add_action( 'wp_ajax_sqc_omit_stale_post', [ $this, 'ajax_omit_post' ] );
        add_action( 'wp_ajax_sqc_unomit_stale_post', [ $this, 'ajax_unomit_post' ] );
    } // End __construct()


    /**
     * Get configured thresholds (days) from settings, falling back to defaults.
     *
     * @return array
     */
    public static function get_thresholds() : array {
        $saved = get_option( 'sqc_stale_thresholds', [] );

        return wp_parse_args( is_array( $saved ) ? $saved : [], self::DEFAULT_THRESHOLDS );
    } // End get_thresholds()


    /**
     * Get post types included in stale content checks, from settings.
     *
     * @return array
     */
    public static function get_included_post_types() : array {
        $saved = get_option( 'sqc_stale_post_types', [ 'post', 'page' ] );

        return is_array( $saved ) && ! empty( $saved ) ? $saved : [ 'post', 'page' ];
    } // End get_included_post_types()


    /**
     * Determine the staleness tier for a given number of days since last modified.
     *
     * @param int $days_stale
     * @return string|null One of: warning, danger, critical. Null if not stale.
     */
    public static function get_tier( int $days_stale ) : ?string {
        $thresholds = self::get_thresholds();

        if ( $days_stale >= $thresholds[ 'critical' ] ) {
            return 'critical';
        }

        if ( $days_stale >= $thresholds[ 'danger' ] ) {
            return 'danger';
        }

        if ( $days_stale >= $thresholds[ 'warning' ] ) {
            return 'warning';
        }

        return null;
    } // End get_tier()


    /**
     * Get all stale content, excluding permanently omitted posts, sorted most stale first.
     *
     * @param bool $most_stale_first
     * @return array Array of [ 'post' => WP_Post, 'days_stale' => int, 'tier' => string ]
     */
    public static function get_stale_content( bool $most_stale_first = true ) : array {
        $post_types = self::get_included_post_types();
        $thresholds = self::get_thresholds();
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $thresholds[ 'warning' ] * DAY_IN_SECONDS ) );

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'date_query'     => [
                [
                    'column' => 'post_modified',
                    'before' => $cutoff,
                ],
            ],
            'meta_query'     => [
                [
                    'key'     => self::OMIT_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        $results = [];
        $now = time();

        foreach ( $posts as $post ) {
            $modified_timestamp = get_post_modified_time( 'U', false, $post );
            $days_stale = (int) floor( ( $now - $modified_timestamp ) / DAY_IN_SECONDS );
            $tier = self::get_tier( $days_stale );

            if ( null === $tier ) {
                continue;
            }

            $results[] = [
                'post'       => $post,
                'days_stale' => $days_stale,
                'tier'       => $tier,
            ];
        }

        usort( $results, function ( $a, $b ) use ( $most_stale_first ) {
            return $most_stale_first
                ? $b[ 'days_stale' ] <=> $a[ 'days_stale' ]
                : $a[ 'days_stale' ] <=> $b[ 'days_stale' ];
        } );

        return $results;
    } // End get_stale_content()


    /**
     * Get counts per tier for the dashboard widget.
     *
     * @return array [ 'warning' => int, 'danger' => int, 'critical' => int ]
     */
    public static function get_counts() : array {
        $stale = self::get_stale_content();
        $counts = [ 'warning' => 0, 'danger' => 0, 'critical' => 0 ];

        foreach ( $stale as $item ) {
            $counts[ $item[ 'tier' ] ]++;
        }

        return $counts;
    } // End get_counts()


    /**
     * Permanently omit a post from stale content views.
     *
     * @param int $post_id
     * @return void
     */
    public static function omit_post( int $post_id ) : void {
        update_post_meta( $post_id, self::OMIT_META_KEY, time() );
    } // End omit_post()


    /**
     * Remove a post's permanent omit flag.
     *
     * @param int $post_id
     * @return void
     */
    public static function unomit_post( int $post_id ) : void {
        delete_post_meta( $post_id, self::OMIT_META_KEY );
    } // End unomit_post()


    /**
     * Get all permanently omitted posts, for the "show omitted" view.
     *
     * @return array
     */
    public static function get_omitted_posts() : array {
        return get_posts( [
            'post_type'      => self::get_included_post_types(),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => self::OMIT_META_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ] );
    } // End get_omitted_posts()


    /**
     * AJAX: omit a post from stale content views.
     *
     * @return void
     */
    public function ajax_omit_post() : void {
        check_ajax_referer( 'sqc_stale_content_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }

        $post_id = (int) ( $_POST[ 'post_id' ] ?? 0 );

        self::omit_post( $post_id );

        wp_send_json_success();
    } // End ajax_omit_post()


    /**
     * AJAX: remove a post's omit flag.
     *
     * @return void
     */
    public function ajax_unomit_post() : void {
        check_ajax_referer( 'sqc_stale_content_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }

        $post_id = (int) ( $_POST[ 'post_id' ] ?? 0 );

        self::unomit_post( $post_id );

        wp_send_json_success();
    } // End ajax_unomit_post()


        /**
     * Render the Stale Content admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-quality-check' ) );
        }

        wp_enqueue_script(
            'sqc-stale-content',
            Bootstrap::url() . 'inc/js/stale-content.js',
            [ 'jquery' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqc-stale-content', 'sqcStaleContent', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sqc_stale_content_nonce' ),
        ] );

        $sort_desc = ! isset( $_GET[ 'sqc_sort' ] ) || 'desc' === $_GET[ 'sqc_sort' ];
        $stale = self::get_stale_content( $sort_desc );
        ?>
        <div class="wrap sqc-content-wrap sqc-stale-content">
            <?php if ( empty( $stale ) ) : ?>
                <p><?php esc_html_e( 'No stale content found.', 'site-quality-check' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Title', 'site-quality-check' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'site-quality-check' ); ?></th>
                            <th><?php esc_html_e( 'Last Modified', 'site-quality-check' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'site-quality-check' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'site-quality-check' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stale as $item ) : ?>
                            <?php $post = $item[ 'post' ]; ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
                                <td><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></td>
                                <td><?php echo esc_html( get_the_modified_date( '', $post ) ); ?> (<?php echo esc_html( $item[ 'days_stale' ] ); ?> <?php esc_html_e( 'days', 'site-quality-check' ); ?>)</td>
                                <td><span class="sqc-badge sqc-badge-<?php echo esc_attr( $item[ 'tier' ] ); ?>"><?php echo esc_html( ucfirst( $item[ 'tier' ] ) ); ?></span></td>
                                <td><button type="button" class="button sqc-omit-post" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Omit', 'site-quality-check' ); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    } // End render_page()

} // End class StaleContent

StaleContent::instance();