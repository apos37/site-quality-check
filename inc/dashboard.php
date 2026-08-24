<?php
/**
 * DASHBOARD
 *
 * Registers the dashboard hook contract and renders registered widgets.
 * Third-party developers can hook into 'sqc_dashboard_widgets' to add their own.
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
        // Widget files hook themselves in on 'plugins_loaded' via Bootstrap::load_dashboard_widgets().
    } // End __construct()


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
         * Filter: sqc_dashboard_widgets
         *
         * Register a dashboard widget on the Site Quality Check dashboard.
         *
         * @param array $widgets Array of widget definitions, keyed by slug.
         *
         * Example:
         *
         * add_filter( 'sqc_dashboard_widgets', function ( $widgets ) {
         *     $widgets[ 'my_widget' ] = [
         *         'title'    => __( 'My Widget', 'my-textdomain' ),
         *         'priority' => 15,
         *         'callback' => 'my_widget_render_function',
         *     ];
         *     return $widgets;
         * } );
         */
        $widgets = apply_filters( 'sqc_dashboard_widgets', [] );

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


    /**
     * Render the dashboard page: all registered widgets in a grid.
     *
     * @return void
     */
    public static function render() : void {
        $widgets = self::get_widgets();
        wp_enqueue_style(
            'sqc-dashboard',
            Bootstrap::url() . 'inc/css/dashboard.css',
            [ 'sqc-theme' ],
            Bootstrap::script_version()
        );
        ?>
        <div class="wrap sqc-content-wrap sqc-dashboard">
            <div class="sqc-dashboard-grid">
                <?php foreach ( $widgets as $slug => $widget ) : ?>
                    <div class="sqc-widget sqc-widget-<?php echo esc_attr( $slug ); ?>">
                        <h2><?php echo esc_html( $widget[ 'title' ] ); ?></h2>
                        <div class="sqc-widget-body">
                            <?php call_user_func( $widget[ 'callback' ] ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End render()

} // End class Dashboard

Dashboard::instance();