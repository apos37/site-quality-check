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
        'url'      => admin_url( 'admin.php?page=site-quality-check-checklists' ),
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

    echo '<ul class="sqc-plain-list">';

    foreach ( $checklists as $checklist ) {
        $stats = Checklists::get_completion_stats( $checklist->ID );
        $percent = $stats[ 'percent' ];
        $label = null === $percent ? __( 'Nothing due', 'site-quality-check' ) : $percent . '%';

        echo '<li>';
        echo '<span class="sqc-checklist-row-top"><span>' . esc_html( $checklist->post_title ) . '</span><span class="sqc-checklist-percent">' . esc_html( $label ) . '</span></span>';

        if ( null !== $percent ) {
            echo '<span class="sqc-mini-progress-bar"><span class="sqc-mini-progress-bar-fill" style="width: ' . esc_attr( $percent ) . '%;"></span></span>';
        }

        echo '</li>';
    }

    echo '</ul>';
} // End render_checklists_widget()