<?php
/**
 * PLUGIN PAGE
 *
 * Adds row meta links (Docs, Support, Guide) under the plugin name
 * on the Plugins screen.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class PluginPage {

    /**
     * @var PluginPage|null Singleton instance
     */
    private static ?PluginPage $instance = null;


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
        add_filter( 'plugin_row_meta', [ $this, 'add_row_meta' ], 10, 2 );
        add_filter( 'plugin_action_links_' . plugin_basename( Bootstrap::file() ), [ $this, 'add_action_links' ] );
    } // End __construct()


    /**
     * Add Guide, Docs, and Support links to the plugin's row meta.
     *
     * @param array $links
     * @param string $plugin_file
     * @return array
     */
    public function add_row_meta( array $links, string $plugin_file ) : array {
        if ( plugin_basename( Bootstrap::file() ) !== $plugin_file ) {
            return $links;
        }

        $links[] = '<a href="https://pluginrx.com/guide/plugin/site-quality-check/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How-To Guide', 'site-quality-check' ) . '</a>';
        $links[] = '<a href="https://pluginrx.com/docs/plugin/site-quality-check/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Developer Docs', 'site-quality-check' ) . '</a>';
        $links[] = '<a href="https://pluginrx.com/support/plugin/site-quality-check/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'site-quality-check' ) . '</a>';

        return $links;
    } // End add_row_meta()


    /**
     * Add a Settings link to the plugin's action links.
     *
     * @param array $links
     * @return array
     */
    public function add_action_links( array $links ) : array {
        $settings_url = admin_url( 'admin.php?page=' . Menu::MENU_SLUG . '-settings' );
        $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'site-quality-check' ) . '</a>';

        array_unshift( $links, $settings_link );

        return $links;
    } // End add_action_links()

} // End class PluginPage

PluginPage::instance();