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
        add_action( 'sqc_subheader_left', [ $this, 'render_subheader_toggle' ] );
        add_action( 'sqc_subheader_right', [ $this, 'render_subheader_search' ] );
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
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }

        $post_id = (int) wp_unslash( $_POST[ 'post_id' ] ?? 0 );

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

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }

        $post_id = (int) wp_unslash( $_POST[ 'post_id' ] ?? 0 );

        self::unomit_post( $post_id );

        wp_send_json_success();
    } // End ajax_unomit_post()


    /**
     * Render the "Show Omitted" / "Show Stale Content" toggle in the subheader (left side).
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_toggle( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-stale-content' !== $active_page ) {
            return;
        }

        $showing_omitted = isset( $_GET[ 'sqc_view' ] ) && 'omitted' === $_GET[ 'sqc_view' ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
        $omitted_count = count( self::get_omitted_posts() );
        ?>
        <?php if ( $showing_omitted ) : ?>
            <a href="<?php echo esc_url( remove_query_arg( 'sqc_view' ) ); ?>" class="sqc-button"><?php esc_html_e( 'Show Stale Content', 'site-quality-check' ); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url( add_query_arg( 'sqc_view', 'omitted' ) ); ?>" class="sqc-button"><?php echo esc_html( sprintf(
                /* translators: %d: number of omitted items */
                __( 'Show Omitted (%d)', 'site-quality-check' ),
                $omitted_count
            ) ); ?></a>
        <?php endif; ?>
        <?php
    } // End render_subheader_toggle()


    /**
     * Render the search box in the subheader (right side).
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_search( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-stale-content' !== $active_page ) {
            return;
        }

        $search_value = sanitize_text_field( wp_unslash( $_GET[ 's' ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
        ?>
        <form method="get" class="sqc-posttype-search">
            <?php foreach ( $_GET as $key => $value ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
                if ( in_array( $key, [ 's', 'paged' ], true ) ) continue;
            ?>
                <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
            <?php endforeach; ?>

            <input type="search" name="s" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'Search', 'site-quality-check' ); ?>" class="sqc-search-input">
            <button type="submit" class="sqc-button"><?php esc_html_e( 'Search', 'site-quality-check' ); ?></button>

            <?php if ( $search_value ) : ?>
                <a href="<?php echo esc_url( remove_query_arg( 's' ) ); ?>" class="sqc-button"><?php esc_html_e( 'Clear', 'site-quality-check' ); ?></a>
            <?php endif; ?>
        </form>
        <?php
    } // End render_subheader_search()


    /**
     * Render the Stale Content admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_access() ) {
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

        $table = new StaleContentListTable();
        $table->prepare_items();
        $showing_omitted = isset( $_GET[ 'sqc_view' ] ) && 'omitted' === $_GET[ 'sqc_view' ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
        ?>
        <div class="wrap sqc-content-wrap sqc-stale-content">
            <?php if ( $showing_omitted ) : ?>
                <div class="sqc-omitted-banner">
                    <span class="dashicons dashicons-hidden"></span>
                    <?php esc_html_e( 'Showing omitted items — these are excluded from the stale content list above.', 'site-quality-check' ); ?>
                </div>
            <?php endif; ?>

            <div class="sqc-box">
                <div class="sqc-box-body">
                    <form method="get">
                        <input type="hidden" name="page" value="<?php echo esc_attr( Menu::MENU_SLUG . '-stale-content' ); ?>">
                        <?php $table->display(); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    } // End render_page()

} // End class StaleContent

StaleContent::instance();