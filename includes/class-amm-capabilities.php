<?php
/**
 * Szerepkörök és jogosultságok.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Capabilities
 */
class AMM_Capabilities {

	const MANAGE  = 'amm_manage_menus';
	const SETTING = 'amm_manage_settings';

	/**
	 * Az összes saját jogosultság.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			self::MANAGE  => __( 'Menük szerkesztése', 'and-menumanager' ),
			self::SETTING => __( 'Beállítások és hozzáférés kezelése', 'and-menumanager' ),
		);
	}

	/**
	 * Jogosultságok hozzáadása az adminisztrátorokhoz.
	 *
	 * @return void
	 */
	public static function add_caps_to_admins() {
		foreach ( array( 'administrator' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( array_keys( self::all() ) as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Minden jogosultság eltávolítása minden szerepkörből.
	 *
	 * @return void
	 */
	public static function remove_all_caps() {
		foreach ( wp_roles()->roles as $role_name => $data ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( array_keys( self::all() ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Szerepkörönkénti jogosultság-térkép mentése.
	 *
	 * Az adminisztrátor mindig megtartja a jogokat, hogy ne lehessen
	 * kizárni magunkat a felületről.
	 *
	 * @param array $map Szerepkör => jogosultságok listája.
	 * @return void
	 */
	public static function save_map( $map ) {
		$valid_caps = array_keys( self::all() );

		foreach ( wp_roles()->roles as $role_name => $data ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			$granted = isset( $map[ $role_name ] ) ? (array) $map[ $role_name ] : array();

			if ( 'administrator' === $role_name ) {
				$granted = $valid_caps;
			}

			foreach ( $valid_caps as $cap ) {
				if ( in_array( $cap, $granted, true ) ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Aktuális jogosultság-térkép szerepkörönként.
	 *
	 * @return array
	 */
	public static function get_map() {
		$map        = array();
		$valid_caps = array_keys( self::all() );

		foreach ( wp_roles()->roles as $role_name => $data ) {
			$caps  = isset( $data['capabilities'] ) ? $data['capabilities'] : array();
			$owned = array();

			foreach ( $valid_caps as $cap ) {
				if ( ! empty( $caps[ $cap ] ) ) {
					$owned[] = $cap;
				}
			}

			$map[ $role_name ] = array(
				'name' => translate_user_role( $data['name'] ),
				'caps' => $owned,
			);
		}

		return $map;
	}

	/**
	 * Biztonsági háló: a teljes jogú adminisztrátor mindig hozzáfér.
	 *
	 * Így akkor sem lehet kizárni magunkat, ha a jogosultságok valamiért
	 * nem kerültek be a szerepkörbe (pl. fájlból másolt frissítés).
	 *
	 * @param array $allcaps A felhasználó jogosultságai.
	 * @param array $caps    Kért jogosultságok.
	 * @param array $args    Kontextus.
	 * @param mixed $user    Felhasználó.
	 * @return array
	 */
	public static function grant_to_admins( $allcaps, $caps, $args, $user ) {
		if ( empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}

		foreach ( array_keys( self::all() ) as $cap ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * Szerkesztheti-e a felhasználó a menüket?
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::MANAGE ) || current_user_can( 'manage_options' );
	}

	/**
	 * Módosíthatja-e a beállításokat?
	 *
	 * @return bool
	 */
	public static function can_configure() {
		return current_user_can( self::SETTING ) || current_user_can( 'manage_options' );
	}
}
