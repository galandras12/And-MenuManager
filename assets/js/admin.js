/**
 * And-MenuManager – admin alkalmazás.
 *
 * Függőségmentes, egyfájlos felület. A nagy oldalszám kezelésének kulcsa,
 * hogy soha nem rajzolunk ki több ezer sort: a fa csak a tárolt
 * szabály-elemeket mutatja, az aloldalak igény szerint, lapozva töltődnek.
 */
(function () {
	'use strict';

	var D = window.AMM_DATA || {};
	var T = D.i18n || {};

	/* ---------------------------------------------------------------
	 * Segédfüggvények
	 * ------------------------------------------------------------ */

	/**
	 * DOM elem építése. A szöveg mindig textContent, így nincs XSS.
	 */
	function h( tag, attrs ) {
		var el = document.createElement( tag );
		var children = Array.prototype.slice.call( arguments, 2 );

		attrs = attrs || {};

		Object.keys( attrs ).forEach( function ( key ) {
			var value = attrs[ key ];

			if ( null === value || false === value || undefined === value ) {
				return;
			}

			if ( 'class' === key ) {
				el.className = value;
			} else if ( 'text' === key ) {
				el.textContent = value;
			} else if ( 'html' === key ) {
				el.innerHTML = value;
			} else if ( 'dataset' === key ) {
				Object.keys( value ).forEach( function ( dataKey ) {
					el.dataset[ dataKey ] = value[ dataKey ];
				} );
			} else if ( 0 === key.indexOf( 'on' ) && 'function' === typeof value ) {
				el.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else if ( true === value ) {
				el.setAttribute( key, '' );
			} else {
				el.setAttribute( key, value );
			}
		} );

		children.forEach( function append( child ) {
			if ( null === child || undefined === child || false === child ) {
				return;
			}

			if ( Array.isArray( child ) ) {
				child.forEach( append );
				return;
			}

			el.appendChild( 'object' === typeof child ? child : document.createTextNode( String( child ) ) );
		} );

		return el;
	}

	function clear( node ) {
		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}

		return node;
	}

	function query( params ) {
		var parts = [];

		Object.keys( params || {} ).forEach( function ( key ) {
			var value = params[ key ];

			if ( undefined === value || null === value || '' === value ) {
				return;
			}

			parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( value ) );
		} );

		return parts.length ? '?' + parts.join( '&' ) : '';
	}

	/**
	 * REST URL összeállítása.
	 *
	 * Sima (nem "szép") permalinkeknél a REST gyökér maga is
	 * lekérdezés-paraméter (?rest_route=...), ezért a további
	 * paramétereket nem lehet egyszerűen hozzáfűzni – egy második "?"
	 * elrontaná az útvonalat.
	 */
	function buildUrl( path ) {
		var root = D.root || '';

		if ( root.indexOf( '?' ) === -1 ) {
			return root + path;
		}

		var split = path.split( '?' );

		return root + encodeURIComponent( split[ 0 ] ) + ( split[ 1 ] ? '&' + split[ 1 ] : '' );
	}

	function api( path, options ) {
		options = options || {};

		var namespace = D.namespace || 'and-menumanager/v1';

		// A wp.apiFetch ismeri mindkét REST URL-formát és a nonce-ot is kezeli.
		if ( window.wp && window.wp.apiFetch ) {
			return window.wp.apiFetch( {
				path: '/' + namespace + path,
				method: options.method || 'GET',
				data: options.body
			} );
		}

		return fetch( buildUrl( path ), {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': D.nonce
			},
			body: options.body ? JSON.stringify( options.body ) : undefined
		} ).then( function ( response ) {
			return response.text().then( function ( text ) {
				var data = null;

				try {
					data = text ? JSON.parse( text ) : null;
				} catch ( error ) {
					throw new Error( 'A szerver nem érvényes választ adott: ' + text.slice( 0, 200 ) );
				}

				if ( ! response.ok ) {
					throw new Error( ( data && data.message ) || ( T.error + ' (HTTP ' + response.status + ')' ) );
				}

				return data;
			} );
		} );
	}

	var toastTimer = null;

	function toast( message, type ) {
		var existing = document.querySelector( '.amm-toast' );

		if ( existing ) {
			existing.remove();
		}

		var node = h( 'div', { class: 'amm-toast' + ( type ? ' amm-toast--' + type : '' ), role: 'status', text: message } );

		document.body.appendChild( node );
		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( function () {
			node.remove();
		}, 3200 );
	}

	/**
	 * Tartós hibasáv. A rövid ideig látszó "toast" mellett a hiba a
	 * felület tetején is megmarad, amíg be nem zárják.
	 */
	function showError( message ) {
		if ( ! root ) {
			return;
		}

		state.error = message;

		var existing = document.getElementById( 'amm-error' );
		var banner = h( 'div', { id: 'amm-error', class: 'amm-alert amm-alert--error' },
			h( 'span', { text: message } ),
			h( 'button', {
				class: 'amm-btn amm-btn--sm amm-btn--ghost',
				type: 'button',
				text: '✕',
				'aria-label': 'Bezárás',
				onClick: function () {
					state.error = '';
					banner.remove();
				}
			} )
		);

		if ( existing ) {
			existing.parentNode.replaceChild( banner, existing );
		} else {
			root.insertBefore( banner, root.firstChild );
		}
	}

	function fail( error ) {
		var message = error && error.message ? error.message : T.error;

		showError( message );
		toast( message, 'error' );
	}

	/**
	 * Folyamatjelző sáv a felület tetején.
	 */
	function showPending( message ) {
		if ( ! root ) {
			return;
		}

		var existing = document.getElementById( 'amm-pending' );
		var banner = h( 'div', { id: 'amm-pending', class: 'amm-alert amm-alert--info', role: 'status', 'aria-live': 'polite' },
			h( 'span', { class: 'amm-alert__body' },
				h( 'span', { class: 'amm-alert__spinner', 'aria-hidden': 'true' } ),
				h( 'span', { text: message } )
			)
		);

		if ( existing ) {
			existing.parentNode.replaceChild( banner, existing );
		} else {
			root.insertBefore( banner, root.firstChild );
		}
	}

	function hidePending() {
		var existing = document.getElementById( 'amm-pending' );

		if ( existing ) {
			existing.remove();
		}
	}

	/**
	 * Gomb "dolgozik" állapotba tétele: pörgő ikon, felirat, letiltás.
	 *
	 * A gomb eredeti tartalmát csomópontonként őrizzük meg, így a
	 * visszaállítás nem jár HTML újraértelmezéssel.
	 *
	 * @return {Function} A visszaállító függvény.
	 */
	function setButtonBusy( button, label ) {
		if ( ! button ) {
			return function () {};
		}

		var saved = Array.prototype.slice.call( button.childNodes );
		var wasDisabled = button.disabled;
		var width = button.offsetWidth;

		button.disabled = true;
		button.classList.add( 'is-busy' );
		button.setAttribute( 'aria-busy', 'true' );

		// A gomb ne ugráljon, amíg a felirat cserélődik.
		if ( width ) {
			button.style.minWidth = width + 'px';
		}

		clear( button );
		button.appendChild( h( 'span', { class: 'amm-btn__spinner', 'aria-hidden': 'true' } ) );
		button.appendChild( document.createTextNode( label ) );

		return function restore() {
			button.disabled = wasDisabled;
			button.classList.remove( 'is-busy' );
			button.removeAttribute( 'aria-busy' );
			button.style.minWidth = '';
			clear( button );

			saved.forEach( function ( node ) {
				button.appendChild( node );
			} );
		};
	}

	/**
	 * Hosszú művelet futtatása látható visszajelzéssel.
	 *
	 * @param {Element}  button  A megnyomott gomb (lehet null).
	 * @param {string}   label   Felirat futás közben.
	 * @param {Function} factory A műveletet indító függvény, ami ígéretet ad vissza.
	 * @return {Promise}
	 */
	function runTask( button, label, factory ) {
		var restore = setButtonBusy( button, label );

		state.busy = true;
		state.error = '';
		showPending( label );

		function done() {
			state.busy = false;
			restore();
			hidePending();
		}

		return factory().then( function ( result ) {
			done();

			return result;
		}, function ( error ) {
			done();
			fail( error );

			throw error;
		} );
	}

	function debounce( fn, wait ) {
		var timer = null;

		return function () {
			var args = arguments;
			var self = this;

			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				fn.apply( self, args );
			}, wait );
		};
	}

	function confirmDialog( message ) {
		return window.confirm( message ); // eslint-disable-line no-alert
	}

	/* ---------------------------------------------------------------
	 * Állapot
	 * ------------------------------------------------------------ */

	var state = {
		menus: [],
		menusTotal: 0,
		menuSearch: '',
		menu: null,
		tree: [],
		stats: {},
		selectedItem: 0,
		panel: 'add',
		expanded: {},
		autoCache: {},
		picker: {
			parent: 0,
			search: '',
			page: 1,
			items: [],
			total: 0,
			crumbs: [],
			selected: {},
			postType: 'page',
			loading: false
		},
		settings: null,
		locations: [],
		roles: null,
		health: null,
		busy: false,
		creating: false,
		newMenuName: '',
		error: ''
	};

	var root = document.getElementById( 'amm-app' );

	/* ---------------------------------------------------------------
	 * Fa segédműveletek
	 * ------------------------------------------------------------ */

	function findNode( nodes, id, parent ) {
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].id === id ) {
				return { node: nodes[ i ], list: nodes, index: i, parent: parent || null };
			}

			var found = findNode( nodes[ i ].children || [], id, nodes[ i ] );

			if ( found ) {
				return found;
			}
		}

		return null;
	}

	function collectIds( node, out ) {
		out = out || {};
		out[ node.id ] = true;

		( node.children || [] ).forEach( function ( child ) {
			collectIds( child, out );
		} );

		return out;
	}

	function flattenBatch( nodes, parentId, out ) {
		out = out || [];

		nodes.forEach( function ( node, index ) {
			out.push( { id: node.id, parent_id: parentId, position: index } );
			flattenBatch( node.children || [], node.id, out );
		} );

		return out;
	}

	function countNodes( nodes ) {
		var total = 0;

		nodes.forEach( function ( node ) {
			total += 1 + countNodes( node.children || [] );
		} );

		return total;
	}

	/* ---------------------------------------------------------------
	 * Adatbetöltés
	 * ------------------------------------------------------------ */

	function loadMenus() {
		return api( '/menus' + query( { search: state.menuSearch, per_page: 200 } ) ).then( function ( data ) {
			state.menus = data.items || [];
			state.menusTotal = data.total || 0;
		} );
	}

	function loadTree( menuId ) {
		return api( '/menus/' + menuId + '/tree' ).then( function ( data ) {
			state.menu = data.menu;
			state.tree = data.tree || [];
			state.stats = data.stats || {};
			state.autoCache = {};
		} );
	}

	function loadPicker() {
		var picker = state.picker;

		picker.loading = true;
		renderAside();

		return api( '/objects' + query( {
			parent: picker.search ? undefined : picker.parent,
			search: picker.search,
			page: picker.page,
			post_type: picker.postType,
			menu_id: state.menu ? state.menu.id : 0
		} ) ).then( function ( data ) {
			picker.loading = false;
			picker.items = 1 === picker.page ? data.items : picker.items.concat( data.items );
			picker.total = data.total;
			picker.crumbs = data.crumbs || [];
			renderAside();
		} ).catch( function ( error ) {
			picker.loading = false;
			fail( error );
			renderAside();
		} );
	}

	function loadAutoChildren( item, page ) {
		var cache = state.autoCache[ item.id ] || { items: [], page: 0, total: 0, loading: false };

		state.autoCache[ item.id ] = cache;
		cache.loading = true;
		renderEditor();

		return api( '/objects' + query( {
			parent: item.object_id,
			post_type: item.object_type,
			page: page || 1,
			menu_id: state.menu.id
		} ) ).then( function ( data ) {
			cache.loading = false;
			cache.page = data.page;
			cache.total = data.total;
			cache.items = 1 === data.page ? data.items : cache.items.concat( data.items );
			renderEditor();
		} ).catch( function ( error ) {
			cache.loading = false;
			fail( error );
			renderEditor();
		} );
	}

	function selectMenu( menuId ) {
		state.selectedItem = 0;
		state.panel = 'add';
		state.expanded = {};
		state.picker.parent = 0;
		state.picker.page = 1;
		state.picker.search = '';
		state.picker.selected = {};

		return loadTree( menuId ).then( function () {
			state.picker.postType = state.menu.settings.post_type || 'page';
			renderMenusView();

			return loadPicker();
		} ).catch( fail );
	}

	/* ---------------------------------------------------------------
	 * Műveletek
	 * ------------------------------------------------------------ */

	function saveOrder() {
		var batch = flattenBatch( state.tree, 0 );

		return api( '/menus/' + state.menu.id + '/reorder', {
			method: 'POST',
			body: { items: batch }
		} ).then( function ( data ) {
			state.tree = data.tree;
			state.stats = data.stats;
			renderEditor();
			toast( T.saved, 'success' );
		} ).catch( function ( error ) {
			fail( error );
			loadTree( state.menu.id ).then( renderMenusView );
		} );
	}

	function addObjects( objects ) {
		if ( ! objects.length ) {
			return Promise.resolve();
		}

		var items = objects.map( function ( object ) {
			return {
				type: 'post_type',
				object_type: object.post_type,
				object_id: object.id
			};
		} );

		return api( '/menus/' + state.menu.id + '/items', {
			method: 'POST',
			body: { items: items }
		} ).then( function ( data ) {
			state.tree = data.tree;
			state.stats = data.stats;
			state.picker.selected = {};
			renderMenusView();
			loadPicker();

			if ( data.skipped ) {
				toast( data.created.length + ' hozzáadva, ' + data.skipped + ' már szerepelt a menüben.' );
			} else {
				toast( data.created.length + ' elem hozzáadva.', 'success' );
			}
		} ).catch( fail );
	}

	function updateItem( itemId, payload, silent ) {
		return api( '/items/' + itemId, { method: 'POST', body: payload } ).then( function ( data ) {
			state.tree = data.tree;
			state.stats = data.stats;
			renderMenusView();

			if ( ! silent ) {
				toast( T.saved, 'success' );
			}
		} ).catch( fail );
	}

	function deleteItem( itemId ) {
		if ( ! confirmDialog( T.confirmDelete ) ) {
			return Promise.resolve();
		}

		return api( '/items/' + itemId, { method: 'DELETE' } ).then( function ( data ) {
			state.tree = data.tree;
			state.stats = data.stats;
			state.selectedItem = 0;
			state.panel = 'add';
			renderMenusView();
			toast( T.saved, 'success' );
		} ).catch( fail );
	}

	function setExclusion( ids, exclude ) {
		return api( '/menus/' + state.menu.id + '/exclusions', {
			method: 'POST',
			body: { ids: ids, exclude: exclude }
		} ).then( function ( data ) {
			state.menu = data.menu;
			state.stats = data.stats;
			renderMenusView();
			loadPicker();
		} ).catch( fail );
	}

	function isExcluded( objectId ) {
		if ( ! state.menu ) {
			return false;
		}

		return ( state.menu.settings.excluded || [] ).indexOf( objectId ) !== -1;
	}

	function focusNewMenuInput() {
		window.setTimeout( function () {
			var input = document.getElementById( 'amm-new-menu-name' );

			if ( input ) {
				input.focus();
				input.select();
			}
		}, 0 );
	}

	function openCreateForm() {
		state.creating = true;
		renderMenusView();
		focusNewMenuInput();
	}

	function createMenu( name ) {
		name = ( name || '' ).trim();

		if ( ! name ) {
			showError( 'Adj nevet az új menünek.' );
			focusNewMenuInput();

			return Promise.resolve();
		}

		state.busy = true;

		return api( '/menus', { method: 'POST', body: { name: name } } ).then( function ( menu ) {
			state.busy = false;
			state.creating = false;
			state.newMenuName = '';
			state.error = '';

			return loadMenus().then( function () {
				return selectMenu( menu.id );
			} ).then( function () {
				toast( 'A(z) „' + menu.name + '” menü létrejött.', 'success' );
			} );
		} ).catch( function ( error ) {
			state.busy = false;
			fail( error );
		} );
	}

	function deleteMenu() {
		if ( ! state.menu || ! confirmDialog( T.confirmDelete ) ) {
			return;
		}

		api( '/menus/' + state.menu.id, { method: 'DELETE' } ).then( function () {
			state.menu = null;
			state.tree = [];

			return loadMenus();
		} ).then( renderMenusView ).catch( fail );
	}

	function duplicateMenu() {
		if ( ! state.menu ) {
			return;
		}

		api( '/menus/' + state.menu.id + '/duplicate', { method: 'POST' } ).then( function ( menu ) {
			return loadMenus().then( function () {
				return selectMenu( menu.id );
			} );
		} ).catch( fail );
	}

	function saveMenuSettings( payload ) {
		return api( '/menus/' + state.menu.id, { method: 'POST', body: payload } ).then( function ( menu ) {
			state.menu = menu;

			return loadMenus().then( function () {
				return loadTree( menu.id );
			} );
		} ).then( function () {
			renderMenusView();
			toast( T.saved, 'success' );
		} ).catch( fail );
	}

	/* ---------------------------------------------------------------
	 * Drag & drop
	 * ------------------------------------------------------------ */

	var drag = null;

	function clearIndicator() {
		var existing = document.querySelector( '.amm-drop-indicator' );

		if ( existing ) {
			existing.remove();
		}
	}

	function onDragStart( event, node, row ) {
		drag = { id: node.id, subtree: collectIds( node ), target: null };
		event.dataTransfer.effectAllowed = 'move';

		try {
			event.dataTransfer.setData( 'text/plain', String( node.id ) );
		} catch ( error ) {
			// Az IE-örökség miatt csendben elnyeljük.
		}

		row.classList.add( 'is-dragging' );
	}

	function onDragOver( event ) {
		if ( ! drag ) {
			return;
		}

		var row = event.target.closest ? event.target.closest( '.amm-node__row' ) : null;

		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';
		clearIndicator();

		if ( ! row ) {
			drag.target = { refId: 0, before: false, asChild: false };

			return;
		}

		var refId = parseInt( row.dataset.id, 10 );

		if ( drag.subtree[ refId ] ) {
			drag.target = null;

			return;
		}

		var rect = row.getBoundingClientRect();
		var before = event.clientY < rect.top + rect.height / 2;
		var asChild = ! before && ( event.clientX - rect.left ) > 40;

		drag.target = { refId: refId, before: before, asChild: asChild };

		var indicator = h( 'div', { class: 'amm-drop-indicator', style: asChild ? 'margin-left:26px' : '' } );

		if ( before ) {
			row.parentNode.insertBefore( indicator, row );
		} else {
			row.parentNode.insertBefore( indicator, row.nextSibling );
		}
	}

	function onDrop( event ) {
		event.preventDefault();
		clearIndicator();

		if ( ! drag || ! drag.target ) {
			drag = null;
			renderEditor();

			return;
		}

		var moved = findNode( state.tree, drag.id );

		if ( ! moved ) {
			drag = null;

			return;
		}

		moved.list.splice( moved.index, 1 );

		var target = drag.target;

		if ( ! target.refId ) {
			state.tree.push( moved.node );
		} else {
			var ref = findNode( state.tree, target.refId );

			if ( ! ref ) {
				state.tree.push( moved.node );
			} else if ( target.asChild ) {
				ref.node.children = ref.node.children || [];
				ref.node.children.unshift( moved.node );
			} else {
				ref.list.splice( target.before ? ref.index : ref.index + 1, 0, moved.node );
			}
		}

		drag = null;
		renderEditor();
		saveOrder();
	}

	function onDragEnd() {
		clearIndicator();
		drag = null;

		var dragging = document.querySelector( '.amm-node__row.is-dragging' );

		if ( dragging ) {
			dragging.classList.remove( 'is-dragging' );
		}
	}

	/* Billentyűzetes mozgatás (akadálymentesség). */
	function moveItem( itemId, direction ) {
		var found = findNode( state.tree, itemId );

		if ( ! found ) {
			return;
		}

		if ( 'up' === direction || 'down' === direction ) {
			var target = 'up' === direction ? found.index - 1 : found.index + 1;

			if ( target < 0 || target >= found.list.length ) {
				return;
			}

			found.list.splice( found.index, 1 );
			found.list.splice( target, 0, found.node );
		}

		if ( 'in' === direction ) {
			if ( 0 === found.index ) {
				return;
			}

			var newParent = found.list[ found.index - 1 ];

			found.list.splice( found.index, 1 );
			newParent.children = newParent.children || [];
			newParent.children.push( found.node );
		}

		if ( 'out' === direction ) {
			if ( ! found.parent ) {
				return;
			}

			var grand = findNode( state.tree, found.parent.id );

			found.list.splice( found.index, 1 );
			grand.list.splice( grand.index + 1, 0, found.node );
		}

		renderEditor();
		saveOrder();
	}

	/* ---------------------------------------------------------------
	 * Megjelenítés – menülista
	 * ------------------------------------------------------------ */

	function renderMenuList() {
		var list = h( 'div', { class: 'amm-menulist' } );

		if ( ! state.menus.length ) {
			list.appendChild( h( 'p', { class: 'amm-empty', text: T.noMenus } ) );
		}

		state.menus.forEach( function ( menu ) {
			var active = state.menu && state.menu.id === menu.id;

			list.appendChild(
				h( 'button', {
					class: 'amm-menulist__item' + ( active ? ' is-active' : '' ),
					type: 'button',
					onClick: function () {
						selectMenu( menu.id );
					}
				},
					h( 'span', { class: 'amm-menulist__name', text: menu.name } ),
					h( 'span', { class: 'amm-menulist__meta', text: ( menu.item_count || 0 ) + ' ' + T.items } )
				)
			);
		} );

		var createForm = state.creating ? h( 'form', {
			class: 'amm-createform',
			onSubmit: function ( event ) {
				event.preventDefault();
				createMenu( state.newMenuName );
			}
		},
			h( 'label', { class: 'amm-field__label', for: 'amm-new-menu-name', text: 'Az új menü neve' } ),
			h( 'input', {
				class: 'amm-input',
				id: 'amm-new-menu-name',
				type: 'text',
				autocomplete: 'off',
				placeholder: 'pl. Főmenü',
				value: state.newMenuName,
				onInput: function ( event ) {
					state.newMenuName = event.target.value;
				},
				onKeydown: function ( event ) {
					if ( 'Escape' === event.key ) {
						state.creating = false;
						state.newMenuName = '';
						renderMenusView();
					}
				}
			} ),
			h( 'div', { class: 'amm-createform__actions' },
				h( 'button', { class: 'amm-btn amm-btn--primary amm-btn--sm', type: 'submit', text: 'Létrehozás' } ),
				h( 'button', {
					class: 'amm-btn amm-btn--sm',
					type: 'button',
					text: 'Mégse',
					onClick: function () {
						state.creating = false;
						state.newMenuName = '';
						renderMenusView();
					}
				} )
			)
		) : null;

		return h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' },
				h( 'h2', { class: 'amm-panel__title', text: T.menus + ' (' + state.menusTotal + ')' } ),
				h( 'button', {
					class: 'amm-btn amm-btn--sm amm-btn--primary',
					type: 'button',
					onClick: openCreateForm,
					title: T.newMenu,
					text: '+'
				} )
			),
			h( 'div', { class: 'amm-panel__body' },
				h( 'input', {
					class: 'amm-input',
					type: 'search',
					placeholder: T.searchMenus,
					value: state.menuSearch,
					onInput: debounce( function ( event ) {
						state.menuSearch = event.target.value;
						loadMenus().then( function () {
							var panel = document.querySelector( '.amm-menulist' );

							if ( panel ) {
								renderMenusView();
							}
						} );
					}, 220 )
				} ),
				createForm
			),
			list
		);
	}

	/* ---------------------------------------------------------------
	 * Megjelenítés – fa
	 * ------------------------------------------------------------ */

	function nodeBadges( node ) {
		var badges = [];

		if ( node.auto_children ) {
			var label = T.autoChildren;

			if ( null !== node.auto_total && undefined !== node.auto_total ) {
				label += ' · ' + node.auto_total;
			}

			badges.push( h( 'span', { class: 'amm-badge amm-badge--auto', text: label } ) );
		}

		if ( 'missing' === node.status ) {
			badges.push( h( 'span', { class: 'amm-badge amm-badge--warn', text: 'törölt oldal' } ) );
		}

		if ( ! node.enabled ) {
			badges.push( h( 'span', { class: 'amm-badge', text: T.hidden } ) );
		}

		if ( node.excluded ) {
			badges.push( h( 'span', { class: 'amm-badge amm-badge--warn', text: T.excluded } ) );
		}

		if ( 'custom' === node.type ) {
			badges.push( h( 'span', { class: 'amm-badge amm-badge--muted', text: 'link' } ) );
		}

		if ( 'heading' === node.type ) {
			badges.push( h( 'span', { class: 'amm-badge amm-badge--muted', text: 'címsor' } ) );
		}

		return badges;
	}

	function renderAutoBlock( node ) {
		var cache = state.autoCache[ node.id ];
		var expanded = !! state.expanded[ 'auto-' + node.id ];
		var wrap = h( 'div', { class: 'amm-node__children' } );

		var head = h( 'div', { class: 'amm-node__auto' },
			h( 'span', { text: expanded ? '▾' : '▸' } ),
			h( 'span', { text: T.autoChildren + ( node.auto_total ? ' (' + node.auto_total + ')' : '' ) } ),
			h( 'button', {
				class: 'amm-btn amm-btn--sm amm-btn--ghost',
				type: 'button',
				text: expanded ? 'Bezár' : 'Aloldalak kezelése',
				onClick: function () {
					state.expanded[ 'auto-' + node.id ] = ! expanded;

					if ( ! expanded && ! state.autoCache[ node.id ] ) {
						loadAutoChildren( node, 1 );
					} else {
						renderEditor();
					}
				}
			} )
		);

		wrap.appendChild( head );

		if ( ! expanded ) {
			return wrap;
		}

		if ( ! cache || cache.loading ) {
			wrap.appendChild( h( 'div', { class: 'amm-node__auto', text: T.loading } ) );

			return wrap;
		}

		if ( ! cache.items.length ) {
			wrap.appendChild( h( 'div', { class: 'amm-node__auto', text: T.emptyPicker } ) );

			return wrap;
		}

		cache.items.forEach( function ( child ) {
			var excluded = isExcluded( child.id );

			wrap.appendChild(
				h( 'div', { class: 'amm-node__auto' },
					h( 'span', { text: excluded ? '🚫' : '•' } ),
					h( 'span', { class: 'amm-node__label', text: child.title, style: excluded ? 'text-decoration:line-through;opacity:.6' : '' } ),
					child.child_count ? h( 'span', { class: 'amm-badge', text: child.child_count } ) : null,
					h( 'button', {
						class: 'amm-btn amm-btn--sm amm-btn--ghost',
						type: 'button',
						text: excluded ? T.visible : T.hidden,
						title: excluded ? 'Visszakapcsolás a menübe' : 'Kizárás ebből a menüből',
						onClick: function () {
							setExclusion( [ child.id ], ! excluded );
						}
					} ),
					h( 'button', {
						class: 'amm-btn amm-btn--sm amm-btn--ghost',
						type: 'button',
						text: 'Rögzítés',
						title: 'Külön menüelemként rögzíti, hogy átnevezhető és sorba rendezhető legyen',
						onClick: function () {
							api( '/menus/' + state.menu.id + '/items', {
								method: 'POST',
								body: {
									items: [ {
										type: 'post_type',
										object_type: child.post_type,
										object_id: child.id,
										parent_id: node.id,
										auto_children: 0,
										allow_duplicate: 1
									} ]
								}
							} ).then( function ( data ) {
								state.tree = data.tree;
								state.stats = data.stats;
								renderMenusView();
								toast( T.saved, 'success' );
							} ).catch( fail );
						}
					} )
				)
			);
		} );

		if ( cache.items.length < cache.total ) {
			wrap.appendChild(
				h( 'div', { class: 'amm-node__auto' },
					h( 'button', {
						class: 'amm-btn amm-btn--sm',
						type: 'button',
						text: T.more + ' (' + cache.items.length + '/' + cache.total + ')',
						onClick: function () {
							loadAutoChildren( node, cache.page + 1 );
						}
					} )
				)
			);
		}

		return wrap;
	}

	function renderNode( node ) {
		var hasChildren = ( node.children || [] ).length > 0;
		var expandedKey = 'node-' + node.id;
		var expanded = undefined === state.expanded[ expandedKey ] ? true : state.expanded[ expandedKey ];
		var selected = state.selectedItem === node.id;

		var row = h( 'div', {
			class: 'amm-node__row' + ( selected ? ' is-selected' : '' ) + ( node.enabled ? '' : ' is-disabled' ),
			draggable: 'true',
			dataset: { id: node.id },
			onClick: function () {
				state.selectedItem = node.id;
				state.panel = 'item';
				renderMenusView();
			},
			onDragstart: function ( event ) {
				onDragStart( event, node, row );
			},
			onDragend: onDragEnd
		},
			h( 'span', { class: 'amm-node__handle', title: 'Húzd az átrendezéshez', text: '⋮⋮' } ),
			hasChildren ? h( 'button', {
				class: 'amm-node__toggle',
				type: 'button',
				'aria-label': expanded ? 'Bezárás' : 'Kinyitás',
				text: expanded ? '▾' : '▸',
				onClick: function ( event ) {
					event.stopPropagation();
					state.expanded[ expandedKey ] = ! expanded;
					renderEditor();
				}
			} ) : h( 'span', { class: 'amm-node__toggle amm-node__toggle--placeholder' } ),
			h( 'span', { class: 'amm-node__label', text: node.label || '(névtelen)' } ),
			h( 'span', { class: 'amm-node__badges' }, nodeBadges( node ) )
		);

		var wrap = h( 'div', { class: 'amm-node' }, row );

		if ( node.auto_children && 'post_type' === node.type && node.object_id ) {
			wrap.appendChild( renderAutoBlock( node ) );
		}

		if ( hasChildren && expanded ) {
			var children = h( 'div', { class: 'amm-node__children' } );

			node.children.forEach( function ( child ) {
				children.appendChild( renderNode( child ) );
			} );

			wrap.appendChild( children );
		}

		return wrap;
	}

	function renderTreePanel() {
		var body = h( 'div', {
			class: 'amm-tree',
			onDragover: onDragOver,
			onDrop: onDrop,
			onDragleave: function ( event ) {
				if ( event.target === event.currentTarget ) {
					clearIndicator();
				}
			}
		} );

		if ( ! state.tree.length ) {
			body.appendChild( h( 'p', { class: 'amm-tree__empty', text: 'Üres menü – adj hozzá tartalmat a jobb oldali panelről.' } ) );
		}

		state.tree.forEach( function ( node ) {
			body.appendChild( renderNode( node ) );
		} );

		return h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' },
				h( 'h2', { class: 'amm-panel__title', text: T.structure } ),
				h( 'div', { class: 'amm-stats' },
					h( 'span', { class: 'amm-stat' }, h( 'strong', { text: String( countNodes( state.tree ) ) } ), T.items ),
					h( 'span', { class: 'amm-stat' }, h( 'strong', { text: String( state.stats.links || 0 ) } ), T.links ),
					h( 'span', { class: 'amm-stat' }, h( 'strong', { text: String( state.stats.excluded || 0 ) } ), T.excluded )
				)
			),
			h( 'div', { class: 'amm-panel__body amm-panel__body--flush' }, body )
		);
	}

	/* ---------------------------------------------------------------
	 * Megjelenítés – oldalválasztó
	 * ------------------------------------------------------------ */

	function renderPicker() {
		var picker = state.picker;
		var wrap = h( 'div', {} );

		var toolbar = h( 'div', { class: 'amm-picker__toolbar' },
			h( 'input', {
				class: 'amm-input',
				type: 'search',
				placeholder: T.searchPages,
				value: picker.search,
				onInput: debounce( function ( event ) {
					picker.search = event.target.value;
					picker.page = 1;
					loadPicker();
				}, 260 )
			} )
		);

		if ( D.postTypes && D.postTypes.length > 1 ) {
			var select = h( 'select', {
				class: 'amm-select',
				style: 'max-width:120px',
				onChange: function ( event ) {
					picker.postType = event.target.value;
					picker.parent = 0;
					picker.page = 1;
					loadPicker();
				}
			} );

			D.postTypes.forEach( function ( type ) {
				select.appendChild( h( 'option', { value: type.name, selected: type.name === picker.postType, text: type.label } ) );
			} );

			toolbar.appendChild( select );
		}

		wrap.appendChild( toolbar );

		if ( ! picker.search ) {
			var crumbs = h( 'div', { class: 'amm-picker__crumbs' },
				h( 'button', {
					class: 'amm-picker__crumb',
					type: 'button',
					text: T.root,
					onClick: function () {
						picker.parent = 0;
						picker.page = 1;
						loadPicker();
					}
				} )
			);

			( picker.crumbs || [] ).forEach( function ( crumb ) {
				crumbs.appendChild( h( 'span', { text: '›' } ) );
				crumbs.appendChild( h( 'button', {
					class: 'amm-picker__crumb',
					type: 'button',
					text: crumb.title,
					onClick: function () {
						picker.parent = crumb.id;
						picker.page = 1;
						loadPicker();
					}
				} ) );
			} );

			wrap.appendChild( crumbs );
		}

		var list = h( 'div', { class: 'amm-picker__list' } );

		if ( picker.loading && ! picker.items.length ) {
			list.appendChild( h( 'p', { class: 'amm-empty', text: T.loading } ) );
		} else if ( ! picker.items.length ) {
			list.appendChild( h( 'p', { class: 'amm-empty', text: T.emptyPicker } ) );
		}

		picker.items.forEach( function ( item ) {
			var checkbox = h( 'input', {
				type: 'checkbox',
				checked: !! picker.selected[ item.id ],
				onChange: function ( event ) {
					if ( event.target.checked ) {
						picker.selected[ item.id ] = item;
					} else {
						delete picker.selected[ item.id ];
					}

					renderAside();
				}
			} );

			var titleWrap = h( 'label', { class: 'amm-picker__title' },
				h( 'span', { text: item.title } ),
				item.breadcrumb && item.breadcrumb.length ? h( 'small', { text: item.breadcrumb.join( ' › ' ) } ) : null,
				'private' === item.status ? h( 'small', { text: 'privát' } ) : null
			);

			titleWrap.insertBefore( checkbox, titleWrap.firstChild );

			list.appendChild(
				h( 'div', { class: 'amm-picker__row' + ( item.in_menu ? ' is-in-menu' : '' ) },
					titleWrap,
					item.child_count ? h( 'button', {
						class: 'amm-picker__drill',
						type: 'button',
						text: item.child_count + ' →',
						title: 'Aloldalak megnyitása',
						onClick: function () {
							picker.parent = item.id;
							picker.page = 1;
							picker.search = '';
							loadPicker();
						}
					} ) : null,
					h( 'button', {
						class: 'amm-btn amm-btn--sm',
						type: 'button',
						text: '+',
						title: T.add,
						onClick: function () {
							addObjects( [ item ] );
						}
					} )
				)
			);
		} );

		wrap.appendChild( list );

		var selectedCount = Object.keys( picker.selected ).length;

		wrap.appendChild(
			h( 'div', { class: 'amm-picker__footer' },
				h( 'span', { text: picker.items.length + ' / ' + picker.total } ),
				h( 'div', { style: 'display:flex;gap:6px' },
					picker.items.length < picker.total ? h( 'button', {
						class: 'amm-btn amm-btn--sm',
						type: 'button',
						text: T.more,
						onClick: function () {
							picker.page += 1;
							loadPicker();
						}
					} ) : null,
					h( 'button', {
						class: 'amm-btn amm-btn--sm amm-btn--primary',
						type: 'button',
						disabled: ! selectedCount,
						text: T.addSelected + ( selectedCount ? ' (' + selectedCount + ')' : '' ),
						onClick: function ( event ) {
							var objects = Object.keys( picker.selected ).map( function ( key ) {
								return picker.selected[ key ];
							} );

							runTask( event.currentTarget, 'Hozzáadás…', function () {
								return addObjects( objects );
							} ).catch( function () {} );
						}
					} )
				)
			)
		);

		return wrap;
	}

	/* ---------------------------------------------------------------
	 * Megjelenítés – elem beállítások
	 * ------------------------------------------------------------ */

	function field( label, control, hint ) {
		return h( 'label', { class: 'amm-field' },
			h( 'span', { class: 'amm-field__label', text: label } ),
			control,
			hint ? h( 'span', { class: 'amm-field__hint', text: hint } ) : null
		);
	}

	function checkbox( label, checked, onChange, hint ) {
		return h( 'label', { class: 'amm-check' },
			h( 'input', { type: 'checkbox', checked: !! checked, onChange: onChange } ),
			h( 'span', {}, h( 'strong', { text: label } ), hint ? h( 'small', { text: hint } ) : null )
		);
	}

	function renderItemPanel() {
		var found = findNode( state.tree, state.selectedItem );

		if ( ! found ) {
			return h( 'p', { class: 'amm-empty', text: 'Válassz egy menüelemet a fából.' } );
		}

		var node = found.node;
		var draft = {};
		var wrap = h( 'div', {} );

		function bind( key, value ) {
			draft[ key ] = value;
		}

		wrap.appendChild( h( 'div', { class: 'amm-hint', text: node.label } ) );

		wrap.appendChild( field( 'Címke felülírása', h( 'input', {
			class: 'amm-input',
			type: 'text',
			value: node.title,
			placeholder: node.label,
			onInput: function ( event ) {
				bind( 'title', event.target.value );
			}
		} ), 'Üresen hagyva mindig az oldal aktuális címe jelenik meg.' ) );

		if ( 'custom' === node.type || 'heading' === node.type ) {
			wrap.appendChild( field( 'URL', h( 'input', {
				class: 'amm-input',
				type: 'url',
				value: node.url,
				onInput: function ( event ) {
					bind( 'url', event.target.value );
				}
			} ) ) );
		}

		if ( 'post_type' === node.type ) {
			wrap.appendChild( checkbox( T.autoChildren, node.auto_children, function ( event ) {
				bind( 'auto_children', event.target.checked ? 1 : 0 );
			}, 'Az összes aloldal automatikusan megjelenik – az újonnan létrehozottak is.' ) );

			var depthSelect = h( 'select', {
				class: 'amm-select',
				onChange: function ( event ) {
					bind( 'auto_depth', event.target.value );
				}
			} );

			[ [ 0, 'Korlátlan' ], [ 1, '1 szint' ], [ 2, '2 szint' ], [ 3, '3 szint' ], [ 4, '4 szint' ], [ 5, '5 szint' ] ].forEach( function ( option ) {
				depthSelect.appendChild( h( 'option', { value: option[ 0 ], selected: node.auto_depth === option[ 0 ], text: option[ 1 ] } ) );
			} );

			wrap.appendChild( field( 'Automatikus mélység', depthSelect ) );

			var orderSelect = h( 'select', {
				class: 'amm-select',
				onChange: function ( event ) {
					bind( 'auto_order', event.target.value );
				}
			} );

			[ [ 'menu_order', 'Oldal sorrend (menu_order)' ], [ 'title', 'Cím szerint (A→Z)' ], [ 'slug', 'Slug szerint' ], [ 'date', 'Dátum szerint (új elöl)' ] ].forEach( function ( option ) {
				orderSelect.appendChild( h( 'option', { value: option[ 0 ], selected: node.auto_order === option[ 0 ], text: option[ 1 ] } ) );
			} );

			wrap.appendChild( field( 'Aloldalak rendezése', orderSelect ) );
		}

		var targetSelect = h( 'select', {
			class: 'amm-select',
			onChange: function ( event ) {
				bind( 'target', event.target.value );
			}
		},
			h( 'option', { value: '', selected: '' === node.target, text: 'Azonos ablak' } ),
			h( 'option', { value: '_blank', selected: '_blank' === node.target, text: 'Új ablak' } )
		);

		wrap.appendChild( field( 'Megnyitás', targetSelect ) );

		wrap.appendChild( field( 'CSS osztály', h( 'input', {
			class: 'amm-input',
			type: 'text',
			value: node.css_class,
			onInput: function ( event ) {
				bind( 'css_class', event.target.value );
			}
		} ) ) );

		wrap.appendChild( field( 'Leírás / tooltip', h( 'input', {
			class: 'amm-input',
			type: 'text',
			value: node.description,
			onInput: function ( event ) {
				bind( 'description', event.target.value );
			}
		} ) ) );

		wrap.appendChild( checkbox( 'Megjelenik a menüben', node.enabled, function ( event ) {
			bind( 'enabled', event.target.checked ? 1 : 0 );
		} ) );

		/* Láthatóság */
		var visibility = node.visibility || { roles: [], login: 'any', start: '', end: '' };
		var visDraft = {
			roles: ( visibility.roles || [] ).slice(),
			login: visibility.login,
			start: visibility.start,
			end: visibility.end
		};

		function pushVisibility() {
			bind( 'visibility', visDraft );
		}

		var loginSelect = h( 'select', {
			class: 'amm-select',
			onChange: function ( event ) {
				visDraft.login = event.target.value;
				pushVisibility();
			}
		},
			h( 'option', { value: 'any', selected: 'any' === visibility.login, text: 'Mindenkinek' } ),
			h( 'option', { value: 'in', selected: 'in' === visibility.login, text: 'Csak bejelentkezve' } ),
			h( 'option', { value: 'out', selected: 'out' === visibility.login, text: 'Csak kijelentkezve' } )
		);

		wrap.appendChild( field( 'Kinek látszik', loginSelect ) );

		var rolesBox = h( 'div', { style: 'max-height:120px;overflow:auto;border:1px solid var(--amm-border);border-radius:7px;padding:6px' } );

		Object.keys( D.roles || {} ).forEach( function ( roleKey ) {
			rolesBox.appendChild(
				h( 'label', { class: 'amm-check', style: 'margin-bottom:4px' },
					h( 'input', {
						type: 'checkbox',
						checked: visDraft.roles.indexOf( roleKey ) !== -1,
						onChange: function ( event ) {
							if ( event.target.checked ) {
								visDraft.roles.push( roleKey );
							} else {
								visDraft.roles = visDraft.roles.filter( function ( item ) {
									return item !== roleKey;
								} );
							}

							pushVisibility();
						}
					} ),
					h( 'span', { text: D.roles[ roleKey ].name } )
				)
			);
		} );

		wrap.appendChild( field( 'Csak ezeknek a szerepköröknek', rolesBox, 'Üresen hagyva mindenkinek látszik.' ) );

		wrap.appendChild( field( 'Megjelenés kezdete', h( 'input', {
			class: 'amm-input',
			type: 'datetime-local',
			value: visibility.start ? visibility.start.replace( ' ', 'T' ) : '',
			onInput: function ( event ) {
				visDraft.start = event.target.value.replace( 'T', ' ' );
				pushVisibility();
			}
		} ) ) );

		wrap.appendChild( field( 'Megjelenés vége', h( 'input', {
			class: 'amm-input',
			type: 'datetime-local',
			value: visibility.end ? visibility.end.replace( ' ', 'T' ) : '',
			onInput: function ( event ) {
				visDraft.end = event.target.value.replace( 'T', ' ' );
				pushVisibility();
			}
		} ) ) );

		/* Mozgatás billentyűzettel */
		wrap.appendChild(
			h( 'div', { style: 'display:flex;gap:6px;margin:12px 0' },
				h( 'button', { class: 'amm-btn amm-btn--sm', type: 'button', title: 'Fel', text: '↑', onClick: function () { moveItem( node.id, 'up' ); } } ),
				h( 'button', { class: 'amm-btn amm-btn--sm', type: 'button', title: 'Le', text: '↓', onClick: function () { moveItem( node.id, 'down' ); } } ),
				h( 'button', { class: 'amm-btn amm-btn--sm', type: 'button', title: 'Beljebb', text: '→', onClick: function () { moveItem( node.id, 'in' ); } } ),
				h( 'button', { class: 'amm-btn amm-btn--sm', type: 'button', title: 'Kijjebb', text: '←', onClick: function () { moveItem( node.id, 'out' ); } } )
			)
		);

		wrap.appendChild(
			h( 'div', { style: 'display:flex;gap:6px;justify-content:space-between' },
				h( 'button', {
					class: 'amm-btn amm-btn--primary',
					type: 'button',
					text: T.save,
					onClick: function () {
						updateItem( node.id, draft );
					}
				} ),
				h( 'button', {
					class: 'amm-btn amm-btn--danger',
					type: 'button',
					text: T.delete,
					onClick: function () {
						deleteItem( node.id );
					}
				} )
			)
		);

		return wrap;
	}

	/* ---------------------------------------------------------------
	 * Megjelenítés – menü beállítások
	 * ------------------------------------------------------------ */

	function renderMenuPanel() {
		var menu = state.menu;
		var draft = { settings: {} };
		var wrap = h( 'div', {} );

		wrap.appendChild( field( T.name, h( 'input', {
			class: 'amm-input',
			type: 'text',
			value: menu.name,
			onInput: function ( event ) {
				draft.name = event.target.value;
			}
		} ) ) );

		wrap.appendChild( field( 'Azonosító (slug)', h( 'input', {
			class: 'amm-input',
			type: 'text',
			value: menu.slug,
			onInput: function ( event ) {
				draft.slug = event.target.value;
			}
		} ), 'Ezzel hivatkozhatsz rá shortcode-ban és sablonban.' ) );

		var styleSelect = h( 'select', {
			class: 'amm-select',
			onChange: function ( event ) {
				draft.settings.style = event.target.value;
			}
		} );

		[ [ 'vertical', 'Függőleges' ], [ 'accordion', 'Lenyíló (harmonika)' ], [ 'horizontal', 'Vízszintes' ], [ 'columns', 'Oszlopos' ] ].forEach( function ( option ) {
			styleSelect.appendChild( h( 'option', { value: option[ 0 ], selected: menu.settings.style === option[ 0 ], text: option[ 1 ] } ) );
		} );

		wrap.appendChild( field( 'Megjelenés', styleSelect ) );

		wrap.appendChild( checkbox( 'Új elemeknél az aloldalak alapból automatikusak', menu.settings.auto_children, function ( event ) {
			draft.settings.auto_children = event.target.checked;
		} ) );

		wrap.appendChild( checkbox( 'Új gyökér-oldal automatikus felvétele', menu.settings.auto_add_root, function ( event ) {
			draft.settings.auto_add_root = event.target.checked;
		}, 'Ha egy új, szülő nélküli oldal jön létre, magától bekerül ebbe a menübe.' ) );

		wrap.appendChild( checkbox( 'Főoldal link a menü elején', menu.settings.show_home, function ( event ) {
			draft.settings.show_home = event.target.checked;
		} ) );

		wrap.appendChild( checkbox( 'Privát oldalak elrejtése', menu.settings.hide_private, function ( event ) {
			draft.settings.hide_private = event.target.checked;
		} ) );

		var depthSelect = h( 'select', {
			class: 'amm-select',
			onChange: function ( event ) {
				draft.settings.depth_limit = event.target.value;
			}
		} );

		[ 0, 1, 2, 3, 4, 5, 6 ].forEach( function ( value ) {
			depthSelect.appendChild( h( 'option', {
				value: value,
				selected: menu.settings.depth_limit === value,
				text: 0 === value ? 'Korlátlan' : value + ' szint'
			} ) );
		} );

		wrap.appendChild( field( 'Maximális mélység', depthSelect ) );

		wrap.appendChild( field( 'Menü CSS osztály', h( 'input', {
			class: 'amm-input',
			type: 'text',
			value: menu.settings.css_class,
			onInput: function ( event ) {
				draft.settings.css_class = event.target.value;
			}
		} ) ) );

		wrap.appendChild( field( 'Gyorsítótár élettartam (mp)', h( 'input', {
			class: 'amm-input',
			type: 'number',
			min: '0',
			value: menu.settings.cache_ttl,
			onInput: function ( event ) {
				draft.settings.cache_ttl = event.target.value;
			}
		} ) ) );

		wrap.appendChild(
			h( 'div', { class: 'amm-field' },
				h( 'span', { class: 'amm-field__label', text: 'Beillesztés' } ),
				h( 'div', { class: 'amm-code' },
					h( 'code', { text: '[amm_menu id="' + menu.slug + '"]' } ),
					h( 'button', {
						class: 'amm-btn amm-btn--sm amm-btn--ghost',
						type: 'button',
						text: '⧉',
						title: 'Másolás',
						onClick: function () {
							var text = '[amm_menu id="' + menu.slug + '"]';

							if ( navigator.clipboard ) {
								navigator.clipboard.writeText( text );
							}

							toast( T.shortcodeCopied, 'success' );
						}
					} )
				),
				h( 'span', { class: 'amm-field__hint', text: 'Sablonban: <?php amm_menu( \'' + menu.slug + '\' ); ?>' } )
			)
		);

		wrap.appendChild(
			h( 'div', { style: 'display:flex;gap:6px;flex-wrap:wrap' },
				h( 'button', {
					class: 'amm-btn amm-btn--primary',
					type: 'button',
					text: T.save,
					onClick: function () {
						saveMenuSettings( draft );
					}
				} ),
				h( 'button', {
					class: 'amm-btn',
					type: 'button',
					text: T.preview,
					onClick: showPreview
				} ),
				h( 'button', { class: 'amm-btn', type: 'button', text: T.duplicate, onClick: duplicateMenu } ),
				h( 'button', { class: 'amm-btn amm-btn--danger', type: 'button', text: T.delete, onClick: deleteMenu } )
			)
		);

		return wrap;
	}

	function showPreview() {
		api( '/menus/' + state.menu.id + '/preview' ).then( function ( data ) {
			openModal( T.preview, h( 'div', { class: 'amm-preview', html: data.html || '<p>—</p>' } ) );
		} ).catch( fail );
	}

	function openModal( title, content, actions ) {
		var overlay = h( 'div', {
			class: 'amm-modal',
			onClick: function ( event ) {
				if ( event.target === overlay ) {
					overlay.remove();
				}
			}
		},
			h( 'div', { class: 'amm-modal__box' },
				h( 'div', { class: 'amm-modal__head', text: title } ),
				h( 'div', { class: 'amm-modal__body' }, content ),
				h( 'div', { class: 'amm-modal__foot' },
					actions || null,
					h( 'button', {
						class: 'amm-btn',
						type: 'button',
						text: 'Bezárás',
						onClick: function () {
							overlay.remove();
						}
					} )
				)
			)
		);

		document.body.appendChild( overlay );

		return overlay;
	}

	/* ---------------------------------------------------------------
	 * Megjelenítés – oldalsó panel
	 * ------------------------------------------------------------ */

	function renderAsidePanel() {
		var tabs = h( 'div', { class: 'amm-tabs' } );

		[ [ 'add', T.pagePicker ], [ 'item', 'Elem' ], [ 'menu', 'Menü' ] ].forEach( function ( tab ) {
			tabs.appendChild( h( 'button', {
				class: 'amm-tab' + ( state.panel === tab[ 0 ] ? ' is-active' : '' ),
				type: 'button',
				text: tab[ 1 ],
				onClick: function () {
					state.panel = tab[ 0 ];
					renderMenusView();
				}
			} ) );
		} );

		var body;

		if ( 'item' === state.panel ) {
			body = renderItemPanel();
		} else if ( 'menu' === state.panel ) {
			body = renderMenuPanel();
		} else {
			body = renderPicker();
		}

		return h( 'section', { class: 'amm-panel amm-panel--aside' },
			h( 'div', { class: 'amm-panel__body' }, tabs ),
			h( 'div', { class: 'add' === state.panel ? 'amm-panel__body amm-panel__body--flush' : 'amm-panel__body' }, body )
		);
	}

	function renderAside() {
		var container = document.querySelector( '.amm-layout' );

		if ( ! container ) {
			return;
		}

		var existing = container.querySelector( '.amm-panel--aside' );

		if ( existing ) {
			container.replaceChild( renderAsidePanel(), existing );
		}
	}

	function renderEditor() {
		var container = document.querySelector( '.amm-layout' );

		if ( ! container ) {
			return;
		}

		var existing = container.querySelector( '.amm-panel--tree' );
		var next = renderTreePanel();

		next.classList.add( 'amm-panel--tree' );

		if ( existing ) {
			container.replaceChild( next, existing );
		}
	}

	/* ---------------------------------------------------------------
	 * Nézetek
	 * ------------------------------------------------------------ */

	function renderMenusView() {
		clear( root );

		var header = h( 'div', { class: 'amm-header' },
			h( 'div', { class: 'amm-header__titles' },
				h( 'h1', { text: T.menus } ),
				h( 'p', { text: D.pageCount + ' oldal az indexben' + ( D.indexed ? '' : ' (közvetlen mód)' ) } )
			),
			h( 'div', { class: 'amm-header__actions' },
				h( 'button', {
					class: 'amm-btn',
					type: 'button',
					text: 'WordPress menük átemelése',
					onClick: function ( event ) {
						importCore( event.currentTarget );
					}
				} ),
				h( 'button', {
					class: 'amm-btn',
					type: 'button',
					text: 'Gyorsítótár ürítése',
					onClick: function ( event ) {
						runTask( event.currentTarget, 'Ürítés…', function () {
							return api( '/tools/flush', { method: 'POST' } );
						} ).then( function ( data ) {
							toast( data.message, 'success' );
						} ).catch( function () {} );
					}
				} ),
				h( 'a', { class: 'amm-btn', href: D.adminUrl.replace( 'page=and-menumanager', 'page=and-menumanager-settings' ), text: T.settings } ),
				h( 'button', { class: 'amm-btn amm-btn--primary', type: 'button', text: T.newMenu, onClick: openCreateForm } )
			)
		);

		root.appendChild( header );

		var layout = h( 'div', { class: 'amm-layout' } );

		layout.appendChild( renderMenuList() );

		if ( state.menu ) {
			var tree = renderTreePanel();

			tree.classList.add( 'amm-panel--tree' );
			layout.appendChild( tree );
			layout.appendChild( renderAsidePanel() );
		} else {
			layout.appendChild( h( 'section', { class: 'amm-panel' },
				h( 'div', { class: 'amm-panel__body' }, h( 'p', { class: 'amm-empty', text: T.selectMenu } ) )
			) );
		}

		root.appendChild( layout );

		if ( state.error ) {
			showError( state.error );
		}
	}

	function importCore( button ) {
		if ( ! confirmDialog( 'A beépített WordPress menük másolatként bekerülnek a menükezelőbe. Folytatod?' ) ) {
			return;
		}

		var count = 0;

		runTask( button, 'Átemelés folyamatban…', function () {
			return api( '/tools/import-core', { method: 'POST' } ).then( function ( data ) {
				count = data.count;

				return loadMenus();
			} );
		} ).then( function () {
			renderMenusView();
			toast( count + ' menü átemelve.', 'success' );
		} ).catch( function () {
			renderMenusView();
		} );
	}

	/* ---------------------------------------------------------------
	 * Beállítások nézet
	 * ------------------------------------------------------------ */

	function renderSettingsView() {
		clear( root );

		root.appendChild(
			h( 'div', { class: 'amm-header' },
				h( 'div', { class: 'amm-header__titles' },
					h( 'h1', { text: T.settings } ),
					h( 'p', { text: 'Automatizmusok, sablonpozíciók, hozzáférés és karbantartás.' } )
				),
				h( 'div', { class: 'amm-header__actions' },
					h( 'a', { class: 'amm-btn', href: D.adminUrl, text: T.menus } )
				)
			)
		);

		var cards = h( 'div', { class: 'amm-cards' } );
		var settings = state.settings || {};
		var draft = {};

		/* Menü létrehozása – ugyanaz a művelet, mint a Menük oldalon. */
		var createBody = h( 'div', { class: 'amm-panel__body' } );
		var createName = '';

		createBody.appendChild(
			h( 'form', {
				onSubmit: function ( event ) {
					event.preventDefault();
					createName = ( createName || '' ).trim();

					if ( ! createName ) {
						showError( 'Adj nevet az új menünek.' );

						return;
					}

					api( '/menus', { method: 'POST', body: { name: createName } } ).then( function ( menu ) {
						state.error = '';

						return loadMenus().then( function () {
							renderSettingsView();
							toast( 'A(z) „' + menu.name + '” menü létrejött.', 'success' );
						} );
					} ).catch( fail );
				}
			},
				field( 'Az új menü neve', h( 'input', {
					class: 'amm-input',
					type: 'text',
					autocomplete: 'off',
					placeholder: 'pl. Főmenü',
					onInput: function ( event ) {
						createName = event.target.value;
					}
				} ) ),
				h( 'button', { class: 'amm-btn amm-btn--primary', type: 'submit', text: 'Menü létrehozása' } )
			)
		);

		if ( state.menus.length ) {
			var existing = h( 'ul', { style: 'margin:14px 0 0;padding-left:18px' } );

			state.menus.slice( 0, 12 ).forEach( function ( menu ) {
				existing.appendChild( h( 'li', {},
					h( 'a', { href: D.adminUrl, text: menu.name } ),
					h( 'span', { class: 'amm-field__hint', style: 'display:inline;margin-left:6px', text: '(' + ( menu.item_count || 0 ) + ' ' + T.items + ')' } )
				) );
			} );

			createBody.appendChild( existing );
		}

		cards.appendChild( h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' }, h( 'h2', { class: 'amm-panel__title', text: T.menus } ) ),
			createBody
		) );

		/* Általános */
		var general = h( 'div', { class: 'amm-panel__body' } );

		general.appendChild( checkbox( 'Új oldalak automatikus felvétele', settings.auto_add_new_pages, function ( event ) {
			draft.auto_add_new_pages = event.target.checked;
		}, 'Menünként kapcsolható be – ott dől el, melyik menü kapja meg az új gyökér-oldalakat.' ) );

		general.appendChild( checkbox( 'Törölt oldalak menüelemeinek takarítása', settings.cleanup_deleted, function ( event ) {
			draft.cleanup_deleted = event.target.checked;
		}, 'Végleges törléskor az árva menüelemek automatikusan eltűnnek.' ) );

		general.appendChild( checkbox( 'Gyorsítótár használata', settings.cache_enabled, function ( event ) {
			draft.cache_enabled = event.target.checked;
		} ) );

		general.appendChild( checkbox( 'Alap stíluslap betöltése a látogatói oldalon', settings.frontend_css, function ( event ) {
			draft.frontend_css = event.target.checked;
		} ) );

		general.appendChild( checkbox( 'Gyorslink az admin sávban', settings.admin_bar, function ( event ) {
			draft.admin_bar = event.target.checked;
		} ) );

		general.appendChild( field( 'Gyorsítótár élettartam (mp)', h( 'input', {
			class: 'amm-input',
			type: 'number',
			min: '0',
			value: settings.cache_ttl,
			onInput: function ( event ) {
				draft.cache_ttl = event.target.value;
			}
		} ) ) );

		general.appendChild( field( 'Index küszöb (oldalszám)', h( 'input', {
			class: 'amm-input',
			type: 'number',
			min: '500',
			value: settings.index_threshold,
			onInput: function ( event ) {
				draft.index_threshold = event.target.value;
			}
		} ), 'E fölött a plugin nem tart teljes indexet memóriában, hanem szintenként kérdez le.' ) );

		general.appendChild( field( 'Lapméret a szerkesztőben', h( 'input', {
			class: 'amm-input',
			type: 'number',
			min: '20',
			max: '500',
			value: settings.editor_page_size,
			onInput: function ( event ) {
				draft.editor_page_size = event.target.value;
			}
		} ) ) );

		general.appendChild( h( 'button', {
			class: 'amm-btn amm-btn--primary',
			type: 'button',
			text: T.save,
			disabled: ! D.canConfig,
			onClick: function () {
				api( '/settings', { method: 'POST', body: { settings: draft } } ).then( function ( data ) {
					state.settings = data.settings;
					toast( T.saved, 'success' );
				} ).catch( fail );
			}
		} ) );

		cards.appendChild( h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' }, h( 'h2', { class: 'amm-panel__title', text: 'Általános' } ) ),
			general
		) );

		/* Sablonpozíciók */
		var locations = h( 'div', { class: 'amm-panel__body' } );

		if ( ! state.locations.length ) {
			locations.appendChild( h( 'p', { class: 'amm-empty', text: 'A téma nem regisztrált menüpozíciót.' } ) );
		} else {
			var locDraft = {};

			state.locations.forEach( function ( location ) {
				var select = h( 'select', {
					class: 'amm-select',
					onChange: function ( event ) {
						locDraft[ location.key ] = event.target.value;
					}
				}, h( 'option', { value: '0', text: '— WordPress alapértelmezett —' } ) );

				state.menus.forEach( function ( menu ) {
					select.appendChild( h( 'option', {
						value: menu.id,
						selected: menu.id === location.menu_id,
						text: menu.name
					} ) );
				} );

				locations.appendChild( field( location.label, select ) );
			} );

			locations.appendChild( h( 'button', {
				class: 'amm-btn amm-btn--primary',
				type: 'button',
				text: T.save,
				disabled: ! D.canConfig,
				onClick: function () {
					var merged = {};

					state.locations.forEach( function ( location ) {
						merged[ location.key ] = location.menu_id;
					} );

					Object.keys( locDraft ).forEach( function ( key ) {
						merged[ key ] = locDraft[ key ];
					} );

					api( '/settings', { method: 'POST', body: { settings: { locations: merged } } } ).then( function () {
						return loadSettings();
					} ).then( function () {
						renderSettingsView();
						toast( T.saved, 'success' );
					} ).catch( fail );
				}
			} ) );
		}

		cards.appendChild( h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' }, h( 'h2', { class: 'amm-panel__title', text: 'Sablonpozíciók' } ) ),
			locations
		) );

		/* Szerepkörök */
		var rolesBody = h( 'div', { class: 'amm-panel__body' } );
		var roleDraft = {};

		if ( state.roles ) {
			var table = h( 'table', { class: 'amm-table' } );
			var head = h( 'tr', {}, h( 'th', { text: 'Szerepkör' } ) );

			Object.keys( state.roles.caps ).forEach( function ( cap ) {
				head.appendChild( h( 'th', { class: 'amm-table__center', text: state.roles.caps[ cap ] } ) );
			} );

			table.appendChild( h( 'thead', {}, head ) );

			var tbody = h( 'tbody', {} );

			Object.keys( state.roles.roles ).forEach( function ( roleKey ) {
				var role = state.roles.roles[ roleKey ];

				roleDraft[ roleKey ] = role.caps.slice();

				var row = h( 'tr', {}, h( 'td', { text: role.name } ) );

				Object.keys( state.roles.caps ).forEach( function ( cap ) {
					row.appendChild( h( 'td', { class: 'amm-table__center' },
						h( 'input', {
							type: 'checkbox',
							checked: role.caps.indexOf( cap ) !== -1,
							disabled: 'administrator' === roleKey || ! D.canConfig,
							onChange: function ( event ) {
								if ( event.target.checked ) {
									roleDraft[ roleKey ].push( cap );
								} else {
									roleDraft[ roleKey ] = roleDraft[ roleKey ].filter( function ( item ) {
										return item !== cap;
									} );
								}
							}
						} )
					) );
				} );

				tbody.appendChild( row );
			} );

			table.appendChild( tbody );
			rolesBody.appendChild( table );

			rolesBody.appendChild( h( 'p', { class: 'amm-field__hint', text: 'Az adminisztrátor jogosultsága nem vehető el.' } ) );

			rolesBody.appendChild( h( 'button', {
				class: 'amm-btn amm-btn--primary',
				type: 'button',
				text: T.save,
				disabled: ! D.canConfig,
				onClick: function () {
					api( '/roles', { method: 'POST', body: { roles: roleDraft } } ).then( function ( data ) {
						state.roles.roles = data.roles;
						toast( T.saved, 'success' );
					} ).catch( fail );
				}
			} ) );
		}

		cards.appendChild( h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' }, h( 'h2', { class: 'amm-panel__title', text: 'Hozzáférés' } ) ),
			rolesBody
		) );

		/* Eszközök */
		var tools = h( 'div', { class: 'amm-panel__body' } );

		function toolButton( label, action, busyLabel, hint ) {
			tools.appendChild(
				h( 'div', { style: 'margin-bottom:10px' },
					h( 'button', {
						class: 'amm-btn',
						type: 'button',
						text: label,
						onClick: function ( event ) {
							var message = '';

							runTask( event.currentTarget, busyLabel, function () {
								return api( '/tools/' + action, { method: 'POST' } ).then( function ( data ) {
									message = data.message || T.saved;

									if ( 'import-core' === action ) {
										return loadMenus();
									}
								} );
							} ).then( function () {
								toast( message, 'success' );

								if ( 'import-core' === action ) {
									renderSettingsView();
								}
							} ).catch( function () {} );
						}
					} ),
					hint ? h( 'span', { class: 'amm-field__hint', text: hint } ) : null
				)
			);
		}

		toolButton( 'Gyorsítótár ürítése', 'flush', 'Ürítés…', 'Minden menü újraépül a következő megjelenítéskor.' );
		toolButton( 'Oldalindex előmelegítése', 'prewarm', 'Index építése…', 'Az index felépül, így az első látogató sem vár rá.' );
		toolButton( 'Árva menüelemek törlése', 'orphans', 'Takarítás…', 'A már nem létező oldalakra mutató elemek eltávolítása.' );
		toolButton( 'WordPress menük átemelése', 'import-core', 'Átemelés folyamatban…', 'A beépített menük másolatként bekerülnek.' );

		tools.appendChild(
			h( 'div', { style: 'display:flex;gap:6px;flex-wrap:wrap;margin-top:12px' },
				h( 'button', {
					class: 'amm-btn',
					type: 'button',
					text: 'Exportálás (JSON)',
					onClick: function () {
						api( '/export' ).then( function ( data ) {
							var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
							var url = URL.createObjectURL( blob );
							var link = h( 'a', { href: url, download: 'and-menumanager-export.json' } );

							document.body.appendChild( link );
							link.click();
							link.remove();
							URL.revokeObjectURL( url );
						} ).catch( fail );
					}
				} ),
				h( 'button', {
					class: 'amm-btn',
					type: 'button',
					text: 'Importálás (JSON)',
					onClick: function () {
						var input = h( 'input', { type: 'file', accept: 'application/json', style: 'display:none' } );

						input.addEventListener( 'change', function () {
							var file = input.files[ 0 ];

							if ( ! file ) {
								return;
							}

							file.text().then( function ( text ) {
								return api( '/import', { method: 'POST', body: { payload: JSON.parse( text ) } } );
							} ).then( function ( data ) {
								toast( data.count + ' menü importálva.', 'success' );

								return loadMenus();
							} ).catch( fail );
						} );

						document.body.appendChild( input );
						input.click();
						input.remove();
					}
				} )
			)
		);

		cards.appendChild( h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' }, h( 'h2', { class: 'amm-panel__title', text: 'Karbantartás' } ) ),
			tools
		) );

		/* Állapot */
		var healthBody = h( 'div', { class: 'amm-panel__body' } );

		if ( state.health ) {
			var report = state.health.report;
			var index = state.health.index;

			healthBody.appendChild( h( 'div', { class: 'amm-stats', style: 'margin-bottom:12px' },
				h( 'span', { class: 'amm-stat' }, h( 'strong', { text: String( index.pages ) } ), 'oldal' ),
				h( 'span', { class: 'amm-stat' }, h( 'strong', { text: String( report.total_menus ) } ), 'menü' ),
				h( 'span', { class: 'amm-stat' }, h( 'strong', { text: String( report.missing_objects.length ) } ), 'árva elem' ),
				h( 'span', { class: 'amm-stat' }, h( 'strong', { text: index.indexed ? 'index' : 'közvetlen' } ), 'mód' )
			) );

			if ( report.missing_objects.length ) {
				var missing = h( 'ul', { style: 'margin:0;padding-left:18px' } );

				report.missing_objects.slice( 0, 20 ).forEach( function ( entry ) {
					missing.appendChild( h( 'li', { text: entry.menu_name + ': #' + entry.object_id } ) );
				} );

				healthBody.appendChild( missing );
			}

			if ( state.health.database ) {
				var db = state.health.database;

				healthBody.appendChild( h( 'p', {
					class: 'amm-field__hint',
					text: 'Adatbázis: ' + ( db.tables_ok ? 'a táblák rendben' : 'HIÁNYZÓ TÁBLÁK – nyisd meg újra a Menük oldalt a javításhoz' ) +
						' · séma v' + db.db_version + ' · plugin v' + db.version
				} ) );

				if ( db.last_error ) {
					healthBody.appendChild( h( 'p', { class: 'amm-field__hint', text: 'Utolsó adatbázis-hiba: ' + db.last_error } ) );
				}
			}

			if ( report.empty_menus.length ) {
				healthBody.appendChild( h( 'p', { class: 'amm-field__hint', text: 'Üres menük: ' + report.empty_menus.map( function ( menu ) {
					return menu.name;
				} ).join( ', ' ) } ) );
			}
		}

		cards.appendChild( h( 'section', { class: 'amm-panel' },
			h( 'div', { class: 'amm-panel__head' }, h( 'h2', { class: 'amm-panel__title', text: 'Állapot' } ) ),
			healthBody
		) );

		root.appendChild( cards );

		if ( state.error ) {
			showError( state.error );
		}
	}

	function loadSettings() {
		return api( '/settings' ).then( function ( data ) {
			state.settings = data.settings;
			state.locations = data.locations || [];
		} );
	}

	/* ---------------------------------------------------------------
	 * Indítás
	 * ------------------------------------------------------------ */

	function boot() {
		if ( ! root ) {
			return;
		}

		if ( 'settings' === root.dataset.view ) {
			Promise.all( [
				loadMenus(),
				loadSettings(),
				api( '/roles' ).then( function ( data ) {
					state.roles = data;
				} ).catch( function () {
					state.roles = null;
				} ),
				api( '/health' ).then( function ( data ) {
					state.health = data;
				} ).catch( function () {
					state.health = null;
				} )
			] ).then( renderSettingsView ).catch( function ( error ) {
				fail( error );
				renderSettingsView();
			} );

			return;
		}

		loadMenus().then( function () {
			renderMenusView();

			if ( state.menus.length ) {
				return selectMenu( state.menus[ 0 ].id );
			}
		} ).catch( function ( error ) {
			fail( error );
			renderMenusView();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}());
