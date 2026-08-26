<?php
/**
 * DASHBOARD WIDGET: CHECKLISTS
 *
 * Shows a quick summary of each checklist tab's completion percentage.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqc_dashboard_widgets', function ( array $widgets ) : array {
    $widgets[ 'checklists' ] = [
        'title'    => __( 'Checklists', 'site-quality-check' ),
        'priority' => 10,
        'callback' => __NAMESPACE__ . '\\render_checklists_widget',
    ];

    return $widgets;
} );


/**
 * Render the checklists widget body.
 *
 * @return void
 */
function render_checklists_widget() : void {
    $checklists = Checklists::get_all();

    if ( empty( $checklists ) ) {
        echo '<p>' . esc_html__( 'No checklists available.', 'site-quality-check' ) . '</p>';
        return;
    }

    echo '<ul class="sqc-checklist-summary-list">';

    foreach ( $checklists as $checklist ) {
        $stats = Checklists::get_completion_stats( $checklist->ID );
        $label = null === $stats[ 'percent' ] ? __( 'Nothing due', 'site-quality-check' ) : $stats[ 'percent' ] . '%';
        $url = admin_url( 'admin.php?page=site-quality-check-checklists&checklist=' . $checklist->ID );

        echo '<li><a href="' . esc_url( $url ) . '" class="sqc-checklist-name">' . esc_html( $checklist->post_title ) . '</a><span class="sqc-checklist-percent">' . esc_html( $label ) . '</span></li>';
    }

    echo '</ul>';
} // End render_checklists_widget()