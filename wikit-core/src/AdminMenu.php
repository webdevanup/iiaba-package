<?php
namespace WDG\Core;

/**
 * This class applies customized navigation menu structure
 */
class AdminMenu {

	/**
	 * The order we want the post types in the admin menu
	 * - groups are inserted before the key of the group
	 *
	 * @var array
	 * @access protected
	 */
	protected $custom_order = [
		'separator1' => [
			'edit.php?post_type=page',
			'edit.php',
			'edit.php?post_type=event',
			'edit-comments.php',
		],
		'separator2' => [
			'upload.php',
			'gf_edit_forms',
			'nav-menus.php',
			'widgets.php',
		],
	];

	/**
	 * A flattened list for comparison to the generated order
	 *
	 * @var array
	 * @access protected
	 */
	protected $custom_order_flat = [];

	/**
	 * List of additional separators to be added to the menu
	 * - the key is the menu id and the value is the item to be inserted before
	 *
	 * @var array
	 * @access protected
	 */
	protected $separators = [
		'separator3' => 'themes.php',
	];

	/**
	 * Child items that should be promoted to a top level menu item
	 * - key is the slug of the submenu page with keys for the associated parent and new icon
	 *
	 * @var array
	 * @access protected
	 */
	protected $promoted = [
		'nav-menus.php' => [
			'parent' => 'themes.php',
			'icon'   => 'dashicons-menu',
		],
	];

	public function __construct() {
		if ( empty( $this->custom_order ) ) {
			return;
		}

		add_filter( 'custom_menu_order', '__return_true' ); // required to activate the menu_order filter
		add_filter( 'menu_order', [ $this, 'menu_order' ], 999 );
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 1 );
		add_filter( 'parent_file', [ $this, 'parent_file' ] );

		$this->custom_order_flat = array_reduce(
			$this->custom_order,
			function ( $carry, $item ) {
				return array_merge( $carry, $item );
			},
			[]
		);
	}

	/**
	 * Apply the post types order
	 *
	 * @param array $items
	 * @return array
	 * @access public
	 * @filter menu_order
	 */
	public function menu_order( $order ) {
		$custom_order = [];

		// filter out items that may not exist
		$custom_order_flat = array_intersect( $this->custom_order_flat, $order );

		foreach ( $this->custom_order as $custom_order_after => &$custom_order_after_items ) {
			if ( in_array( $custom_order_after, $order, true ) ) {
				$custom_order[ $custom_order_after ] = array_intersect( $custom_order_after_items, $order );
			}
		}

		unset( $custom_order_after );

		// remove our custom order items from the initial order
		$order = array_values( array_diff( $order, $custom_order_flat ) );

		foreach ( $custom_order as $custom_order_after => $custom_order_after_items ) {
			array_splice( $order, array_search( $custom_order_after, $order, true ) + 1, 0, $custom_order_after_items );
		}

		unset( $custom_order_after );

		return $order;
	}

	/**
	 * Move menus to the top level menu item and add an additional separator above Media
	 *
	 * @return void
	 * @uses $menu
	 * @action admin_menu
	 */
	public function admin_menu() {
		global $menu, $submenu;

		if ( empty( $menu ) ) {
			return;
		}

		if ( ! empty( $this->separators ) ) {
			foreach ( $this->separators as $separator => $separator_before ) {
				$separator_before_index = array_search( $separator_before, array_column( $menu, 2 ), true );

				if ( false !== $separator_before_index ) {
					// splice additional separators
					array_splice(
						$menu,
						$separator_before_index,
						0,
						[
							[
								'',
								'read',
								$separator,
								'',
								'wp-menu-separator',
							],
						]
					);
				}
			}
		}

		// move promoted items to the top level
		foreach ( $this->promoted as $child => $child_data ) {
			if ( empty( $child_data['parent'] ) ) {
				continue;
			}

			if ( ! empty( $submenu[ $child_data['parent'] ] ) ) {
				foreach ( $submenu[ $child_data['parent'] ] as $submenu_item ) {
					if ( count( $submenu_item ) >= 3 && ! empty( $submenu_item[2] ) && $child === $submenu_item[2] ) {
						$submenu_item = array_replace(
							[
								null, // Menu Item Name
								null, // Capability
								$child, // URL
								$submenu_item[0], // 'Page Title',
								'menu-top', // Classes
								sanitize_title( $submenu_item[0] ?? $child ), // ID
								$child_data['icon'] ?? 'dashicons-generic', // Icon

							],
							$submenu_item
						);

						array_push( $menu, $submenu_item );

						remove_submenu_page( $child_data['parent'], $child );
						break;
					}
				}
			}
		}
	}

	/**
	 * Fix the old menu parent from being open since we've moved a child
	 *
	 * @param string $parent_file
	 * @return string
	 * @filter parent_file
	 */
	public function parent_file( $parent_file ) {
		global $pagenow;

		if ( ! empty( $pagenow ) && isset( $this->promoted[ $pagenow ] ) ) {
			$parent_file = $pagenow;
		}

		return $parent_file;
	}
}
