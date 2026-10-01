jQuery( function( $ ) {
    $( document ).on( 'change', '.sqcheck-per-page', function() {
        const url = new URL( window.location.href );

        url.searchParams.set( sqcheckPerPage.key, $( this ).val() );
        url.searchParams.set( sqcheckPerPage.nonceArg, sqcheckPerPage.nonce );
        url.searchParams.delete( 'paged' );

        window.location.href = url.toString();
    } );
} );