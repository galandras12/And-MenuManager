<?php
/**
 * Menük adatelérési rétege.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Menu_Repository
 */
class AMM_Menu_Repository {

	/**
	 * Menü-szintű alapbeállítások.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'post_type'     => 'page',
			'auto_children' => true,
			'auto_add_root' => false,
			'auto_depth'    => 0,
			'auto_order'    => 'menu_order',
			'excluded'      => array(),
			'depth_limit'   => 0,
			'style'         => 'vertical',
			'css_class'     => '',
			'show_home'     => false,
			'cache_ttl'     => HOUR_IN_SECONDS,
			'hide_private'  => true,
			'collapse_subs' => true,
		);
	}

	/**
	 * Beállítások normalizálása.
	 *
	 * @param mixed $settings Nyers beállítások.
	 * @return array
	 */
	public static function normalize_settings( $settings ) {
		if ( is_string( $settings ) ) {
			$settings = json_decode( $settings, true );
		}

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, self::default_settings() );

		$settings['post_type']     = sanitize_key( $settings['post_type'] );
		$settings['auto_children'] = (bool) $settings['auto_children'];
		$settings['auto_add_root'] = (bool) $settings['auto_add_root'];
		$settings['auto_depth']    = max( 0, (int) $settings['auto_depth'] );
		$settings['depth_limit']   = max( 0, (int) $settings['depth_limit'] );
		$settings['cache_ttl']     = max( 0, (int) $settings['cache_ttl'] );
		$settings['show_home']     = (bool) $settings['show_home'];
		$settings['hide_private']  = (bool) $settings['hide_private'];
		$settings['collapse_subs'] = (bool) $settings['collapse_subs'];
		$settings['css_class']     = sanitize_text_field( $settings['css_class'] );

		$allowed_order         = array( 'menu_order', 'title', 'date', 'slug' );
		$settings['auto_order'] = in_array( $settings['auto_order'], $allowed_order, true ) ? $settings['auto_order'] : 'menu_order';

		$allowed_style     = array( 'vertical', 'horizontal', 'accordion', 'columns' );
		$settings['style'] = in_array( $settings['style'], $allowed_style, true ) ? $settings['style'] : 'vertical';

		$settings['excluded'] = array_values( array_unique( array_filter( array_map( 'intval', (array) $settings['excluded'] ) ) ) );

