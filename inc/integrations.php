<?php
/**
 * INTEGRATIONS
 *
 * Generic plugin detection helpers and the Integrations admin page.
 * All plugin-specific logic lives in inc/integrations/{slug}/integration.php.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Integrations {

    /**
     * @var Integrations|null Singleton instance
     */
    private static ?Integrations $instance = null;


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
        add_action( 'wp_ajax_sqcheck_activate_plugin', [ $this, 'ajax_activate_plugin' ] );
    } // End __construct()


    /**
     * Get the list of plugins to show on the Integrations page. Each active
     * integration in inc/integrations/{slug}/integration.php adds its own
     * entry via this filter.
     *
     * @return array
     */
    public static function get_plugin_list() : array {
        return apply_filters( 'sqcheck_integration_plugins', [] );
    } // End get_plugin_list()


    /**
     * Check if a plugin is active by its main file path (slug/slug.php).
     *
     * @param string $plugin_file
     * @return bool
     */
    public static function is_active( string $plugin_file ) : bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_file );
    } // End is_active()


    /**
     * Check if a plugin is installed (file exists) regardless of activation status.
     *
     * @param string $plugin_file
     * @return bool
     */
    public static function is_installed( string $plugin_file ) : bool {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        return isset( $all_plugins[ $plugin_file ] );
    } // End is_installed()


    /**
     * Render the Integrations admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-quality-check' ) );
        }

        wp_enqueue_style(
            'sqcheck-integrations',
            Bootstrap::url() . 'inc/css/integrations.css',
            [ 'sqcheck-theme' ],
            Bootstrap::script_version()
        );

        wp_enqueue_script( 'updates' );
        wp_enqueue_script(
            'sqcheck-integrations',
            Bootstrap::url() . 'inc/js/integrations.js',
            [ 'jquery', 'updates' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqcheck-integrations', 'sqcheckIntegrations', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sqcheck_integrations_nonce' ),
        ] );

        $plugins = self::get_plugin_list();
        ?>
        <div class="wrap sqcheck-content-wrap sqcheck-integrations">
            <p><?php esc_html_e( 'Site Quality Check works great on its own, but pairs with these plugins to add extra features. Install, activate, or learn more about each one below.', 'site-quality-check' ); ?></p>

            <div class="sqcheck-integrations-list">
                <?php foreach ( $plugins as $slug => $plugin ) : ?>
                    <?php
                    $plugin_file = $plugin[ 'file' ] ?? '';
                    $is_active = self::is_active( $plugin_file );
                    $is_installed = self::is_installed( $plugin_file );
                    ?>
                    <div class="sqcheck-integration-card">
                        <div class="sqcheck-integration-card-header">
                            <?php if ( ! empty( $plugin[ 'logo' ] ) ) : ?>
                                <img src="<?php echo esc_url( $plugin[ 'logo' ] ); ?>" alt="" class="logo">
                            <?php endif; ?>
                            <div class="sqcheck-integration-header-text">
                                <h2><?php echo esc_html( $plugin[ 'name' ] ); ?></h2>
                                <?php if ( ! empty( $plugin[ 'author' ] ) ) : ?>
                                    <p class="sqcheck-integration-author"><?php esc_html_e( 'By', 'site-quality-check' ); ?> <?php echo esc_html( $plugin[ 'author' ] ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sqcheck-integration-card-body">
                            <p><?php echo esc_html( $plugin[ 'description' ] ); ?></p>
                            <p class="sqcheck-integration-detail"><strong><?php esc_html_e( 'Integration:', 'site-quality-check' ); ?></strong> <?php echo esc_html( $plugin[ 'integration' ] ); ?></p>
                        </div>
                        <div class="sqcheck-integration-card-footer">
                            <?php if ( $is_active ) : ?>
                                <span class="sqcheck-badge sqcheck-badge-success"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Active', 'site-quality-check' ); ?></span>

                                <?php if ( isset( $plugin[ 'extra_footer' ] ) && is_callable( $plugin[ 'extra_footer' ] ) ) : ?>
                                    <?php call_user_func( $plugin[ 'extra_footer' ] ); ?>
                                <?php endif; ?>
                            <?php elseif ( $is_installed ) : ?>
                                <button type="button" class="sqcheck-button sqcheck-button-wp-blue sqcheck-activate-plugin" data-file="<?php echo esc_attr( $plugin_file ); ?>"><?php esc_html_e( 'Activate', 'site-quality-check' ); ?></button>
                            <?php elseif ( $plugin[ 'wp_repo' ] ?? false ) : ?>
                                <button type="button" class="sqcheck-button sqcheck-install-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-installed-file="<?php echo esc_attr( $plugin_file ); ?>"><?php esc_html_e( 'Install Now', 'site-quality-check' ); ?></button>
                            <?php else : ?>
                                <a class="sqcheck-button" href="<?php echo esc_url( $plugin[ 'url' ] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Plugin', 'site-quality-check' ); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * AJAX: activate a plugin by its file path.
     *
     * @return void
     */
    public function ajax_activate_plugin() : void {
        check_ajax_referer( 'sqcheck_integrations_nonce', 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'site-quality-check' ) );
        }

        $plugin_file = sanitize_text_field( wp_unslash( $_POST[ 'plugin_file' ] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via check_ajax_referer above.

        if ( ! $plugin_file ) {
            wp_send_json_error( __( 'Missing plugin file.', 'site-quality-check' ) );
        }

        $result = activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success();
    } // End ajax_activate_plugin()

} // End class Integrations

Integrations::instance();