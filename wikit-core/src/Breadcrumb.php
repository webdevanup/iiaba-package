<?php

namespace WDG\Core;

use WP_Post;
use WP_Term;

class Breadcrumb {

	use SingletonTrait;

	protected static bool $show_home = true;

	protected function __construct() {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_filter( 'wdg/block-editor/config', [ $this, 'wdg_block_editor_config' ] );
	}

	public function wdg_block_editor_config( $config ) {
		return array_merge(
			$config,
			[
				'breadcrumb' => [
					'home' => static::$show_home,
				],
			],
		);
	}

	/**
	 * Register the rest api routes on rest_api_init
	 *
	 * @return void
	 */
	public function rest_api_init() : void {
		register_rest_field(
			'type',
			'breadcrumb',
			[
				'get_callback' => [ $this, 'post_type_rest_get_callback' ],
				'schema'       => $this->schema,
			]
		);
	}

	protected array $schema = [
		'type'  => 'array',
		'items' => [
			'type'       => 'object',
			'properties' => [
				'link'  => [
					'type' => 'string',
				],
				'title' => [
					'type' => 'string',
				],
				'type'  => [
					'type' => 'string',
				],
			],
		],
	];

	public static function post_type_items( $type ) {
		static $cache = [];

		if ( ! isset( $cache[ $type ] ) ) {
			$cache[ $type ] = [];

			$show_on_front  = (int) get_option( 'show_on_front' );
			$page_for_posts = (int) get_option( 'page_for_posts' );

			if ( static::$show_home ) {
				$cache[ $type ][] = (object) [
					'link'  => get_home_url(),
					'title' => 'Home',
					'type'  => 'page' === $show_on_front ? 'page' : 'index',
				];
			}

			switch ( $type ) {
				case 'page':
					// no-op
					break;
				case 'post':
					if ( 'posts' !== $show_on_front && ! empty( $page_for_posts ) ) {
						$page_for_posts_ancestors = array_reverse( get_post_ancestors( $page_for_posts ) );

						if ( ! empty( $page_for_posts_ancestors ) ) {
							foreach ( $page_for_posts_ancestors as $ancestor ) {
								$cache[ $type ][] = static::get_post( $ancestor );
							}
						}

						$cache[ $type ][] = static::get_post( $page_for_posts );
					}
					break;
				default:
					$post_type          = get_post_type_object( $type );
					$page_for_post_type = get_option( 'page_for_' . $type );

					if ( ! empty( $page_for_post_type ) ) {
						$page_for_post_type_ancestors = array_reverse( get_post_ancestors( $page_for_post_type ) );

						if ( ! empty( $page_for_post_type_ancestors ) ) {
							foreach ( $page_for_post_type_ancestors as $ancestor ) {
								$cache[ $type ][] = static::get_post( $ancestor );
							}
						}

						$cache[ $type ][] = static::get_post( $page_for_post_type );
					} else {
						$cache[ $type ][] = (object) [
							'type'  => 'archive',
							'link'  => get_post_type_archive_link( $type ),
							'title' => $post_type->label,
						];
					}
					break;
			}

			$cache[ $type ] = array_filter( $cache[ $type ] );
		}

		return $cache[ $type ];
	}

	/**
	 * Handle the rest route of the breadcrumbs for the postType object
	 *
	 * @param WP_REST_Request
	 * @return array
	 */
	public function post_type_rest_get_callback( $request ) {
		return static::post_type_items( $request['slug'] );
	}

	/**
	 * Get the breadcrumb for an post object
	 *
	 * @param mixed $post
	 * @return array
	 */
	public static function items( mixed $post = null ) : array {
		$post ??= get_post();
		$items = [];

		if ( $post instanceof WP_Post ) {
			$items = array_merge( $items, static::post_type_items( $post->post_type ) );

			if ( is_post_type_hierarchical( $post->post_type ) ) {
				$ancestors = array_reverse( get_post_ancestors( $post ) );

				if ( ! empty( $ancestors ) ) {
					foreach ( $ancestors as $ancestor ) {
						$items[] = static::get_post( $ancestor );
					}
				}
			}
		}

		return $items;
	}

	public static function get_post( $post ) {
		return (object) [
			'link'  => get_permalink( $post ),
			'title' => get_the_title( $post ),
			'type'  => get_post_type( $post ),
		];
	}

	public static function get_term( $term, $taxonomy = null ) {
		if ( $term instanceof WP_Term ) {
			$taxonomy = $term->taxonomy;
		}

		return (object) [
			'link'  => get_term_link( $term, $taxonomy ),
			'title' => get_term_field( 'name', $term, $taxonomy ),
			'type'  => $taxonomy,
		];
	}
}
