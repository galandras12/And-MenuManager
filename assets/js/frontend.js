/**
 * And-MenuManager – látogatói oldali viselkedés.
 * Almenü nyitás/zárás, billentyűzet és kívülre kattintás kezelése.
 */
(function () {
	'use strict';

	function closeAll( except ) {
		document.querySelectorAll( '.amm-toggle[aria-expanded="true"]' ).forEach( function ( toggle ) {
			if ( except && ( toggle === except || toggle.parentNode.contains( except ) ) ) {
				return;
			}

			collapse( toggle );
		} );
	}

	function submenuOf( toggle ) {
		var node = toggle.nextElementSibling;

		while ( node && ! node.classList.contains( 'amm-submenu' ) ) {
			node = node.nextElementSibling;
		}

		return node;
	}

	function collapse( toggle ) {
		var submenu = submenuOf( toggle );

		toggle.setAttribute( 'aria-expanded', 'false' );

		if ( submenu ) {
			submenu.hidden = true;
		}
	}

	function expand( toggle ) {
		var submenu = submenuOf( toggle );

		toggle.setAttribute( 'aria-expanded', 'true' );

		if ( submenu ) {
			submenu.hidden = false;
		}
	}

	function init() {
		var menus = document.querySelectorAll( '.amm-menu' );

		if ( ! menus.length ) {
			return;
		}

		menus.forEach( function ( menu ) {
			var collapsible = menu.classList.contains( 'amm-menu--collapsible' ) ||
				menu.classList.contains( 'amm-menu--accordion' ) ||
				menu.classList.contains( 'amm-menu--horizontal' );

			menu.querySelectorAll( '.amm-toggle' ).forEach( function ( toggle ) {
				var submenu = submenuOf( toggle );

				if ( ! submenu ) {
					return;
				}

				// Az aktuális ág nyitva marad.
				var containsCurrent = submenu.querySelector( '.amm-item--current, .amm-item--current-ancestor' );

				if ( collapsible && ! containsCurrent ) {
					submenu.hidden = true;
					toggle.setAttribute( 'aria-expanded', 'false' );
				} else {
					toggle.setAttribute( 'aria-expanded', 'true' );
				}

				toggle.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					event.stopPropagation();

					if ( 'true' === toggle.getAttribute( 'aria-expanded' ) ) {
						collapse( toggle );
					} else {
						if ( menu.classList.contains( 'amm-menu--horizontal' ) ) {
							closeAll( toggle );
						}

						expand( toggle );
					}
				} );
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest || ! event.target.closest( '.amm-menu--horizontal' ) ) {
				document.querySelectorAll( '.amm-menu--horizontal .amm-toggle[aria-expanded="true"]' ).forEach( collapse );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				document.querySelectorAll( '.amm-menu--horizontal .amm-toggle[aria-expanded="true"]' ).forEach( collapse );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}());
