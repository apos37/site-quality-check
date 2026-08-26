<?php
/**
 * DASHBOARD WIDGET: BROKEN LINKS
 *
 * Passive integration with Broken Link Notifier — shows current count
 * and links to its results page. Hidden entirely if plugin not active.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqcheck_dashboard_widgets', function ( array $widgets ) : array {
    if ( ! Integrations::is_broken_link_notifier_active() ) {
        return $widgets;
    }

    $widgets[ 'broken_links' ] = [
        'title'    => __( 'Broken Links', 'site-quality-check' ),
        'priority' => 30,
        'url'      => Integrations::get_broken_link_notifier_url(),
        'callback' => __NAMESPACE__ . '\\render_broken_links_widget',
    ];

    return $widgets;
} );


/**
 * Render the broken links widget body.
 *
 * @return void
 */
function render_broken_links_widget() : void {
    $count = Integrations::get_broken_link_count();

    echo '<p class="sqcheck-score">' . esc_html( $count ) . '</p>';
    echo '<p>' . esc_html__( 'broken links found', 'site-quality-check' ) . '</p>';
} // End render_broken_links_widget()