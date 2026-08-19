<?php
/**
 * Oldalhierarchia-index.
 *
 * Ez a plugin sebességének a lelke. A WordPress beépített menüszerkesztője
 * teljes WP_Post objektumokat tölt be minden oldalhoz (több ezer oldalnál
 * ez memóriaéhes és lassú). Itt helyette egyetlen, szűk oszloplistás
 * lekérdezésből épül fel egy lapos index, amit gyorsítótárazunk.
 *
 * Nagyon nagy oldalszám fölött automatikusan "közvetlen" módra vált,
 * ahol csak az éppen kért szint kerül lekérdezésre.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Pages
 */
class AMM_Pages {

	/**
	 * Figyelembe vett bejegyzés-státuszok.
	 *
	 * @return array
	 */
	public static function statuses() {
		/**
		 * A menüben feloldható bejegyzés-státuszok.
		 *
		 * @param array $statuses Státuszok.
		 */
		return apply_filters( 'amm_post_statuses', array( 'publish', 'private' ) );
	}

	/**
	 * Kezelhető bejegyzéstípusok listája (címkékkel).
	 *
	 * @return array
	 */
	public static function post_types() {
		$types = get_post_types( array( 'show_in_nav_menus' => true ), 'objects' );
		$out   = array();

		foreach ( $types as $type ) {
			$out[ $type->name ] = array(
				'name'         => $type->name,
				'label'        => $type->labels->name,
				'hierarchical' => (bool) $type->hierarchical,
			);
		}

		if ( ! isset( $out['page'] ) && post_type_exists( 'page' ) ) {
			$out['page'] = array(
				'name'         => 'page',
				'label'        => __( 'Oldalak', 'and-menumanager' ),
				'hierarchical' => true,
			);
		}

		return $out;
	}

