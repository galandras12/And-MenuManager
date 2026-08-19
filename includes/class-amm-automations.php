<?php
/**
 * Automatizmusok: önműködő karbantartás a tartalomváltozásokra.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Automations
 */
class AMM_Automations {

	/**
	 * Hookok bekötése.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ), 10, 1 );
		add_action( 'trashed_post', array( $this, 'on_trashed_post' ), 10, 1 );
		add_action( 'untrashed_post', array( $this, 'on_status_change' ), 10, 1 );
		add_action( 'amm_daily_maintenance', array( $this, 'daily_maintenance' ) );
	}

	/**
	 * Érinti-e a bejegyzéstípus a menüket?
	 *
	 * @param string $post_type Bejegyzéstípus.
	 * @return bool
	 */
	private function is_tracked( $post_type ) {
		$types = AMM_Pages::post_types();

		return isset( $types[ $post_type ] );
	}

	/**
	 * Frissítéskor csak akkor ürítünk cache-t, ha menüt érintő mező változott.
	 *
	 * @param int     $post_id     Bejegyzés azonosító.
	 * @param WP_Post $post_after  Új állapot.
	 * @param WP_Post $post_before Régi állapot.
	 * @return void
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->is_tracked( $post_after->post_type ) ) {
			return;
		}

		$watched = array( 'post_title', 'post_name', 'post_parent', 'menu_order', 'post_status', 'post_password' );

		foreach ( $watched as $field ) {
			if ( $post_after->$field !== $post_before->$field ) {
				AMM_Cache::flush();

				return;
			}
		}
	}

	/**
	 * Új tartalom mentése.
	 *
	 * @param int     $post_id Bejegyzés azonosító.
	 * @param WP_Post $post    Bejegyzés.
	 * @param bool    $update  Frissítés-e.
	 * @return void
	 */
	public function on_save_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->is_tracked( $post->post_type ) ) {
			return;
		}

		if ( ! in_array( $post->post_status, AMM_Pages::statuses(), true ) ) {
			return;
		}

		AMM_Cache::flush();

		if ( ! $update ) {
			$this->auto_add_new_page( $post );
		}
	}

	/**
	 * Új gyökérszintű oldal automatikus felvétele.
	 *
	 * Az aloldalak nem igényelnek beavatkozást: azokat a szabályalapú
	 * feloldás magától megjeleníti. Itt csak a szülő nélküli új oldalakat
	 * kell felvenni azokba a menükbe, ahol ezt bekapcsolták.
	 *
	 * @param WP_Post $post Bejegyzés.
	 * @return void
	 */
	private function auto_add_new_page( $post ) {
		if ( ! AMM_Settings::value( 'auto_add_new_pages' ) ) {
			return;
		}

		if ( (int) $post->post_parent > 0 ) {
			return;
		}

		$menus = AMM_Menu_Repository::all( array( 'per_page' => 200 ) );

		foreach ( $menus['items'] as $menu ) {
			if ( empty( $menu['settings']['auto_add_root'] ) ) {
				continue;
			}

			if ( $menu['settings']['post_type'] !== $post->post_type ) {
				continue;
			}

			if ( AMM_Item_Repository::exists_in_menu( $menu['id'], $post->ID ) ) {
				continue;
			}

			AMM_Item_Repository::insert(
				$menu['id'],
				array(
					'type'          => 'post_type',
					'object_type'   => $post->post_type,
					'object_id'     => $post->ID,
					'auto_children' => $menu['settings']['auto_children'] ? 1 : 0,
					'auto_order'    => $menu['settings']['auto_order'],
				)
			);

			/**
			 * Új oldal automatikusan bekerült egy menübe.
			 *
			 * @param int $menu_id Menü azonosító.
			 * @param int $post_id Bejegyzés azonosító.
			 */
			do_action( 'amm_auto_added_item', $menu['id'], $post->ID );
		}
	}

	/**
	 * Végleges törlés: árva hivatkozások takarítása.
	 *
	 * @param int $post_id Bejegyzés azonosító.
	 * @return void
	 */
	public function on_deleted_post( $post_id ) {
		if ( AMM_Settings::value( 'cleanup_deleted' ) ) {
			AMM_Item_Repository::delete_by_object( $post_id );
			$this->drop_exclusion( $post_id );
		}

		AMM_Cache::flush();
	}

	/**
	 * Kukába helyezés.
	 *
	 * @param int $post_id Bejegyzés azonosító.
	 * @return void
	 */
	public function on_trashed_post( $post_id ) {
		AMM_Cache::flush();
	}

	/**
	 * Visszaállítás a kukából.
	 *
	 * @param int $post_id Bejegyzés azonosító.
	 * @return void
	 */
	public function on_status_change( $post_id ) {
		AMM_Cache::flush();
	}

	/**
	 * Kizárási listákból is töröljük a megszűnt bejegyzést.
	 *
	 * @param int $post_id Bejegyzés azonosító.
	 * @return void
	 */
	private function drop_exclusion( $post_id ) {
		$post_id = (int) $post_id;
		$menus   = AMM_Menu_Repository::all( array( 'per_page' => 200 ) );

		foreach ( $menus['items'] as $menu ) {
			if ( ! in_array( $post_id, $menu['settings']['excluded'], true ) ) {
				continue;
			}

			$excluded = array_values( array_diff( $menu['settings']['excluded'], array( $post_id ) ) );

			AMM_Menu_Repository::update( $menu['id'], array( 'settings' => array( 'excluded' => $excluded ) ) );
		}
	}

	/**
	 * Napi karbantartás: állapotjelentés és gyorsítótár-előmelegítés.
	 *
	 * @return void
	 */
	public function daily_maintenance() {
		$report = self::health_report();

		update_option(
			'amm_health_report',
			array(
				'generated' => current_time( 'mysql' ),
				'data'      => $report,
			),
			false
		);

		// Az oldalindex előmelegítése, hogy az első látogató se várjon rá.
		foreach ( array_keys( AMM_Pages::post_types() ) as $post_type ) {
			if ( AMM_Pages::use_index( $post_type ) ) {
				AMM_Pages::get_index( $post_type );
			}
		}
	}

	/**
	 * Állapotjelentés: hibás vagy figyelmet igénylő elemek.
	 *
	 * @return array
	 */
	public static function health_report() {
		$menus  = AMM_Menu_Repository::all( array( 'per_page' => 200 ) );
		$report = array(
			'missing_objects' => array(),
			'empty_menus'     => array(),
			'empty_urls'      => array(),
			'unused_menus'    => array(),
			'total_menus'     => $menus['total'],
		);

		$settings  = AMM_Settings::get();
		$locations = array_values( $settings['locations'] );

		foreach ( $menus['items'] as $menu ) {
			$items = AMM_Item_Repository::get_for_menu( $menu['id'] );

			if ( empty( $items ) ) {
				$report['empty_menus'][] = array(
					'id'   => $menu['id'],
					'name' => $menu['name'],
				);
			}

			foreach ( $items as $item ) {
				if ( 'post_type' === $item['type'] && $item['object_id'] && ! AMM_Pages::get_node( $item['object_id'], $item['object_type'] ) ) {
					$report['missing_objects'][] = array(
						'menu_id'   => $menu['id'],
						'menu_name' => $menu['name'],
						'item_id'   => $item['id'],
						'object_id' => $item['object_id'],
						'title'     => $item['title'],
					);
				}

				if ( 'custom' === $item['type'] && '' === trim( $item['url'] ) ) {
					$report['empty_urls'][] = array(
						'menu_id' => $menu['id'],
						'item_id' => $item['id'],
						'title'   => $item['title'],
					);
				}
			}

			if ( ! in_array( (int) $menu['id'], array_map( 'intval', $locations ), true ) ) {
				$report['unused_menus'][] = array(
					'id'   => $menu['id'],
					'name' => $menu['name'],
				);
			}
		}

		return $report;
	}

	/**
	 * Aloldalak rászinkronizálása egy menüre.
	 *
	 * Azoknál a menüpontoknál, amelyek olyan oldalra mutatnak, aminek van
	 * aloldala, bekapcsolja az automatikus aloldal-kezelést a megadott
	 * mélységig. Így a WordPress menüből hiányzó aloldalak is megjelennek,
	 * anélkül hogy több ezer menüpontot kellene létrehozni.
	 *
	 * @param int $menu_id Menü azonosító.
	 * @param int $depth   Mélység (0 = korlátlan).
	 * @return array|WP_Error
	 */
	public static function sync_children( $menu_id, $depth = 0 ) {
		$menu = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$items    = AMM_Item_Repository::get_for_menu( $menu_id );
		$depth    = max( 0, (int) $depth );
		$updated  = 0;
		$already  = 0;
		$parents  = 0;
		$failed   = 0;

		AMM_Cache::suspend();

		foreach ( $items as $item ) {
			if ( 'post_type' !== $item['type'] || ! $item['object_id'] ) {
				continue;
			}

			if ( ! AMM_Pages::has_children( $item['object_id'], $item['object_type'] ) ) {
				continue;
			}

			++$parents;

			if ( $item['auto_children'] && (int) $item['auto_depth'] === $depth ) {
				++$already;
				continue;
			}

			$result = AMM_Item_Repository::update(
				$item['id'],
				array(
					'auto_children' => 1,
					'auto_depth'    => $depth,
				)
			);

			if ( is_wp_error( $result ) ) {
				++$failed;
				AMM_Log::add( $result->get_error_message(), sprintf( 'Aloldal-szinkron: %s', $menu['name'] ) );
				continue;
			}

			++$updated;
		}

		AMM_Item_Repository::flush_touched();
		AMM_Cache::resume();
		AMM_Cache::flush();

		return array(
			'menu_id' => (int) $menu_id,
			'name'    => $menu['name'],
			'updated' => $updated,
			'already' => $already,
			'parents' => $parents,
			'failed'  => $failed,
			'links'   => AMM_Tree::stats( $menu_id ),
		);
	}

	/**
	 * Aloldalak rászinkronizálása minden menüre.
	 *
	 * @param int $depth Mélység (0 = korlátlan).
	 * @return array
	 */
	public static function sync_children_all( $depth = 0 ) {
		$menus   = AMM_Menu_Repository::all( array( 'per_page' => 500 ) );
		$results = array();
		$updated = 0;
		$already = 0;

		foreach ( $menus['items'] as $menu ) {
			$result = self::sync_children( $menu['id'], $depth );

			if ( is_wp_error( $result ) ) {
				continue;
			}

			$updated  += $result['updated'];
			$already  += $result['already'];
			$results[] = $result;
		}

		return array(
			'menus'   => $results,
			'updated' => $updated,
			'already' => $already,
			'count'   => count( $results ),
		);
	}

	/**
	 * Árva elemek eltávolítása minden menüből.
	 *
	 * @return int Törölt elemek száma.
	 */
	public static function purge_orphans() {
		$report  = self::health_report();
		$deleted = 0;

		foreach ( $report['missing_objects'] as $entry ) {
			if ( AMM_Item_Repository::delete( $entry['item_id'] ) ) {
				++$deleted;
			}
		}

		AMM_Cache::flush();

		return $deleted;
	}
}
