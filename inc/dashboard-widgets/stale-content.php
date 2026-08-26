<?php
/**
 * DASHBOARD WIDGET: STALE CONTENT
 *
 * Shows a count of stale content and a link to the full stale content page.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqcheck_dashboard_widgets', function ( array $widgets ) : array {
    $widgets[ 'stale_content' ] = [
        'title'    => __( 'Stale Content', 'site-quality-check' ),
        'priority' => 20,
        'url'      => admin_url( 'admin.php?page=site-quality-check-stale-content' ),
        'callback' => __NAMESPACE__ . '\\render_stale_content_widget',
    ];

    return $widgets;
} );


/**
 * Render the stale content widget body.
 *
 * @return void
 */
function render_stale_content_widget() : void {
    $counts = StaleContent::get_counts();

    echo '<ul class="sqcheck-plain-list">';
    echo '<li><span class="sqcheck-badge sqcheck-badge-warning">' . esc_html( $counts[ 'warning' ] ) . '</span> ' . esc_html__( 'over 6 months', 'site-quality-check' ) . '</li>';
    echo '<li><span class="sqcheck-badge sqcheck-badge-danger">' . esc_html( $counts[ 'danger' ] ) . '</span> ' . esc_html__( 'over 1 year', 'site-quality-check' ) . '</li>';
    echo '<li><span class="sqcheck-badge sqcheck-badge-critical">' . esc_html( $counts[ 'critical' ] ) . '</span> ' . esc_html__( 'over 2 years', 'site-quality-check' ) . '</li>';
    echo '</ul>';
} // End render_stale_content_widget()