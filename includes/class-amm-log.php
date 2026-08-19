<?php
/**
 * Hibanapló.
 *
 * A felületen és a szerveren keletkező hibákat gyűjti dátummal, hogy a
 * hiba akkor is visszakereshető legyen, ha az értesítés már eltűnt.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Log
 */
class AMM_Log {

	const OPTION = 'amm_error_log';
	const LIMIT  = 200;

	/**
	 * Bejegyzés hozzáadása.
	 *
	 * @param string $message Hibaüzenet.
	 * @param string $context Honnan származik.
	 * @param string $level   Szint: error | warning | info.
	 * @return void
	 */
	public static function add( $message, $context = '', $level = 'error' ) {
		$message = trim( wp_strip_all_tags( (string) $message ) );

		if ( '' === $message ) {
			return;
		}

		$entries = self::all();
		$user    = wp_get_current_user();

		array_unshift(
			$entries,
			array(
				'time'    => current_time( 'mysql' ),
				'level'   => in_array( $level, array( 'error', 'warning', 'info' ), true ) ? $level : 'error',
				'context' => sanitize_text_field( $context ),
				'message' => mb_substr( $message, 0, 500 ),
				'user'    => ( $user && $user->exists() ) ? $user->user_login : '',
			)
		);

		if ( count( $entries ) > self::LIMIT ) {
			$entries = array_slice( $entries, 0, self::LIMIT );
		}

		update_option( self::OPTION, $entries, false );
	}

	/**
	 * Bejegyzések lekérése (legújabb elöl).
	 *
	 * @return array
	 */
	public static function all() {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Napló ürítése.
	 *
	 * @return int A törölt bejegyzések száma.
	 */
	public static function clear() {
		$count = count( self::all() );

		delete_option( self::OPTION );

		return $count;
	}

	/**
	 * Napló szöveges formában (exportáláshoz).
	 *
	 * @return string
	 */
	public static function as_text() {
		$lines = array(
			'And-MenuManager hibanapló',
			'Oldal: ' . home_url( '/' ),
			'Készült: ' . current_time( 'mysql' ),
			'Plugin verzió: ' . AMM_VERSION,
			str_repeat( '-', 60 ),
		);

		$entries = self::all();

		if ( empty( $entries ) ) {
			$lines[] = 'A napló üres.';
		}

		foreach ( $entries as $entry ) {
			$lines[] = sprintf(
				'[%s] %s %s%s',
				isset( $entry['time'] ) ? $entry['time'] : '',
				strtoupper( isset( $entry['level'] ) ? $entry['level'] : 'error' ),
				! empty( $entry['context'] ) ? '(' . $entry['context'] . ') ' : '',
				isset( $entry['message'] ) ? $entry['message'] : ''
			);
		}

		return implode( "\n", $lines ) . "\n";
	}
}
