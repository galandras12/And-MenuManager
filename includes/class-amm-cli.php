<?php
/**
 * WP-CLI parancsok.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_CLI
 */
class AMM_CLI {

	/**
	 * Parancsok regisztrálása.
	 *
	 * @return void
	 */
	public static function register() {
		WP_CLI::add_command( 'amm', 'AMM_CLI' );
	}

	/**
	 * Menük listázása.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amm list-menus
	 *
	 * @return void
	 */
	public function list_menus() {
		$result = AMM_Menu_Repository::all( array( 'per_page' => 200 ) );
		$rows   = array();

		foreach ( $result['items'] as $menu ) {
			$stats  = AMM_Tree::stats( $menu['id'] );
			$rows[] = array(
				'id'    => $menu['id'],
				'name'  => $menu['name'],
				'slug'  => $menu['slug'],
				'items' => isset( $stats['items'] ) ? $stats['items'] : 0,
				'links' => isset( $stats['links'] ) ? $stats['links'] : 0,
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'name', 'slug', 'items', 'links' ) );
	}

	/**
	 * Gyorsítótár ürítése.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amm flush
	 *
	 * @return void
	 */
	public function flush() {
		AMM_Cache::flush();
		WP_CLI::success( __( 'A menü gyorsítótár kiürült.', 'and-menumanager' ) );
	}

	/**
	 * Oldalindex előmelegítése.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amm prewarm
	 *
	 * @return void
	 */
	public function prewarm() {
		foreach ( array_keys( AMM_Pages::post_types() ) as $post_type ) {
			if ( AMM_Pages::use_index( $post_type ) ) {
				AMM_Pages::get_index( $post_type );
				WP_CLI::log( sprintf( '%s: %d', $post_type, AMM_Pages::count( $post_type ) ) );
			}
		}

		WP_CLI::success( __( 'Az index felépült.', 'and-menumanager' ) );
	}

	/**
	 * Beépített WordPress menük átemelése.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amm import-core
	 *
	 * @return void
	 */
	public function import_core() {
		$result = AMM_Importer::import_core_menus();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( '%d menü átemelve.', $result['count'] ) );
	}

	/**
	 * Árva menüelemek eltávolítása.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amm purge-orphans
	 *
	 * @return void
	 */
	public function purge_orphans() {
		$deleted = AMM_Automations::purge_orphans();

		WP_CLI::success( sprintf( '%d árva elem eltávolítva.', $deleted ) );
	}

	/**
	 * Menük exportálása JSON-be.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Kimeneti fájl. Alapértelmezésben a képernyőre ír.
	 *
	 * @param array $args       Pozicionális argumentumok.
	 * @param array $assoc_args Kapcsolók.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$payload = wp_json_encode( AMM_Importer::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( ! empty( $assoc_args['file'] ) ) {
			file_put_contents( $assoc_args['file'], $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			WP_CLI::success( sprintf( 'Mentve: %s', $assoc_args['file'] ) );

			return;
		}

		WP_CLI::line( $payload );
	}
}
