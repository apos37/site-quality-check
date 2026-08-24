<?php
/**
 * DASHBOARD WIDGET: QUICK ACTIONS
 *
 * Buttons to run on-demand checks. Results show as a toast, nothing is stored.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'sqc_dashboard_widgets', function ( array $widgets ) : array {
    if ( ! Access::can_manage() ) {
        return $widgets;
    }

    $widgets[ 'quick_actions' ] = [
        'title'    => __( 'Quick Actions', 'site-quality-check' ),
        'priority' => 40,
        'callback' => __NAMESPACE__ . '\\render_quick_actions_widget',
    ];

    return $widgets;
} );


/**
 * Render the quick actions widget body.
 *
 * @return void
 */
function render_quick_actions_widget() : void {
    $enabled = get_option( 'sqc_enabled_quick_actions', array_keys( QuickActions::get_available_actions() ) );
    $actions = QuickActions::get_available_actions();

    wp_enqueue_script(
        'sqc-quick-actions',
        Bootstrap::url() . 'inc/js/quick-actions.js',
        [ 'jquery' ],
        Bootstrap::version(),
        true
    );

    wp_localize_script( 'sqc-quick-actions', 'sqcQuickActions', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'sqc_quick_actions_nonce' ),
        'i18n'    => [
            'running' => __( 'Running…', 'site-quality-check' ),
        ],
    ] );

    echo '<div class="sqc-quick-actions" id="sqc-quick-actions-toast-target">';

    foreach ( $actions as $slug => $action ) {
        if ( ! $action[ 'available' ] || ! in_array( $slug, $enabled, true ) ) {
            continue;
        }

        echo '<button type="button" class="sqc-button sqc-quick-action-btn" data-action="' . esc_attr( $slug ) . '">' . esc_html( $action[ 'label' ] ) . '</button>';
    }

    echo '</div>';
    echo '<div class="sqc-toast" id="sqc-toast" style="display:none;"></div>';
} // End render_quick_actions_widget()