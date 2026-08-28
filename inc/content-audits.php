<?php
/**
 * CONTENT AUDITS
 *
 * Tabbed content audits. Tabs, descriptions, and details-column renderers are
 * all derived from registered audit types (see Audits::get_types() and the
 * 'sqcheck_audit_types' / 'sqcheck_audit_details_renderers' filters) — this
 * file has no knowledge of which specific audit types exist.
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
        add_action( 'sqcheck_subheader_left', [ $this, 'render_subheader_tabs' ] );
        add_action( 'sqcheck_subheader_right', [ $this, 'render_subheader_controls' ] );
    } // End __construct()


    /**
     * Audit type => label, derived from the registered audit types.
     *
     * @return array
     */
    public static function get_audit_tabs() : array {
        $tabs = [];

        foreach ( Audits::get_types() as $type => $config ) {
            $tabs[ $type ] = $config[ 'label' ];
        }

        return $tabs;
    } // End get_audit_tabs()


    /**
     * Get an audit type's description.
     *
     * @param string $audit_type
     * @return string
     */
    private static function get_audit_description( string $audit_type ) : string {
        $types = Audits::get_types();

        return $types[ $audit_type ][ 'description' ] ?? '';
    } // End get_audit_description()


    /**
     * Get the current audit tab, from $_GET, falling back to the user's last-viewed, then default.
     *
     * @return string
     */
    public static function get_current_tab() : string {
        $tabs = array_keys( self::get_audit_tabs() );

        if ( empty( $tabs ) ) {
            return '';
        }

        if ( isset( $_GET[ 'audit' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector, no state change.
            $tab = sanitize_key( wp_unslash( $_GET[ 'audit' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if ( in_array( $tab, $tabs, true ) ) {
                update_user_meta( get_current_user_id(), 'sqcheck_last_audit_tab', $tab );
                return $tab;
            }
        }

        $last = get_user_meta( get_current_user_id(), 'sqcheck_last_audit_tab', true );

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
            <a href="<?php echo esc_url( add_query_arg( 'audit', $slug, remove_query_arg( 'sqcheck_view' ) ) ); ?>" class="sqcheck-button <?php echo $current_tab === $slug ? '' : 'button-secondary'; ?>"><?php echo esc_html( $label ); ?></a>
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

        if ( '' === $current_tab ) {
            return;
        }

        $showing_omitted = isset( $_GET[ 'sqcheck_view' ] ) && 'omitted' === $_GET[ 'sqcheck_view' ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
        $omitted_count = count( Audits::get_results( $current_tab, true ) );
        ?>
        <span id="sqcheck-audit-last-checked" data-audit-type="<?php echo esc_attr( $current_tab ); ?>">
            <?php
            /* translators: %s: date the audit was last checked */
            echo esc_html( sprintf( __( 'Last checked: %s', 'site-quality-check' ), Audits::get_last_checked_display( $current_tab ) ) );
            ?>
        </span>

        <button type="button" class="sqcheck-button sqcheck-refresh-audit" id="sqcheck-refresh-audit" data-audit-type="<?php echo esc_attr( $current_tab ); ?>" title="<?php esc_attr_e( 'Rescan', 'site-quality-check' ); ?>">
            <span class="dashicons dashicons-update"></span>
        </button>

        <?php if ( $showing_omitted ) : ?>
            <a href="<?php echo esc_url( remove_query_arg( 'sqcheck_view' ) ); ?>" class="sqcheck-button"><?php esc_html_e( 'Show Active', 'site-quality-check' ); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url( add_query_arg( 'sqcheck_view', 'omitted' ) ); ?>" class="sqcheck-button"><?php echo esc_html( sprintf(
                /* translators: %d: number of omitted items */
                __( 'Show Omitted (%d)', 'site-quality-check' ),
                $omitted_count
            ) ); ?></a>
        <?php endif; ?>
        <?php
    } // End render_subheader_controls()


    /**
     * Details column renderer for a given audit type, gathered via filter so
     * integrations can register renderers for their own audit types.
     *
     * @param string $audit_type
     * @return callable
     */
    private static function get_details_renderer( string $audit_type ) : callable {
        $renderers = apply_filters( 'sqcheck_audit_details_renderers', [
            'orphaned' => function ( $details ) {
                return '—';
            },
            'alt_text' => function ( $details ) {
                $labels = [ 'featured' => __( 'Featured Image', 'site-quality-check' ), 'content' => __( 'In Content', 'site-quality-check' ) ];
                $out = [];

                foreach ( $details[ 'images' ] ?? [] as $image ) {
                    $src = $image[ 'src' ] ?? '';
                    $source_label = $labels[ $image[ 'source' ] ?? '' ] ?? '';

                    $out[] = '<span class="sqcheck-alt-thumb-row"><img src="' . esc_url( $src ) . '" class="sqcheck-alt-thumb sqcheck-alt-thumb-clickable" data-full-src="' . esc_url( $src ) . '" alt=""><span class="sqcheck-alt-thumb-label">' . esc_html( $source_label ) . '</span></span>';
                }

                return implode( '', $out );
            },
            'mixed_content' => function ( $details ) {
                return esc_html( implode( ', ', $details[ 'urls' ] ?? [] ) );
            },
        ] );

        return $renderers[ $audit_type ] ?? function () {
            return '—';
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
            'sqcheck-content-audits',
            Bootstrap::url() . 'inc/js/content-audits.js',
            [ 'jquery' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqcheck-content-audits', 'sqcheckAudits', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sqcheck_audits_nonce' ),
            'i18n'    => [
                'scanning' => __( 'Scanning:', 'site-quality-check' )
            ],
        ] );

        $current_tab = self::get_current_tab();

        if ( '' === $current_tab ) {
            ?>
            <div class="wrap sqcheck-content-wrap sqcheck-content-audits">
                <div class="sqcheck-box"><div class="sqcheck-box-body">
                    <p><?php esc_html_e( 'No content audits are currently available.', 'site-quality-check' ); ?></p>
                </div></div>
            </div>
            <?php
            return;
        }

        $showing_omitted = isset( $_GET[ 'sqcheck_view' ] ) && 'omitted' === $_GET[ 'sqcheck_view' ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.

        $table = new ContentAuditsListTable( $current_tab, self::get_details_renderer( $current_tab ) );
        $table->prepare_items();
        ?>
        <div class="wrap sqcheck-content-wrap sqcheck-content-audits">
            <p><?php echo esc_html( self::get_audit_description( $current_tab ) ); ?></p>

            <?php if ( $showing_omitted ) : ?>
                <div class="sqcheck-omitted-banner">
                    <span class="dashicons dashicons-hidden"></span>
                    <?php esc_html_e( 'Showing omitted items — these are excluded from the active audit above.', 'site-quality-check' ); ?>
                </div>
            <?php endif; ?>

            <div id="sqcheck-audit-scanning-status" style="display:none;"></div>
            <div class="sqcheck-box" id="sqcheck-audit-results-box">
                <div class="sqcheck-box-body">
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

            <div id="sqcheck-image-modal" class="sqcheck-modal" style="display:none;">
                <div class="sqcheck-modal-overlay"></div>
                <div class="sqcheck-modal-content">
                    <button type="button" class="sqcheck-modal-close" aria-label="<?php esc_attr_e( 'Close', 'site-quality-check' ); ?>">&times;</button>
                    <img src="" alt="" id="sqcheck-modal-image">
                    <p class="sqcheck-modal-url"><a href="" target="_blank" rel="noopener noreferrer" id="sqcheck-modal-link"></a></p>
                </div>
            </div>
        </div>
        <?php
    } // End render_page()

} // End class ContentAudits

ContentAudits::instance();