<?php
/**
 * DASHBOARD WIDGET: BROKEN LINKS
 *
 * Passive integration with Broken Link Notifier — shows current count
 * and links to its results page. Hidden entirely if plugin not active.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqc_dashboard_widgets', function ( array $widgets ) : array {
    if ( ! Integrations::is_broken_link_notifier_active() ) {
        return $widgets;
    }

    $widgets[ 'broken_links' ] = [
        'title'    => __( 'Broken Links', 'site-quality-check' ),
        'priority' => 30,
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

    echo '<p class="sqc-score">' . esc_html( $count ) . '</p>';
    echo '<p>' . esc_html__( 'broken links found', 'site-quality-check' ) . '</p>';

    $url = Integrations::get_broken_link_notifier_url();

    if ( $url ) {
        echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'View results', 'site-quality-check' ) . '</a></p>';
    }
} // End render_broken_links_widget()