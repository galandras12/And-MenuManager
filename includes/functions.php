<?php
/**
 * Sablonfüggvények témákhoz.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'amm_menu' ) ) {
	/**
	 * Menü kiírása a sablonban.
	 *
	 * Példa: <?php amm_menu( 'fomenu', array( 'style' => 'horizontal' ) ); ?>
	 *
	 * @param int|string $menu Menü azonosító vagy slug.
	 * @param array      $args Paraméterek.
	 * @return void
	 */
	function amm_menu( $menu, $args = array() ) {
		$args['echo'] = true;

		AMM_Renderer::render( $menu, $args );
	}
}

if ( ! function_exists( 'amm_get_menu' ) ) {
	/**
	 * Menü HTML lekérése.
	 *
	 * @param int|string $menu Menü azonosító vagy slug.
	 * @param array      $args Paraméterek.
	 * @return string
	 */
	function amm_get_menu( $menu, $args = array() ) {
		$args['echo'] = false;

		return AMM_Renderer::render( $menu, $args );
	}
}

if ( ! function_exists( 'amm_menu_exists' ) ) {
	/**
	 * Létezik-e a menü?
	 *
	 * @param int|string $menu Menü azonosító vagy slug.
	 * @return bool
	 */
	function amm_menu_exists( $menu ) {
		$record = is_numeric( $menu ) ? AMM_Menu_Repository::get( (int) $menu ) : AMM_Menu_Repository::get_by_slug( (string) $menu );

		return (bool) $record;
	}
}

if ( ! function_exists( 'amm_get_menu_tree' ) ) {
	/**
	 * Feloldott menüfa tömbként (egyedi megjelenítéshez).
	 *
	 * @param int|string $menu Menü azonosító vagy slug.
	 * @return array
	 */
	function amm_get_menu_tree( $menu ) {
		$record = is_numeric( $menu ) ? AMM_Menu_Repository::get( (int) $menu ) : AMM_Menu_Repository::get_by_slug( (string) $menu );

		if ( ! $record ) {
			return array();
		}

		return AMM_Tree::build( $record['id'] );
	}
}
