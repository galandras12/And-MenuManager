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
	 * A futó átemelés alatt egyszer betöltött menülista.
	 *
	 * @var array|null
	 */
	private static $menu_cache = null;

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
	 * Átállás és újraellenőrzés: a beépített WordPress menük átemelése.
	 *
	 * Az ismételt futtatás nem duplikál: a már átemelt menüket megkeresi,
	 * összeveti a WordPress menü tartalmával, és csak a hiányzó
	 * menüpontokat pótolja. A kézzel hozzáadott elemeket nem bántja.
	 *
	 * @return array|WP_Error
	 */
	public static function import_core_menus() {
		$core_menus = wp_get_nav_menus();

		if ( empty( $core_menus ) || is_wp_error( $core_menus ) ) {
			return new WP_Error( 'amm_no_core_menus', __( 'Nincs átemelhető WordPress menü.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$settings  = AMM_Settings::get();
		$locations = get_nav_menu_locations();
		$results   = array();
		$created   = 0;
		$synced    = 0;
		$added     = 0;

		// Kötegelt mód: a gyorsítótár ürítése egyszer fut le a végén,
		// nem menüpontonként. Nagy menüknél ez a művelet dandárja.
		AMM_Cache::suspend();
		self::$menu_cache = null;

		foreach ( $core_menus as $core_menu ) {
			$target = self::find_imported_menu( $core_menu );
			$result = $target ? self::sync_from_core( $core_menu, $target ) : self::create_from_core( $core_menu );

			if ( is_wp_error( $result ) ) {
				AMM_Log::add(
					$result->get_error_message(),
					/* translators: %s: menü neve. */
					sprintf( __( 'Átemelés: %s', 'and-menumanager' ), $core_menu->name )
				);

				continue;
			}

			if ( 'created' === $result['status'] ) {
				++$created;
			} else {
				++$synced;
			}

			$added += $result['added'];

			// A sablonpozíciót csak akkor töltjük ki, ha még üres – a
			// szándékosan másra állított pozíciót nem írjuk felül.
			foreach ( $locations as $location => $term_id ) {
				if ( (int) $term_id !== (int) $core_menu->term_id ) {
					continue;
				}

				if ( empty( $settings['locations'][ $location ] ) ) {
					$settings['locations'][ $location ] = $result['id'];
				}
			}

			$results[] = $result;
		}

		AMM_Item_Repository::flush_touched();
		self::$menu_cache = null;

		AMM_Settings::update( array( 'locations' => $settings['locations'] ) );
		AMM_Cache::flush();
		AMM_Cache::resume();

		return array(
			'menus'   => $results,
			'created' => $created,
			'synced'  => $synced,
			'added'   => $added,
			'count'   => count( $results ),
			'message' => self::import_message( $created, $synced, $added ),
		);
	}

	/**
	 * Összefoglaló üzenet az átemelésről.
	 *
	 * @param int $created Újonnan létrehozott menük.
	 * @param int $synced  Újraellenőrzött menük.
	 * @param int $added   Pótolt menüpontok.
	 * @return string
	 */
	private static function import_message( $created, $synced, $added ) {
		$parts = array();

		if ( $created ) {
			/* translators: %d: menük száma. */
			$parts[] = sprintf( __( '%d menü átemelve', 'and-menumanager' ), $created );
		}

		if ( $synced ) {
			/* translators: %d: menük száma. */
			$parts[] = sprintf( __( '%d menü újraellenőrizve', 'and-menumanager' ), $synced );
		}

		if ( $added ) {
			/* translators: %d: menüpontok száma. */
			$parts[] = sprintf( __( '%d hiányzó menüpont pótolva', 'and-menumanager' ), $added );
		} elseif ( $synced && ! $created ) {
			$parts[] = __( 'nem hiányzott semmi', 'and-menumanager' );
		}

		if ( empty( $parts ) ) {
			return __( 'Nem volt teendő.', 'and-menumanager' );
		}

		return implode( ', ', $parts ) . '.';
	}

	/**
	 * Egy WordPress menühöz tartozó, korábban átemelt menü megkeresése.
	 *
	 * Elsődlegesen a mentett eredet (term_id) alapján, majd slug és név
	 * szerint – így a 0.3 előtt átemelt menük is felismerhetők.
	 *
	 * @param WP_Term $core_menu WordPress menü.
	 * @return array|null
	 */
	private static function find_imported_menu( $core_menu ) {
		if ( null === self::$menu_cache ) {
			$all              = AMM_Menu_Repository::all( array( 'per_page' => 500 ) );
			self::$menu_cache = $all['items'];
		}

		$by_slug = null;
		$by_name = null;

		foreach ( self::$menu_cache as $menu ) {
			$source = isset( $menu['settings']['source'] ) ? $menu['settings']['source'] : array();

			if ( ! empty( $source['term_id'] ) && (int) $source['term_id'] === (int) $core_menu->term_id ) {
				return $menu;
			}

			if ( null === $by_slug && $menu['slug'] === $core_menu->slug ) {
				$by_slug = $menu;
			}

			if ( null === $by_name && 0 === strcasecmp( $menu['name'], $core_menu->name ) ) {
				$by_name = $menu;
			}
		}

		return $by_slug ? $by_slug : $by_name;
	}

	/**
	 * Az eredet elmentése a menü beállításaiba.
	 *
	 * @param int     $menu_id   Menü azonosító.
	 * @param WP_Term $core_menu WordPress menü.
	 * @return void
	 */
	private static function stamp_source( $menu_id, $core_menu ) {
		AMM_Menu_Repository::update(
			$menu_id,
			array(
				'settings' => array(
					'source' => array(
						'type'    => 'core',
						'term_id' => (int) $core_menu->term_id,
						'slug'    => $core_menu->slug,
						'checked' => current_time( 'mysql' ),
					),
				),
			)
		);
	}

	/**
	 * Egy WordPress menüpont átalakítása saját menüelem-adattá.
	 *
	 * @param object $core_item WordPress menüpont.
	 * @return array
	 */
	private static function core_item_to_data( $core_item ) {
		$data = array(
			'title'       => $core_item->title,
			'target'      => $core_item->target,
			'css_class'   => is_array( $core_item->classes ) ? trim( implode( ' ', $core_item->classes ) ) : (string) $core_item->classes,
			'link_rel'    => $core_item->xfn,
			'description' => $core_item->description,
			'object_type' => '',
			'object_id'   => 0,
			'url'         => '',
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
		if ( 'post_type' === $data['type'] && $data['object_id'] ) {
			$node = AMM_Pages::get_node( $data['object_id'], $data['object_type'] );

			if ( $node && $node['title'] === $data['title'] ) {
				$data['title'] = '';
			}
		}

		return $data;
	}

	/**
	 * Menüelem azonosító ujjlenyomata az összevetéshez.
	 *
	 * A tartalomra mutató elemeket a céljuk azonosítja (nem a címük), az
	 * egyedi linkeket az URL és a felirat.
	 *
	 * @param array $item Menüelem adatai.
	 * @return string
	 */
	private static function item_key( $item ) {
		$type        = isset( $item['type'] ) ? $item['type'] : 'custom';
		$object_type = isset( $item['object_type'] ) ? $item['object_type'] : '';
		$object_id   = isset( $item['object_id'] ) ? (int) $item['object_id'] : 0;
		$url         = isset( $item['url'] ) ? (string) $item['url'] : '';
		$title       = isset( $item['title'] ) ? (string) $item['title'] : '';

		switch ( $type ) {
			case 'post_type':
			case 'taxonomy':
				return $type . '|' . $object_type . '|' . $object_id;

			case 'archive':
				return 'archive|' . $object_type;

			default:
				return $type . '|' . strtolower( untrailingslashit( $url ) ) . '|' . strtolower( trim( $title ) );
		}
	}

	/**
	 * Új menü létrehozása egy WordPress menüből.
	 *
	 * @param WP_Term $core_menu WordPress menü.
	 * @return array|WP_Error
	 */
	private static function create_from_core( $core_menu ) {
		$menu_id = AMM_Menu_Repository::insert(
			array(
				'name'        => $core_menu->name,
				'slug'        => $core_menu->slug,
				'description' => __( 'Átemelve a beépített WordPress menükezelőből.', 'and-menumanager' ),
			)
		);

		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		$core_items = wp_get_nav_menu_items( $core_menu->term_id, array( 'update_post_term_cache' => false ) );
		$map        = array();
		$added      = 0;

		foreach ( (array) $core_items as $core_item ) {
			$parent = (int) $core_item->menu_item_parent;

			$data              = self::core_item_to_data( $core_item );
			$data['parent_id'] = ( $parent && isset( $map[ $parent ] ) ) ? $map[ $parent ] : 0;
			$data['position']  = (int) $core_item->menu_order;

			$new_id = AMM_Item_Repository::insert( $menu_id, $data );

			if ( ! is_wp_error( $new_id ) ) {
				$map[ (int) $core_item->ID ] = $new_id;
				++$added;
			}
		}

		self::stamp_source( $menu_id, $core_menu );

		if ( is_array( self::$menu_cache ) ) {
			$fresh = AMM_Menu_Repository::get( $menu_id );

			if ( $fresh ) {
				self::$menu_cache[] = $fresh;
			}
		}

		return array(
			'id'      => $menu_id,
			'name'    => $core_menu->name,
			'status'  => 'created',
			'added'   => $added,
			'matched' => 0,
			'extra'   => 0,
		);
	}

	/**
	 * Meglévő menü újraellenőrzése a WordPress menü alapján.
	 *
	 * Csak pótol: a hiányzó menüpontokat felveszi, a menüben már meglévő
	 * (akár kézzel hozzáadott) elemeket változatlanul hagyja.
	 *
	 * @param WP_Term $core_menu WordPress menü.
	 * @param array   $menu      Saját menü rekord.
	 * @return array
	 */
	private static function sync_from_core( $core_menu, $menu ) {
		$existing = AMM_Item_Repository::get_for_menu( $menu['id'] );
		$index    = array();

		foreach ( $existing as $item ) {
			$index[ self::item_key( $item ) ] = (int) $item['id'];
		}

		$core_items = wp_get_nav_menu_items( $core_menu->term_id, array( 'update_post_term_cache' => false ) );
		$map        = array();
		$seen       = array();
		$added      = 0;
		$matched    = 0;

		foreach ( (array) $core_items as $core_item ) {
			$data   = self::core_item_to_data( $core_item );
			$key    = self::item_key( $data );
			$parent = (int) $core_item->menu_item_parent;

			if ( isset( $index[ $key ] ) ) {
				$map[ (int) $core_item->ID ] = $index[ $key ];
				$seen[ $key ]                = true;
				++$matched;
				continue;
			}

			$data['parent_id'] = ( $parent && isset( $map[ $parent ] ) ) ? $map[ $parent ] : 0;
			$data['position']  = (int) $core_item->menu_order;

			$new_id = AMM_Item_Repository::insert( $menu['id'], $data );

			if ( is_wp_error( $new_id ) ) {
				continue;
			}

			$map[ (int) $core_item->ID ] = $new_id;
			$index[ $key ]               = $new_id;
			$seen[ $key ]                = true;
			++$added;
		}

		$extra = 0;

		foreach ( $existing as $item ) {
			if ( ! isset( $seen[ self::item_key( $item ) ] ) ) {
				++$extra;
			}
		}

		self::stamp_source( $menu['id'], $core_menu );

		return array(
			'id'      => (int) $menu['id'],
			'name'    => $menu['name'],
			'status'  => 'synced',
			'added'   => $added,
			'matched' => $matched,
			'extra'   => $extra,
		);
	}
}
