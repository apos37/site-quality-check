<?php
/**
 * DASHBOARD WIDGET: CONTENT AUDITS
 *
 * Shows a combined count of findings across all four audit types.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqc_dashboard_widgets', function ( array $widgets ) : array {
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
    $labels = ContentAudits::get_audit_tabs();
    $any_checked = false;
    $total = 0;

    echo '<ul class="sqc-plain-list">';

    foreach ( $labels as $type => $label ) {
        if ( 'seo_meta' === $type && ! Integrations::is_yoast_active() ) {
            continue;
        }

        if ( 'mixed_content' === $type && 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
            continue;
        }

        $last_checked = get_option( 'sqc_audit_last_checked_' . $type, 0 );

        if ( $last_checked ) {
            $any_checked = true;
        }

        $count = count( Audits::get_results( $type, false ) );
        $total += $count;

        $pill_class = 0 === $count ? 'sqc-count-pill-zero' : 'sqc-count-pill-active';

        echo '<li class="sqc-checklist-row-top"><span>' . esc_html( $label ) . '</span><span class="sqc-count-pill ' . esc_attr( $pill_class ) . '">' . esc_html( $count ) . '</span></li>';
    }

    echo '</ul>';

    if ( ! $any_checked ) {
        echo '<p>' . esc_html__( 'No audits have been run yet.', 'site-quality-check' ) . '</p>';
    } elseif ( 0 === $total ) {
        echo '<p>' . esc_html__( 'No issues found.', 'site-quality-check' ) . '</p>';
    }
} // End render_content_audits_widget()