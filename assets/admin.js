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

		// Bulk user assignment: live filter + select-all/none of shown users.
		var filter = document.querySelector( '.icmm-user-filter' );
		var multi  = document.querySelector( '.icmm-user-multiselect' );
		if ( filter && multi ) {
			filter.addEventListener( 'input', function () {
				var q = filter.value.toLowerCase();
				Array.prototype.forEach.call( multi.options, function ( o ) {
					o.hidden = q && o.text.toLowerCase().indexOf( q ) === -1;
				} );
			} );
		}
		if ( multi ) {
			var all = document.querySelector( '.icmm-select-all' );
			var none = document.querySelector( '.icmm-select-none' );
			if ( all ) {
				all.addEventListener( 'click', function () {
					Array.prototype.forEach.call( multi.options, function ( o ) {
						if ( ! o.hidden ) {
							o.selected = true;
						}
					} );
				} );
			}
			if ( none ) {
				none.addEventListener( 'click', function () {
					Array.prototype.forEach.call( multi.options, function ( o ) {
						o.selected = false;
					} );
				} );
			}
		}
	} );
} )();
