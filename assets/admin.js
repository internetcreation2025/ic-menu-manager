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

		// Menu Order tab: drag to reorder, plus ↑/↓ buttons. Each row carries its
		// own hidden order[] input, so the DOM order IS the submitted order — no
		// serialisation needed.
		var orderList = document.querySelector( '.icmm-order-list' );
		if ( orderList ) {
			var dragging = null;

			orderList.addEventListener( 'dragstart', function ( e ) {
				var li = e.target.closest( '.icmm-order-item' );
				if ( ! li ) {
					return;
				}
				dragging = li;
				li.classList.add( 'icmm-dragging' );
				if ( e.dataTransfer ) {
					e.dataTransfer.effectAllowed = 'move';
				}
			} );

			orderList.addEventListener( 'dragend', function () {
				if ( dragging ) {
					dragging.classList.remove( 'icmm-dragging' );
				}
				dragging = null;
			} );

			orderList.addEventListener( 'dragover', function ( e ) {
				if ( ! dragging ) {
					return;
				}
				e.preventDefault();
				var after = afterElement( orderList, e.clientY );
				if ( null === after ) {
					orderList.appendChild( dragging );
				} else if ( after !== dragging ) {
					orderList.insertBefore( dragging, after );
				}
			} );

			function afterElement( container, y ) {
				var items = Array.prototype.slice.call(
					container.querySelectorAll( '.icmm-order-item:not(.icmm-dragging)' )
				);
				var closest = { offset: -Infinity, el: null };
				items.forEach( function ( el ) {
					var box = el.getBoundingClientRect();
					var offset = y - box.top - box.height / 2;
					if ( offset < 0 && offset > closest.offset ) {
						closest = { offset: offset, el: el };
					}
				} );
				return closest.el;
			}

			orderList.addEventListener( 'click', function ( e ) {
				var up = e.target.closest( '.icmm-move-up' );
				var down = e.target.closest( '.icmm-move-down' );
				if ( ! up && ! down ) {
					return;
				}
				e.preventDefault();
				var li = e.target.closest( '.icmm-order-item' );
				if ( ! li ) {
					return;
				}
				if ( up && li.previousElementSibling ) {
					orderList.insertBefore( li, li.previousElementSibling );
				} else if ( down && li.nextElementSibling ) {
					orderList.insertBefore( li.nextElementSibling, li );
				}
			} );
		}
	} );
} )();
