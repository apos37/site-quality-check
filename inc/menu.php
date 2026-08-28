<?php
/**
 * MENU
 *
 * Registers the admin menu and submenu pages, positioned just under the Dashboard.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Menu {

    /**
     * Top-level menu slug.
     */
    public const MENU_SLUG = 'site-quality-check';


    /**
     * Page slug => title, used by the subheader. Populated via register_page_title().
     *
     * @var array
     */
    private static array $page_titles = [];


    /**
     * @var Menu|null Singleton instance
     */
    private static ?Menu $instance = null;


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
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_shared_assets' ] );
        add_action( 'in_admin_header', [ $this, 'maybe_render_header' ] );
    } // End __construct()


    /**
     * Register the top-level menu and submenus.
     *
     * @return void
     */
    public function register_menu() : void {
        if ( ! Access::can_access() ) {
            return;
        }

        $menu_title = get_option( 'sqcheck_menu_title', __( 'Quality Check', 'site-quality-check' ) );
        $page_title = get_option( 'sqcheck_page_title', __( 'Site Quality Check', 'site-quality-check' ) );
        $menu_icon = get_option( 'sqcheck_menu_icon', 'yes-alt' );

        add_menu_page(
            $page_title,
            $menu_title,
            'read',
            self::MENU_SLUG,
            [ $this, 'render_dashboard' ],
            'dashicons-' . $menu_icon,
            2
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'site-quality-check' ),
            __( 'Dashboard', 'site-quality-check' ),
            'read',
            self::MENU_SLUG,
            [ $this, 'render_dashboard' ]
        );
        self::register_page_title( self::MENU_SLUG, __( 'Dashboard', 'site-quality-check' ) );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Checklists', 'site-quality-check' ),
            __( 'Checklists', 'site-quality-check' ),
            'read',
            self::MENU_SLUG . '-checklists',
            [ $this, 'render_checklists' ]
        );
        self::register_page_title( self::MENU_SLUG . '-checklists', __( 'Checklists', 'site-quality-check' ) );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Stale Content', 'site-quality-check' ),
            __( 'Stale Content', 'site-quality-check' ),
            'read',
            self::MENU_SLUG . '-stale-content',
            [ '\PluginRx\SiteQualityCheck\StaleContent', 'render_page' ]
        );
        self::register_page_title( self::MENU_SLUG . '-stale-content', __( 'Stale Content', 'site-quality-check' ) );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Content Audits', 'site-quality-check' ),
            __( 'Content Audits', 'site-quality-check' ),
            'read',
            self::MENU_SLUG . '-content-audits',
            [ '\PluginRx\SiteQualityCheck\ContentAudits', 'render_page' ]
        );
        self::register_page_title( self::MENU_SLUG . '-content-audits', __( 'Content Audits', 'site-quality-check' ) );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Integrations', 'site-quality-check' ),
            __( 'Integrations', 'site-quality-check' ),
            'manage_options',
            self::MENU_SLUG . '-integrations',
            [ '\PluginRx\SiteQualityCheck\Integrations', 'render_page' ]
        );
        self::register_page_title( self::MENU_SLUG . '-integrations', __( 'Integrations', 'site-quality-check' ) );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'site-quality-check' ),
            __( 'Settings', 'site-quality-check' ),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [ '\PluginRx\SiteQualityCheck\Settings', 'render_page' ]
        );
        self::register_page_title( self::MENU_SLUG . '-settings', __( 'Settings', 'site-quality-check' ) );
    } // End register_menu()


    /**
     * Register a page's subheader title. Call this at class-load time (constructors),
     * not inside a render_page() method, since the header renders before page callbacks run.
     *
     * @param string $slug
     * @param string $title
     * @return void
     */
    public static function register_page_title( string $slug, string $title ) : void {
        self::$page_titles[ $slug ] = $title;
    } // End register_page_title()


    /**
     * Render the header on in_admin_header, before any admin notices, but only on our screens.
     *
     * @return void
     */
    public function maybe_render_header() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $active_page = self::get_current_page_slug();

        self::render_header( $active_page );
        self::render_subheader( self::$page_titles[ $active_page ] ?? '', $active_page );
    } // End maybe_render_header()


    /**
     * Determine the current SQCheck admin page slug from $_GET['page'].
     *
     * @return string
     */
    public static function get_current_page_slug() : string {
        return isset( $_GET[ 'page' ] ) ? sanitize_key( wp_unslash( $_GET[ 'page' ] ) ) : self::MENU_SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
    } // End get_current_page_slug()


    /**
     * Render the shared top header: logo, title, and top-level tabs.
     *
     * @param string $active_page
     * @return void
     */
    public static function render_header( string $active_page ) : void {
        $page_title = get_option( 'sqcheck_page_title', __( 'Site Quality Check', 'site-quality-check' ) );
        $logo = get_option( 'sqcheck_logo', '' );

        if ( ! $logo ) {
            $logo = self::get_default_logo();
        }

        $tabs = [
            self::MENU_SLUG                     => __( 'Dashboard', 'site-quality-check' ),
            self::MENU_SLUG . '-checklists'     => __( 'Checklists', 'site-quality-check' ),
            self::MENU_SLUG . '-stale-content'  => __( 'Stale Content', 'site-quality-check' ),
            self::MENU_SLUG . '-content-audits' => __( 'Content Audits', 'site-quality-check' ),
            self::MENU_SLUG . '-integrations'   => __( 'Integrations', 'site-quality-check' ),
        ];

        if ( Access::can_manage() ) {
            $tabs[ self::MENU_SLUG . '-settings' ] = __( 'Settings', 'site-quality-check' );
        }
        ?>
        <div id="sqcheck-header">
            <img src="<?php echo esc_url( $logo ); ?>" alt="" class="logo">

            <div class="title-cont">
                <h1><?php echo esc_html( $page_title ); ?></h1>
            </div>

            <div class="tabs-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <?php
                    $url = admin_url( 'admin.php?page=' . $slug );
                    $class = ( $active_page === $slug ) ? 'sqcheck-tab sqcheck-tab-active' : 'sqcheck-tab';
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End render_header()


    /**
     * Get the default logo URL, used when no custom logo is set in Settings.
     * Falls back to the plugin's own bundled logo; integrations can hook
     * 'sqcheck_default_logo' to provide a better fallback if a compatible
     * companion plugin (with its own branding) is active.
     *
     * @return string
     */
    public static function get_default_logo() : string {
        $default = Bootstrap::url() . 'inc/img/logo.png';

        return apply_filters( 'sqcheck_default_logo', $default );
    } // End get_default_logo()


    /**
     * Render the white subheader bar: page title on the left, action buttons on the right.
     * Fires 'sqcheck_subheader_left' and 'sqcheck_subheader_right' so other code can add buttons.
     *
     * @param string $title
     * @param string $active_page
     * @return void
     */
    public static function render_subheader( string $title, string $active_page ) : void {
        ?>
        <div id="sqcheck-subheader">
            <div class="subheader-left">
                <h2 class="tab-title"><?php echo esc_html( $title ); ?></h2>
                <?php do_action( 'sqcheck_subheader_left', $active_page ); ?>
            </div>
            <div class="subheader-right">
                <?php do_action( 'sqcheck_subheader_right', $active_page ); ?>
            </div>
        </div>
        <?php
    } // End render_subheader()


    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public function render_dashboard() : void {
        Dashboard::render();
    } // End render_dashboard()


    /**
     * Render the checklists page.
     *
     * @return void
     */
    public function render_checklists() : void {
        Checklists::render_page();
    } // End render_checklists()


    /**
     * Enqueue the shared admin stylesheet on any Site Quality Check screen.
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_shared_assets( string $hook ) : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        wp_enqueue_style(
            'sqcheck-theme',
            Bootstrap::url() . 'inc/css/theme.css',
            [],
            Bootstrap::script_version()
        );

        $colors = self::get_theme_colors();

        if ( ! empty( $colors ) ) {
            $declarations = [];

            foreach ( $colors as $var => $value ) {
                $declarations[] = '--sqcheck-color-' . sanitize_key( $var ) . ': ' . sanitize_text_field( $value ) . ';';
            }

            if ( $declarations ) {
                wp_add_inline_style( 'sqcheck-theme', ':root {' . implode( '', $declarations ) . '}' );
            }
        }
    } // End enqueue_shared_assets()


    /**
     * Get theme color overrides. Empty by default; integrations can hook
     * 'sqcheck_theme_colors' to provide overrides matching their own color scheme.
     *
     * Expected keys: header-bg, header-font, header-tab, header-tab-link,
     * accent, button, button-font, button-hover.
     *
     * @return array
     */
    public static function get_theme_colors() : array {
        return apply_filters( 'sqcheck_theme_colors', [] );
    } // End get_theme_colors()

} // End class Menu

Menu::instance();