/**
 * SITE QUALITY CHECK — QUICK ACTIONS
 *
 * Runs a quick action via AJAX and shows the result as a toast.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {
        $( document ).on( 'click', '.sqcheck-quick-action-btn', function () {
            var button = $( this );
            var action = button.data( 'action' );
            var originalText = button.text();

            button.prop( 'disabled', true ).addClass( 'sqcheck-button-loading' ).html( '<span class="dashicons dashicons-update spin"></span> ' + sqcheckQuickActions.i18n.running );

            $.post( sqcheckQuickActions.ajaxUrl, {
                action: 'sqcheck_run_quick_action',
                nonce: sqcheckQuickActions.nonce,
                quick_action: action
            } )
                .done( function ( response ) {
                    var success = response.success && response.data && response.data.success;
                    var message = response.data && response.data.message ? response.data.message : ( response.success ? 'Done.' : 'Error.' );

                    showToast( message, success );
                } )
                .fail( function () {
                    showToast( 'Request failed.', false );
                } )
                .always( function () {
                    button.prop( 'disabled', false ).removeClass( 'sqcheck-button-loading' ).text( originalText );
                } );
        } );
    } );


    /**
     * Show a toast message near the quick actions widget.
     *
     * @param {string} message
     * @param {boolean} success
     */
    function showToast( message, success ) {
        var toast = $( '#sqcheck-toast' );

        toast
            .text( message )
            .removeClass( 'sqcheck-toast-success sqcheck-toast-error' )
            .addClass( success ? 'sqcheck-toast-success' : 'sqcheck-toast-error' )
            .stop( true, true )
            .fadeIn( 150 );

        clearTimeout( toast.data( 'sqcheck-timeout' ) );

        var timeout = setTimeout( function () {
            toast.fadeOut( 300 );
        }, 6000 );

        toast.data( 'sqcheck-timeout', timeout );
    } // End showToast()

} )( jQuery );