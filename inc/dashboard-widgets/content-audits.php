<?php
/**
 * DASHBOARD WIDGET: CONTENT AUDITS
 *
 * Shows a combined count of findings across all four audit types.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqcheck_dashboard_widgets', function ( array $widgets ) : array {
    $widgets[ 'content_audits' ] = [
        'title'    => __( 'Content Audits', 'site-quality-check' ),
        'priority' => 25,
        'url'      => admin_url( 'admin.php?page=site-quality-check-content-audits' ),
        'callback' => __NAMESPACE__ . '\\render_content_audits_widget',
    ];

    return $widgets;
} );


/**
 * Render the content audits widget body.
 *
 * @return void
 */
function render_content_audits_widget() : void {
    $tabs = ContentAudits::get_audit_tabs();
    $any_checked = false;
    $total = 0;

    if ( empty( $tabs ) ) {
        echo '<p>' . esc_html__( 'No content audits are currently available.', 'site-quality-check' ) . '</p>';
        return;
    }

    echo '<ul class="sqcheck-plain-list">';

    foreach ( $tabs as $type => $label ) {
        $last_checked = get_option( 'sqcheck_audit_last_checked_' . $type, 0 );

        if ( $last_checked ) {
            $any_checked = true;
        }

        $count = count( Audits::get_results( $type, false ) );
        $total += $count;

        $pill_class = 0 === $count ? 'sqcheck-count-pill-zero' : 'sqcheck-count-pill-active';

        echo '<li class="sqcheck-checklist-row-top"><span>' . esc_html( $label ) . '</span><span class="sqcheck-count-pill ' . esc_attr( $pill_class ) . '">' . esc_html( $count ) . '</span></li>';
    }

    echo '</ul>';

    if ( ! $any_checked ) {
        echo '<p>' . esc_html__( 'No audits have been run yet.', 'site-quality-check' ) . '</p>';
    } elseif ( 0 === $total ) {
        echo '<p>' . esc_html__( 'No issues found.', 'site-quality-check' ) . '</p>';
    }
} // End render_content_audits_widget()