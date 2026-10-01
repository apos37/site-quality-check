<?php
/**
 * PER PAGE
 *
 * User-level items per page setting shared by the plugin's list tables.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class PerPage {

    /**
     * @var PerPage|null Singleton instance
     */
    private static ?PerPage $instance = null;


    /**
     * User meta key, query arg and nonce action.
     */
    public const KEY = 'sqcheck_per_page';


    /**
     * Nonce query arg.
     */
    public const NONCE_ARG = 'sqcheck_per_page_nonce';


    /**
     * Available choices.
     */
    public const CHOICES = [ 10, 20, 50, 100 ];


    /**
     * Fallback value.
     */
    public const DEFAULT_PER_PAGE = 20;


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
        add_action( 'admin_init', [ $this, 'maybe_save' ] );
    } // End __construct()


    /**
     * Get the current user's per page value.
     *
     * @return int
     */
    public static function get() : int {
        $saved = (int) get_user_meta( get_current_user_id(), self::KEY, true );

        return in_array( $saved, self::CHOICES, true ) ? $saved : self::DEFAULT_PER_PAGE;
    } // End get()


    /**
     * Save the chosen value, then redirect to a clean URL.
     *
     * @return void
     */
    public function maybe_save() : void {
        if ( ! isset( $_GET[ self::KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
            return;
        }

        $page = sanitize_key( wp_unslash( $_GET[ 'page' ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.

        if ( ! in_array( $page, [ Menu::MENU_SLUG . '-content-audits', Menu::MENU_SLUG . '-stale-content' ], true ) || ! Access::can_access() ) {
            return;
        }

        check_admin_referer( self::KEY, self::NONCE_ARG );

        $value = absint( wp_unslash( $_GET[ self::KEY ] ) );

        if ( in_array( $value, self::CHOICES, true ) ) {
            update_user_meta( get_current_user_id(), self::KEY, $value );
        }

        wp_safe_redirect( remove_query_arg( [ self::KEY, self::NONCE_ARG, 'paged', 'action', 'action2', '_wpnonce', '_wp_http_referer', 'result_ids', 'post_ids' ] ) );
        exit;
    } // End maybe_save()


    /**
     * Render the per page dropdown inside a list table's tablenav.
     *
     * @param string $which
     * @return void
     */
    public static function render_field( string $which ) : void {
        self::enqueue();

        $current = self::get();
        $id = 'sqcheck-per-page-' . $which;
        ?>
        <div class="alignleft actions sqcheck-per-page-wrap">
            <label for="<?php echo esc_attr( $id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Items per page', 'site-quality-check' ); ?></label>
            <select id="<?php echo esc_attr( $id ); ?>" class="sqcheck-per-page">
                <?php foreach ( self::CHOICES as $choice ) : ?>
                    <option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $current, $choice ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of items */ __( '%d per page', 'site-quality-check' ), $choice ) ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    } // End render_field()


    /**
     * Enqueue the change handler once per request.
     *
     * @return void
     */
    private static function enqueue() : void {
        static $done = false;

        if ( $done ) {
            return;
        }

        $done = true;

        wp_enqueue_script(
            'sqcheck-per-page',
            Bootstrap::url() . 'inc/js/per-page.js',
            [ 'jquery' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqcheck-per-page', 'sqcheckPerPage', [
            'key'      => self::KEY,
            'nonceArg' => self::NONCE_ARG,
            'nonce'    => wp_create_nonce( self::KEY ),
        ] );
    } // End enqueue()

} // End class PerPage

PerPage::instance();