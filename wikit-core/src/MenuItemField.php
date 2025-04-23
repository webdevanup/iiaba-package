<?php

namespace WDG\Core;

/**
 * This class implements a base class to specify menu item options
 *
 * @package wdgdc/wikit-theme
 */
class MenuItemField {

	/**
	 * The meta key of the menu item field
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * The field type to render
	 *
	 * - text, select, checkbox, textarea (?)
	 *
	 * @var string
	 */
	public string $type = 'text';

	/**
	 * The label of the field in the admin
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * Limit the functionality to menus assigned to the specified locations
	 * e.g. primary, footer
	 *
	 * - if empty is allowed on all menus
	 */
	public array $locations = [];

	/**
	 * The name of the nonce action to verify legitimate editing
	 *
	 * @var string
	 */
	public string $menu_nonce_action;

	/**
	 * The allowed depths of
	 */
	public array $depth = [];

	public array $options = [];

	public $render;

	public function __construct( string $key, $args = [] ) {
		$this->key = $key;

		foreach ( $args as $arg => $val ) {
			if ( property_exists( $this, $arg ) ) {
				$this->{$arg} = $val;
			}
		}

		$this->label           ??= $this->key;
		$this->menu_nonce_action = "_{$this->key}_nonce";

		add_filter( 'wp_nav_menu_objects', [ $this, 'wp_nav_menu_objects' ], 1, 2 );
		add_action( 'wp_nav_menu_item_custom_fields', [ $this, 'wp_nav_menu_item_custom_fields' ], 1, 5 );
		add_action( 'wp_update_nav_menu_item', [ $this, 'wp_update_nav_menu_item' ], 10, 2 );
		add_filter( 'manage_nav-menus_columns', [ $this, 'manage_nav_menus_columns' ], 20 );
		add_action( 'admin_head', [ $this, 'admin_head' ] );
	}

	public function admin_head() {
		global $nav_menu_selected_id;

		$screen = get_current_screen();

		if ( empty( $screen ) || 'nav-menus' !== $screen->id ) {
			return;
		}

		if ( ! $this->menu_is_allowed( $nav_menu_selected_id ) ) {
			return;
		}

		if ( ! empty( $this->depth ) ) {
			printf(
				"<style> .field-%s:not(%s) { display: none } </style>\n",
				esc_attr( $this->key ),
				implode(
					', ',
					array_map( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						fn( $depth ) => sprintf( '.menu-item.menu-item-depth-%d .field-%s', $depth, $this->key ),
						$this->depth
					)
				)
			);
		}
	}

