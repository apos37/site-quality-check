<?php
/**
 * INTEGRATION: CLEAR CACHE EVERYWHERE
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the Clear Cache Everywhere plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'clear-cache-everywhere' ] = [
        'name'        => 'Clear Cache Everywhere',
        'author'      => 'PluginRx',
        'file'        => 'clear-cache-everywhere/clear-cache-everywhere.php',
        'url'         => 'https://wordpress.org/plugins/clear-cache-everywhere/',
        'description' => __( 'One-click cache clearing across common host/plugin caches.', 'site-quality-check' ),
        'integration' => __( 'Recommended after making bulk content updates from your checklist. Adds a clear cache button to the dashboard.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/clear-cache-everywhere/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'clear-cache-everywhere/clear-cache-everywhere.php' ) ) {
    return;
}


/**
 * Add a quick action to clear all caches.
 */
add_filter( 'sqcheck_quick_actions', function ( array $actions ) : array {
    $actions[ 'clear_cache' ] = [
        'label'     => __( 'Clear All Caches', 'site-quality-check' ),
        'available' => true,
        'callback'  => function () : array {
            if ( ! function_exists( 'cce_clear_all_caches' ) ) {
                return [ 'success' => false, 'message' => __( 'Could not trigger cache clear.', 'site-quality-check' ) ];
            }

            cce_clear_all_caches();

            return [ 'success' => true, 'message' => __( 'All caches cleared.', 'site-quality-check' ) ];
        },
    ];

    return $actions;
} );