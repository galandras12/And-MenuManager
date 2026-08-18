<?php
/**
 * Fő plugin osztály – a komponensek összefűzése.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Plugin
 */
final class AMM_Plugin {

	/**
	 * Singleton példány.
	 *
	 * @var AMM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Betöltött komponensek.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Példány lekérése.
	 *
	 * @return AMM_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Konstruktor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	/**
	 * Fordítások betöltése.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'and-menumanager', false, dirname( plugin_basename( AMM_FILE ) ) . '/languages' );
	}

	/**
	 * Komponensek indítása.
	 *
	 * @return void
	 */
	public function boot() {
		AMM_Installer::maybe_upgrade();

		add_filter( 'user_has_cap', array( 'AMM_Capabilities', 'grant_to_admins' ), 10, 4 );

		$this->components['settings']     = new AMM_Settings();
		$this->components['automations']  = new AMM_Automations();
		$this->components['integrations'] = new AMM_Integrations();
		$this->components['rest']         = new AMM_Rest();

		if ( is_admin() ) {
			$this->components['admin'] = new AMM_Admin();
		}

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'hooks' ) ) {
				$component->hooks();
			}
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			AMM_CLI::register();
		}

		/**
		 * A plugin elindult, a bővítmények bekapcsolódhatnak.
		 *
		 * @param AMM_Plugin $plugin A plugin példánya.
		 */
		do_action( 'amm_loaded', $this );
	}

	/**
	 * Komponens lekérése.
	 *
	 * @param string $key Komponens kulcsa.
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->components[ $key ] ) ? $this->components[ $key ] : null;
	}
}
