<?php
/**
 * DASHBOARD
 *
 * Registers the dashboard hook contract and renders registered widgets.
 * Third-party developers can hook into 'sqcheck_dashboard_widgets' to add their own.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Dashboard {

    /**
     * @var Dashboard|null Singleton instance
     */
    private static ?Dashboard $instance = null;


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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    } // End __construct()


    /**
     * Enqueue dashboard-specific CSS, only on the Dashboard screen.
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        $screen = get_current_screen();

        if ( ! $screen || 'toplevel_page_' . Menu::MENU_SLUG !== $screen->id ) {
            return;
        }

        wp_enqueue_style(
            'sqcheck-dashboard',
            Bootstrap::url() . 'inc/css/dashboard.css',
            [ 'sqcheck-theme' ],
            Bootstrap::script_version()
        );
    } // End enqueue_assets()


    /**
     * Render the dashboard page: all registered widgets in a grid.
     *
     * @return void
     */
    public static function render() : void {
        $widgets = self::get_widgets();
        ?>
        <div class="wrap sqcheck-content-wrap sqcheck-dashboard">
            <div class="sqcheck-dashboard-grid">
                <?php foreach ( $widgets as $slug => $widget ) : ?>
                    <div class="sqcheck-widget sqcheck-widget-<?php echo esc_attr( $slug ); ?>">
                        <div class="sqcheck-widget-header">
                            <h2><?php echo esc_html( $widget[ 'title' ] ); ?></h2>
                            <?php if ( ! empty( $widget[ 'url' ] ) ) : ?>
                                <a href="<?php echo esc_url( $widget[ 'url' ] ); ?>" class="sqcheck-widget-goto" title="<?php esc_attr_e( 'View', 'site-quality-check' ); ?>"><span class="dashicons dashicons-external"></span></a>
                            <?php endif; ?>
                        </div>
                        <div class="sqcheck-widget-body">
                            <?php call_user_func( $widget[ 'callback' ] ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End render()


    /**
     * Get all registered widgets, sorted by priority.
     *
     * Widget shape:
     * [
     *     'slug'     => string   Unique identifier.
     *     'title'    => string   Widget heading.
     *     'priority' => int      Lower renders first. Default 10.
     *     'callback' => callable Renders the widget body, echoes HTML directly.
     * ]
     *
     * @return array
     */
    public static function get_widgets() : array {
        /**
         * Filter: sqcheck_dashboard_widgets
         *
         * Register a dashboard widget on the Site Quality Check dashboard.
         *
         * @param array $widgets Array of widget definitions, keyed by slug.
         *
         * Example:
         *
         * add_filter( 'sqcheck_dashboard_widgets', function ( $widgets ) {
         *     $widgets[ 'my_widget' ] = [
         *         'title'    => __( 'My Widget', 'my-textdomain' ),
         *         'priority' => 15,
         *         'callback' => 'my_widget_render_function',
         *     ];
         *     return $widgets;
         * } );
         */
        $widgets = apply_filters( 'sqcheck_dashboard_widgets', [] );

        $widgets = array_filter( $widgets, function ( $widget ) {
            return isset( $widget[ 'title' ], $widget[ 'callback' ] ) && is_callable( $widget[ 'callback' ] );
        } );

        uasort( $widgets, function ( $a, $b ) {
            $priority_a = $a[ 'priority' ] ?? 10;
            $priority_b = $b[ 'priority' ] ?? 10;

            return $priority_a <=> $priority_b;
        } );

        return $widgets;
    } // End get_widgets()

} // End class Dashboard

Dashboard::instance();