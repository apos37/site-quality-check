<?php
/**
 * Plugin Name:         Site Quality Check
 * Plugin URI:          https://pluginrx.com/plugin/site-quality-check/
 * Description:         Keep every site up to date with checklists, stale content tracking, and automated quality checks.
 * Version:             1.0.0
 * Requires at least:   6.0
 * Tested up to:        7.1
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         site-quality-check
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Created on:          August 21, 2026
 * Premium:             false
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SQC_PLUGIN_FILE', __FILE__ );
define( 'SQC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SQC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 * BOOTSTRAP
 *
 * Loads plugin metadata, performs environment checks, and initializes the plugin.
 */
final class Bootstrap {

    /**
     * Plugin files to load.
     *
     * This array contains the paths to all plugin files that need to be included.
     */
    public const FILES = [
        'helpers.php',
        'access.php',
        'menu.php',
        'plugin-page.php',
        'checklists.php',
        'default-data.php',
        'checklists-ajax.php',
        'stale-content-list-table.php',
        'stale-content.php',
        'audits.php',
        'quick-actions.php',
        'integrations.php',
        'content-audits-list-table.php',
        'content-audits.php',
        'settings.php',
        'advanced.php',
        'dashboard.php',
    ];


    /**
     * Dashboard widget files, loaded separately from FILES so third-party
     * devs can see the pattern clearly in /inc/dashboard-widgets/.
     */
    public const DASHBOARD_WIDGET_FILES = [
        'site-health.php',
        'checklists.php',
        'stale-content.php',
        'broken-links.php',
        'quick-actions.php',
    ];


    /**
     * Plugin header keys for get_file_data()
     */
    public const HEADER_KEYS = [
        'name'         => 'Plugin Name',
        'description'  => 'Description',
        'version'      => 'Version',
        'plugin_uri'   => 'Plugin URI',
        'requires_php' => 'Requires PHP',
        'textdomain'   => 'Text Domain',
        'author'       => 'Author',
        'author_uri'   => 'Author URI',
        'discord_uri'  => 'Discord URI'
    ];


    /**
     * @var array Plugin metadata from file header
     */
    private array $meta;


    /**
     * @var Bootstrap|null Singleton instance
     */
    private static ?Bootstrap $instance = null;


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
        $this->meta = $this->load_meta();
        $this->check_environment();
        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
        add_action( 'plugins_loaded', [ $this, 'load_dashboard_widgets' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    } // End __construct()


    /**
     * Load plugin meta from file header
     *
     * @return array
     */
    private function load_meta() : array {
        return get_file_data( __FILE__, self::HEADER_KEYS );
    } // End load_meta()

    
    /**
     * Get a meta value
     *
     * @param string $key
     * @return string
     */
    public function meta( string $key ) : string {
        return $this->meta[ $key ] ?? '';
    } // End meta()


    /**
     * Get plugin version
     *
     * @return string
     */
    public static function version() : string {
        return self::instance()->meta( 'version' );
    } // End version()


    /**
     * Check if the site is in test mode via Developer Debug Tools.
     *
     * @return bool
     */
    public static function test_mode() : bool {
        return (bool) get_option( 'ddtt_test_mode' );
    } // End test_mode()


    /**
     * Get the version string used for enqueued scripts/styles.
     *
     * @return string
     */
    public static function script_version() : string {
        return self::test_mode() ? (string) time() : self::version();
    } // End script_version()


    /**
     * Get the plugin's base directory path, with trailing slash.
     *
     * @return string
     */
    public static function dir() : string {
        return plugin_dir_path( __FILE__ );
    } // End dir()


    /**
     * Get the plugin's base URL, with trailing slash.
     *
     * @return string
     */
    public static function url() : string {
        return plugin_dir_url( __FILE__ );
    } // End url()


    /**
     * Get the plugin's main file path.
     *
     * @return string
     */
    public static function file() : string {
        return __FILE__;
    } // End file()


    /**
     * Check environment requirements, deactivate if not met
     *
     * @return void
     */
    private function check_environment() : void {
        global $wp_version;

        $requires_php = $this->meta( 'requires_php' );
        if ( $requires_php && version_compare( PHP_VERSION, $requires_php, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_php_version' ] );
            return;
        }
    } // End check_environment()


    /**
     * Admin notice for unsupported PHP version
     *
     * @return void
     */
    public function notice_php_version() : void {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Site Quality Check requires a newer version of PHP. Please contact your host to upgrade.', 'site-quality-check' ) . '</p></div>';
    } // End notice_php_version()


    /**
     * Load plugin files
     *
     * @return void
     */
    public function load_files() : void {
        foreach ( self::FILES as $file ) {
            $path = plugin_dir_path( __FILE__ ) . 'inc/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    } // End load_files()


    /**
     * Load dashboard widget files
     *
     * @return void
     */
    public function load_dashboard_widgets() : void {
        foreach ( self::DASHBOARD_WIDGET_FILES as $file ) {
            $path = plugin_dir_path( __FILE__ ) . 'inc/dashboard-widgets/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    } // End load_dashboard_widgets()


    /**
     * Activation hook
     *
     * @return void
     */
    public function activate() : void {
        if ( ! get_option( 'sqc_activated_time' ) ) {
            update_option( 'sqc_activated_time', time() );
        }

        self::create_tables();

        add_action( 'init', function () {
            do_action( 'sqc_activated' );
        }, 20 );
    } // End activate()


    /**
     * Create custom database tables.
     *
     * @return void
     */
    public static function create_tables() : void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'sqc_audit_results';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            audit_type VARCHAR(50) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            details LONGTEXT NULL,
            omitted TINYINT(1) NOT NULL DEFAULT 0,
            found_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY audit_type (audit_type),
            KEY post_id (post_id),
            KEY omitted (omitted)
        ) {$charset_collate};";

        dbDelta( $sql );
    } // End create_tables()

} // End class Bootstrap

Bootstrap::instance();