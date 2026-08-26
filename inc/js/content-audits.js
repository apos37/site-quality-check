/**
 * SITE QUALITY CHECK — CONTENT AUDITS
 *
 * Chunked AJAX scanning with a spinning refresh icon and status message,
 * plus omit/un-omit row actions.
 */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {

        $( document ).on( 'click', '#sqc-refresh-audit', function () {
            var button = $( this );
            var auditType = button.data( 'audit-type' );
            var icon = button.find( '.dashicons' );

            if ( icon.hasClass( 'spin' ) ) {
                return;
            }

            icon.addClass( 'spin' );
            $( '#sqc-audit-results-box' ).hide();
            $( '#sqc-audit-scanning-status' ).show().text( sqcAudits.i18n.scanning + ' ...' );

            runScan( auditType, 0 );
        } );

        function runScan( auditType, offset ) {
            $.post( sqcAudits.ajaxUrl, {
                action: 'sqc_scan_chunk',
                nonce: sqcAudits.nonce,
                audit_type: auditType,
                offset: offset
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( 'Scan failed.' );
                    $( '#sqc-refresh-audit .dashicons' ).removeClass( 'spin' );
                    return;
                }

                var data = response.data;

                $( '#sqc-audit-scanning-status' ).text( sqcAudits.i18n.scanning + ' ' + data.last_title + ' (' + data.offset + '/' + data.total + ')' );

                if ( data.done ) {
                    $( '#sqc-refresh-audit .dashicons' ).removeClass( 'spin' );
                    window.location.reload();
                } else {
                    runScan( auditType, data.offset );
                }
            } );
        } // End runScan()

        $( document ).on( 'click', 'a.sqc-omit-result, a.sqc-unomit-result', function ( e ) {
            e.preventDefault();

            var link = $( this );
            var isOmit = link.hasClass( 'sqc-omit-result' );
            var id = link.data( 'id' );
            var row = link.closest( 'tr' );

            $.post( sqcAudits.ajaxUrl, {
                action: isOmit ? 'sqc_omit_audit_result' : 'sqc_unomit_audit_result',
                nonce: sqcAudits.nonce,
                id: id
            } ).done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( 'Error' );
                    return;
                }

                row.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );

        $( document ).on( 'click', '.sqc-alt-thumb-clickable', function () {
            var src = $( this ).data( 'full-src' );

            $( '#sqc-modal-image' ).attr( 'src', src );
            $( '#sqc-modal-link' ).attr( 'href', src ).text( src );
            $( '#sqc-image-modal' ).css( 'display', 'flex' ).hide().fadeIn( 150 );
        } );

        $( document ).on( 'click', '.sqc-modal-close, .sqc-modal-overlay', function () {
            $( '#sqc-image-modal' ).fadeOut( 150 );
        } );

        $( document ).on( 'keydown', function ( e ) {
            if ( 'Escape' === e.key ) {
                $( '#sqc-image-modal' ).fadeOut( 150 );
            }
        } );

    } );

} )( jQuery );