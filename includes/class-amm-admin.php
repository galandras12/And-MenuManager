<?php
/**
 * Admin felület: menüpontok, eszközök betöltése, alkalmazás gyökér.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Admin
 */
class AMM_Admin {

	const SLUG          = 'and-menumanager';
	const SETTINGS_SLUG = 'and-menumanager-settings';

	/**
	 * Hookok bekötése.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AMM_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Admin menüpontok.
	 *
	 * @return void
	 */
	public function register_pages() {
		add_menu_page(
			__( 'Menükezelő', 'and-menumanager' ),
			__( 'Menükezelő', 'and-menumanager' ),
			AMM_Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render_app' ),
			'dashicons-menu-alt3',
			26
		);

		add_submenu_page(
			self::SLUG,
			__( 'Menük', 'and-menumanager' ),
			__( 'Menük', 'and-menumanager' ),
			AMM_Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render_app' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Beállítások', 'and-menumanager' ),
			__( 'Beállítások', 'and-menumanager' ),
			AMM_Capabilities::MANAGE,
			self::SETTINGS_SLUG,
			array( $this, 'render_app' )
		);
	}

	/**
	 * A plugin listaoldali gyorslinkjei.
	 *
	 * @param array $links Linkek.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
				esc_html__( 'Menük', 'and-menumanager' )
			)
		);

		return $links;
	}

	/**
	 * Admin sávból indított műveletek.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( empty( $_GET['amm_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['amm_action'] ) );

		if ( 'flush' === $action && AMM_Capabilities::can_manage() && check_admin_referer( 'amm_flush' ) ) {
			AMM_Cache::flush();

			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&amm_flushed=1' ) );
			exit;
		}
	}

	/**
	 * A jelenlegi képernyő a pluginé?
	 *
	 * @param string $hook Hook utótag.
	 * @return string Nézet neve vagy üres.
	 */
	private function current_view( $hook ) {
		if ( false !== strpos( $hook, self::SETTINGS_SLUG ) ) {
			return 'settings';
		}

		if ( false !== strpos( $hook, self::SLUG ) ) {
			return 'menus';
		}

		return '';
	}

	/**
	 * Eszközök betöltése.
	 *
	 * @param string $hook Hook utótag.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$view = $this->current_view( $hook );

		if ( ! $view ) {
			return;
		}

		// A saját képernyőkön ellenőrizzük, hogy a táblák megvannak-e.
		AMM_Installer::verify_tables( true );

		wp_enqueue_style( 'amm-admin', AMM_URL . 'assets/css/admin.css', array(), AMM_VERSION );
		wp_enqueue_script( 'amm-admin', AMM_URL . 'assets/js/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), AMM_VERSION, true );

		$settings = AMM_Settings::get();

		wp_localize_script(
			'amm-admin',
			'AMM_DATA',
			array(
				'root'       => esc_url_raw( rest_url( AMM_REST_NAMESPACE ) ),
				'namespace'  => AMM_REST_NAMESPACE,
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'view'       => $view,
				'adminUrl'   => admin_url( 'admin.php?page=' . self::SLUG ),
				'canConfig'  => AMM_Capabilities::can_configure(),
				'postTypes'  => array_values( AMM_Pages::post_types() ),
				'pageSize'   => (int) $settings['editor_page_size'],
				'pageCount'  => AMM_Pages::count( 'page' ),
				'indexed'    => AMM_Pages::use_index( 'page' ),
				'roles'      => AMM_Capabilities::get_map(),
				'caps'       => AMM_Capabilities::all(),
				'homeUrl'    => home_url( '/' ),
				'i18n'       => $this->strings(),
			)
		);
	}

	/**
	 * A felület szövegei.
	 *
	 * @return array
	 */
	private function strings() {
		return array(
			'menus'            => __( 'Menük', 'and-menumanager' ),
			'newMenu'          => __( 'Új menü', 'and-menumanager' ),
			'searchMenus'      => __( 'Menü keresése…', 'and-menumanager' ),
			'searchPages'      => __( 'Oldal keresése…', 'and-menumanager' ),
			'noMenus'          => __( 'Még nincs menü. Hozz létre egyet, vagy emeld át a meglévő WordPress menüket.', 'and-menumanager' ),
			'selectMenu'       => __( 'Válassz egy menüt a bal oldali listából.', 'and-menumanager' ),
			'structure'        => __( 'Menü felépítése', 'and-menumanager' ),
			'pagePicker'       => __( 'Tartalom hozzáadása', 'and-menumanager' ),
			'settings'         => __( 'Beállítások', 'and-menumanager' ),
			'save'             => __( 'Mentés', 'and-menumanager' ),
			'saved'            => __( 'Mentve.', 'and-menumanager' ),
			'saving'           => __( 'Mentés…', 'and-menumanager' ),
			'delete'           => __( 'Törlés', 'and-menumanager' ),
			'duplicate'        => __( 'Másolat', 'and-menumanager' ),
			'confirmDelete'    => __( 'Biztosan törlöd? A művelet nem vonható vissza.', 'and-menumanager' ),
			'loading'          => __( 'Betöltés…', 'and-menumanager' ),
			'error'            => __( 'Hiba történt.', 'and-menumanager' ),
			'add'              => __( 'Hozzáadás', 'and-menumanager' ),
			'addSelected'      => __( 'Kijelöltek hozzáadása', 'and-menumanager' ),
			'name'             => __( 'Név', 'and-menumanager' ),
			'items'            => __( 'elem', 'and-menumanager' ),
			'links'            => __( 'link', 'and-menumanager' ),
			'autoChildren'     => __( 'Aloldalak automatikusan', 'and-menumanager' ),
			'hidden'           => __( 'Rejtve', 'and-menumanager' ),
			'visible'          => __( 'Látható', 'and-menumanager' ),
			'excluded'         => __( 'Kizárva', 'and-menumanager' ),
			'emptyPicker'      => __( 'Nincs találat.', 'and-menumanager' ),
			'more'             => __( 'Továbbiak betöltése', 'and-menumanager' ),
			'back'             => __( 'Vissza', 'and-menumanager' ),
			'root'             => __( 'Gyökér', 'and-menumanager' ),
			'preview'          => __( 'Előnézet', 'and-menumanager' ),
			'shortcodeCopied'  => __( 'Shortcode a vágólapra másolva.', 'and-menumanager' ),
		);
	}

	/**
	 * Az alkalmazás gyökere.
	 *
	 * @return void
	 */
	public function render_app() {
		if ( ! AMM_Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Nincs jogosultságod ehhez az oldalhoz.', 'and-menumanager' ) );
		}

		$view = isset( $_GET['page'] ) && self::SETTINGS_SLUG === $_GET['page'] ? 'settings' : 'menus'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap amm-wrap">
			<div id="amm-app" class="amm-app" data-view="<?php echo esc_attr( $view ); ?>">
				<div class="amm-boot">
					<span class="amm-spinner" aria-hidden="true"></span>
					<p><?php esc_html_e( 'A menükezelő betöltése…', 'and-menumanager' ); ?></p>
					<noscript><?php esc_html_e( 'A kezelőfelület JavaScriptet igényel.', 'and-menumanager' ); ?></noscript>
				</div>
			</div>
		</div>
		<?php
	}
}
