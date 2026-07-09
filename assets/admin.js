/* IC Menu Manager — builder interactions */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// When a top-level menu item is blocked, its sub-items are moot: disable them.
		var tops = document.querySelectorAll( '.icmm-top-cb' );
		tops.forEach( function ( top ) {
			var block = top.closest( '.icmm-menu-block' );
			if ( ! block ) {
				return;
			}
			var subs = block.querySelectorAll( '.icmm-subs input[type="checkbox"]' );

			function sync() {
				subs.forEach( function ( sub ) {
					sub.disabled = top.checked;
					if ( top.checked ) {
						sub.checked = false;
					}
				} );
			}

			top.addEventListener( 'change', sync );
			sync();
		} );
	} );
} )();
