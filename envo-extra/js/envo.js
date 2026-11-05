( function ( $ ) {
    'use strict';

    var $window = $( window ),
        ticking = false;

    // float cart
    var $myDiv = $( '.woo-float-info' ),
        $cartForm = $( '.woocommerce div.product form.cart' ),
        floatVisible = false;

    // return to top button
    var $toTop = $( '#return-to-top' ),
        toTopVisible = false;

    function onScroll() {
        var scrollTop = $window.scrollTop(),
            show;

        if ( $myDiv.length && $cartForm.length ) {
            show = scrollTop > $cartForm.offset().top - 60;
            if ( show !== floatVisible ) {
                floatVisible = show;
                $myDiv.stop( true ).animate( { 'bottom': show ? '0' : '-400px' }, show ? 200 : 100 );
            }
        }

        // ===== Scroll to Top ====
        if ( $toTop.length ) {
            show = scrollTop >= 50;        // If page is scrolled more than 50px
            if ( show !== toTopVisible ) {
                toTopVisible = show;
                $toTop.stop( true, true )[ show ? 'fadeIn' : 'fadeOut' ]( 200 );
            }
        }

        ticking = false;
    }

    $window.on( 'scroll', function () {
        if ( !ticking ) {
            ticking = true;
            window.requestAnimationFrame( onScroll );
        }
    } );

    $myDiv.on( 'click', '.close-me', function () {
        $( this ).parent().remove();
        $myDiv = $();
    } );

    $toTop.on( 'click', function ( e ) {      // When arrow is clicked
        e.preventDefault();
        $( 'body,html' ).animate( {
            scrollTop: 0                       // Scroll to top of body
        }, 500 );
    } );

} )( jQuery );