	/**
	 * Should this class operate for the given menu_id
	 *
	 * @param int|string $menu_id
	 * @return boolean
	 * @access protected
	 */
	protected function menu_is_allowed( $menu_id = null ) : bool {
		if ( null === $menu_id ) {
			$menu_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( ! is_numeric( $menu_id ) ) {
			return false;
		}

		if ( empty( $this->locations ) ) {
			return true;
		}

		$assigned_locations = get_theme_mod( 'nav_menu_locations' );

		if ( empty( $assigned_locations ) ) {
			return false;
		}

		$valid_menu_ids = array_intersect_key( $assigned_locations, array_fill_keys( $this->locations, null ) );

		if ( empty( $valid_menu_ids ) ) {
			return false;
		}

		return in_array( $menu_id, $valid_menu_ids, true );
	}

	/**
	 * Output the fields on the menu item
	 *
	 * @param string $item_id
	 * @param array $item
	 * @param int $depth
	 * @param array $args
	 * @param int $id
	 * @return void
	 * @access public
	 * @action wp_nav_menu_item_custom_fields
	 *
	 * @see https://make.wordpress.org/core/2020/02/25/wordpress-5-4-introduces-new-hooks-to-add-custom-fields-to-menu-items/
	 */
	public function wp_nav_menu_item_custom_fields( $item_id ) {
		global $nav_menu_selected_id;

		if ( ! $this->menu_is_allowed( $nav_menu_selected_id ) ) {
			return;
		}

		wp_nonce_field( $this->menu_nonce_action, $this->menu_nonce_action, false, true );

		$value = get_post_meta( $item_id, $this->key, true );
		?>
		<p class="field-<?= esc_attr( $this->key ); ?>">
			<label for="edit-menu-item-<?= esc_attr( $this->key ); ?>-<?= esc_attr( $item_id ); ?>">
				<?= esc_html( $this->label ); ?><br>
				<?php
				if ( is_callable( $this->render ) ) {
					call_user_func( $this->render, $value, $item_id, $this );
				} elseif ( is_callable( [ $this, "render_{$this->type}" ] ) ) {
					call_user_func( [ $this, "render_{$this->type}" ], $value, $item_id, $this );
				} else {
					$this->render_input( $value, $item_id );
				}
				?>
			</label>
		</p>
		<?php
	}

	/**
	 * Render the input (with any input type) field in the admin
	 *
	 * @param mixed $value
	 * @param int $item_id
	 * @return void
	 */
	protected function render_input( mixed $value, int $item_id ) : void {
		printf(
			'<input type="%4$s" id="edit-menu-item-%1$s-%2$d" name="%1$s[%2$d]" value="%3$s" class="widefat edit-%1$s">',
			esc_attr( $this->key ),
			esc_attr( $item_id ),
			esc_attr( $value ),
			esc_attr( $this->type ),
		);
	}

	/**
	 * Render a checkbox input field in the admin
	 *
	 * @param mixed $value
	 * @param int $item_id
	 * @return void
	 */
	protected function render_checkbox( mixed $value, int $item_id ) : void {
		printf(
			'<input type="checkbox" id="edit-menu-item-%1$s-%2$d" name="%1$s[%2$d]"%3$s class="widefat edit-%1$s">',
			esc_attr( $this->key ),
			esc_attr( $item_id ),
			checked( (bool) $value, true, false ),
		);
	}

	/**
	 * Render a checkbox input field in the admin
	 *
	 * @param mixed $value
	 * @param int $item_id
	 * @return void
	 */
	protected function render_checkboxes( mixed $value, int $item_id ) : void {
		foreach ( $this->options as $option => $label ) {
			printf(
				'<label><input type="checkbox" id="edit-menu-item-%1$s-%2$d-%3$s" name="%1$s[%2$d][%3$s]"%4$s class="widefat edit-%1$s"> %5$s</label><br>',
				esc_attr( $this->key ),
				esc_attr( $item_id ),
				esc_attr( $option ),
				checked( in_array( $option, (array) $value, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Render a checkbox input field in the admin
	 *
	 * @param mixed $value
	 * @param int $item_id
	 * @return void
	 */
	protected function render_textarea( mixed $value, int $item_id ) : void {
		printf(
			'<textarea id="edit-menu-item-%1$s-%2$d" name="%1$s[%2$d]" value="%3$s" class="widefat edit-%1$s">%3$s</textarea>',
			esc_attr( $this->key ),
			esc_attr( $item_id ),
			esc_textarea( $value ),
		);
	}

	/**
	 * Render a select field based on this->type
	 *
	 * @param mixed $value
	 * @param int $item_id
	 * @return void
	 */
	public function render_select( mixed $value, int $item_id ) : void {
		printf(
			'<select id="edit-menu-item-%1$s-%2$d" class="widefat edit-%1$s" name="%1$s[%2$d]">%3$s</select>',
			esc_attr( $this->key ),
			esc_attr( $item_id ),
			implode(
				'',
				array_map( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					function ( $option ) use ( $value ) {
						return sprintf(
							'<option value="%1$s"%2$s>%3$s</option>',
							esc_attr( $option ),
							selected( $option, $value, false ),
							esc_html( $this->options[ $option ] )
						);
					},
					array_keys( $this->options ?? [] )
				)
			)
		);
	}

	/**
	 * Update the menu item meta on save
	 *
	 * @param int $menu_id
	 * @param int $menu_item_db_id
	 * @return void
	 * @action wp_update_nav_menu_item
	 */
	public function wp_update_nav_menu_item( $menu_id, $menu_item_db_id ) : void {
		if ( ! $this->menu_is_allowed( $menu_id ) || ! isset( $_POST[ $this->menu_nonce_action ] ) || ! wp_verify_nonce( $_POST[ $this->menu_nonce_action ], $this->menu_nonce_action ) ) {
			return;
		}

		if ( isset( $_POST[ $this->key ][ $menu_item_db_id ] ) ) {
			if ( 'checkboxes' === $this->type ) {
				$value = array_map( 'sanitize_text_field', array_keys( $_POST[ $this->key ][ $menu_item_db_id ] ) );
			} else {
				$value = sanitize_text_field( $_POST[ $this->key ][ $menu_item_db_id ] );
			}

			update_post_meta( $menu_item_db_id, $this->key, $value );
		} else {
			delete_post_meta( $menu_item_db_id, $this->key );
		}
	}

	/**
	 * Add icon fields to the columns to they can be toggled
	 *
	 * @return array
	 */
	public function manage_nav_menus_columns( $columns ) {
		return array_merge(
			(array) $columns,
			[
				$this->key => $this->label,
			]
		);
	}

	/**
	 *
	 */
	public function wp_nav_menu_objects( $items ) {
		foreach ( $items as &$item ) {
			$item->{$this->key} = get_post_meta( $item->ID, $this->key, true );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Factory pattern for creating menu items
	 *
	 * @param string $key
	 * @param array $args
	 */
	public static function create( $key, $args = [] ) {
		return new static( $key, $args );
	}
}
