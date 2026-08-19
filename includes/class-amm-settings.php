<?php
/**
 * Globális beállítások.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Settings
 */
class AMM_Settings {

	const OPTION = 'amm_settings';

	/**
	 * Alapértelmezett beállítások.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'index_threshold'    => 25000,
			'locations'          => array(),
			'auto_add_new_pages' => true,
			'cleanup_deleted'    => true,
			'cache_enabled'      => true,
			'cache_ttl'          => HOUR_IN_SECONDS,
			'frontend_css'       => true,
			'admin_bar'          => true,
			'editor_page_size'   => 100,
		);
	}

	/**
	 * Beállítások lekérése.
	 *
	 * @return array
	 */
	public static function get() {
		$settings = get_option( self::OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, self::defaults() );

		$settings['index_threshold']    = max( 500, (int) $settings['index_threshold'] );
		$settings['cache_ttl']          = max( 0, (int) $settings['cache_ttl'] );
		$settings['editor_page_size']   = max( 20, min( 500, (int) $settings['editor_page_size'] ) );
		$settings['auto_add_new_pages'] = (bool) $settings['auto_add_new_pages'];
		$settings['cleanup_deleted']    = (bool) $settings['cleanup_deleted'];
		$settings['cache_enabled']      = (bool) $settings['cache_enabled'];
		$settings['frontend_css']       = (bool) $settings['frontend_css'];
		$settings['admin_bar']          = (bool) $settings['admin_bar'];
		$settings['locations']          = is_array( $settings['locations'] ) ? array_map( 'intval', $settings['locations'] ) : array();

		return $settings;
	}

	/**
	 * Egy beállítás értéke.
	 *
	 * @param string $key     Kulcs.
	 * @param mixed  $default Alapérték.
	 * @return mixed
	 */
	public static function value( $key, $default = null ) {
		$settings = self::get();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Beállítások mentése.
	 *
	 * @param array $data Új értékek.
	 * @return array Mentett beállítások.
	 */
	public static function update( $data ) {
		$settings = self::get();

		foreach ( array( 'auto_add_new_pages', 'cleanup_deleted', 'cache_enabled', 'frontend_css', 'admin_bar' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$settings[ $key ] = (bool) $data[ $key ];
			}
		}

		foreach ( array( 'index_threshold', 'cache_ttl', 'editor_page_size' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$settings[ $key ] = (int) $data[ $key ];
			}
		}

		if ( isset( $data['locations'] ) && is_array( $data['locations'] ) ) {
			$locations = array();

			foreach ( $data['locations'] as $location => $menu_id ) {
				$menu_id = (int) $menu_id;

				if ( $menu_id > 0 ) {
					$locations[ sanitize_key( $location ) ] = $menu_id;
				}
			}

			$settings['locations'] = $locations;
		}

		update_option( self::OPTION, $settings );
		AMM_Cache::flush();

		return self::get();
	}

	/**
	 * Menü leválasztása minden pozícióról (törléskor).
	 *
	 * @param int $menu_id Menü azonosító.
	 * @return void
	 */
	public static function detach_menu( $menu_id ) {
		$settings  = self::get();
		$locations = array();

		foreach ( $settings['locations'] as $location => $assigned ) {
			if ( (int) $assigned !== (int) $menu_id ) {
				$locations[ $location ] = (int) $assigned;
			}
		}

		$settings['locations'] = $locations;
		update_option( self::OPTION, $settings );
	}

	/**
	 * Egy sablonpozícióhoz rendelt menü.
	 *
	 * @param string $location Pozíció kulcsa.
	 * @return int
	 */
	public static function menu_for_location( $location ) {
		$settings = self::get();

		return isset( $settings['locations'][ $location ] ) ? (int) $settings['locations'][ $location ] : 0;
	}

	/**
	 * Hookok (jelenleg nincs szükség egyedi hookra, a felület REST-en át ír).
	 *
	 * @return void
	 */
	public function hooks() {}
}
