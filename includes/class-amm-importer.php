<?php
/**
 * Import / export és átállás a beépített WordPress menükről.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Importer
 */
class AMM_Importer {

	/**
	 * Menük exportálása.
	 *
	 * @param array $ids Menü azonosítók (üres = mind).
	 * @return array
	 */
	public static function export( $ids = array() ) {
		if ( empty( $ids ) ) {
			$result = AMM_Menu_Repository::all( array( 'per_page' => 200 ) );
			$menus  = $result['items'];
		} else {
			$menus = array();

			foreach ( $ids as $id ) {
				$menu = AMM_Menu_Repository::get( $id );

				if ( $menu ) {
					$menus[] = $menu;
				}
			}
		}

		$payload = array(
			'format'    => 'and-menumanager',
			'version'   => AMM_VERSION,
			'exported'  => current_time( 'mysql' ),
			'site'      => home_url( '/' ),
			'menus'     => array(),
		);

		foreach ( $menus as $menu ) {
			$payload['menus'][] = array(
				'name'        => $menu['name'],
				'slug'        => $menu['slug'],
				'description' => $menu['description'],
				'settings'    => $menu['settings'],
				'items'       => AMM_Item_Repository::get_for_menu( $menu['id'] ),
			);
		}

		return $payload;
	}

	/**
	 * Menük importálása.
	 *
	 * @param array $payload Export adat.
	 * @return array
	 */
	public static function import( $payload ) {
		$menus    = isset( $payload['menus'] ) ? (array) $payload['menus'] : array();
		$imported = array();

		foreach ( $menus as $menu ) {
			if ( empty( $menu['name'] ) ) {
				continue;
			}

			$menu_id = AMM_Menu_Repository::insert(
				array(
					'name'        => $menu['name'],
					'slug'        => isset( $menu['slug'] ) ? $menu['slug'] : '',
					'description' => isset( $menu['description'] ) ? $menu['description'] : '',
					'settings'    => isset( $menu['settings'] ) ? $menu['settings'] : array(),
				)
			);

			if ( is_wp_error( $menu_id ) ) {
				continue;
			}

			self::insert_items( $menu_id, isset( $menu['items'] ) ? (array) $menu['items'] : array() );

			$imported[] = array(
				'id'   => $menu_id,
				'name' => $menu['name'],
			);
		}

		AMM_Cache::flush();

		return array(
			'imported' => $imported,
			'count'    => count( $imported ),
		);
	}

	/**
	 * Elemek beszúrása szülő-leképezéssel.
	 *
	 * @param int   $menu_id Menü azonosító.
	 * @param array $items   Elemek (eredeti id / parent_id mezőkkel).
	 * @return void
	 */
	private static function insert_items( $menu_id, $items ) {
		$map     = array();
		$pending = $items;
		$guard   = 0;

		while ( $pending && ++$guard < 50 ) {
			$next = array();

			foreach ( $pending as $item ) {
				$old_parent = isset( $item['parent_id'] ) ? (int) $item['parent_id'] : 0;

				if ( $old_parent && ! isset( $map[ $old_parent ] ) ) {
					$next[] = $item;
					continue;
				}

				$old_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
				$data   = $item;

				unset( $data['id'], $data['menu_id'] );
				$data['parent_id'] = $old_parent ? $map[ $old_parent ] : 0;

				$new_id = AMM_Item_Repository::insert( $menu_id, $data );

				if ( ! is_wp_error( $new_id ) && $old_id ) {
					$map[ $old_id ] = $new_id;
				}
			}

			if ( count( $next ) === count( $pending ) ) {
				break;
			}

			$pending = $next;
		}
	}

	/**
	 * Átállás: a beépített WordPress menük átemelése.
	 *
	 * @return array|WP_Error
	 */
	public static function import_core_menus() {
		$core_menus = wp_get_nav_menus();

		if ( empty( $core_menus ) ) {
			return new WP_Error( 'amm_no_core_menus', __( 'Nincs átemelhető WordPress menü.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$imported  = array();
		$locations = get_nav_menu_locations();
		$settings  = AMM_Settings::get();

		foreach ( $core_menus as $core_menu ) {
			$menu_id = AMM_Menu_Repository::insert(
				array(
					'name'        => $core_menu->name,
					'slug'        => $core_menu->slug,
					'description' => __( 'Átemelve a beépített WordPress menükezelőből.', 'and-menumanager' ),
				)
			);

			if ( is_wp_error( $menu_id ) ) {
				continue;
			}

			$core_items = wp_get_nav_menu_items( $core_menu->term_id, array( 'update_post_term_cache' => false ) );
			$map        = array();

			foreach ( (array) $core_items as $core_item ) {
				$parent = (int) $core_item->menu_item_parent;

				if ( $parent && ! isset( $map[ $parent ] ) ) {
					continue; // A szülő nélküli elemek a következő körben sem lennének helyesek.
				}

				$data = array(
					'parent_id'   => $parent ? $map[ $parent ] : 0,
					'position'    => (int) $core_item->menu_order,
					'title'       => $core_item->title,
					'target'      => $core_item->target,
					'css_class'   => is_array( $core_item->classes ) ? implode( ' ', $core_item->classes ) : (string) $core_item->classes,
					'link_rel'    => $core_item->xfn,
					'description' => $core_item->description,
				);

				switch ( $core_item->type ) {
					case 'post_type':
						$data['type']        = 'post_type';
						$data['object_type'] = $core_item->object;
						$data['object_id']   = (int) $core_item->object_id;
						break;

					case 'taxonomy':
						$data['type']        = 'taxonomy';
						$data['object_type'] = $core_item->object;
						$data['object_id']   = (int) $core_item->object_id;
						break;

					case 'post_type_archive':
						$data['type']        = 'archive';
						$data['object_type'] = $core_item->object;
						break;

					default:
						$data['type'] = 'custom';
						$data['url']  = $core_item->url;
						break;
				}

				// A cím csak akkor marad felülírásként, ha eltér az eredetitől.
				if ( 'post_type' === $data['type'] && ! empty( $data['object_id'] ) ) {
					$node = AMM_Pages::get_node( $data['object_id'], $data['object_type'] );

					if ( $node && $node['title'] === $data['title'] ) {
						$data['title'] = '';
					}
				}

				$new_id = AMM_Item_Repository::insert( $menu_id, $data );

				if ( ! is_wp_error( $new_id ) ) {
					$map[ (int) $core_item->ID ] = $new_id;
				}
			}

			// A sablonpozíció-hozzárendelés átvétele.
			foreach ( $locations as $location => $term_id ) {
				if ( (int) $term_id === (int) $core_menu->term_id ) {
					$settings['locations'][ $location ] = $menu_id;
				}
			}

			$imported[] = array(
				'id'    => $menu_id,
				'name'  => $core_menu->name,
				'items' => count( $map ),
			);
		}

		AMM_Settings::update( array( 'locations' => $settings['locations'] ) );
		AMM_Cache::flush();

		return array(
			'imported' => $imported,
			'count'    => count( $imported ),
		);
	}
}
