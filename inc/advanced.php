<?php
/**
 * ADVANCED
 *
 * Export/import settings and checklists as JSON, and reset settings to defaults.
 * Rendered as a box inside Settings::render_page() — this class holds the
 * handler logic only, it has no page of its own.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Advanced {

    /**
     * Current export format version. Bump when the export shape changes,
     * so future versions can migrate older exports.
     */
    public const EXPORT_VERSION = 1;


    /**
     * @var Advanced|null Singleton instance
     */
    private static ?Advanced $instance = null;


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
        add_action( 'admin_post_sqcheck_export', [ $this, 'handle_export' ] );
        add_action( 'admin_post_sqcheck_reset_settings', [ $this, 'handle_reset_settings' ] );
    } // End __construct()


    /**
     * Build the full export payload: settings + checklists.
     *
     * @return array
     */
    private function build_export_payload() : array {
        $checklists = [];

        foreach ( Checklists::get_all() as $checklist ) {
            $checklists[] = [
                'title'    => $checklist->post_title,
                'order'    => $checklist->menu_order,
                'access'   => Checklists::get_access( $checklist->ID ),
                'sections' => Checklists::get_sections( $checklist->ID ),
            ];
        }

        return [
            'export_version' => self::EXPORT_VERSION,
            'exported_at'    => gmdate( 'c' ),
            'plugin_version' => Bootstrap::version(),
            'settings'       => [
                'menu_title'              => get_option( 'sqcheck_menu_title', '' ),
                'page_title'              => get_option( 'sqcheck_page_title', '' ),
                'menu_icon'               => get_option( 'sqcheck_menu_icon', '' ),
                'logo'                    => get_option( 'sqcheck_logo', '' ),
                'allowed_roles'           => get_option( 'sqcheck_allowed_roles', [] ),
                'stale_thresholds'        => get_option( 'sqcheck_stale_thresholds', StaleContent::DEFAULT_THRESHOLDS ),
                'stale_post_types'        => get_option( 'sqcheck_stale_post_types', [ 'post', 'page' ] ),
                'contact_page_id'         => get_option( 'sqcheck_contact_page_id', 0 ),
                'contact_form_id'         => get_option( 'sqcheck_contact_form_id', 0 ),
                'enabled_quick_actions'   => get_option( 'sqcheck_enabled_quick_actions', [] ),
                'clear_data_on_uninstall' => get_option( 'sqcheck_clear_data_on_uninstall', false ),
            ],
            'checklists' => $checklists,
        ];
    } // End build_export_payload()


    /**
     * Handle export request: output JSON file for download.
     *
     * @return void
     */
    public function handle_export() : void {
        check_admin_referer( 'sqcheck_advanced_action' );

        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'site-quality-check' ) );
        }

        $payload = $this->build_export_payload();
        $filename = 'site-quality-check-settings.json';

        nocache_headers();
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
        exit;
    } // End handle_export()


    /**
     * Reset all settings to defaults. Does not touch checklists.
     *
     * @return void
     */
    public function handle_reset_settings() : void {
        check_admin_referer( 'sqcheck_advanced_action' );

        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'site-quality-check' ) );
        }

        delete_option( 'sqcheck_menu_title' );
        delete_option( 'sqcheck_page_title' );
        delete_option( 'sqcheck_menu_icon' );
        delete_option( 'sqcheck_logo' );
        delete_option( 'sqcheck_allowed_roles' );
        delete_option( 'sqcheck_stale_thresholds' );
        delete_option( 'sqcheck_stale_post_types' );
        delete_option( 'sqcheck_contact_page_id' );
        delete_option( 'sqcheck_contact_form_id' );
        delete_option( 'sqcheck_enabled_quick_actions' );
        delete_option( 'sqcheck_clear_data_on_uninstall' );

        $redirect = add_query_arg( 'sqcheck_reset', 'success', admin_url( 'admin.php?page=' . Menu::MENU_SLUG . '-settings' ) );

        wp_safe_redirect( $redirect );
        exit;
    } // End handle_reset_settings()


    /**
     * Render admin notices based on query string results from the handlers above.
     *
     * @return void
     */
    public static function render_notices() : void {
        $messages = [
            'sqcheck_import' => [
                'success'       => [ 'success', __( 'Import completed successfully.', 'site-quality-check' ) ],
                'invalid'       => [ 'error', __( 'The uploaded file is not a valid export.', 'site-quality-check' ) ],
                'no_file'       => [ 'error', __( 'No file was uploaded.', 'site-quality-check' ) ],
                'newer_version' => [ 'error', __( 'This export was created by a newer version of the plugin and cannot be imported.', 'site-quality-check' ) ],
            ],
            'sqcheck_reset' => [
                'success' => [ 'success', __( 'Settings reset to defaults.', 'site-quality-check' ) ],
            ],
        ];

        foreach ( $messages as $param => $options ) {
            if ( ! isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
                continue;
            }

            $key = sanitize_text_field( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.

            if ( ! isset( $options[ $key ] ) ) {
                continue;
            }

            [ $type, $text ] = $options[ $key ];
            ?>
            <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
                <p><?php echo esc_html( $text ); ?></p>
            </div>
            <?php
        }
    } // End render_notices()

} // End class Advanced

Advanced::instance();