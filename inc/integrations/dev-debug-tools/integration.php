<?php
/**
 * INTEGRATION: DEVELOPER DEBUG TOOLS
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the Developer Debug Tools plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'dev-debug-tools' ] = [
        'name'        => 'Developer Debug Tools',
        'author'      => 'PluginRx',
        'file'        => 'dev-debug-tools/dev-debug-tools.php',
        'url'         => 'https://wordpress.org/plugins/dev-debug-tools/',
        'description' => __( 'Debug log viewer and developer utilities.', 'site-quality-check' ),
        'integration' => __( 'Enables test mode, refreshing cached assets on every page load for easier development.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/dev-debug-tools/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'dev-debug-tools/dev-debug-tools.php' ) ) {
    return;
}