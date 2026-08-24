/**
 * SITE QUALITY CHECK — STALE CONTENT
 *
 * Handles the omit button on the stale content list.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {
        $( document ).on( 'click', '.sqc-omit-post', function () {
            var button = $( this );
            var postId = button.data( 'post-id' );
            var row = button.closest( 'tr' );

            $.post( sqcStaleContent.ajaxUrl, {
                action: 'sqc_omit_stale_post',
                nonce: sqcStaleContent.nonce,
                post_id: postId
            } )
                .done( function ( response ) {
                    if ( ! response.success ) {
                        window.alert( response.data && response.data.message ? response.data.message : 'Error' );
                        return;
                    }

                    row.fadeOut( 200, function () {
                        $( this ).remove();
                    } );
                } )
                .fail( function () {
                    window.alert( 'Request failed.' );
                } );
        } );
    } );

} )( jQuery );