<?php
/**
 * Telepítés, adatbázis séma és verziókezelés.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Installer
 */
class AMM_Installer {

	/**
	 * Menü tábla neve.
	 *
	 * @return string
	 */
	public static function menus_table() {
		global $wpdb;

		return $wpdb->prefix . 'amm_menus';
	}

	/**
	 * Menüelem tábla neve.
	 *
	 * @return string
	 */
	public static function items_table() {
		global $wpdb;

		return $wpdb->prefix . 'amm_items';
	}

	/**
	 * Aktiválás.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		AMM_Capabilities::add_caps_to_admins();

		if ( false === get_option( 'amm_settings', false ) ) {
			add_option( 'amm_settings', AMM_Settings::defaults() );
		}

		update_option( 'amm_db_version', AMM_DB_VERSION );
		update_option( 'amm_version', AMM_VERSION );

		if ( ! wp_next_scheduled( 'amm_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'amm_daily_maintenance' );
		}

		AMM_Cache::flush();
	}

	/**
	 * Deaktiválás.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'amm_daily_maintenance' );
		AMM_Cache::flush();
	}

	/**
	 * Léteznek-e a saját táblák?
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;

		foreach ( array( self::menus_table(), self::items_table() ) as $table ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			// phpcs:enable

			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Önjavító ellenőrzés: ha a táblák hiányoznak, újra létrehozzuk.
	 *
	 * Ez arra az esetre kell, amikor az aktiválás félbemaradt (pl. a
	 * verziószám már elmentődött, de a táblák nem jöttek létre) – enélkül
	 * a plugin némán, javíthatatlanul hibás állapotban maradna.
	 *
	 * @param bool $force Kihagyja-e az óránkénti korlátozást.
	 * @return bool Rendben vannak-e a táblák.
	 */
	public static function verify_tables( $force = false ) {
		$last = (int) get_option( 'amm_tables_checked', 0 );

		if ( ! $force && $last && ( time() - $last ) < HOUR_IN_SECONDS ) {
			return true;
		}

		update_option( 'amm_tables_checked', time(), false );

		if ( self::tables_exist() ) {
			return true;
		}

		self::create_tables();

		return self::tables_exist();
	}

	/**
	 * Séma frissítés futás közben (pl. ha a plugint fájlból frissítették).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'amm_db_version' ) === AMM_DB_VERSION ) {
			self::verify_tables();

			return;
		}

		self::create_tables();
		AMM_Capabilities::add_caps_to_admins();
		update_option( 'amm_db_version', AMM_DB_VERSION );
		update_option( 'amm_version', AMM_VERSION );
		AMM_Cache::flush();
	}

	/**
	 * Táblák létrehozása / frissítése.
	 *
	 * A séma szándékosan lapos és jól indexelt: a menük kis számú
	 * "szabály-elemet" tárolnak, a több ezer aloldal nem kerül be
	 * sorokként, hanem futásidőben, gyorsítótárazva oldódik fel.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$menus   = self::menus_table();
		$items   = self::items_table();

		$sql_menus = "CREATE TABLE {$menus} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL DEFAULT '',
			name varchar(255) NOT NULL DEFAULT '',
			description text NULL,
			settings longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql_items = "CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			menu_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			position int(11) NOT NULL DEFAULT 0,
			type varchar(32) NOT NULL DEFAULT 'post_type',
			object_type varchar(32) NOT NULL DEFAULT 'page',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			url text NULL,
			target varchar(20) NOT NULL DEFAULT '',
			css_class varchar(255) NOT NULL DEFAULT '',
			link_rel varchar(100) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			auto_children tinyint(1) NOT NULL DEFAULT 0,
			auto_depth tinyint(4) NOT NULL DEFAULT 0,
			auto_order varchar(32) NOT NULL DEFAULT 'menu_order',
			visibility longtext NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY menu_tree (menu_id,parent_id,position),
			KEY object (object_type,object_id),
			KEY menu_object (menu_id,object_id)
		) {$charset};";

		dbDelta( $sql_menus );
		dbDelta( $sql_items );
	}

	/**
	 * Teljes eltávolítás (uninstall.php hívja).
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		$menus = self::menus_table();
		$items = self::items_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$items}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$menus}" );
		// phpcs:enable

		delete_option( 'amm_settings' );
		delete_option( 'amm_db_version' );
		delete_option( 'amm_version' );
		delete_option( 'amm_cache_version' );
		delete_option( 'amm_tables_checked' );
		delete_option( 'amm_health_report' );
		delete_option( 'amm_error_log' );

		AMM_Capabilities::remove_all_caps();
		wp_clear_scheduled_hook( 'amm_daily_maintenance' );
	}
}
