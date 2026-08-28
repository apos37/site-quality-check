<?php
/**
 * INTEGRATION: WCAG ADMIN ACCESSIBILITY TOOLS
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the WCAG Admin Accessibility Tools plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'wcag-admin-accessibility-tools' ] = [
        'name'        => 'WCAG Admin Accessibility Tools',
        'author'      => 'PluginRx',
        'file'        => 'wcag-admin-accessibility-tools/wcag-admin-accessibility-tools.php',
        'url'         => 'https://wordpress.org/plugins/wcag-admin-accessibility-tools/',
        'description' => __( 'Accessibility auditing tools for wp-admin.', 'site-quality-check' ),
        'integration' => __( 'Complements the Missing Alt Text audit — check the current page for a fuller accessibility scan while browsing your site.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/wcag-admin-accessibility-tools/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'wcag-admin-accessibility-tools/wcag-admin-accessibility-tools.php' ) ) {
    return;
}