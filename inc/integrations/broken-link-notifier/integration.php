<?php
/**
 * INTEGRATION: BROKEN LINK NOTIFIER
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the Broken Link Notifier plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'broken-link-notifier' ] = [
        'name'        => 'Broken Link Notifier',
        'author'      => 'PluginRx',
        'file'        => 'broken-link-notifier/broken-link-notifier.php',
        'url'         => 'https://wordpress.org/plugins/broken-link-notifier/',
        'description' => __( 'Scans and reports broken links.', 'site-quality-check' ),
        'integration' => __( 'Adds a broken links widget to your dashboard with a live count.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/broken-link-notifier/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'broken-link-notifier/broken-link-notifier.php' ) ) {
    return;
}


/**
 * Get the current broken link count.
 *
 * @return int
 */
function sqcheck_get_broken_link_count() : int {
    return ( new \BLNOTIFIER_HELPERS() )->count_broken_links();
} // End sqcheck_get_broken_link_count()


/**
 * Get the URL to Broken Link Notifier's results page.
 *
 * @return string
 */
function sqcheck_get_broken_link_notifier_url() : string {
    return admin_url( 'admin.php?page=broken-link-notifier&tab=results' );
} // End sqcheck_get_broken_link_notifier_url()


/**
 * Register the Broken Links dashboard widget.
 */
add_filter( 'sqcheck_dashboard_widgets', function ( array $widgets ) : array {
    $widgets[ 'broken_links' ] = [
        'title'    => __( 'Broken Links', 'site-quality-check' ),
        'priority' => 30,
        'url'      => sqcheck_get_broken_link_notifier_url(),
        'callback' => 'PluginRx\\SiteQualityCheck\\sqcheck_render_broken_links_widget',
    ];

    return $widgets;
} );


/**
 * Render the broken links widget body.
 *
 * @return void
 */
function sqcheck_render_broken_links_widget() : void {
    $count = sqcheck_get_broken_link_count();

    echo '<p class="sqcheck-score">' . esc_html( $count ) . '</p>';
    echo '<p>' . esc_html__( 'broken links found', 'site-quality-check' ) . '</p>';
} // End sqcheck_render_broken_links_widget()