/**
 * And-MenuManager – blokk szerkesztői felülete (build lépés nélkül).
 */
(function ( blocks, element, components, blockEditor, serverSideRender, apiFetch ) {
	'use strict';

	var el = element.createElement;
	var useState = element.useState;
	var useEffect = element.useEffect;
	var data = window.AMM_BLOCK || {};

	function Edit( props ) {
		var menusState = useState( [] );
		var menus = menusState[ 0 ];
		var setMenus = menusState[ 1 ];

		useEffect( function () {
			apiFetch( { path: data.menusPath || '/and-menumanager/v1/menus?per_page=200' } ).then( function ( response ) {
				setMenus( ( response && response.items ) || [] );
			} ).catch( function () {
				setMenus( [] );
			} );
		}, [] );

		var options = [ { label: '— válassz menüt —', value: 0 } ].concat(
			menus.map( function ( menu ) {
				return { label: menu.name, value: menu.id };
			} )
		);

		var controls = el(
			blockEditor.InspectorControls,
			{},
			el(
				components.PanelBody,
				{ title: 'Menü beállításai', initialOpen: true },
				el( components.SelectControl, {
					label: 'Menü',
					value: props.attributes.menuId,
					options: options,
					onChange: function ( value ) {
						props.setAttributes( { menuId: parseInt( value, 10 ) || 0 } );
					}
				} ),
				el( components.SelectControl, {
					label: 'Megjelenés',
					value: props.attributes.display,
					options: [
						{ label: 'Menü alapbeállítása', value: '' },
						{ label: 'Függőleges', value: 'vertical' },
						{ label: 'Lenyíló (harmonika)', value: 'accordion' },
						{ label: 'Vízszintes', value: 'horizontal' },
						{ label: 'Oszlopos', value: 'columns' }
					],
					onChange: function ( value ) {
						props.setAttributes( { display: value } );
					}
				} ),
				el( components.RangeControl, {
					label: 'Maximális mélység (0 = korlátlan)',
					value: props.attributes.depth,
					min: 0,
					max: 8,
					onChange: function ( value ) {
						props.setAttributes( { depth: value || 0 } );
					}
				} )
			)
		);

		var preview;

		if ( ! props.attributes.menuId ) {
			preview = el( components.Placeholder, {
				icon: 'menu-alt3',
				label: 'And-MenuManager menü',
				instructions: 'Válassz menüt a jobb oldali beállítások panelen.'
			}, el( components.SelectControl, {
				value: props.attributes.menuId,
				options: options,
				onChange: function ( value ) {
					props.setAttributes( { menuId: parseInt( value, 10 ) || 0 } );
				}
			} ) );
		} else {
			preview = el( serverSideRender, {
				block: 'and-menumanager/menu',
				attributes: props.attributes
			} );
		}

		return el( 'div', blockEditor.useBlockProps(), controls, preview );
	}

	blocks.registerBlockType( 'and-menumanager/menu', {
		edit: Edit,
		save: function () {
			return null;
		}
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.serverSideRender,
	window.wp.apiFetch
));
