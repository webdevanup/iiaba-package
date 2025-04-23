<?php

namespace WDG\Core;

class Menu {

	public string $id;
	public string $label;
	protected ?\WP_Term $menu;

	public array $items = [];

	protected array $args = [
		'menu'                 => '',
		'container'            => 'div',
		'container_class'      => '',
		'container_id'         => '',
		'container_aria_label' => '',
		'menu_class'           => 'menu',
		'menu_id'              => '',
		'echo'                 => false,
		'fallback_cb'          => 'wp_page_menu',
		'before'               => '',
		'after'                => '',
		'link_before'          => '',
		'link_after'           => '',
		'items_wrap'           => '<ul id="%1$s" class="%2$s">%3$s</ul>',
		'item_spacing'         => 'preserve',
		'depth'                => 0,
		'walker'               => '',
		'theme_location'       => null,
	];

	public function __construct( $menu, $args = [] ) {
		$locations  = get_nav_menu_locations();
		$this->menu = wp_get_nav_menu_object( $locations[ $menu ] ?? $menu ) ?: null;
		$this->args = array_merge( $this->args, $args );

		$this->args['theme_location'] ??= $args['location'] ?? '';

		if ( ! empty( $this->menu->term_id ) ) {
			$this->prepare_items();
		}
	}

	protected function prepare_items() : void {
		$this->items = wp_get_nav_menu_items( $this->menu->term_id, array( 'update_post_term_cache' => false ) );

		_wp_menu_item_classes_by_context( $this->items );

		// apply the menu objects filter with some fake arguments for plugins that expect them
		$this->items = apply_filters( 'wp_nav_menu_objects', $this->items, (object) $this->args );

		// apply menu hierarchy
		$indexed_items = array_combine( array_column( $this->items, 'ID' ), $this->items );

		foreach ( $indexed_items as &$item ) {
			if ( ! empty( $item->menu_item_parent ) && isset( $indexed_items[ (int) $item->menu_item_parent ] ) ) {
				if ( ! is_array( $indexed_items[ (int) $item->menu_item_parent ]->children ) ) {
					$indexed_items[ (int) $item->menu_item_parent ]->children = [];
				}

				$indexed_items[ (int) $item->menu_item_parent ]->children[] = $item;
			}
		}

		// filter out items with parents
		$this->items = array_values( array_filter( $indexed_items, fn( $item ) => empty( $item->menu_item_parent ) ) );

		array_walk( $this->items, [ $this, 'prepare_item' ], 0 );
	}

	protected function prepare_item( $item, $index, $depth = 0 ) : void {
		if ( ! empty( $item->children ) ) {
			$item->classes[] = 'menu-item-has-children';

			array_walk( $item->children, [ $this, __FUNCTION__ ], $depth + 1 );
		}

		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$item->classes = apply_filters( 'nav_menu_css_class', array_filter( $item->classes ), $item, (object) $this->args, $depth );

		/** This filter is documented in wp-includes/post-template.php */
		$item->title = apply_filters( 'the_title', $item->title, $item->ID );

		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$item->title = apply_filters( 'nav_menu_item_title', $item->title, $item, (object) $this->args, $depth );

		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$item->attributes = apply_filters( 'nav_menu_item_attributes', $item->attributes, $item, (object) $this->args, $depth );

		$item->link_attributes = array_filter(
			[
				'title'        => $item->attr_title ?? '',
				'target'       => $item->target ?? '',
				'class'        => '',
				'href'         => $item->url ?? '',
				'aria-current' => $item->current ? 'page' : '',
			]
		);

		$item->link_attributes['rel'] = ( '_blank' === $item->target ) ? 'noopener' : $item->xfn;

		if ( ! empty( $item->url ) && get_privacy_policy_url() === $item->url ) {
			$item->link_attributes['rel'] = trim( $item->link_attributes['rel'] . ' privacy-policy' );
		}

		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$item->link_attributes = apply_filters( 'nav_menu_link_attributes', $item->link_attributes, $item, (object) $this->args, $depth );
	}
}
