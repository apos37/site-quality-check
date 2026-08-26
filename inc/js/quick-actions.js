/**
 * SITE QUALITY CHECK — QUICK ACTIONS
 *
 * Runs a quick action via AJAX and shows the result as a toast.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {
        $( document ).on( 'click', '.sqc-quick-action-btn', function () {
            var button = $( this );
            var action = button.data( 'action' );
            var originalText = button.text();

            button.prop( 'disabled', true ).addClass( 'sqc-button-loading' ).html( '<span class="dashicons dashicons-update spin"></span> ' + sqcQuickActions.i18n.running );

            $.post( sqcQuickActions.ajaxUrl, {
                action: 'sqc_run_quick_action',
                nonce: sqcQuickActions.nonce,
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
                    button.prop( 'disabled', false ).removeClass( 'sqc-button-loading' ).text( originalText );
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
        var toast = $( '#sqc-toast' );

        toast
            .text( message )
            .removeClass( 'sqc-toast-success sqc-toast-error' )
            .addClass( success ? 'sqc-toast-success' : 'sqc-toast-error' )
            .stop( true, true )
            .fadeIn( 150 );

        clearTimeout( toast.data( 'sqc-timeout' ) );

        var timeout = setTimeout( function () {
            toast.fadeOut( 300 );
        }, 6000 );

        toast.data( 'sqc-timeout', timeout );
    } // End showToast()

} )( jQuery );