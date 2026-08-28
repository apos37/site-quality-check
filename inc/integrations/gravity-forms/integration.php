<?php
/**
 * INTEGRATION: GRAVITY FORMS
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add Gravity Forms to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'gravityforms' ] = [
        'name'        => 'Gravity Forms',
        'author'      => 'Rocketgenius, Inc.',
        'file'        => 'gravityforms/gravityforms.php',
        'url'         => 'https://www.gravityforms.com/',
        'description' => __( 'Advanced form builder for WordPress.', 'site-quality-check' ),
        'integration' => __( 'Enables the Test Contact Form quick action and a contact form selector in Settings.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/gravity-forms/logo.png',
        'wp_repo'     => false,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'gravityforms/gravityforms.php' ) ) {
    return;
}



/**
 * Register the "Test Contact Form" quick action.
 */
add_filter( 'sqcheck_quick_actions', function ( array $actions ) : array {
    $actions[ 'test_contact_form' ] = [
        'label'     => __( 'Test Contact Form', 'site-quality-check' ),
        'available' => true,
        'callback'  => 'PluginRx\\SiteQualityCheck\\sqcheck_test_gravity_forms_contact_form',
    ];

    return $actions;
} );


/**
 * Register Gravity Forms as a contact form provider for the Settings dropdown.
 */
add_filter( 'sqcheck_contact_form_providers', function ( array $providers ) : array {
    if ( ! class_exists( '\GFAPI' ) ) {
        return $providers;
    }

    $forms = [];

    foreach ( \GFAPI::get_forms() as $form ) {
        $forms[ $form[ 'id' ] ] = $form[ 'title' ];
    }

    $providers[ 'gravityforms' ] = [
        'forms' => $forms,
    ];

    return $providers;
} );


/**
 * Quick action callback: validate contact form notification routing.
 *
 * @return array
 */
function sqcheck_test_gravity_forms_contact_form() : array {
    if ( ! class_exists( '\GFAPI' ) ) {
        return [ 'success' => false, 'message' => __( 'Gravity Forms is not active.', 'site-quality-check' ) ];
    }

    $selected_form_id = (int) get_option( 'sqcheck_contact_form_id', 0 );
    $forms = $selected_form_id ? [ \GFAPI::get_form( $selected_form_id ) ] : \GFAPI::get_forms();
    $forms = array_filter( $forms );

    if ( empty( $forms ) ) {
        return [ 'success' => false, 'message' => __( 'No forms found.', 'site-quality-check' ) ];
    }

    $issues = [];

    foreach ( $forms as $form ) {
        $notifications = $form[ 'notifications' ] ?? [];

        if ( empty( $notifications ) ) {
            $issues[] = sprintf(
                /* translators: %s: form title */
                __( '"%s" has no notifications configured.', 'site-quality-check' ),
                $form[ 'title' ]
            );
            continue;
        }

        foreach ( $notifications as $notification ) {
            $to = $notification[ 'to' ] ?? '';

            if ( '' === trim( $to ) ) {
                $issues[] = sprintf(
                    /* translators: 1: form title, 2: notification name */
                    __( '"%1$s" notification "%2$s" has a blank recipient.', 'site-quality-check' ),
                    $form[ 'title' ],
                    $notification[ 'name' ] ?? ''
                );
            }
        }
    }

    if ( ! empty( $issues ) ) {
        return [ 'success' => false, 'message' => implode( ' ', $issues ) ];
    }

    return [ 'success' => true, 'message' => __( 'All forms have valid notification routing.', 'site-quality-check' ) ];
} // End sqcheck_test_gravity_forms_contact_form()