<?php
/**
 * INTEGRATION: PLUGINRX CONTROL CENTER
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the PluginRx Control Center plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'pluginrx-control-center' ] = [
        'name'        => 'PluginRx Control Center',
        'author'      => 'PluginRx',
        'file'        => 'pluginrx-control-center/pluginrx-control-center.php',
        'url'         => 'https://pluginrx.com/plugin/pluginrx-control-center/',
        'description' => __( 'Manage multiple sites from one dashboard. Requires PluginRx Agent to be installed on each connected site.', 'site-quality-check' ),
        'integration' => __( 'View checklist completion and stale content counts across all connected sites.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/pluginrx-control-center/logo.png',
        'wp_repo'     => false,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'pluginrx-control-center/pluginrx-control-center.php' ) ) {
    return;
}