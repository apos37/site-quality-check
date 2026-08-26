<?php
/**
 * DASHBOARD WIDGET: SITE HEALTH
 *
 * Summary widget combining checklist completion, stale content count,
 * and broken link count into one glanceable score.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqcheck_dashboard_widgets', function ( array $widgets ) : array {
    $widgets[ 'site_health' ] = [
        'title'    => __( 'Site Health', 'site-quality-check' ),
        'priority' => 1,
        'callback' => __NAMESPACE__ . '\\render_site_health_widget',
    ];

    return $widgets;
} );


/**
 * Render the site health widget body.
 *
 * @return void
 */
function render_site_health_widget() : void {
    $checklists = Checklists::get_all();
    $total_complete = 0;
    $total_incomplete = 0;

    foreach ( $checklists as $checklist ) {
        $stats = Checklists::get_completion_stats( $checklist->ID );
        $total_complete += $stats[ 'complete' ];
        $total_incomplete += $stats[ 'incomplete' ];
    }

    $total = $total_complete + $total_incomplete;
    $percent = $total > 0 ? (int) round( ( $total_complete / $total ) * 100 ) : null;

    echo '<div class="sqcheck-site-health-score">';

    if ( null === $percent ) {
        echo '<p>' . esc_html__( 'Nothing due right now.', 'site-quality-check' ) . '</p>';
    } else {
        echo '<p class="sqcheck-score">' . esc_html( $percent ) . '%</p>';

        echo '<div class="sqcheck-progress-bar"><div class="sqcheck-progress-bar-fill" style="width: ' . esc_attr( $percent ) . '%;"></div></div>';

        echo '<p>' . esc_html( sprintf(
            /* translators: 1: completed items, 2: total items */
            __( '%1$d of %2$d checklist items complete', 'site-quality-check' ),
            $total_complete,
            $total
        ) ) . '</p>';
    }

    echo '</div>';
} // End render_site_health_widget()