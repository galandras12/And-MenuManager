<?php
/**
 * Menü megjelenítése a látogatói oldalon.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Renderer
 */
class AMM_Renderer {

	/**
	 * Alapértelmezett megjelenítési paraméterek.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'style'           => '',
			'container'       => 'nav',
			'container_class' => '',
			'container_id'    => '',
			'menu_class'      => '',
			'menu_id'         => '',
			'compat'          => false,
			'depth'           => 0,
			'toggles'         => true,
			'aria_label'      => '',
			'echo'            => false,
			'fallback'        => '',
		);
	}

	/**
	 * Menü kirajzolása azonosító vagy slug alapján.
	 *
	 * @param int|string $menu Menü azonosító vagy slug.
	 * @param array      $args Paraméterek.
	 * @return string
	 */
	public static function render( $menu, $args = array() ) {
		$args   = wp_parse_args( $args, self::defaults() );
		$record = is_numeric( $menu ) ? AMM_Menu_Repository::get( (int) $menu ) : AMM_Menu_Repository::get_by_slug( (string) $menu );

		if ( ! $record ) {
			return self::output( $args['fallback'], $args );
		}

		$tree = AMM_Tree::build( $record['id'] );

		if ( empty( $tree ) ) {
			return self::output( $args['fallback'], $args );
		}

		$style = $args['style'] ? $args['style'] : $record['settings']['style'];

		if ( $args['compat'] ) {
			// Téma-kompatibilis mód: a téma saját osztályai maradnak, a
			// sajátjainkat nem tesszük hozzá, hogy a téma CSS-e és
			// JavaScriptje pontosan úgy működjön, mint a beépített menüvel.
			$classes = array();

			if ( $args['menu_class'] ) {
				$classes = preg_split( '/\s+/', $args['menu_class'] );
			}

			if ( $record['settings']['css_class'] ) {
				$classes = array_merge( $classes, preg_split( '/\s+/', $record['settings']['css_class'] ) );
			}

			if ( empty( $classes ) ) {
				$classes = array( 'menu' );
			}
		} else {
			$classes = array( 'amm-menu', 'amm-menu--' . sanitize_html_class( $style ) );

			if ( $record['settings']['css_class'] ) {
				$classes = array_merge( $classes, preg_split( '/\s+/', $record['settings']['css_class'] ) );
			}

			if ( $args['menu_class'] ) {
				$classes = array_merge( $classes, preg_split( '/\s+/', $args['menu_class'] ) );
			}

			if ( $record['settings']['collapse_subs'] ) {
				$classes[] = 'amm-menu--collapsible';
			}
		}

		$classes = array_values( array_unique( array_filter( array_map( 'sanitize_html_class', $classes ) ) ) );

		$current = self::current_context();
		$html    = self::render_level( $tree, $args, $current, 1, $classes );

		if ( $args['menu_id'] ) {
			// A téma CSS-e gyakran azonosítóra hivatkozik (pl. #primary-menu).
			$html = preg_replace(
				'/^<ul /',
				sprintf( '<ul id="%s" ', esc_attr( $args['menu_id'] ) ),
				$html,
				1
			);
		}

		if ( 'none' !== $args['container'] && $args['container'] ) {
			$tag             = tag_escape( $args['container'] );
			$container_class = trim( 'amm-menu-container ' . $args['container_class'] );
			$attributes      = sprintf( ' class="%s"', esc_attr( $container_class ) );

			if ( $args['container_id'] ) {
				$attributes .= sprintf( ' id="%s"', esc_attr( $args['container_id'] ) );
			}

			if ( 'nav' === $tag ) {
				$label       = $args['aria_label'] ? $args['aria_label'] : $record['name'];
				$attributes .= sprintf( ' aria-label="%s"', esc_attr( $label ) );
			}

			$html = sprintf( '<%1$s%2$s>%3$s</%1$s>', $tag, $attributes, $html );
		}

		/**
		 * A kész menü HTML.
		 *
		 * @param string $html   HTML.
		 * @param array  $record Menü rekord.
		 * @param array  $args   Paraméterek.
		 */
		$html = apply_filters( 'amm_menu_html', $html, $record, $args );

		return self::output( $html, $args );
	}

	/**
	 * Kimenet kezelése (echo vagy visszatérés).
	 *
	 * @param string $html HTML.
	 * @param array  $args Paraméterek.
	 * @return string
	 */
	private static function output( $html, $args ) {
		if ( ! empty( $args['echo'] ) ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Kirajzoláskor escape-elve.
		}

		return $html;
	}

