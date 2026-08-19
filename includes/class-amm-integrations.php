<?php
/**
 * Beépülési pontok: shortcode, sablonpozíció, widget, blokk, admin sáv.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Integrations
 */
class AMM_Integrations {

	/**
	 * Hookok bekötése.
	 *
	 * @return void
	 */
	public function hooks() {
		add_shortcode( 'amm_menu', array( $this, 'shortcode' ) );
		add_filter( 'pre_wp_nav_menu', array( $this, 'replace_theme_location' ), 10, 2 );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 90 );
	}

	/**
	 * Shortcode: [amm_menu id="fomenu" style="horizontal"].
	 *
	 * @param array $atts Attribútumok.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'        => '',
				'menu'      => '',
				'style'     => '',
				'depth'     => 0,
				'class'     => '',
				'container' => 'nav',
				'compat'    => '',
			),
			$atts,
			'amm_menu'
		);

		$menu = '' !== $atts['id'] ? $atts['id'] : $atts['menu'];

		if ( '' === $menu ) {
			return '';
		}

		return AMM_Renderer::render(
			$menu,
			array(
				'style'      => sanitize_key( $atts['style'] ),
				'depth'      => (int) $atts['depth'],
				'menu_class' => sanitize_text_field( $atts['class'] ),
				'container'  => sanitize_key( $atts['container'] ),
				'compat'     => ! empty( $atts['compat'] ) && 'false' !== $atts['compat'],
			)
		);
	}

	/**
	 * Sablonpozícióhoz rendelt menü kiszolgálása.
	 *
	 * Így a téma wp_nav_menu() hívásait átvesszük anélkül, hogy a témát
	 * módosítani kellene.
	 *
	 * FONTOS: itt csak visszaadjuk a HTML-t, kiírni nem szabad. A
	 * wp_nav_menu() a szűrő nem-null visszatérési értékét maga írja ki,
	 * ha az $args->echo igaz – ha mi is kiírnánk, a menü kétszer
	 * jelenne meg az oldalon.
	 *
	 * @param string|null $output Eredeti kimenet.
	 * @param object      $args   wp_nav_menu argumentumok.
	 * @return string|null
	 */
	public function replace_theme_location( $output, $args ) {
		if ( empty( $args->theme_location ) ) {
			return $output;
		}

		$menu_id = AMM_Settings::menu_for_location( $args->theme_location );

		if ( ! $menu_id ) {
			return $output;
		}

		// Ha a téma kifejezetten konténer nélkül kéri a menüt, ne tegyünk
		// köré sajátot – különben szétesik a téma elrendezése.
		$container = 'none';

		if ( isset( $args->container ) && $args->container ) {
			$container = $args->container;
		} elseif ( ! isset( $args->container ) ) {
			$container = 'nav';
		}

		$html = AMM_Renderer::render(
			$menu_id,
			array(
				'container'       => $container,
				'container_class' => isset( $args->container_class ) ? $args->container_class : '',
				'container_id'    => isset( $args->container_id ) ? $args->container_id : '',
				'menu_class'      => isset( $args->menu_class ) ? $args->menu_class : '',
				'menu_id'         => isset( $args->menu_id ) ? $args->menu_id : '',
				'depth'           => isset( $args->depth ) ? (int) $args->depth : 0,
				/**
				 * Téma-kompatibilis kimenet a sablonpozíciókon.
				 *
				 * Alapból bekapcsolva: a WordPress beépített menüjével
				 * azonos osztályokat adunk ki, hogy a téma stílusai és
				 * szkriptjei változatlanul működjenek.
				 *
				 * @param bool   $compat   Kompatibilis mód.
				 * @param string $location Sablonpozíció kulcsa.
				 */
				'compat'          => apply_filters( 'amm_theme_location_compat', true, $args->theme_location ),
			)
		);

		if ( '' === $html ) {
			return $output;
		}

		return $html;
	}

	/**
	 * Klasszikus widget regisztrálása.
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( 'AMM_Widget' );
	}

	/**
	 * Szerver oldalon renderelt blokk regisztrálása.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block_dir = AMM_PATH . 'blocks/menu';

		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			return;
		}

		wp_register_script(
			'amm-block-editor',
			AMM_URL . 'blocks/menu/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch' ),
			AMM_VERSION,
			true
		);

		wp_localize_script(
			'amm-block-editor',
			'AMM_BLOCK',
			array( 'menusPath' => '/' . AMM_REST_NAMESPACE . '/menus?per_page=200' )
		);

		register_block_type(
			$block_dir,
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Blokk kirajzolása.
	 *
	 * @param array $attributes Blokk attribútumok.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$menu_id = isset( $attributes['menuId'] ) ? (int) $attributes['menuId'] : 0;

		if ( ! $menu_id ) {
			return '';
		}

		$html = AMM_Renderer::render(
			$menu_id,
			array(
				'style' => isset( $attributes['display'] ) ? sanitize_key( $attributes['display'] ) : '',
				'depth' => isset( $attributes['depth'] ) ? (int) $attributes['depth'] : 0,
			)
		);

		if ( '' === $html ) {
			return '';
		}

		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';

		return sprintf( '<div %s>%s</div>', $wrapper, $html );
	}

	/**
	 * Látogatói oldali eszközök betöltése.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$settings = AMM_Settings::get();

		if ( $settings['frontend_css'] ) {
			wp_enqueue_style( 'amm-frontend', AMM_URL . 'assets/css/frontend.css', array(), AMM_VERSION );
		}

		wp_enqueue_script( 'amm-frontend', AMM_URL . 'assets/js/frontend.js', array(), AMM_VERSION, true );
	}

	/**
	 * Gyorsgomb az admin sávban.
	 *
	 * @param WP_Admin_Bar $bar Admin sáv.
	 * @return void
	 */
	public function admin_bar( $bar ) {
		if ( ! AMM_Settings::value( 'admin_bar' ) || ! AMM_Capabilities::can_manage() ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'amm-menus',
				'title' => __( 'Menükezelő', 'and-menumanager' ),
				'href'  => admin_url( 'admin.php?page=and-menumanager' ),
			)
		);

		$bar->add_node(
			array(
				'parent' => 'amm-menus',
				'id'     => 'amm-flush',
				'title'  => __( 'Menü gyorsítótár ürítése', 'and-menumanager' ),
				'href'   => wp_nonce_url( admin_url( 'admin.php?page=and-menumanager&amm_action=flush' ), 'amm_flush' ),
			)
		);
	}
}
