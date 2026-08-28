<?php
/**
 * INTEGRATION: FAKE USER DETECTOR
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the Fake User Detector plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'fake-user-detector' ] = [
        'name'        => 'Fake User Detector',
        'author'      => 'PluginRx',
        'file'        => 'fake-user-detector/fake-user-detector.php',
        'url'         => 'https://wordpress.org/plugins/fake-user-detector/',
        'description' => __( 'Flags likely fake or spam user registrations.', 'site-quality-check' ),
        'integration' => __( 'Adds a flagged accounts widget to your dashboard.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/fake-user-detector/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'fake-user-detector/fake-user-detector.php' ) ) {
    return;
}