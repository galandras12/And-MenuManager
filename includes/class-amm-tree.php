<?php
/**
 * Menüfa felépítése a tárolt szabályokból.
 *
 * A menü nem több ezer sort tárol, hanem néhány szabály-elemet.
 * A tényleges fa (az összes aloldallal) itt, futásidőben áll össze,
 * és gyorsítótárba kerül.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Tree
 */
class AMM_Tree {

	/**
	 * A megjelenítendő fa felépítése.
	 *
	 * @param int   $menu_id Menü azonosító.
	 * @param array $args    Opciók (skip_cache).
	 * @return array Csomópontok fája.
	 */
	public static function build( $menu_id, $args = array() ) {
		$menu = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return array();
		}

		$args      = wp_parse_args( $args, array( 'skip_cache' => false ) );
		$settings  = AMM_Settings::get();
		$cache_key = 'tree_' . $menu['id'] . '_' . self::context_signature();

		if ( $settings['cache_enabled'] && ! $args['skip_cache'] ) {
			$cached = AMM_Cache::get( $cache_key );

			if ( null !== $cached ) {
				return $cached;
			}
		}

		$tree = self::resolve( $menu );

		if ( $settings['cache_enabled'] && ! $args['skip_cache'] ) {
			$ttl = $menu['settings']['cache_ttl'] > 0 ? $menu['settings']['cache_ttl'] : $settings['cache_ttl'];
			AMM_Cache::set( $cache_key, $tree, $ttl );
		}

