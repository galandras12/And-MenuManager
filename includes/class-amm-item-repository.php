<?php
/**
 * Menüelemek adatelérési rétege.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Item_Repository
 */
class AMM_Item_Repository {

	/**
	 * Alapértelmezett láthatósági szabályok.
	 *
	 * @return array
	 */
	public static function default_visibility() {
		return array(
			'roles' => array(),
			'login' => 'any',
			'start' => '',
			'end'   => '',
		);
	}

	/**
	 * Láthatósági szabályok normalizálása.
	 *
	 * @param mixed $visibility Nyers érték.
	 * @return array
	 */
	public static function normalize_visibility( $visibility ) {
		if ( is_string( $visibility ) ) {
			$visibility = json_decode( $visibility, true );
		}

		if ( ! is_array( $visibility ) ) {
			$visibility = array();
		}

		$visibility = wp_parse_args( $visibility, self::default_visibility() );

		$visibility['roles'] = array_values( array_filter( array_map( 'sanitize_key', (array) $visibility['roles'] ) ) );
		$visibility['login'] = in_array( $visibility['login'], array( 'any', 'in', 'out' ), true ) ? $visibility['login'] : 'any';
		$visibility['start'] = self::sanitize_datetime( $visibility['start'] );
		$visibility['end']   = self::sanitize_datetime( $visibility['end'] );

		return $visibility;
	}

	/**
	 * Dátum-idő tisztítása (Y-m-d H:i formátum).
	 *
	 * @param string $value Nyers érték.
	 * @return string
	 */
	private static function sanitize_datetime( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$time = strtotime( $value );

		return $time ? gmdate( 'Y-m-d H:i', $time ) : '';
	}

	/**
	 * Elemtípusok.
	 *
	 * @return array
	 */
	public static function types() {
		return array( 'post_type', 'taxonomy', 'custom', 'archive', 'heading' );
	}

