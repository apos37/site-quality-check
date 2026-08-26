<?php
/**
 * INTEGRATIONS
 *
 * Passive detection of related PluginRx plugins (and Broken Link Notifier),
 * plus the Integrations admin page listing install links and descriptions.
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
     * Config list of related plugins for the Integrations page.
     *
     * Each entry: slug, name, description, how it integrates, detection callback, plugin file (for install/activate links).
     *
     * @return array
     */
    public static function get_plugin_list() : array {
        $plugins = [
            'pluginrx-control-center' => [
                'name'        => 'PluginRx Control Center',
                'file'        => 'pluginrx-control-center/pluginrx-control-center.php',
                'url'         => 'https://pluginrx.com/plugin/pluginrx-control-center/',
                'description' => __( 'Manage multiple sites from one dashboard. Requires PluginRx Agent to be installed on each connected site.', 'site-quality-check' ),
                'integration' => __( 'View checklist completion and stale content counts across all connected sites.', 'site-quality-check' ),
                'logo'        => Bootstrap::url() . 'inc/img/pluginrx-control-center.png',
                'wp_repo'     => false,
            ],
            'pluginrx-agent' => [
                'name'        => 'PluginRx Agent',
                'file'        => 'pluginrx-agent/pluginrx-agent.php',
                'url'         => 'https://pluginrx.com/plugin/pluginrx-agent/',
                'description' => __( 'Required on each site managed by Control Center.', 'site-quality-check' ),
                'integration' => __( 'Exposes this site\'s quality check data to your Control Center.', 'site-quality-check' ),
                'logo'        => Bootstrap::url() . 'inc/img/pluginrx-agent.png',
                'wp_repo'     => false,
            ],
            'admin-help-docs' => [
                'name'        => 'Admin Help Docs',
                'file'        => 'admin-help-docs/admin-help-docs.php',
                'url'         => 'https://wordpress.org/plugins/admin-help-docs/',
                'description' => __( 'Add documentation directly inside wp-admin.', 'site-quality-check' ),
                'integration' => __( 'Shares its color theme with Site Quality Check for a matching look.', 'site-quality-check' ),
                'logo'        => Bootstrap::url() . 'inc/img/admin-help-docs.png',
                'wp_repo'     => true,
            ],
            'broken-link-notifier' => [
                'name'        => 'Broken Link Notifier',
                'file'        => 'broken-link-notifier/broken-link-notifier.php',
                'url'         => 'https://wordpress.org/plugins/broken-link-notifier/',
                'description' => __( 'Scans and reports broken links.', 'site-quality-check' ),
                'integration' => __( 'Adds a broken links widget to your dashboard with a live count.', 'site-quality-check' ),
                'logo'        => Bootstrap::url() . 'inc/img/broken-link-notifier.png',
                'wp_repo'     => true,
            ],
            'clear-cache-everywhere' => [
                'name'        => 'Clear Cache Everywhere',
                'file'        => 'clear-cache-everywhere/clear-cache-everywhere.php',
                'url'         => 'https://wordpress.org/plugins/clear-cache-everywhere/',
                'description' => __( 'One-click cache clearing across common host/plugin caches.', 'site-quality-check' ),
                'integration' => __( 'Recommended after making bulk content updates from your checklist. Adds a clear cache button to the dashboard.', 'site-quality-check' ),
                'logo'        => Bootstrap::url() . 'inc/img/clear-cache-everywhere.png',
                'wp_repo'     => true,
            ],
            // 'wcag-admin-accessibility-tools' => [
            //     'name'        => 'WCAG Admin Accessibility Tools',
            //     'file'        => 'wcag-admin-accessibility-tools/wcag-admin-accessibility-tools.php',
            //     'url'         => 'https://wordpress.org/plugins/wcag-admin-accessibility-tools/',
            //     'description' => __( 'Accessibility auditing tools for wp-admin.', 'site-quality-check' ),
            //     'integration' => __( 'Complements the image alt-text audit with fuller WCAG checks.', 'site-quality-check' ),
            //     'logo'        => Bootstrap::url() . 'inc/img/wcag-admin-accessibility-tools.png',
            //     'wp_repo'     => true,
            // ],
            // 'fake-user-detector' => [
            //     'name'        => 'Fake User Detector',
            //     'file'        => 'fake-user-detector/fake-user-detector.php',
            //     'url'         => 'https://wordpress.org/plugins/fake-user-detector/',
            //     'description' => __( 'Flags likely fake or spam user registrations.', 'site-quality-check' ),
            //     'integration' => __( 'No data integration currently — reserved for future use.', 'site-quality-check' ),
            //     'logo'        => Bootstrap::url() . 'inc/img/fake-user-detector.png',
            //     'wp_repo'     => true,
            // ],
            // 'eri-file-library' => [
            //     'name'        => 'ERI File Library',
            //     'file'        => 'eri-file-library/eri-file-library.php',
            //     'url'         => 'https://wordpress.org/plugins/eri-file-library/',
            //     'description' => __( 'Centralized file/document library management.', 'site-quality-check' ),
            //     'integration' => __( 'No data integration currently — reserved for future use.', 'site-quality-check' ),
            //     'logo'        => Bootstrap::url() . 'inc/img/eri-file-library.png',
            //     'wp_repo'     => true,
            // ],
            // 'dev-debug-tools' => [
            //     'name'        => 'Developer Debug Tools',
            //     'file'        => 'dev-debug-tools/dev-debug-tools.php',
            //     'url'         => 'https://wordpress.org/plugins/dev-debug-tools/',
            //     'description' => __( 'Debug log viewer and developer utilities.', 'site-quality-check' ),
            //     'integration' => __( 'Enables test mode, refreshing cached assets on every page load for easier development.', 'site-quality-check' ),
            //     'logo'        => Bootstrap::url() . 'inc/img/dev-debug-tools.png',
            //     'wp_repo'     => true,
            // ],
        ];

        /**
         * Filter: sqcheck_integration_plugins
         *
         * Add your own plugin to the Site Quality Check Integrations page.
         *
         * @param array $plugins Array of plugin definitions, keyed by slug.
         *
         * Example:
         *
         * add_filter( 'sqcheck_integration_plugins', function ( $plugins ) {
         *     $plugins[ 'my-plugin' ] = [
         *         'name'        => 'My Plugin',
         *         'file'        => 'my-plugin/my-plugin.php',
         *         'url'         => 'https://example.com/my-plugin/',
         *         'description' => 'What it does.',
         *         'integration' => 'How it integrates with Site Quality Check.',
         *         'wp_repo'     => true,
         *     ];
         *     return $plugins;
         * } );
         */
        return apply_filters( 'sqcheck_integration_plugins', $plugins );
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
     * Check if Broken Link Notifier is active.
     *
     * @return bool
     */
    public static function is_broken_link_notifier_active() : bool {
        return self::is_active( 'broken-link-notifier/broken-link-notifier.php' );
    } // End is_broken_link_notifier_active()


    /**
     * Check if Admin Help Docs is active.
     *
     * @return bool
     */
    public static function is_admin_help_docs_active() : bool {
        return self::is_active( 'admin-help-docs/admin-help-docs.php' );
    } // End is_admin_help_docs_active()


    /**
     * Check if Gravity Forms is active.
     *
     * @return bool
     */
    public static function is_gravity_forms_active() : bool {
        return self::is_active( 'gravityforms/gravityforms.php' );
    } // End is_gravity_forms_active()


    /**
     * Get the current broken link count from Broken Link Notifier, if active.
     *
     * @return int
     */
    public static function get_broken_link_count() : int {
        if ( ! self::is_broken_link_notifier_active() ) {
            return 0;
        }

        return ( new \BLNOTIFIER_HELPERS() )->count_broken_links();
    } // End get_broken_link_count()


    /**
     * Get the admin URL for Broken Link Notifier's results page, if active.
     *
     * @return string|null
     */
    public static function get_broken_link_notifier_url() : ?string {
        if ( ! self::is_broken_link_notifier_active() ) {
            return null;
        }

        return admin_url( 'admin.php?page=broken-link-notifier&tab=results' );
    } // End get_broken_link_notifier_url()


    /**
     * Check if Yoast SEO is active.
     *
     * @return bool
     */
    public static function is_yoast_active() : bool {
        return class_exists( '\WPSEO_Meta' ) || defined( 'WPSEO_VERSION' );
    } // End is_yoast_active()


    /**
     * Get Yoast meta description for a post, falling back to raw postmeta if the object API is unavailable.
     *
     * @param int $post_id
     * @return string
     */
    public static function get_yoast_meta_description( int $post_id ) : string {
        if ( function_exists( 'YoastSEO' ) ) {
            $meta = YoastSEO()->meta->for_post( $post_id );

            if ( $meta && isset( $meta->meta_description ) ) {
                return (string) $meta->meta_description;
            }
        }

        return (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
    } // End get_yoast_meta_description()


    /**
     * Get Yoast SEO title for a post, falling back to raw postmeta if the object API is unavailable.
     *
     * @param int $post_id
     * @return string
     */
    public static function get_yoast_title( int $post_id ) : string {
        if ( function_exists( 'YoastSEO' ) ) {
            $meta = YoastSEO()->meta->for_post( $post_id );

            if ( $meta && isset( $meta->title ) ) {
                return (string) $meta->title;
            }
        }

        return (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
    } // End get_yoast_title()


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
                            <h2><?php echo esc_html( $plugin[ 'name' ] ); ?></h2>
                        </div>
                        <div class="sqcheck-integration-card-body">
                            <p><?php echo esc_html( $plugin[ 'description' ] ); ?></p>
                            <p class="sqcheck-integration-detail"><strong><?php esc_html_e( 'Integration:', 'site-quality-check' ); ?></strong> <?php echo esc_html( $plugin[ 'integration' ] ); ?></p>
                        </div>
                        <div class="sqcheck-integration-card-footer">
                            <?php if ( $is_active ) : ?>
                                <span class="sqcheck-badge sqcheck-badge-success"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Active', 'site-quality-check' ); ?></span>
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
     * Get Admin Help Docs' saved color values, if active.
     *
     * @return array|null Associative array of color key => hex value, or null if not active/not found.
     */
    public static function get_admin_help_docs_colors() : ?array {
        if ( ! self::is_admin_help_docs_active() ) {
            return null;
        }

        $colors = get_option( 'helpdocs_colors', [] );

        return is_array( $colors ) && ! empty( $colors ) ? $colors : null;
    } // End get_admin_help_docs_colors()


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

        $plugin_file = sanitize_text_field( wp_unslash( $_POST[ 'plugin_file' ] ?? '' ) );

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