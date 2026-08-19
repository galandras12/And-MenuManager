<?php
/**
 * REST API végpontok az admin felülethez.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Rest
 */
class AMM_Rest {

	/**
	 * Hookok bekötése.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Jogosultság: menük szerkesztése.
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage() {
		if ( AMM_Capabilities::can_manage() ) {
			return true;
		}

		return new WP_Error( 'amm_forbidden', __( 'Nincs jogosultságod a menük kezeléséhez.', 'and-menumanager' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Jogosultság: beállítások.
	 *
	 * @return bool|WP_Error
	 */
	public function can_configure() {
		if ( AMM_Capabilities::can_configure() ) {
			return true;
		}

		return new WP_Error( 'amm_forbidden', __( 'Nincs jogosultságod a beállítások módosításához.', 'and-menumanager' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Útvonalak regisztrálása.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns      = AMM_REST_NAMESPACE;
		$manage  = array( $this, 'can_manage' );
		$config  = array( $this, 'can_configure' );

		register_rest_route(
			$ns,
			'/menus',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_menus' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_menu' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_menu' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_menu' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_menu' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_menu' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/tree',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tree' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/items',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_items' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder_items' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/exclusions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_exclusions' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/menus/(?P<id>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'preview_menu' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/items/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			$ns,
			'/objects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_objects' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => $config,
				),
			)
		);

		register_rest_route(
			$ns,
			'/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_roles' ),
					'permission_callback' => $config,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_roles' ),
					'permission_callback' => $config,
				),
			)
		);

		register_rest_route(
			$ns,
			'/tools/(?P<action>[a-z-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_tool' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$ns,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * Menük listája.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function get_menus( $request ) {
		$result = AMM_Menu_Repository::all(
			array(
				'search'   => $request->get_param( 'search' ),
				'page'     => $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1,
				'per_page' => $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 50,
				'orderby'  => $request->get_param( 'orderby' ) ? $request->get_param( 'orderby' ) : 'name',
				'order'    => $request->get_param( 'order' ) ? $request->get_param( 'order' ) : 'ASC',
			)
		);

		$ids    = wp_list_pluck( $result['items'], 'id' );
		$counts = AMM_Item_Repository::count_by_menus( $ids );

		foreach ( $result['items'] as &$menu ) {
			$menu['item_count'] = isset( $counts[ $menu['id'] ] ) ? $counts[ $menu['id'] ] : 0;
		}

		unset( $menu );

		return rest_ensure_response( $result );
	}

	/**
	 * Egy menü.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_menu( $request ) {
		$menu = AMM_Menu_Repository::get( (int) $request['id'] );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $menu );
	}

	/**
	 * Menü létrehozása.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_menu( $request ) {
		$id = AMM_Menu_Repository::insert(
			array(
				'name'        => $request->get_param( 'name' ),
				'slug'        => $request->get_param( 'slug' ),
				'description' => $request->get_param( 'description' ),
				'settings'    => $request->get_param( 'settings' ),
			)
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return rest_ensure_response( AMM_Menu_Repository::get( $id ) );
	}

	/**
	 * Menü frissítése.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_menu( $request ) {
		$data = array();

		foreach ( array( 'name', 'slug', 'description', 'settings' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$data[ $key ] = $request->get_param( $key );
			}
		}

		$result = AMM_Menu_Repository::update( (int) $request['id'], $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( AMM_Menu_Repository::get( (int) $request['id'] ) );
	}

	/**
	 * Menü törlése.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function delete_menu( $request ) {
		$deleted = AMM_Menu_Repository::delete( (int) $request['id'] );

		return rest_ensure_response( array( 'deleted' => $deleted ) );
	}

	/**
	 * Menü másolása.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_menu( $request ) {
		$id = AMM_Menu_Repository::duplicate( (int) $request['id'] );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return rest_ensure_response( AMM_Menu_Repository::get( $id ) );
	}

	/**
	 * Szerkesztői fa és statisztika.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tree( $request ) {
		$menu_id = (int) $request['id'];
		$menu    = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'menu'  => $menu,
				'tree'  => AMM_Tree::editor_tree( $menu_id ),
				'stats' => AMM_Tree::stats( $menu_id ),
			)
		);
	}

	/**
	 * Elemek felvétele (egyesével vagy kötegelten).
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_items( $request ) {
		$menu_id = (int) $request['id'];
		$menu    = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$items   = $request->get_param( 'items' );
		$created = array();
		$skipped = 0;

		if ( ! is_array( $items ) || empty( $items ) ) {
			$items = array( $request->get_params() );
		}

		foreach ( $items as $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$object_id = isset( $data['object_id'] ) ? (int) $data['object_id'] : 0;
			$type      = isset( $data['type'] ) ? $data['type'] : 'post_type';

			if ( 'post_type' === $type && $object_id && empty( $data['allow_duplicate'] ) && AMM_Item_Repository::exists_in_menu( $menu_id, $object_id ) ) {
				++$skipped;
				continue;
			}

			if ( ! isset( $data['auto_children'] ) && 'post_type' === $type ) {
				$data['auto_children'] = $menu['settings']['auto_children'] ? 1 : 0;
			}

			if ( ! isset( $data['auto_order'] ) ) {
				$data['auto_order'] = $menu['settings']['auto_order'];
			}

			$new_id = AMM_Item_Repository::insert( $menu_id, $data );

			if ( is_wp_error( $new_id ) ) {
				return $new_id;
			}

			$created[] = $new_id;
		}

		return rest_ensure_response(
			array(
				'created' => $created,
				'skipped' => $skipped,
				'tree'    => AMM_Tree::editor_tree( $menu_id ),
				'stats'   => AMM_Tree::stats( $menu_id ),
			)
		);
	}

	/**
	 * Elem frissítése.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$item = AMM_Item_Repository::get( (int) $request['id'] );

		if ( ! $item ) {
			return new WP_Error( 'amm_not_found', __( 'A menüelem nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$data   = $request->get_params();
		$result = AMM_Item_Repository::update( (int) $request['id'], $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'item'  => AMM_Item_Repository::get( (int) $request['id'] ),
				'tree'  => AMM_Tree::editor_tree( $item['menu_id'] ),
				'stats' => AMM_Tree::stats( $item['menu_id'] ),
			)
		);
	}

	/**
	 * Elem törlése.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$item = AMM_Item_Repository::get( (int) $request['id'] );

		if ( ! $item ) {
			return new WP_Error( 'amm_not_found', __( 'A menüelem nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		AMM_Item_Repository::delete( (int) $request['id'] );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'tree'    => AMM_Tree::editor_tree( $item['menu_id'] ),
				'stats'   => AMM_Tree::stats( $item['menu_id'] ),
			)
		);
	}

	/**
	 * Kötegelt átrendezés.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_items( $request ) {
		$menu_id = (int) $request['id'];
		$batch   = $request->get_param( 'items' );

		if ( ! is_array( $batch ) ) {
			return new WP_Error( 'amm_invalid_payload', __( 'Hiányzó sorrend adat.', 'and-menumanager' ), array( 'status' => 400 ) );
		}

		$result = AMM_Item_Repository::reorder( $menu_id, $batch );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'ok'    => true,
				'tree'  => AMM_Tree::editor_tree( $menu_id ),
				'stats' => AMM_Tree::stats( $menu_id ),
			)
		);
	}

	/**
	 * Kizárások módosítása.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_exclusions( $request ) {
		$menu_id = (int) $request['id'];
		$menu    = AMM_Menu_Repository::get( $menu_id );

		if ( ! $menu ) {
			return new WP_Error( 'amm_not_found', __( 'A menü nem található.', 'and-menumanager' ), array( 'status' => 404 ) );
		}

		$ids     = array_filter( array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
		$exclude = (bool) $request->get_param( 'exclude' );
		$current = $menu['settings']['excluded'];

		if ( $exclude ) {
			$current = array_merge( $current, $ids );
		} else {
			$current = array_diff( $current, $ids );
		}

		$result = AMM_Menu_Repository::update(
			$menu_id,
			array( 'settings' => array( 'excluded' => array_values( array_unique( $current ) ) ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'menu'  => AMM_Menu_Repository::get( $menu_id ),
				'stats' => AMM_Tree::stats( $menu_id ),
			)
		);
	}

	/**
	 * Élő előnézet.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function preview_menu( $request ) {
		$menu_id = (int) $request['id'];

		return rest_ensure_response(
			array(
				'html' => AMM_Renderer::render(
					$menu_id,
					array(
						'container' => 'div',
						'style'     => $request->get_param( 'style' ) ? sanitize_key( $request->get_param( 'style' ) ) : '',
					)
				),
			)
		);
	}

	/**
	 * Tartalom böngészése és keresése a választóhoz.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function get_objects( $request ) {
		$post_type = $request->get_param( 'post_type' ) ? sanitize_key( $request->get_param( 'post_type' ) ) : 'page';
		$search    = (string) $request->get_param( 'search' );
		$parent    = (int) $request->get_param( 'parent' );
		$menu_id   = (int) $request->get_param( 'menu_id' );
		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : AMM_Settings::value( 'editor_page_size' );
		$per_page  = max( 10, min( 500, $per_page ) );

		$types = AMM_Pages::post_types();

		if ( ! isset( $types[ $post_type ] ) ) {
			$post_type = 'page';
		}

		$menu     = $menu_id ? AMM_Menu_Repository::get( $menu_id ) : null;
		$excluded = $menu ? array_flip( $menu['settings']['excluded'] ) : array();
		$in_menu  = array();

		if ( $menu ) {
			foreach ( AMM_Item_Repository::get_for_menu( $menu_id ) as $item ) {
				if ( $item['object_id'] ) {
					$in_menu[ (int) $item['object_id'] ] = true;
				}
			}
		}

		if ( '' !== trim( $search ) ) {
			$nodes = AMM_Pages::search( $search, $post_type, $per_page );
			$total = count( $nodes );
			$mode  = 'search';
		} else {
			$offset = ( $page - 1 ) * $per_page;
			$nodes  = AMM_Pages::get_children_page( $parent, $post_type, $offset, $per_page );
			$total  = AMM_Pages::count_children( $parent, $post_type );
			$mode   = 'browse';
		}

		$counts = AMM_Pages::count_children_batch( wp_list_pluck( $nodes, 'id' ), $post_type );
		$items  = array();

		foreach ( $nodes as $node ) {
			$entry = array(
				'id'           => $node['id'],
				'parent'       => $node['parent'],
				'title'        => '' !== trim( $node['title'] ) ? $node['title'] : sprintf( '#%d', $node['id'] ),
				'slug'         => $node['slug'],
				'status'       => $node['status'],
				'post_type'    => $post_type,
				'child_count'  => isset( $counts[ $node['id'] ] ) ? $counts[ $node['id'] ] : 0,
				'in_menu'      => isset( $in_menu[ $node['id'] ] ),
				'excluded'     => isset( $excluded[ $node['id'] ] ),
				'edit_link'    => get_edit_post_link( $node['id'], 'raw' ),
			);

			if ( 'search' === $mode ) {
				$crumbs = array();

				foreach ( AMM_Pages::breadcrumb( $node['parent'], $post_type ) as $crumb ) {
					$crumbs[] = $crumb['title'];
				}

				$entry['breadcrumb'] = $crumbs;
			}

			$items[] = $entry;
		}

		return rest_ensure_response(
			array(
				'items'     => $items,
				'total'     => $total,
				'page'      => $page,
				'per_page'  => $per_page,
				'mode'      => $mode,
				'parent'    => $parent,
				'post_type' => $post_type,
				'crumbs'    => $parent ? AMM_Pages::breadcrumb( $parent, $post_type ) : array(),
			)
		);
	}

	/**
	 * Beállítások lekérése.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response(
			array(
				'settings'   => AMM_Settings::get(),
				'locations'  => $this->locations(),
				'post_types' => array_values( AMM_Pages::post_types() ),
				'can_config' => AMM_Capabilities::can_configure(),
			)
		);
	}

	/**
	 * Beállítások mentése.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function update_settings( $request ) {
		$settings = AMM_Settings::update( (array) $request->get_param( 'settings' ) );

		return rest_ensure_response( array( 'settings' => $settings ) );
	}

	/**
	 * Sablonpozíciók listája.
	 *
	 * @return array
	 */
	private function locations() {
		$registered = get_registered_nav_menus();
		$assigned   = AMM_Settings::value( 'locations', array() );
		$out        = array();

		foreach ( $registered as $key => $label ) {
			$out[] = array(
				'key'     => $key,
				'label'   => $label,
				'menu_id' => isset( $assigned[ $key ] ) ? (int) $assigned[ $key ] : 0,
			);
		}

		return $out;
	}

	/**
	 * Szerepkörök és jogosultságok.
	 *
	 * @return WP_REST_Response
	 */
	public function get_roles() {
		return rest_ensure_response(
			array(
				'roles' => AMM_Capabilities::get_map(),
				'caps'  => AMM_Capabilities::all(),
			)
		);
	}

	/**
	 * Szerepkör-jogosultságok mentése.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function update_roles( $request ) {
		$map = (array) $request->get_param( 'roles' );

		AMM_Capabilities::save_map( $map );

		return rest_ensure_response( array( 'roles' => AMM_Capabilities::get_map() ) );
	}

	/**
	 * Karbantartó műveletek.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_tool( $request ) {
		$action = sanitize_key( $request['action'] );

		switch ( $action ) {
			case 'flush':
				AMM_Cache::flush();

				return rest_ensure_response( array( 'message' => __( 'A gyorsítótár kiürült.', 'and-menumanager' ) ) );

			case 'orphans':
				$deleted = AMM_Automations::purge_orphans();

				return rest_ensure_response(
					array(
						/* translators: %d: eltávolított elemek száma. */
						'message' => sprintf( __( '%d árva menüelem eltávolítva.', 'and-menumanager' ), $deleted ),
						'deleted' => $deleted,
					)
				);

			case 'import-core':
				$result = AMM_Importer::import_core_menus();

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return rest_ensure_response( $result );

			case 'prewarm':
				foreach ( array_keys( AMM_Pages::post_types() ) as $post_type ) {
					if ( AMM_Pages::use_index( $post_type ) ) {
						AMM_Pages::get_index( $post_type );
					}
				}

				return rest_ensure_response( array( 'message' => __( 'Az oldalindex felépült.', 'and-menumanager' ) ) );

			default:
				return new WP_Error( 'amm_unknown_tool', __( 'Ismeretlen művelet.', 'and-menumanager' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Állapotjelentés.
	 *
	 * @return WP_REST_Response
	 */
	public function get_health() {
		$stored = get_option( 'amm_health_report', array() );

		return rest_ensure_response(
			array(
				'report'    => AMM_Automations::health_report(),
				'generated' => isset( $stored['generated'] ) ? $stored['generated'] : '',
				'index'     => array(
					'pages'     => AMM_Pages::count( 'page' ),
					'indexed'   => AMM_Pages::use_index( 'page' ),
					'threshold' => (int) AMM_Settings::value( 'index_threshold' ),
				),
			)
		);
	}

	/**
	 * Exportálás JSON-be.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function export( $request ) {
		$ids = $request->get_param( 'ids' );
		$ids = $ids ? array_filter( array_map( 'intval', (array) $ids ) ) : array();

		return rest_ensure_response( AMM_Importer::export( $ids ) );
	}

	/**
	 * Importálás JSON-ből.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( $request ) {
		$payload = $request->get_param( 'payload' );

		if ( is_string( $payload ) ) {
			$payload = json_decode( $payload, true );
		}

		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'amm_invalid_payload', __( 'Érvénytelen import adat.', 'and-menumanager' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( AMM_Importer::import( $payload ) );
	}
}