	/**
	 * Elemszám egy bejegyzéstípusra.
	 *
	 * @param string $post_type Bejegyzéstípus.
	 * @return int
	 */
	public static function count( $post_type = 'page' ) {
		$cached = AMM_Cache::get( 'count_' . $post_type );

		if ( null !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})",
				$params
			)
		);
		// phpcs:enable

		AMM_Cache::set( 'count_' . $post_type, $count, HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Használható-e a teljes index (memóriában tartott fa)?
	 *
	 * @param string $post_type Bejegyzéstípus.
	 * @return bool
	 */
	public static function use_index( $post_type = 'page' ) {
		$settings  = AMM_Settings::get();
		$threshold = (int) $settings['index_threshold'];

		return self::count( $post_type ) <= $threshold;
	}

	/**
	 * Teljes index felépítése egy bejegyzéstípusra.
	 *
	 * @param string $post_type Bejegyzéstípus.
	 * @return array {
	 *     @type array $nodes    id => csomópont.
	 *     @type array $children szülő id => gyerek id-k.
	 *     @type array $roots    gyökér id-k.
	 * }
	 */
	public static function get_index( $post_type = 'page' ) {
		$key    = 'index_' . $post_type;
		$cached = AMM_Cache::get( $key );

		if ( null !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent, post_title, post_name, menu_order, post_status, post_password, post_date
				 FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$placeholders})
				 ORDER BY menu_order ASC, post_title ASC",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		$nodes    = array();
		$children = array();

		foreach ( $rows as $row ) {
			$id           = (int) $row['ID'];
			$parent       = (int) $row['post_parent'];
			$nodes[ $id ] = array(
				'id'        => $id,
				'parent'    => $parent,
				'title'     => $row['post_title'],
				'slug'      => $row['post_name'],
				'order'     => (int) $row['menu_order'],
				'status'    => $row['post_status'],
				'protected' => '' !== $row['post_password'],
				'date'      => $row['post_date'],
				'type'      => $post_type,
			);

			if ( ! isset( $children[ $parent ] ) ) {
				$children[ $parent ] = array();
			}

			$children[ $parent ][] = $id;
		}

		// Az árva elemek (törölt szülő) gyökérként jelennek meg.
		$roots = isset( $children[0] ) ? $children[0] : array();

		foreach ( $nodes as $id => $node ) {
			if ( $node['parent'] && ! isset( $nodes[ $node['parent'] ] ) ) {
				$roots[] = $id;
			}
		}

		$index = array(
			'nodes'    => $nodes,
			'children' => $children,
			'roots'    => array_values( array_unique( $roots ) ),
		);

		AMM_Cache::set( $key, $index, DAY_IN_SECONDS );

		return $index;
	}

	/**
	 * Egy csomópont adatai.
	 *
	 * @param int    $id        Bejegyzés azonosító.
	 * @param string $post_type Bejegyzéstípus.
	 * @return array|null
	 */
	public static function get_node( $id, $post_type = 'page' ) {
		$id = (int) $id;

		if ( $id <= 0 ) {
			return null;
		}

		if ( self::use_index( $post_type ) ) {
			$index = self::get_index( $post_type );

			return isset( $index['nodes'][ $id ] ) ? $index['nodes'][ $id ] : null;
		}

		return AMM_Cache::remember_runtime(
			'node_' . $post_type . '_' . $id,
			function () use ( $id, $post_type ) {
				global $wpdb;

				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID, post_parent, post_title, post_name, menu_order, post_status, post_password, post_date
						 FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s",
						$id,
						$post_type
					),
					ARRAY_A
				);

				if ( ! $row ) {
					return null;
				}

				return array(
					'id'        => (int) $row['ID'],
					'parent'    => (int) $row['post_parent'],
					'title'     => $row['post_title'],
					'slug'      => $row['post_name'],
					'order'     => (int) $row['menu_order'],
					'status'    => $row['post_status'],
					'protected' => '' !== $row['post_password'],
					'date'      => $row['post_date'],
					'type'      => $post_type,
				);
			}
		);
	}

	/**
	 * Egy szint gyermekeinek lekérése.
	 *
	 * @param int    $parent    Szülő azonosító (0 = gyökér).
	 * @param string $post_type Bejegyzéstípus.
	 * @return array Csomópontok listája.
	 */
	public static function get_children( $parent = 0, $post_type = 'page' ) {
		$parent = (int) $parent;

		if ( self::use_index( $post_type ) ) {
			$index = self::get_index( $post_type );
			$ids   = 0 === $parent ? $index['roots'] : ( isset( $index['children'][ $parent ] ) ? $index['children'][ $parent ] : array() );
			$out   = array();

			foreach ( $ids as $id ) {
				if ( isset( $index['nodes'][ $id ] ) ) {
					$out[] = $index['nodes'][ $id ];
				}
			}

			return $out;
		}

		global $wpdb;

		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses, array( $parent ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent, post_title, post_name, menu_order, post_status, post_password, post_date
				 FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$placeholders}) AND post_parent = %d
				 ORDER BY menu_order ASC, post_title ASC",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'id'        => (int) $row['ID'],
				'parent'    => (int) $row['post_parent'],
				'title'     => $row['post_title'],
				'slug'      => $row['post_name'],
				'order'     => (int) $row['menu_order'],
				'status'    => $row['post_status'],
				'protected' => '' !== $row['post_password'],
				'date'      => $row['post_date'],
				'type'      => $post_type,
			);
		}

		return $out;
	}

	/**
	 * Van-e gyermeke egy elemnek?
	 *
	 * @param int    $id        Azonosító.
	 * @param string $post_type Bejegyzéstípus.
	 * @return bool
	 */
	public static function has_children( $id, $post_type = 'page' ) {
		if ( self::use_index( $post_type ) ) {
			$index = self::get_index( $post_type );

			return ! empty( $index['children'][ (int) $id ] );
		}

		global $wpdb;

		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses, array( (int) $id ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$placeholders}) AND post_parent = %d LIMIT 1",
				$params
			)
		);
		// phpcs:enable
	}

	/**
	 * Közvetlen gyermekek száma.
	 *
	 * @param int    $parent    Szülő azonosító.
	 * @param string $post_type Bejegyzéstípus.
	 * @return int
	 */
	public static function count_children( $parent = 0, $post_type = 'page' ) {
		$parent = (int) $parent;

		if ( self::use_index( $post_type ) ) {
			$index = self::get_index( $post_type );

			if ( 0 === $parent ) {
				return count( $index['roots'] );
			}

			return isset( $index['children'][ $parent ] ) ? count( $index['children'][ $parent ] ) : 0;
		}

		global $wpdb;

		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses, array( $parent ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$placeholders}) AND post_parent = %d",
				$params
			)
		);
		// phpcs:enable
	}

	/**
	 * Egy szint gyermekei lapozva.
	 *
	 * Így egy több ezer testvért tartalmazó szint sem terheli a felületet.
	 *
	 * @param int    $parent    Szülő azonosító.
	 * @param string $post_type Bejegyzéstípus.
	 * @param int    $offset    Eltolás.
	 * @param int    $limit     Elemszám.
	 * @return array
	 */
	public static function get_children_page( $parent = 0, $post_type = 'page', $offset = 0, $limit = 100 ) {
		$parent = (int) $parent;
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, min( 500, (int) $limit ) );

		if ( self::use_index( $post_type ) ) {
			$all = self::get_children( $parent, $post_type );

			return array_slice( $all, $offset, $limit );
		}

		global $wpdb;

		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses, array( $parent, $limit, $offset ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent, post_title, post_name, menu_order, post_status, post_password, post_date
				 FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$placeholders}) AND post_parent = %d
				 ORDER BY menu_order ASC, post_title ASC
				 LIMIT %d OFFSET %d",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'id'        => (int) $row['ID'],
				'parent'    => (int) $row['post_parent'],
				'title'     => $row['post_title'],
				'slug'      => $row['post_name'],
				'order'     => (int) $row['menu_order'],
				'status'    => $row['post_status'],
				'protected' => '' !== $row['post_password'],
				'date'      => $row['post_date'],
				'type'      => $post_type,
			);
		}

		return $out;
	}

	/**
	 * Gyermekszám egyszerre több elemre.
	 *
	 * Így a választó listája egyetlen lekérdezésből tudja, melyik sor
	 * alatt van tovább tartalom – nem indul soronként külön kérdés.
	 *
	 * @param array  $ids       Azonosítók.
	 * @param string $post_type Bejegyzéstípus.
	 * @return array id => darabszám.
	 */
	public static function count_children_batch( $ids, $post_type = 'page' ) {
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		$out = array();

		if ( empty( $ids ) ) {
			return $out;
		}

		if ( self::use_index( $post_type ) ) {
			$index = self::get_index( $post_type );

			foreach ( $ids as $id ) {
				$out[ $id ] = isset( $index['children'][ $id ] ) ? count( $index['children'][ $id ] ) : 0;
			}

			return $out;
		}

		global $wpdb;

		$statuses    = self::statuses();
		$status_ph   = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$id_ph       = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params      = array_merge( array( $post_type ), $statuses, $ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_parent, COUNT(*) AS total FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$status_ph}) AND post_parent IN ({$id_ph})
				 GROUP BY post_parent",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		foreach ( $ids as $id ) {
			$out[ $id ] = 0;
		}

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['post_parent'] ] = (int) $row['total'];
		}

		return $out;
	}

	/**
	 * Keresés cím és slug alapján.
	 *
	 * Mindig közvetlen SQL, LIMIT-tel: nagy oldalszámnál is állandó költségű.
	 *
	 * @param string $term      Keresőkifejezés.
	 * @param string $post_type Bejegyzéstípus.
	 * @param int    $limit     Maximum találat.
	 * @return array
	 */
	public static function search( $term, $post_type = 'page', $limit = 50 ) {
		global $wpdb;

		$term = trim( (string) $term );

		if ( '' === $term ) {
			return array();
		}

		$like         = '%' . $wpdb->esc_like( $term ) . '%';
		$statuses     = self::statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $post_type ), $statuses, array( $like, $like, (int) $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent, post_title, post_name, menu_order, post_status, post_password, post_date
				 FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status IN ({$placeholders})
				   AND (post_title LIKE %s OR post_name LIKE %s)
				 ORDER BY post_title ASC
				 LIMIT %d",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'id'        => (int) $row['ID'],
				'parent'    => (int) $row['post_parent'],
				'title'     => $row['post_title'],
				'slug'      => $row['post_name'],
				'order'     => (int) $row['menu_order'],
				'status'    => $row['post_status'],
				'protected' => '' !== $row['post_password'],
				'date'      => $row['post_date'],
				'type'      => $post_type,
			);
		}

		return $out;
	}

	/**
	 * Leszármazottak feloldása szintenként.
	 *
	 * @param int    $id        Kiindulási elem.
	 * @param int    $depth     Maximális mélység (0 = korlátlan).
	 * @param string $post_type Bejegyzéstípus.
	 * @param array  $excluded  Kihagyandó azonosítók (az ág is kimarad).
	 * @return array Szülő id => gyerek csomópontok.
	 */
	public static function descendants( $id, $depth = 0, $post_type = 'page', $excluded = array() ) {
		$id       = (int) $id;
		$excluded = array_flip( array_map( 'intval', $excluded ) );
		$map      = array();
		$level    = array( $id );
		$current  = 0;
		$guard    = 0;

		while ( ! empty( $level ) ) {
			++$current;

			if ( $depth > 0 && $current > $depth ) {
				break;
			}

			if ( ++$guard > 50 ) {
				break; // Körkörös hierarchia elleni védelem.
			}

			$next = array();

			foreach ( $level as $parent_id ) {
				$children = self::get_children( $parent_id, $post_type );
				$kept     = array();

				foreach ( $children as $child ) {
					if ( isset( $excluded[ $child['id'] ] ) ) {
						continue;
					}

					$kept[] = $child;
					$next[] = $child['id'];
				}

				if ( $kept ) {
					$map[ $parent_id ] = $kept;
				}
			}

			$level = $next;
		}

		return $map;
	}

	/**
	 * Szülő-lánc (morzsamenü) egy elemhez.
	 *
	 * @param int    $id        Azonosító.
	 * @param string $post_type Bejegyzéstípus.
	 * @return array Csomópontok a gyökértől lefelé.
	 */
	public static function breadcrumb( $id, $post_type = 'page' ) {
		$chain = array();
		$node  = self::get_node( $id, $post_type );
		$guard = 0;

		while ( $node && ++$guard < 50 ) {
			array_unshift( $chain, $node );

			if ( ! $node['parent'] ) {
				break;
			}

			$node = self::get_node( $node['parent'], $post_type );
		}

		return $chain;
	}

	/**
	 * Gyors permalink előállítás lekérdezés nélkül.
	 *
	 * Hierarchikus típusnál az útvonalat az indexből építjük fel, így
	 * több száz link kirajzolása sem indít külön lekérdezéseket.
	 *
	 * @param int    $id        Azonosító.
	 * @param string $post_type Bejegyzéstípus.
	 * @return string
	 */
	public static function permalink( $id, $post_type = 'page' ) {
		$id = (int) $id;

		if ( 'page' === $post_type && (int) get_option( 'page_on_front' ) === $id ) {
			return home_url( '/' );
		}

		$structure = get_option( 'permalink_structure' );
		$types     = self::post_types();
		$is_hier   = isset( $types[ $post_type ] ) ? $types[ $post_type ]['hierarchical'] : false;

		if ( empty( $structure ) || ! $is_hier || 'page' !== $post_type ) {
			$link = get_permalink( $id );

			return $link ? $link : '';
		}

		$segments = array();
		$node     = self::get_node( $id, $post_type );
		$guard    = 0;

		while ( $node && ++$guard < 50 ) {
			array_unshift( $segments, $node['slug'] );

			if ( ! $node['parent'] ) {
				break;
			}

			$node = self::get_node( $node['parent'], $post_type );
		}

		if ( empty( $segments ) ) {
			return '';
		}

		return user_trailingslashit( home_url( '/' . implode( '/', $segments ) ) );
	}
}
