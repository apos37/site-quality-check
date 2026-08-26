<?php
/**
 * CONTENT AUDITS
 *
 * Tabbed content audits: Orphaned Pages, Missing Alt Text, SEO Meta, Mixed Content.
 * Each tab scans in chunks via AJAX and stores results in a custom table.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class ContentAudits {

    /**
     * @var ContentAudits|null Singleton instance
     */
    private static ?ContentAudits $instance = null;


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
        add_action( 'sqc_subheader_left', [ $this, 'render_subheader_tabs' ] );
        add_action( 'sqc_subheader_right', [ $this, 'render_subheader_controls' ] );
    } // End __construct()


    /**
     * Audit type => label.
     *
     * @return array
     */
    public static function get_audit_tabs() : array {
        return [
            'orphaned'      => __( 'Orphaned Pages', 'site-quality-check' ),
            'alt_text'      => __( 'Missing Alt Text', 'site-quality-check' ),
            'mixed_content' => __( 'Mixed Content', 'site-quality-check' ),
            'seo_meta'      => __( 'SEO Meta', 'site-quality-check' ),
        ];
    } // End get_audit_tabs()


    /**
     * Get an explanatory description for each audit type, shown above the results.
     *
     * @param string $audit_type
     * @return string
     */
    private static function get_audit_description( string $audit_type ) : string {
        return match ( $audit_type ) {
            'orphaned' => __( 'Orphaned pages have no other page or post linking to them, making them hard for visitors and search engines to discover. Fix this by adding an internal link to the page from your navigation menu, a relevant post, or another page on your site.', 'site-quality-check' ),
            'alt_text' => __( 'This checks images used within your page and post content, including featured images — not every file in your Media Library. Images without alt text are invisible to screen readers and are missed by search engines trying to understand your content. Fix this by editing the image in the block editor, or in the media library if it\'s a featured image, and adding a short, descriptive alt text.', 'site-quality-check' ),
            'seo_meta' => __( 'Pages missing an SEO title or meta description may show up poorly in search results, using an auto-generated snippet instead of one you control. Fix this by editing the page and filling in the Yoast SEO title and meta description fields.', 'site-quality-check' ),
            'mixed_content' => __( 'Mixed content means a page served over HTTPS is loading an image or resource over plain HTTP, which browsers may block or flag as insecure. Fix this by editing the page and updating the flagged URL to use https:// instead of http://.', 'site-quality-check' ),
            default => '',
        };
    } // End get_audit_description()


    /**
     * Get the current audit tab, from $_GET, falling back to the user's last-viewed, then default.
     *
     * @return string
     */
    public static function get_current_tab() : string {
        $tabs = array_keys( self::get_audit_tabs() );

        if ( isset( $_GET[ 'audit' ] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET[ 'audit' ] ) );

            if ( in_array( $tab, $tabs, true ) ) {
                update_user_meta( get_current_user_id(), 'sqc_last_audit_tab', $tab );
                return $tab;
            }
        }

        $last = get_user_meta( get_current_user_id(), 'sqc_last_audit_tab', true );

        return in_array( $last, $tabs, true ) ? $last : $tabs[ 0 ];
    } // End get_current_tab()


    /**
     * Render the audit tabs in the subheader (left side).
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_tabs( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-content-audits' !== $active_page ) {
            return;
        }

        $current_tab = self::get_current_tab();
        ?>
        <?php foreach ( self::get_audit_tabs() as $slug => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'audit', $slug, remove_query_arg( 'sqc_view' ) ) ); ?>" class="sqc-button <?php echo $current_tab === $slug ? '' : 'button-secondary'; ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
        <?php
    } // End render_subheader_tabs()


    /**
     * Render last-checked + refresh + show omitted controls (right side).
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_controls( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-content-audits' !== $active_page ) {
            return;
        }

        $current_tab = self::get_current_tab();

        if ( 'mixed_content' === $current_tab && 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
            return;
        }

        if ( 'seo_meta' === $current_tab && ! Integrations::is_yoast_active() ) {
            return;
        }

        $showing_omitted = isset( $_GET[ 'sqc_view' ] ) && 'omitted' === $_GET[ 'sqc_view' ];
        $omitted_count = count( Audits::get_results( $current_tab, true ) );
        ?>
        <span id="sqc-audit-last-checked" data-audit-type="<?php echo esc_attr( $current_tab ); ?>">
            <?php echo esc_html( sprintf( __( 'Last checked: %s', 'site-quality-check' ), Audits::get_last_checked_display( $current_tab ) ) ); ?>
        </span>

        <button type="button" class="sqc-button sqc-refresh-audit" id="sqc-refresh-audit" data-audit-type="<?php echo esc_attr( $current_tab ); ?>" title="<?php esc_attr_e( 'Rescan', 'site-quality-check' ); ?>">
            <span class="dashicons dashicons-update"></span>
        </button>

        <?php if ( $showing_omitted ) : ?>
            <a href="<?php echo esc_url( remove_query_arg( 'sqc_view' ) ); ?>" class="sqc-button"><?php esc_html_e( 'Show Active', 'site-quality-check' ); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url( add_query_arg( 'sqc_view', 'omitted' ) ); ?>" class="sqc-button"><?php echo esc_html( sprintf(
                /* translators: %d: number of omitted items */
                __( 'Show Omitted (%d)', 'site-quality-check' ),
                $omitted_count
            ) ); ?></a>
        <?php endif; ?>
        <?php
    } // End render_subheader_controls()


    /**
     * Details column renderer for each audit type.
     *
     * @param string $audit_type
     * @return callable
     */
    private static function get_details_renderer( string $audit_type ) : callable {
        return match ( $audit_type ) {
            'alt_text' => function ( $details ) {
                $labels = [ 'featured' => __( 'Featured Image', 'site-quality-check' ), 'content' => __( 'In Content', 'site-quality-check' ) ];
                $out = [];

                foreach ( $details[ 'images' ] ?? [] as $image ) {
                    $src = $image[ 'src' ] ?? '';
                    $source_label = $labels[ $image[ 'source' ] ?? '' ] ?? '';

                    $out[] = '<span class="sqc-alt-thumb-row"><img src="' . esc_url( $src ) . '" class="sqc-alt-thumb sqc-alt-thumb-clickable" data-full-src="' . esc_url( $src ) . '" alt=""><span class="sqc-alt-thumb-label">' . esc_html( $source_label ) . '</span></span>';
                }

                return implode( '', $out );
            },
            'seo_meta' => function ( $details ) {
                $labels = [ 'title' => __( 'SEO Title', 'site-quality-check' ), 'description' => __( 'Meta Description', 'site-quality-check' ) ];
                $missing = array_map( fn( $key ) => $labels[ $key ] ?? $key, $details[ 'missing' ] ?? [] );
                return esc_html( implode( ', ', $missing ) );
            },
            'mixed_content' => function ( $details ) {
                return esc_html( implode( ', ', $details[ 'urls' ] ?? [] ) );
            },
            default => function ( $details ) {
                return '—';
            },
        };
    } // End get_details_renderer()


    /**
     * Render the Content Audits admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_access() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-quality-check' ) );
        }

        wp_enqueue_script(
            'sqc-content-audits',
            Bootstrap::url() . 'inc/js/content-audits.js',
            [ 'jquery' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqc-content-audits', 'sqcAudits', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sqc_audits_nonce' ),
            'i18n'    => [
                'scanning' => __( 'Scanning:', 'site-quality-check' ),
                'lastChecked' => __( 'Last checked:', 'site-quality-check' ),
            ],
        ] );

        $current_tab = self::get_current_tab();
        $showing_omitted = isset( $_GET[ 'sqc_view' ] ) && 'omitted' === $_GET[ 'sqc_view' ];

        if ( 'seo_meta' === $current_tab && ! Integrations::is_yoast_active() ) {
            ?>
            <div class="wrap sqc-content-wrap sqc-content-audits">
                <p><?php echo esc_html( self::get_audit_description( $current_tab ) ); ?></p>
                <div class="sqc-box"><div class="sqc-box-body">
                    <p><?php esc_html_e( 'Yoast SEO is not active. Install it to see missing meta title and description reports.', 'site-quality-check' ); ?></p>
                </div></div>
            </div>
            <?php
            return;
        }

        if ( 'mixed_content' === $current_tab && 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
            ?>
            <div class="wrap sqc-content-wrap sqc-content-audits">
                <p><?php echo esc_html( self::get_audit_description( $current_tab ) ); ?></p>
                <div class="sqc-audit-warning-banner">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Your site is not using HTTPS. Every WordPress site should be served over HTTPS — most hosts offer free SSL certificates. This check will run automatically once your site is switched to HTTPS.', 'site-quality-check' ); ?>
                </div>
            </div>
            <?php
            return;
        }

        $table = new ContentAuditsListTable( $current_tab, self::get_details_renderer( $current_tab ) );
        $table->prepare_items();
        ?>
        <div class="wrap sqc-content-wrap sqc-content-audits">
            <p><?php echo esc_html( self::get_audit_description( $current_tab ) ); ?></p>

            <?php if ( $showing_omitted ?? false ) : ?>
                <div class="sqc-omitted-banner">
                    <span class="dashicons dashicons-hidden"></span>
                    <?php esc_html_e( 'Showing omitted items — these are excluded from the active audit above.', 'site-quality-check' ); ?>
                </div>
            <?php endif; ?>

            <div id="sqc-audit-scanning-status" style="display:none;"></div>
            <div class="sqc-box" id="sqc-audit-results-box">
                <div class="sqc-box-body">
                    <?php if ( empty( $table->items ) ) : ?>
                        <p><?php esc_html_e( 'No results found.', 'site-quality-check' ); ?></p>
                    <?php else : ?>
                        <form method="get">
                            <input type="hidden" name="page" value="<?php echo esc_attr( Menu::MENU_SLUG . '-content-audits' ); ?>">
                            <input type="hidden" name="audit" value="<?php echo esc_attr( $current_tab ); ?>">
                            <?php $table->display(); ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div id="sqc-image-modal" class="sqc-modal" style="display:none;">
                <div class="sqc-modal-overlay"></div>
                <div class="sqc-modal-content">
                    <button type="button" class="sqc-modal-close" aria-label="<?php esc_attr_e( 'Close', 'site-quality-check' ); ?>">&times;</button>
                    <img src="" alt="" id="sqc-modal-image">
                    <p class="sqc-modal-url"><a href="" target="_blank" rel="noopener noreferrer" id="sqc-modal-link"></a></p>
                </div>
            </div>
        </div>
        <?php
    } // End render_page()

} // End class ContentAudits

ContentAudits::instance();