		return $tree;
	}

	/**
	 * A gyorsítótár kulcsát megkülönböztető környezeti aláírás.
	 *
	 * A láthatósági szabályok szerepkör- és bejelentkezés-függők, ezért
	 * ezek részei a kulcsnak.
	 *
	 * @return string
	 */
	private static function context_signature() {
		$user  = wp_get_current_user();
		$roles = ( $user && $user->exists() ) ? $user->roles : array( 'guest' );

		sort( $roles );

		$parts = array(
			implode( ',', $roles ),
			is_user_logged_in() ? '1' : '0',
			(string) get_locale(),
			gmdate( 'YmdH' ), // Az időzített megjelenítés óránként frissül.
		);

		/**
		 * A menü gyorsítótár környezeti aláírásának részei.
		 *
		 * @param array $parts Aláírás összetevői.
		 */
		$parts = apply_filters( 'amm_cache_context_parts', $parts );

		return md5( implode( '|', $parts ) );
	}

	/**
	 * A fa tényleges feloldása.
	 *
	 * @param array $menu Menü rekord.
	 * @return array
	 */
	private static function resolve( $menu ) {
		$items    = AMM_Item_Repository::get_for_menu( $menu['id'] );
		$settings = $menu['settings'];
		$excluded = array_flip( $settings['excluded'] );
		$by_parent = array();

		foreach ( $items as $item ) {
			$by_parent[ $item['parent_id'] ][] = $item;
		}

		$tree = self::walk_items( $by_parent, 0, $menu, $excluded, 1 );

		if ( $settings['show_home'] ) {
			array_unshift(
				$tree,
				array(
					'key'         => 'home',
					'item_id'     => 0,
					'object_id'   => 0,
					'type'        => 'custom',
					'title'       => __( 'Főoldal', 'and-menumanager' ),
					'url'         => home_url( '/' ),
					'target'      => '',
					'rel'         => '',
					'description' => '',
					'classes'     => array( 'amm-item--home' ),
					'source'      => 'auto',
					'children'    => array(),
				)
			);
		}

		/**
		 * A feloldott menüfa.
		 *
		 * @param array $tree Csomópontok.
		 * @param array $menu Menü rekord.
		 */
		return apply_filters( 'amm_resolved_tree', $tree, $menu );
	}

	/**
	 * Tárolt elemek bejárása.
	 *
	 * @param array $by_parent Elemek szülő szerint csoportosítva.
	 * @param int   $parent_id Aktuális szülő.
	 * @param array $menu      Menü rekord.
	 * @param array $excluded  Kizárt bejegyzés-azonosítók (flip).
	 * @param int   $depth     Aktuális mélység.
	 * @return array
	 */
	private static function walk_items( $by_parent, $parent_id, $menu, $excluded, $depth ) {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return array();
		}

		$limit = (int) $menu['settings']['depth_limit'];

		if ( $limit > 0 && $depth > $limit ) {
			return array();
		}

		$nodes = array();

		foreach ( $by_parent[ $parent_id ] as $item ) {
			if ( ! $item['enabled'] || ! self::is_visible( $item ) ) {
				continue;
			}

			if ( $item['object_id'] && isset( $excluded[ $item['object_id'] ] ) ) {
				continue;
			}

			$node = self::item_to_node( $item, $menu );

			if ( ! $node ) {
				continue;
			}

			$node['children'] = self::walk_items( $by_parent, $item['id'], $menu, $excluded, $depth + 1 );

			// Automatikus aloldalak hozzáfűzése.
			if ( $item['auto_children'] && 'post_type' === $item['type'] && $item['object_id'] ) {
				$auto_depth = $item['auto_depth'] > 0 ? $item['auto_depth'] : (int) $menu['settings']['auto_depth'];

				if ( $limit > 0 ) {
					$remaining  = $limit - $depth;
					$auto_depth = $auto_depth > 0 ? min( $auto_depth, $remaining ) : $remaining;
				}

				if ( 0 !== $auto_depth || 0 === $limit ) {
					$auto = self::auto_children(
						$item['object_id'],
						$item['object_type'],
						$auto_depth,
						$excluded,
						$item['auto_order'],
						$menu
					);

					if ( $auto ) {
						$node['children'] = self::merge_children( $node['children'], $auto );
					}
				}
			}

			if ( $node['children'] ) {
				$node['classes'][] = 'amm-item--has-children';
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Automatikusan feloldott aloldalak fája.
	 *
	 * @param int    $object_id   Szülő bejegyzés.
	 * @param string $object_type Bejegyzéstípus.
	 * @param int    $depth       Mélység (0 = korlátlan).
	 * @param array  $excluded    Kizárt azonosítók (flip).
	 * @param string $order       Rendezés.
	 * @param array  $menu        Menü rekord.
	 * @return array
	 */
	private static function auto_children( $object_id, $object_type, $depth, $excluded, $order, $menu ) {
		$map = AMM_Pages::descendants( $object_id, $depth, $object_type, array_keys( $excluded ) );

		if ( empty( $map ) ) {
			return array();
		}

		return self::map_to_nodes( $map, $object_id, $object_type, $order, $menu );
	}

	/**
	 * Szülő => gyerekek térkép átalakítása csomópont-fává.
	 *
	 * @param array  $map         Térkép.
	 * @param int    $parent      Aktuális szülő.
	 * @param string $object_type Bejegyzéstípus.
	 * @param string $order       Rendezés.
	 * @param array  $menu        Menü rekord.
	 * @return array
	 */
	private static function map_to_nodes( $map, $parent, $object_type, $order, $menu ) {
		if ( empty( $map[ $parent ] ) ) {
			return array();
		}

		$children = self::sort_nodes( $map[ $parent ], $order );
		$nodes    = array();

		foreach ( $children as $child ) {
			if ( 'private' === $child['status'] && $menu['settings']['hide_private'] && ! current_user_can( 'read_private_pages' ) ) {
				continue;
			}

			$node = array(
				'key'         => 'auto-' . $child['id'],
				'item_id'     => 0,
				'object_id'   => (int) $child['id'],
				'object_type' => $object_type,
				'type'        => 'post_type',
				'title'       => self::node_title( $child ),
				'url'         => AMM_Pages::permalink( $child['id'], $object_type ),
				'target'      => '',
				'rel'         => '',
				'description' => '',
				'classes'     => array( 'amm-item--auto' ),
				'source'      => 'auto',
				'children'    => self::map_to_nodes( $map, $child['id'], $object_type, $order, $menu ),
			);

			if ( $node['children'] ) {
				$node['classes'][] = 'amm-item--has-children';
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Cím kiszámítása (üres cím kezelése).
	 *
	 * @param array $node Index csomópont.
	 * @return string
	 */
	private static function node_title( $node ) {
		$title = trim( (string) $node['title'] );

		if ( '' === $title ) {
			/* translators: %d: bejegyzés azonosító. */
			$title = sprintf( __( '(cím nélkül – #%d)', 'and-menumanager' ), (int) $node['id'] );
		}

		return $title;
	}

	/**
	 * Rendezés.
	 *
	 * @param array  $nodes Index csomópontok.
	 * @param string $order Rendezési mód.
	 * @return array
	 */
	private static function sort_nodes( $nodes, $order ) {
		switch ( $order ) {
			case 'title':
				usort(
					$nodes,
					function ( $a, $b ) {
						return strnatcasecmp( $a['title'], $b['title'] );
					}
				);
				break;

			case 'slug':
				usort(
					$nodes,
					function ( $a, $b ) {
						return strnatcasecmp( $a['slug'], $b['slug'] );
					}
				);
				break;

			case 'date':
				usort(
					$nodes,
					function ( $a, $b ) {
						return strcmp( isset( $b['date'] ) ? $b['date'] : '', isset( $a['date'] ) ? $a['date'] : '' );
					}
				);
				break;

			case 'menu_order':
			default:
				usort(
					$nodes,
					function ( $a, $b ) {
						if ( $a['order'] === $b['order'] ) {
							return strnatcasecmp( $a['title'], $b['title'] );
						}

						return $a['order'] < $b['order'] ? -1 : 1;
					}
				);
				break;
		}

		return $nodes;
	}

	/**
	 * Kézi és automatikus gyerekek összefésülése.
	 *
	 * A kézzel rögzített (kiemelt) elemek elöl maradnak, és az azonos
	 * bejegyzésre mutató automatikus másolat kimarad.
	 *
	 * @param array $manual Kézi elemek.
	 * @param array $auto   Automatikus elemek.
	 * @return array
	 */
	private static function merge_children( $manual, $auto ) {
		$seen = array();

		foreach ( $manual as $node ) {
			if ( ! empty( $node['object_id'] ) ) {
				$seen[ (int) $node['object_id'] ] = true;
			}
		}

		foreach ( $auto as $node ) {
			if ( ! empty( $node['object_id'] ) && isset( $seen[ (int) $node['object_id'] ] ) ) {
				continue;
			}

			$manual[] = $node;
		}

		return $manual;
	}

	/**
	 * Tárolt elem átalakítása csomóponttá.
	 *
	 * @param array $item Elem.
	 * @param array $menu Menü rekord.
	 * @return array|null
	 */
	private static function item_to_node( $item, $menu ) {
		$title = $item['title'];
		$url   = '';

		switch ( $item['type'] ) {
			case 'post_type':
				$node = AMM_Pages::get_node( $item['object_id'], $item['object_type'] );

				if ( ! $node ) {
					return null; // Törölt oldal – csendben kimarad.
				}

				if ( 'private' === $node['status'] && $menu['settings']['hide_private'] && ! current_user_can( 'read_private_pages' ) ) {
					return null;
				}

				if ( '' === $title ) {
					$title = self::node_title( $node );
				}

				$url = AMM_Pages::permalink( $item['object_id'], $item['object_type'] );
				break;

			case 'taxonomy':
				$term = get_term( $item['object_id'] );

				if ( ! $term || is_wp_error( $term ) ) {
					return null;
				}

				if ( '' === $title ) {
					$title = $term->name;
				}

				$link = get_term_link( $term );
				$url  = is_wp_error( $link ) ? '' : $link;
				break;

			case 'archive':
				$link = get_post_type_archive_link( $item['object_type'] );
				$url  = $link ? $link : '';

				if ( '' === $title ) {
					$object = get_post_type_object( $item['object_type'] );
					$title  = $object ? $object->labels->name : $item['object_type'];
				}
				break;

			case 'heading':
				$url = '';
				break;

			case 'custom':
			default:
				$url = $item['url'];
				break;
		}

		if ( '' === trim( (string) $title ) ) {
			return null;
		}

		$classes = array();

		if ( '' !== $item['css_class'] ) {
			$classes = preg_split( '/\s+/', $item['css_class'] );
		}

		if ( 'heading' === $item['type'] ) {
			$classes[] = 'amm-item--heading';
		}

		return array(
			'key'         => 'item-' . $item['id'],
			'item_id'     => (int) $item['id'],
			'object_id'   => (int) $item['object_id'],
			'object_type' => $item['object_type'],
			'type'        => $item['type'],
			'title'       => $title,
			'url'         => $url,
			'target'      => $item['target'],
			'rel'         => $item['link_rel'],
			'description' => $item['description'],
			'classes'     => array_values( array_filter( $classes ) ),
			'source'      => 'item',
			'children'    => array(),
		);
	}

	/**
	 * Láthatósági szabályok kiértékelése.
	 *
	 * @param array $item Elem.
	 * @return bool
	 */
	public static function is_visible( $item ) {
		$rules = $item['visibility'];

		if ( 'in' === $rules['login'] && ! is_user_logged_in() ) {
			return false;
		}

		if ( 'out' === $rules['login'] && is_user_logged_in() ) {
			return false;
		}

		if ( ! empty( $rules['roles'] ) ) {
			$user = wp_get_current_user();

			if ( ! $user || ! $user->exists() ) {
				return false;
			}

			if ( ! array_intersect( $rules['roles'], (array) $user->roles ) ) {
				return false;
			}
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( '' !== $rules['start'] && $now < strtotime( $rules['start'] ) ) {
			return false;
		}

		if ( '' !== $rules['end'] && $now > strtotime( $rules['end'] ) ) {
			return false;
		}

		/**
		 * Egy menüelem látható-e.
		 *
		 * @param bool  $visible Láthatóság.
		 * @param array $item    Elem.
		 */
		return apply_filters( 'amm_item_visible', true, $item );
	}

	/**
	 * Szerkesztői fa: csak a tárolt elemek, gyerekszámokkal.
	 *
	 * Szándékosan nem oldja fel a több ezer aloldalt – azokat a felület
	 * igény szerint, kinyitáskor tölti be.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return array
	 */
	public static function editor_tree( $menu_id ) {
		$menu = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return array();
		}

		$items     = AMM_Item_Repository::get_for_menu( $menu_id );
		$by_parent = array();

		foreach ( $items as $item ) {
			$by_parent[ $item['parent_id'] ][] = $item;
		}

		return self::editor_walk( $by_parent, 0, $menu );
	}

	/**
	 * Szerkesztői fa bejárása.
	 *
	 * @param array $by_parent Elemek szülő szerint.
	 * @param int   $parent_id Szülő.
	 * @param array $menu      Menü rekord.
	 * @return array
	 */
	private static function editor_walk( $by_parent, $parent_id, $menu ) {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return array();
		}

		$excluded = array_flip( $menu['settings']['excluded'] );
		$nodes    = array();

		foreach ( $by_parent[ $parent_id ] as $item ) {
			$object      = null;
			$child_count = 0;
			$auto_total  = null;
			$status      = 'ok';

			if ( 'post_type' === $item['type'] && $item['object_id'] ) {
				$object = AMM_Pages::get_node( $item['object_id'], $item['object_type'] );

				if ( ! $object ) {
					$status = 'missing';
				} else {
					$child_count = AMM_Pages::count_children( $item['object_id'], $item['object_type'] );

					if ( $item['auto_children'] && AMM_Pages::use_index( $item['object_type'] ) ) {
						$auto_total = self::count_descendants( $item, $menu );
					}
				}
			}

			$node = array(
				'id'            => (int) $item['id'],
				'parent_id'     => (int) $item['parent_id'],
				'position'      => (int) $item['position'],
				'type'          => $item['type'],
				'object_type'   => $item['object_type'],
				'object_id'     => (int) $item['object_id'],
				'title'         => $item['title'],
				'label'         => '' !== $item['title'] ? $item['title'] : ( $object ? self::node_title( $object ) : $item['title'] ),
				'url'           => $item['url'],
				'target'        => $item['target'],
				'css_class'     => $item['css_class'],
				'link_rel'      => $item['link_rel'],
				'description'   => $item['description'],
				'auto_children' => (bool) $item['auto_children'],
				'auto_depth'    => (int) $item['auto_depth'],
				'auto_order'    => $item['auto_order'],
				'visibility'    => $item['visibility'],
				'enabled'       => (bool) $item['enabled'],
				'excluded'      => isset( $excluded[ $item['object_id'] ] ),
				'child_count'   => $child_count,
				'auto_total'    => $auto_total,
				'status'        => $status,
				'children'      => self::editor_walk( $by_parent, $item['id'], $menu ),
			);

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Automatikusan feloldódó leszármazottak száma.
	 *
	 * @param array $item Elem.
	 * @param array $menu Menü rekord.
	 * @return int
	 */
	private static function count_descendants( $item, $menu ) {
		$depth = $item['auto_depth'] > 0 ? $item['auto_depth'] : (int) $menu['settings']['auto_depth'];
		$map   = AMM_Pages::descendants( $item['object_id'], $depth, $item['object_type'], $menu['settings']['excluded'] );
		$total = 0;

		foreach ( $map as $children ) {
			$total += count( $children );
		}

		return $total;
	}

	/**
	 * Statisztika egy menüről.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return array
	 */
	public static function stats( $menu_id ) {
		$menu = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return array();
		}

		$tree  = self::build( $menu_id, array( 'skip_cache' => true ) );
		$count = 0;
		$depth = 0;

		$walk = function ( $nodes, $level ) use ( &$walk, &$count, &$depth ) {
			foreach ( $nodes as $node ) {
				++$count;
				$depth = max( $depth, $level );

				if ( $node['children'] ) {
					$walk( $node['children'], $level + 1 );
				}
			}
		};

		$walk( $tree, 1 );

		return array(
			'links'    => $count,
			'depth'    => $depth,
			'items'    => count( AMM_Item_Repository::get_for_menu( $menu_id ) ),
			'excluded' => count( $menu['settings']['excluded'] ),
		);
	}
}
