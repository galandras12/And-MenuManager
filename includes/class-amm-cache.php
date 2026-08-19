<?php
/**
 * Gyorsítótár réteg.
 *
 * Objektum-cache-t használ, ha van, és mindig van transient fallback.
 * A globális érvénytelenítés verziószám-léptetéssel történik, így soha
 * nem kell több ezer kulcsot végigtörölni.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Cache
 */
class AMM_Cache {

	const GROUP = 'amm';

	/**
	 * Futásidejű (request-szintű) memória.
	 *
	 * @var array
	 */
	private static $runtime = array();

	/**
	 * Felfüggesztések száma (egymásba ágyazható).
	 *
	 * @var int
	 */
	private static $suspended = 0;

	/**
	 * Van-e elhalasztott ürítés?
	 *
	 * @var bool
	 */
	private static $pending_flush = false;

	/**
	 * Ürítés felfüggesztése kötegelt művelet idejére.
	 *
	 * Enélkül egy több ezer elemes átemelés minden egyes elem után
	 * kiürítené a gyorsítótárat (opció-írással együtt), ami a művelet
	 * legdrágább része lenne.
	 *
	 * @return void
	 */
	public static function suspend() {
		++self::$suspended;
	}

	/**
	 * Felfüggesztés feloldása; a közben kért ürítés egyszer fut le.
	 *
	 * @return void
	 */
	public static function resume() {
		if ( self::$suspended > 0 ) {
			--self::$suspended;
		}

		if ( 0 === self::$suspended && self::$pending_flush ) {
			self::$pending_flush = false;
			self::flush();
		}
	}

	/**
	 * Fel van-e függesztve az ürítés?
	 *
	 * @return bool
	 */
	public static function is_suspended() {
		return self::$suspended > 0;
	}

	/**
	 * Aktuális cache verzió.
	 *
	 * @return int
	 */
	public static function version() {
		$version = (int) get_option( 'amm_cache_version', 0 );

		if ( $version < 1 ) {
			$version = 1;
			update_option( 'amm_cache_version', $version, true );
		}

		return $version;
	}

	/**
	 * Teljes cache érvénytelenítése.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$runtime = array();

		if ( self::$suspended > 0 ) {
			self::$pending_flush = true;

			return;
		}

		update_option( 'amm_cache_version', self::version() + 1, true );
	}

	/**
	 * Kulcs normalizálása a verzióval.
	 *
	 * @param string $key Nyers kulcs.
	 * @return string
	 */
	private static function key( $key ) {
		return 'amm_' . self::version() . '_' . md5( $key );
	}

	/**
	 * Érték olvasása.
	 *
	 * @param string $key Kulcs.
	 * @return mixed|null Null, ha nincs találat.
	 */
	public static function get( $key ) {
		$full = self::key( $key );

		if ( array_key_exists( $full, self::$runtime ) ) {
			return self::$runtime[ $full ];
		}

		if ( wp_using_ext_object_cache() ) {
			$found = false;
			$value = wp_cache_get( $full, self::GROUP, false, $found );

			if ( $found ) {
				self::$runtime[ $full ] = $value;

				return $value;
			}

			return null;
		}

		$value = get_transient( $full );

		if ( false === $value ) {
			return null;
		}

		self::$runtime[ $full ] = $value;

		return $value;
	}

	/**
	 * Érték írása.
	 *
	 * @param string $key   Kulcs.
	 * @param mixed  $value Érték.
	 * @param int    $ttl   Élettartam másodpercben.
	 * @return void
	 */
	public static function set( $key, $value, $ttl = HOUR_IN_SECONDS ) {
		$full                   = self::key( $key );
		self::$runtime[ $full ] = $value;

		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $full, $value, self::GROUP, $ttl );

			return;
		}

		set_transient( $full, $value, $ttl );
	}

	/**
	 * Csak a futásidejű memóriában tárolt érték (nem perzisztens).
	 *
	 * @param string   $key      Kulcs.
	 * @param callable $callback Előállító függvény.
	 * @return mixed
	 */
	public static function remember_runtime( $key, $callback ) {
		if ( ! array_key_exists( $key, self::$runtime ) ) {
			self::$runtime[ $key ] = call_user_func( $callback );
		}

		return self::$runtime[ $key ];
	}
}
