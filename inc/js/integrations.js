/**
 * SITE QUALITY CHECK — INTEGRATIONS
 *
 * One-click plugin install/activate using WordPress's built-in wp.updates API.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {

        $( document ).on( 'click', '.sqcheck-install-plugin', function () {
            var button = $( this );
            var slug = button.data( 'slug' );
            var installedFile = button.data( 'installed-file' );

            if ( button.prop( 'disabled' ) ) {
                return;
            }

            button.prop( 'disabled', true ).addClass( 'sqcheck-button-loading' ).html( '<span class="dashicons dashicons-update spin"></span> Installing...' );

            wp.updates.installPlugin( {
                slug: slug,
                success: function () {
                    var footer = button.closest( '.sqcheck-integration-card-footer' );
                    footer.html( '<button type="button" class="sqcheck-button sqcheck-button-wp-blue sqcheck-activate-plugin" data-file="' + installedFile + '">Activate</button>' );
                },
                error: function ( errorResponse ) {
                    button.prop( 'disabled', false ).removeClass( 'sqcheck-button-loading' ).text( 'Install Failed' );
                    window.alert( errorResponse.errorMessage || 'Installation failed.' );
                }
            } );
        } );

        $( document ).on( 'click', '.sqcheck-activate-plugin', function () {
            var button = $( this );
            var pluginFile = button.data( 'file' );

            if ( button.prop( 'disabled' ) ) {
                return;
            }

            button.prop( 'disabled', true ).addClass( 'sqcheck-button-loading' ).html( '<span class="dashicons dashicons-update spin"></span> Activating...' );

            $.post( sqcheckIntegrations.ajaxUrl, {
                action: 'sqcheck_activate_plugin',
                nonce: sqcheckIntegrations.nonce,
                plugin_file: pluginFile
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    button.prop( 'disabled', false ).removeClass( 'sqcheck-button-loading' ).text( 'Activate' );
                    window.alert( response.data || 'Activation failed.' );
                    return;
                }

                var footer = button.closest( '.sqcheck-integration-card-footer' );
                footer.html( '<span class="sqcheck-badge sqcheck-badge-success"><span class="dashicons dashicons-yes"></span> Active</span>' );
            } );
        } );

    } );

} )( jQuery );