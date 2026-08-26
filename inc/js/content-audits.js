/**
 * SITE QUALITY CHECK — CONTENT AUDITS
 *
 * Chunked AJAX scanning with a spinning refresh icon and status message,
 * plus omit/un-omit row actions.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {

        $( document ).on( 'click', '#sqcheck-refresh-audit', function () {
            var button = $( this );
            var auditType = button.data( 'audit-type' );
            var icon = button.find( '.dashicons' );

            if ( icon.hasClass( 'spin' ) ) {
                return;
            }

            icon.addClass( 'spin' );
            $( '#sqcheck-audit-results-box' ).hide();
            $( '#sqcheck-audit-scanning-status' ).show().text( sqcheckAudits.i18n.scanning + ' ...' );

            runScan( auditType, 0 );
        } );

        function runScan( auditType, offset ) {
            $.post( sqcheckAudits.ajaxUrl, {
                action: 'sqcheck_scan_chunk',
                nonce: sqcheckAudits.nonce,
                audit_type: auditType,
                offset: offset
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( 'Scan failed.' );
                    $( '#sqcheck-refresh-audit .dashicons' ).removeClass( 'spin' );
                    return;
                }

                var data = response.data;

                $( '#sqcheck-audit-scanning-status' ).text( sqcheckAudits.i18n.scanning + ' ' + data.last_title + ' (' + data.offset + '/' + data.total + ')' );

                if ( data.done ) {
                    $( '#sqcheck-refresh-audit .dashicons' ).removeClass( 'spin' );
                    window.location.reload();
                } else {
                    runScan( auditType, data.offset );
                }
            } );
        } // End runScan()

        $( document ).on( 'click', 'a.sqcheck-omit-result, a.sqcheck-unomit-result', function ( e ) {
            e.preventDefault();

            var link = $( this );
            var isOmit = link.hasClass( 'sqcheck-omit-result' );
            var id = link.data( 'id' );
            var row = link.closest( 'tr' );

            $.post( sqcheckAudits.ajaxUrl, {
                action: isOmit ? 'sqcheck_omit_audit_result' : 'sqcheck_unomit_audit_result',
                nonce: sqcheckAudits.nonce,
                id: id
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( 'Error' );
                    return;
                }

                row.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );

        $( document ).on( 'click', '.sqcheck-alt-thumb-clickable', function () {
            var src = $( this ).data( 'full-src' );

            $( '#sqcheck-modal-image' ).attr( 'src', src );
            $( '#sqcheck-modal-link' ).attr( 'href', src ).text( src );
            $( '#sqcheck-image-modal' ).css( 'display', 'flex' ).hide().fadeIn( 150 );
        } );

        $( document ).on( 'click', '.sqcheck-modal-close, .sqcheck-modal-overlay', function () {
            $( '#sqcheck-image-modal' ).fadeOut( 150 );
        } );

        $( document ).on( 'keydown', function ( e ) {
            if ( 'Escape' === e.key ) {
                $( '#sqcheck-image-modal' ).fadeOut( 150 );
            }
        } );

    } );

} )( jQuery );