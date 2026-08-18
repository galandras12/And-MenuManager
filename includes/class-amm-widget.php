<?php
/**
 * Klasszikus oldalsáv widget.
 *
 * @package And_MenuManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AMM_Widget
 */
class AMM_Widget extends WP_Widget {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		parent::__construct(
			'amm_menu_widget',
			__( 'And-MenuManager menü', 'and-menumanager' ),
			array(
				'description' => __( 'Egy And-MenuManager menü megjelenítése az oldalsávban.', 'and-menumanager' ),
				'classname'   => 'amm-widget',
			)
		);
	}

	/**
	 * Widget megjelenítése.
	 *
	 * @param array $args     Sidebar argumentumok.
	 * @param array $instance Widget beállítások.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$menu_id = isset( $instance['menu_id'] ) ? (int) $instance['menu_id'] : 0;

		if ( ! $menu_id ) {
			return;
		}

		$html = AMM_Renderer::render(
			$menu_id,
			array(
				'style'     => isset( $instance['style'] ) ? $instance['style'] : '',
				'depth'     => isset( $instance['depth'] ) ? (int) $instance['depth'] : 0,
				'container' => 'div',
			)
		);

		if ( '' === $html ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Kirajzoláskor escape-elve.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Beállító űrlap.
	 *
	 * @param array $instance Widget beállítások.
	 * @return void
	 */
	public function form( $instance ) {
		$title   = isset( $instance['title'] ) ? $instance['title'] : '';
		$menu_id = isset( $instance['menu_id'] ) ? (int) $instance['menu_id'] : 0;
		$style   = isset( $instance['style'] ) ? $instance['style'] : '';
		$depth   = isset( $instance['depth'] ) ? (int) $instance['depth'] : 0;
		$menus   = AMM_Menu_Repository::all( array( 'per_page' => 200 ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Cím:', 'and-menumanager' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'menu_id' ) ); ?>"><?php esc_html_e( 'Menü:', 'and-menumanager' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'menu_id' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'menu_id' ) ); ?>">
				<option value="0"><?php esc_html_e( '— válassz —', 'and-menumanager' ); ?></option>
				<?php foreach ( $menus['items'] as $menu ) : ?>
					<option value="<?php echo esc_attr( $menu['id'] ); ?>" <?php selected( $menu_id, $menu['id'] ); ?>>
						<?php echo esc_html( $menu['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"><?php esc_html_e( 'Megjelenés:', 'and-menumanager' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>">
				<option value="" <?php selected( $style, '' ); ?>><?php esc_html_e( 'Menü alapbeállítása', 'and-menumanager' ); ?></option>
				<option value="vertical" <?php selected( $style, 'vertical' ); ?>><?php esc_html_e( 'Függőleges', 'and-menumanager' ); ?></option>
				<option value="accordion" <?php selected( $style, 'accordion' ); ?>><?php esc_html_e( 'Lenyíló (harmonika)', 'and-menumanager' ); ?></option>
				<option value="horizontal" <?php selected( $style, 'horizontal' ); ?>><?php esc_html_e( 'Vízszintes', 'and-menumanager' ); ?></option>
				<option value="columns" <?php selected( $style, 'columns' ); ?>><?php esc_html_e( 'Oszlopos', 'and-menumanager' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'depth' ) ); ?>"><?php esc_html_e( 'Maximális mélység (0 = korlátlan):', 'and-menumanager' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'depth' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'depth' ) ); ?>" type="number" min="0" max="10"
				value="<?php echo esc_attr( $depth ); ?>">
		</p>
		<?php
	}

	/**
	 * Mentés.
	 *
	 * @param array $new_instance Új értékek.
	 * @param array $old_instance Régi értékek.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'   => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'menu_id' => isset( $new_instance['menu_id'] ) ? (int) $new_instance['menu_id'] : 0,
			'style'   => isset( $new_instance['style'] ) ? sanitize_key( $new_instance['style'] ) : '',
			'depth'   => isset( $new_instance['depth'] ) ? (int) $new_instance['depth'] : 0,
		);
	}
}