	/**
	 * Az aktuálisan megtekintett tartalom azonosítói.
	 *
	 * @return array
	 */
	private static function current_context() {
		$object_id = 0;

		if ( is_singular() ) {
			$object_id = (int) get_queried_object_id();
		}

		$ancestors = array();

		if ( $object_id ) {
			$node  = AMM_Pages::get_node( $object_id, get_post_type( $object_id ) );
			$guard = 0;

			while ( $node && $node['parent'] && ++$guard < 50 ) {
				$ancestors[] = (int) $node['parent'];
				$node        = AMM_Pages::get_node( $node['parent'], $node['type'] );
			}
		}

		return array(
			'id'        => $object_id,
			'ancestors' => array_flip( $ancestors ),
			'url'       => self::current_url(),
		);
	}

	/**
	 * Aktuális URL (egyedi linkek kiemeléséhez).
	 *
	 * @return string
	 */
	private static function current_url() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		if ( '' === $host ) {
			return '';
		}

		$uri    = wp_unslash( $_SERVER['REQUEST_URI'] );
		$path   = strtok( $uri, '?' );
		$scheme = is_ssl() ? 'https://' : 'http://';

		return untrailingslashit( esc_url_raw( $scheme . $host . $path ) );
	}

	/**
	 * Egy szint kirajzolása.
	 *
	 * @param array $nodes   Csomópontok.
	 * @param array $args    Paraméterek.
	 * @param array $current Aktuális kontextus.
	 * @param int   $depth   Mélység.
	 * @param array $classes Lista osztályok (csak a legfelső szinten).
	 * @return string
	 */
	private static function render_level( $nodes, $args, $current, $depth, $classes = array() ) {
		if ( empty( $nodes ) ) {
			return '';
		}

		if ( $args['depth'] > 0 && $depth > $args['depth'] ) {
			return '';
		}

		if ( 1 === $depth ) {
			$list_class = implode( ' ', $classes );
		} elseif ( $args['compat'] ) {
			$list_class = 'sub-menu';
		} else {
			$list_class = 'amm-submenu amm-submenu--level-' . (int) $depth;
		}

		$html       = sprintf( '<ul class="%s">', esc_attr( $list_class ) );

		foreach ( $nodes as $node ) {
			$html .= self::render_node( $node, $args, $current, $depth );
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * A WordPress beépített menüjével egyező osztálylista.
	 *
	 * A témák CSS-e és JavaScriptje ezekre épül (menu-item,
	 * menu-item-has-children, current-menu-item, sub-menu), ezért
	 * sablonpozíción ezeket adjuk ki a sajátjaink helyett.
	 *
	 * @param array $node         Csomópont.
	 * @param array $current      Aktuális kontextus.
	 * @param bool  $is_current   Ez az aktuális oldal?
	 * @param bool  $is_ancestor  Az aktuális oldal őse?
	 * @param bool  $has_children Van-e almenüje?
	 * @return array
	 */
	private static function compat_classes( $node, $current, $is_current, $is_ancestor, $has_children ) {
		$classes = array( 'menu-item' );

		switch ( $node['type'] ) {
			case 'post_type':
				$classes[] = 'menu-item-type-post_type';
				$classes[] = 'menu-item-object-' . $node['object_type'];
				break;

			case 'taxonomy':
				$classes[] = 'menu-item-type-taxonomy';
				$classes[] = 'menu-item-object-' . $node['object_type'];
				break;

			case 'archive':
				$classes[] = 'menu-item-type-post_type_archive';
				$classes[] = 'menu-item-object-' . $node['object_type'];
				break;

			default:
				$classes[] = 'menu-item-type-custom';
				$classes[] = 'menu-item-object-custom';
				break;
		}

		if ( $node['object_id'] ) {
			$classes[] = 'menu-item-' . (int) $node['object_id'];
		}

		if ( $has_children ) {
			$classes[] = 'menu-item-has-children';
		}

		if ( $is_current ) {
			$classes[] = 'current-menu-item';
			$classes[] = 'current_page_item';
		} elseif ( $is_ancestor ) {
			$classes[] = 'current-menu-ancestor';
			$classes[] = 'current_page_ancestor';

			// Közvetlen szülő-e az aktuális oldalé?
			foreach ( $node['children'] as $child ) {
				if ( ! empty( $child['object_id'] ) && $child['object_id'] === $current['id'] ) {
					$classes[] = 'current-menu-parent';
					$classes[] = 'current_page_parent';
					break;
				}
			}
		}

		// A felhasználó saját CSS osztályai megmaradnak, a sajátjaink nem.
		foreach ( $node['classes'] as $class ) {
			if ( 0 !== strpos( $class, 'amm-' ) ) {
				$classes[] = $class;
			}
		}

		return $classes;
	}

	/**
	 * Egy csomópont kirajzolása.
	 *
	 * @param array $node    Csomópont.
	 * @param array $args    Paraméterek.
	 * @param array $current Aktuális kontextus.
	 * @param int   $depth   Mélység.
	 * @return string
	 */
	private static function render_node( $node, $args, $current, $depth ) {
		$compat     = ! empty( $args['compat'] );
		$is_current = false;
		$is_ancestor = false;

		if ( $node['object_id'] && $node['object_id'] === $current['id'] ) {
			$is_current = true;
		} elseif ( $node['object_id'] && isset( $current['ancestors'][ $node['object_id'] ] ) ) {
			$is_ancestor = true;
		} elseif ( $node['url'] && $current['url'] && untrailingslashit( $node['url'] ) === $current['url'] ) {
			$is_current = true;
		}

		$children_html = self::render_level( $node['children'], $args, $current, $depth + 1 );

		if ( $compat ) {
			$classes = self::compat_classes( $node, $current, $is_current, $is_ancestor, '' !== $children_html );
		} else {
			$classes = array_merge( array( 'amm-item', 'amm-item--depth-' . (int) $depth ), $node['classes'] );

			if ( $is_current ) {
				$classes[] = 'amm-item--current';
			} elseif ( $is_ancestor ) {
				$classes[] = 'amm-item--current-ancestor';
			}

			if ( $children_html ) {
				$classes[] = 'amm-item--parent';
			}
		}

		$classes = array_values( array_unique( array_filter( array_map( 'sanitize_html_class', $classes ) ) ) );

		$html = sprintf( '<li class="%s">', esc_attr( implode( ' ', $classes ) ) );

		$attributes = '';

		if ( $node['target'] ) {
			$attributes .= sprintf( ' target="%s"', esc_attr( $node['target'] ) );

			if ( '_blank' === $node['target'] ) {
				$node['rel'] = trim( $node['rel'] . ' noopener' );
			}
		}

		if ( $node['rel'] ) {
			$attributes .= sprintf( ' rel="%s"', esc_attr( $node['rel'] ) );
		}

		if ( $node['description'] ) {
			$attributes .= sprintf( ' title="%s"', esc_attr( $node['description'] ) );
		}

		if ( $is_current ) {
			$attributes .= ' aria-current="page"';
		}

		if ( $compat ) {
			// A WordPress beépített menüjével azonos felépítés: sem saját
			// osztály, sem extra elem nem kerül a linkbe, így a téma
			// stílusai és szkriptjei változatlanul működnek.
			if ( $node['url'] ) {
				$html .= sprintf(
					'<a href="%s"%s>%s</a>',
					esc_url( $node['url'] ),
					$attributes,
					esc_html( $node['title'] )
				);
			} else {
				$html .= sprintf( '<span>%s</span>', esc_html( $node['title'] ) );
			}

			$html .= $children_html;
			$html .= '</li>';

			return $html;
		}

		if ( $node['url'] ) {
			$html .= sprintf(
				'<a class="amm-link" href="%s"%s><span class="amm-link__label">%s</span></a>',
				esc_url( $node['url'] ),
				$attributes,
				esc_html( $node['title'] )
			);
		} else {
			$html .= sprintf( '<span class="amm-link amm-link--static">%s</span>', esc_html( $node['title'] ) );
		}

		if ( $children_html && $args['toggles'] ) {
			$html .= sprintf(
				'<button type="button" class="amm-toggle" aria-expanded="false" aria-label="%s"><span class="amm-toggle__icon" aria-hidden="true"></span></button>',
				/* translators: %s: menüpont neve. */
				esc_attr( sprintf( __( '"%s" almenü megnyitása', 'and-menumanager' ), $node['title'] ) )
			);
		}

		$html .= $children_html;
		$html .= '</li>';

		return $html;
	}
}
