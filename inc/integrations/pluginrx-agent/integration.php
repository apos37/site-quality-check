<?php
/**
 * INTEGRATION: PLUGINRX AGENT
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the PluginRx Agent plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'pluginrx-agent' ] = [
        'name'        => 'PluginRx Agent',
        'author'      => 'PluginRx',
        'file'        => 'pluginrx-agent/pluginrx-agent.php',
        'url'         => 'https://pluginrx.com/plugin/pluginrx-agent/',
        'description' => __( 'Required on each site managed by Control Center.', 'site-quality-check' ),
        'integration' => __( 'Exposes this site\'s quality check data to your Control Center.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/pluginrx-agent/logo.png',
        'wp_repo'     => false,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'pluginrx-agent/pluginrx-agent.php' ) ) {
    return;
}