		return $settings;
	}

	/**
	 * Sor normalizálása tömbbé.
	 *
	 * @param array|null $row Adatbázis sor.
	 * @return array|null
	 */
	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}

		return array(
			'id'          => (int) $row['id'],
			'slug'        => $row['slug'],
			'name'        => $row['name'],
			'description' => (string) $row['description'],
			'settings'    => self::normalize_settings( $row['settings'] ),
			'created_at'  => $row['created_at'],
			'updated_at'  => $row['updated_at'],
		);
	}

	/**
	 * Menü lekérése azonosító alapján.
	 *
	 * @param int $id Azonosító.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = AMM_Installer::menus_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		// phpcs:enable

		return self::hydrate( $row );
	}

	/**
	 * Menü lekérése slug alapján.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;

		$table = AMM_Installer::menus_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );
		// phpcs:enable

		return self::hydrate( $row );
	}

	/**
	 * Menük listázása lapozással és kereséssel.
	 *
	 * @param array $args Paraméterek.
	 * @return array {
	 *     @type array $items Menük.
	 *     @type int   $total Összes találat.
	 * }
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'page'     => 1,
				'per_page' => 50,
				'orderby'  => 'name',
				'order'    => 'ASC',
			)
		);

		$table    = AMM_Installer::menus_table();
		$where    = 'WHERE 1=1';
		$params   = array();
		$search   = trim( (string) $args['search'] );
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) ) * $per_page;

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where  .= ' AND (name LIKE %s OR slug LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby_map = array(
			'name'       => 'name',
			'updated_at' => 'updated_at',
			'created_at' => 'created_at',
			'id'         => 'id',
		);
		$orderby     = isset( $orderby_map[ $args['orderby'] ] ) ? $orderby_map[ $args['orderby'] ] : 'name';
		$order       = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql  = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$rows      = $wpdb->get_results(
			$wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		$items = array();

		foreach ( (array) $rows as $row ) {
			$items[] = self::hydrate( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Egyedi slug előállítása.
	 *
	 * @param string $slug    Kiindulási slug.
	 * @param int    $exclude Kihagyandó menü azonosító.
	 * @return string
	 */
	public static function unique_slug( $slug, $exclude = 0 ) {
		global $wpdb;

		$table = AMM_Installer::menus_table();
		$slug  = sanitize_title( $slug );

		if ( '' === $slug ) {
			$slug = 'menu';
		}

		$base   = $slug;
		$suffix = 1;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id <> %d", $slug, (int) $exclude ) ) > 0 ) {
			++$suffix;
			$slug = $base . '-' . $suffix;
		}
		// phpcs:enable

		return $slug;
	}

	/**
	 * Új menü létrehozása.
	 *
	 * @param array $data Adatok.
	 * @return int|WP_Error Új azonosító.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'amm_missing_name', __( 'A menü neve kötelező.', 'and-menumanager' ), array( 'status' => 400 ) );
		}

		$slug     = self::unique_slug( ! empty( $data['slug'] ) ? $data['slug'] : $name );
		$settings = self::normalize_settings( isset( $data['settings'] ) ? $data['settings'] : array() );
		$now      = current_time( 'mysql' );

		$result = $wpdb->insert(
			AMM_Installer::menus_table(),
			array(
				'slug'        => $slug,
				'name'        => $name,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
				'settings'    => wp_json_encode( $settings ),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			$detail = $wpdb->last_error ? ' (' . $wpdb->last_error . ')' : '';

			return new WP_Error(
				'amm_insert_failed',
				__( 'A menü mentése nem sikerült.', 'and-menumanager' ) . $detail,
				array( 'status' => 500 )
			);
		}

		AMM_Cache::flush();

		$menu_id = (int) $wpdb->insert_id;

		/**
		 * Új menü jött létre.
		 *
		 * @param int   $menu_id Menü azonosító.
		 * @param array $data    Bemeneti adatok.
		 */
		do_action( 'amm_menu_created', $menu_id, $data );

		return $menu_id;
	}

	/**
	 * Menü frissítése.
	 *
	 * @param int   $id   Azonosító.
	 * @param array $data Adatok.
	 * @return true|WP_Error
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$menu = self::get( $id );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$fields = array( 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s' );

		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( $data['name'] );

			if ( '' === $name ) {
				return new WP_Error( 'amm_missing_name', __( 'A menü neve kötelező.', 'and-menumanager' ), array( 'status' => 400 ) );
			}

			$fields['name'] = $name;
			$format[]       = '%s';
		}

		if ( isset( $data['slug'] ) ) {
			$fields['slug'] = self::unique_slug( $data['slug'], (int) $id );
			$format[]       = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$fields['description'] = sanitize_textarea_field( $data['description'] );
			$format[]              = '%s';
		}

		if ( isset( $data['settings'] ) ) {
			$settings           = self::normalize_settings( array_merge( $menu['settings'], (array) $data['settings'] ) );
			$fields['settings'] = wp_json_encode( $settings );
			$format[]           = '%s';
		}

		$wpdb->update( AMM_Installer::menus_table(), $fields, array( 'id' => (int) $id ), $format, array( '%d' ) );

		AMM_Cache::flush();

		/**
		 * Menü frissült.
		 *
		 * @param int   $id   Menü azonosító.
		 * @param array $data Bemeneti adatok.
		 */
		do_action( 'amm_menu_updated', (int) $id, $data );

		return true;
	}

	/**
	 * Menü törlése az elemeivel együtt.
	 *
	 * @param int $id Azonosító.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;

		$wpdb->delete( AMM_Installer::items_table(), array( 'menu_id' => $id ), array( '%d' ) );
		$deleted = $wpdb->delete( AMM_Installer::menus_table(), array( 'id' => $id ), array( '%d' ) );

		AMM_Settings::detach_menu( $id );
		AMM_Cache::flush();

		/**
		 * Menü törölve.
		 *
		 * @param int $id Menü azonosító.
		 */
		do_action( 'amm_menu_deleted', $id );

		return (bool) $deleted;
	}

	/**
	 * Menü másolása az elemeivel együtt.
	 *
	 * @param int $id Forrás azonosító.
	 * @return int|WP_Error Új menü azonosító.
	 */
	public static function duplicate( $id ) {
		$menu = self::get( $id );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$new_id = self::insert(
			array(
				/* translators: %s: az eredeti menü neve. */
				'name'        => sprintf( __( '%s (másolat)', 'and-menumanager' ), $menu['name'] ),
				'description' => $menu['description'],
				'settings'    => $menu['settings'],
			)
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		AMM_Item_Repository::copy_items( (int) $id, $new_id );
		AMM_Cache::flush();

		return $new_id;
	}

	/**
	 * Menük száma.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		$table = AMM_Installer::menus_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable
	}
}
