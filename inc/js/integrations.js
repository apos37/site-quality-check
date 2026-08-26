/**
 * SITE QUALITY CHECK — INTEGRATIONS
 *
 * One-click plugin install/activate using WordPress's built-in wp.updates API.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {

        $( document ).on( 'click', '.sqc-install-plugin', function () {
            var button = $( this );
            var slug = button.data( 'slug' );
            var installedFile = button.data( 'installed-file' );

            if ( button.prop( 'disabled' ) ) {
                return;
            }

            button.prop( 'disabled', true ).addClass( 'sqc-button-loading' ).html( '<span class="dashicons dashicons-update spin"></span> Installing...' );

            wp.updates.installPlugin( {
                slug: slug,
                success: function () {
                    var footer = button.closest( '.sqc-integration-card-footer' );
                    footer.html( '<button type="button" class="sqc-button sqc-button-wp-blue sqc-activate-plugin" data-file="' + installedFile + '">Activate</button>' );
                },
                error: function ( errorResponse ) {
                    button.prop( 'disabled', false ).removeClass( 'sqc-button-loading' ).text( 'Install Failed' );
                    window.alert( errorResponse.errorMessage || 'Installation failed.' );
                }
            } );
        } );

        $( document ).on( 'click', '.sqc-activate-plugin', function () {
            var button = $( this );
            var pluginFile = button.data( 'file' );

            if ( button.prop( 'disabled' ) ) {
                return;
            }

            button.prop( 'disabled', true ).addClass( 'sqc-button-loading' ).html( '<span class="dashicons dashicons-update spin"></span> Activating...' );

            $.post( sqcIntegrations.ajaxUrl, {
                action: 'sqc_activate_plugin',
                nonce: sqcIntegrations.nonce,
                plugin_file: pluginFile
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    button.prop( 'disabled', false ).removeClass( 'sqc-button-loading' ).text( 'Activate' );
                    window.alert( response.data || 'Activation failed.' );
                    return;
                }

                var footer = button.closest( '.sqc-integration-card-footer' );
                footer.html( '<span class="sqc-badge sqc-badge-success"><span class="dashicons dashicons-yes"></span> Active</span>' );
            } );
        } );

    } );

} )( jQuery );