	/**
	 * Sor normalizálása.
	 *
	 * @param array|null $row Adatbázis sor.
	 * @return array|null
	 */
	public static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}

		return array(
			'id'            => (int) $row['id'],
			'menu_id'       => (int) $row['menu_id'],
			'parent_id'     => (int) $row['parent_id'],
			'position'      => (int) $row['position'],
			'type'          => $row['type'],
			'object_type'   => $row['object_type'],
			'object_id'     => (int) $row['object_id'],
			'title'         => (string) $row['title'],
			'url'           => (string) $row['url'],
			'target'        => (string) $row['target'],
			'css_class'     => (string) $row['css_class'],
			'link_rel'      => (string) $row['link_rel'],
			'description'   => (string) $row['description'],
			'auto_children' => (bool) $row['auto_children'],
			'auto_depth'    => (int) $row['auto_depth'],
			'auto_order'    => $row['auto_order'],
			'visibility'    => self::normalize_visibility( $row['visibility'] ),
			'enabled'       => (bool) $row['enabled'],
		);
	}

	/**
	 * Egy elem lekérése.
	 *
	 * @param int $id Azonosító.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = AMM_Installer::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		// phpcs:enable

		return self::hydrate( $row );
	}

	/**
	 * Egy menü összes tárolt eleme.
	 *
	 * Ez szándékosan kevés sor: az aloldalak nem sorokként élnek,
	 * hanem szabályból oldódnak fel.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return array
	 */
	public static function get_for_menu( $menu_id ) {
		global $wpdb;

		$table = AMM_Installer::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE menu_id = %d ORDER BY parent_id ASC, position ASC, id ASC", (int) $menu_id ),
			ARRAY_A
		);
		// phpcs:enable

		$items = array();

		foreach ( (array) $rows as $row ) {
			$items[] = self::hydrate( $row );
		}

		return $items;
	}

	/**
	 * Elemszám menünként (egy lekérdezéssel több menüre).
	 *
	 * @param array $menu_ids Menü azonosítók.
	 * @return array menu_id => darabszám.
	 */
	public static function count_by_menus( $menu_ids ) {
		global $wpdb;

		$menu_ids = array_values( array_filter( array_map( 'intval', (array) $menu_ids ) ) );

		if ( empty( $menu_ids ) ) {
			return array();
		}

		$table        = AMM_Installer::items_table();
		$placeholders = implode( ',', array_fill( 0, count( $menu_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT menu_id, COUNT(*) AS total FROM {$table} WHERE menu_id IN ({$placeholders}) GROUP BY menu_id",
				$menu_ids
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['menu_id'] ] = (int) $row['total'];
		}

		return $out;
	}

	/**
	 * Következő pozíció egy szinten.
	 *
	 * @param int $menu_id   Menü azonosító.
	 * @param int $parent_id Szülő elem azonosító.
	 * @return int
	 */
	public static function next_position( $menu_id, $parent_id = 0 ) {
		global $wpdb;

		$table = AMM_Installer::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(position) FROM {$table} WHERE menu_id = %d AND parent_id = %d",
				(int) $menu_id,
				(int) $parent_id
			)
		);
		// phpcs:enable

		return null === $max ? 0 : ( (int) $max + 1 );
	}

	/**
	 * Bemenő adatok mezőkre bontása.
	 *
	 * @param array $data Nyers adat.
	 * @return array {
	 *     @type array $fields Mezők.
	 *     @type array $format Formátumok.
	 * }
	 */
	private static function prepare_fields( $data ) {
		$fields = array();
		$format = array();

		$text_map = array(
			'title'       => 'sanitize_text_field',
			'target'      => 'sanitize_text_field',
			'css_class'   => 'sanitize_text_field',
			'link_rel'    => 'sanitize_text_field',
			'description' => 'sanitize_text_field',
		);

		foreach ( $text_map as $key => $callback ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = call_user_func( $callback, $data[ $key ] );
				$format[]       = '%s';
			}
		}

		if ( isset( $data['url'] ) ) {
			$fields['url'] = esc_url_raw( $data['url'] );
			$format[]      = '%s';
		}

		if ( isset( $data['type'] ) ) {
			$type           = sanitize_key( $data['type'] );
			$fields['type'] = in_array( $type, self::types(), true ) ? $type : 'custom';
			$format[]       = '%s';
		}

		if ( isset( $data['object_type'] ) ) {
			$fields['object_type'] = sanitize_key( $data['object_type'] );
			$format[]              = '%s';
		}

		foreach ( array( 'object_id', 'parent_id', 'position', 'auto_depth' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = max( 0, (int) $data[ $key ] );
				$format[]       = '%d';
			}
		}

		foreach ( array( 'auto_children', 'enabled' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = ! empty( $data[ $key ] ) ? 1 : 0;
				$format[]       = '%d';
			}
		}

		if ( isset( $data['auto_order'] ) ) {
			$allowed              = array( 'menu_order', 'title', 'date', 'slug' );
			$order                = sanitize_key( $data['auto_order'] );
			$fields['auto_order'] = in_array( $order, $allowed, true ) ? $order : 'menu_order';
			$format[]             = '%s';
		}

		if ( isset( $data['visibility'] ) ) {
			$fields['visibility'] = wp_json_encode( self::normalize_visibility( $data['visibility'] ) );
			$format[]             = '%s';
		}

		return array(
			'fields' => $fields,
			'format' => $format,
		);
	}

	/**
	 * Új elem felvétele.
	 *
	 * @param int   $menu_id Menü azonosító.
	 * @param array $data    Adatok.
	 * @return int|WP_Error
	 */
	public static function insert( $menu_id, $data ) {
		global $wpdb;

		$menu_id  = (int) $menu_id;
		$prepared = self::prepare_fields( $data );
		$fields   = $prepared['fields'];
		$format   = $prepared['format'];

		$fields['menu_id'] = $menu_id;
		$format[]          = '%d';

		if ( ! isset( $fields['position'] ) ) {
			$parent             = isset( $fields['parent_id'] ) ? $fields['parent_id'] : 0;
			$fields['position'] = self::next_position( $menu_id, $parent );
			$format[]           = '%d';
		}

		$result = $wpdb->insert( AMM_Installer::items_table(), $fields, $format );

		if ( false === $result ) {
			return new WP_Error( 'amm_item_insert_failed', __( 'A menüelem mentése nem sikerült.', 'and-menumanager' ), array( 'status' => 500 ) );
		}

		self::touch_menu( $menu_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Elem frissítése.
	 *
	 * @param int   $id   Azonosító.
	 * @param array $data Adatok.
	 * @return true|WP_Error
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$item = self::get( $id );

		if ( ! $item ) {
			return new WP_Error( 'amm_not_found', __( 'A menüelem nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$prepared = self::prepare_fields( $data );

		if ( empty( $prepared['fields'] ) ) {
			return true;
		}

		// Körkörös szülő-hivatkozás megelőzése.
		if ( isset( $prepared['fields']['parent_id'] ) && self::would_loop( (int) $id, (int) $prepared['fields']['parent_id'] ) ) {
			return new WP_Error( 'amm_invalid_parent', __( 'Az elem nem helyezhető a saját leszármazottja alá.', 'and-menumanager' ), array( 'status' => 400 ) );
		}

		$wpdb->update(
			AMM_Installer::items_table(),
			$prepared['fields'],
			array( 'id' => (int) $id ),
			$prepared['format'],
			array( '%d' )
		);

		self::touch_menu( $item['menu_id'] );

		return true;
	}

	/**
	 * Okozna-e kört a szülőváltás?
	 *
	 * @param int $id        Elem azonosító.
	 * @param int $parent_id Új szülő.
	 * @return bool
	 */
	private static function would_loop( $id, $parent_id ) {
		if ( 0 === $parent_id ) {
			return false;
		}

		if ( $id === $parent_id ) {
			return true;
		}

		$guard   = 0;
		$current = self::get( $parent_id );

		while ( $current && ++$guard < 100 ) {
			if ( (int) $current['id'] === $id ) {
				return true;
			}

			if ( ! $current['parent_id'] ) {
				break;
			}

			$current = self::get( $current['parent_id'] );
		}

		return false;
	}

	/**
	 * Elem törlése a leszármazottaival együtt.
	 *
	 * @param int $id Azonosító.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$item = self::get( $id );

		if ( ! $item ) {
			return false;
		}

		$ids   = array( (int) $id );
		$queue = array( (int) $id );
		$table = AMM_Installer::items_table();
		$guard = 0;

		while ( $queue && ++$guard < 200 ) {
			$parent = array_shift( $queue );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE parent_id = %d", $parent ) );
			// phpcs:enable

			foreach ( $children as $child ) {
				$child   = (int) $child;
				$ids[]   = $child;
				$queue[] = $child;
			}
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
		// phpcs:enable

		self::touch_menu( $item['menu_id'] );

		return true;
	}

	/**
	 * Kötegelt átrendezés (drag &amp; drop mentése).
	 *
	 * @param int   $menu_id Menü azonosító.
	 * @param array $batch   Elemek: id, parent_id, position.
	 * @return true|WP_Error
	 */
	public static function reorder( $menu_id, $batch ) {
		global $wpdb;

		$menu_id = (int) $menu_id;
		$table   = AMM_Installer::items_table();
		$valid   = array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE menu_id = %d", $menu_id ) );
		// phpcs:enable

		$existing = array_flip( array_map( 'intval', (array) $existing ) );

		foreach ( (array) $batch as $entry ) {
			$id        = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
			$parent_id = isset( $entry['parent_id'] ) ? (int) $entry['parent_id'] : 0;
			$position  = isset( $entry['position'] ) ? (int) $entry['position'] : 0;

			if ( ! isset( $existing[ $id ] ) ) {
				continue;
			}

			if ( $parent_id && ! isset( $existing[ $parent_id ] ) ) {
				continue; // Idegen menübe mutató szülőt nem fogadunk el.
			}

			if ( $id === $parent_id ) {
				continue;
			}

			$valid[ $id ] = array(
				'parent_id' => $parent_id,
				'position'  => $position,
			);
		}

		// Kör-ellenőrzés a teljes beküldött fára.
		foreach ( $valid as $id => $entry ) {
			$guard   = 0;
			$current = $entry['parent_id'];

			while ( $current && ++$guard < 100 ) {
				if ( $current === $id ) {
					return new WP_Error( 'amm_invalid_tree', __( 'A menüfa körkörös hivatkozást tartalmaz.', 'and-menumanager' ), array( 'status' => 400 ) );
				}

				$current = isset( $valid[ $current ] ) ? $valid[ $current ]['parent_id'] : 0;
			}
		}

		foreach ( $valid as $id => $entry ) {
			$wpdb->update(
				$table,
				array(
					'parent_id' => $entry['parent_id'],
					'position'  => $entry['position'],
				),
				array(
					'id'      => $id,
					'menu_id' => $menu_id,
				),
				array( '%d', '%d' ),
				array( '%d', '%d' )
			);
		}

		self::touch_menu( $menu_id );

		return true;
	}

	/**
	 * Elemek másolása menük között.
	 *
	 * @param int $from_menu Forrás menü.
	 * @param int $to_menu   Cél menü.
	 * @return void
	 */
	public static function copy_items( $from_menu, $to_menu ) {
		$items = self::get_for_menu( $from_menu );
		$map   = array();

		// Szülők előbb: a lekérdezés parent_id szerint rendezett, de a
		// biztonság kedvéért több körben oldjuk fel a hivatkozásokat.
		$pending = $items;
		$guard   = 0;

		while ( $pending && ++$guard < 50 ) {
			$next = array();

			foreach ( $pending as $item ) {
				$parent = (int) $item['parent_id'];

				if ( $parent && ! isset( $map[ $parent ] ) ) {
					$next[] = $item;
					continue;
				}

				$data              = $item;
				$data['parent_id'] = $parent ? $map[ $parent ] : 0;
				unset( $data['id'], $data['menu_id'] );

				$new_id = self::insert( $to_menu, $data );

				if ( ! is_wp_error( $new_id ) ) {
					$map[ (int) $item['id'] ] = $new_id;
				}
			}

			if ( count( $next ) === count( $pending ) ) {
				break; // Nincs előrehaladás.
			}

			$pending = $next;
		}
	}

	/**
	 * Mely menük hivatkoznak egy adott bejegyzésre?
	 *
	 * @param int $object_id Bejegyzés azonosító.
	 * @return array Elemek.
	 */
	public static function find_by_object( $object_id ) {
		global $wpdb;

		$table = AMM_Installer::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE object_id = %d", (int) $object_id ),
			ARRAY_A
		);
		// phpcs:enable

		$items = array();

		foreach ( (array) $rows as $row ) {
			$items[] = self::hydrate( $row );
		}

		return $items;
	}

	/**
	 * Egy bejegyzésre mutató összes elem törlése (árva-takarítás).
	 *
	 * @param int $object_id Bejegyzés azonosító.
	 * @return int Törölt elemek száma.
	 */
	public static function delete_by_object( $object_id ) {
		$items   = self::find_by_object( $object_id );
		$deleted = 0;

		foreach ( $items as $item ) {
			if ( self::delete( $item['id'] ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Bejegyzés-azonosító => menüelem-azonosító térkép egy menüre.
	 *
	 * Egyetlen lekérdezésből, hogy a tömeges hozzáadás meg tudja találni
	 * az új elem helyét a meglévő fában.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return array
	 */
	public static function map_objects( $menu_id ) {
		global $wpdb;

		$table = AMM_Installer::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, object_id FROM {$table} WHERE menu_id = %d AND object_id > 0 ORDER BY id ASC",
				(int) $menu_id
			),
			ARRAY_A
		);
		// phpcs:enable

		$map = array();

		foreach ( (array) $rows as $row ) {
			$object_id = (int) $row['object_id'];

			if ( ! isset( $map[ $object_id ] ) ) {
				$map[ $object_id ] = (int) $row['id'];
			}
		}

		return $map;
	}

	/**
	 * A menüben már meglévő legközelebbi ős megkeresése egy oldalhoz.
	 *
	 * Így az újonnan hozzáadott oldal a saját ága alá kerül, nem a menü
	 * végére – nagy menüben ez a különbség a megtalálható és a
	 * megtalálhatatlan között.
	 *
	 * @param int    $object_id   Bejegyzés azonosító.
	 * @param string $object_type Bejegyzéstípus.
	 * @param array  $object_map  Bejegyzés => menüelem térkép.
	 * @return array|null
	 */
	public static function find_ancestor_item( $object_id, $object_type, $object_map ) {
		$node  = AMM_Pages::get_node( $object_id, $object_type );
		$guard = 0;

		while ( $node && $node['parent'] && ++$guard < 50 ) {
			$parent = AMM_Pages::get_node( $node['parent'], $object_type );

			if ( ! $parent ) {
				break;
			}

			if ( isset( $object_map[ $parent['id'] ] ) ) {
				return array(
					'item_id' => (int) $object_map[ $parent['id'] ],
					'title'   => $parent['title'],
				);
			}

			$node = $parent;
		}

		return null;
	}

	/**
	 * Szerepel-e már a bejegyzés a menüben?
	 *
	 * @param int $menu_id   Menü azonosító.
	 * @param int $object_id Bejegyzés azonosító.
	 * @return bool
	 */
	public static function exists_in_menu( $menu_id, $object_id ) {
		global $wpdb;

		$table = AMM_Installer::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE menu_id = %d AND object_id = %d LIMIT 1",
				(int) $menu_id,
				(int) $object_id
			)
		);
		// phpcs:enable
	}

	/**
	 * Kötegelt művelet közben érintett menük.
	 *
	 * @var array
	 */
	private static $touched = array();

	/**
	 * Menü módosítási idejének frissítése és cache ürítés.
	 *
	 * Kötegelt művelet közben (felfüggesztett gyorsítótár) csak
	 * feljegyezzük az érintett menüt, és a végén egyszer írjuk ki –
	 * enélkül minden egyes elem külön adatbázis-írást okozna.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return void
	 */
	private static function touch_menu( $menu_id ) {
		if ( AMM_Cache::is_suspended() ) {
			self::$touched[ (int) $menu_id ] = true;
			AMM_Cache::flush(); // Csak megjelöli: a feloldáskor fut le.

			return;
		}

		self::write_timestamp( $menu_id );
		AMM_Cache::flush();
	}

	/**
	 * Módosítási idő kiírása.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return void
	 */
	private static function write_timestamp( $menu_id ) {
		global $wpdb;

		$wpdb->update(
			AMM_Installer::menus_table(),
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $menu_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * A kötegelt művelet során érintett menük kiírása.
	 *
	 * A gyorsítótár feloldása (AMM_Cache::resume) előtt kell hívni.
	 *
	 * @return int Az érintett menük száma.
	 */
	public static function flush_touched() {
		$count = count( self::$touched );

		foreach ( array_keys( self::$touched ) as $menu_id ) {
			self::write_timestamp( $menu_id );
		}

		self::$touched = array();

		return $count;
	}
}
