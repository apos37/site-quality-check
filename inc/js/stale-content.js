/**
 * SITE QUALITY CHECK — STALE CONTENT
 *
 * Handles the Omit and Un-omit row actions on the stale content list.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {
        $( document ).on( 'click', 'a.sqcheck-omit-post', function ( e ) {
            e.preventDefault();

            var link = $( this );
            var postId = link.data( 'post-id' );
            var row = link.closest( 'tr' );

            $.post( sqcheckStaleContent.ajaxUrl, {
                action: 'sqcheck_omit_stale_post',
                nonce: sqcheckStaleContent.nonce,
                post_id: postId
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( response.data && response.data.message ? response.data.message : 'Error' );
                    return;
                }

                row.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );

        $( document ).on( 'click', 'a.sqcheck-unomit-post', function ( e ) {
            e.preventDefault();

            var link = $( this );
            var postId = link.data( 'post-id' );
            var row = link.closest( 'tr' );

            $.post( sqcheckStaleContent.ajaxUrl, {
                action: 'sqcheck_unomit_stale_post',
                nonce: sqcheckStaleContent.nonce,
                post_id: postId
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( response.data && response.data.message ? response.data.message : 'Error' );
                    return;
                }

                row.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );
    } );

} )( jQuery );