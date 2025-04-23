<?php

namespace WDG\Core;

class PostTypeTerm {

	/**
	 * Thee slug of the post type
	 */
	protected string $post_type;

	/**
	 * the slug of the taxonomy
	 */
	protected string $taxonomy;

	/**
	 * the post types the taxonomy is attached to
	 */
	protected array $object_type = [];

	/**
	 * Holds the object the taxonomy was registered with
	 */
	protected $taxonomy_object = null;

	/**
	 * Default taxonomy arguments for an editable UI but not query-able terms
	 */
	protected $taxonomy_args = [
		'public'               => true,
		'publicly_queryable'   => false,
		'hierarchical'         => false,
		'show_in_menu'         => false,
		'show_in_nav_menus'    => false,
		'show_in_rest'         => true,
		'show_tagcloud'        => false,
		'show_in_quick_edit'   => true,
		'show_admin_column'    => true,
		'block_editor_control' => 'radio',
		'capabilities'         => [
			'manage_terms' => 'do_not_allow',
			'delete_terms' => 'do_not_allow',
		],
	];

	/**
	 * The list of other post types that are relatable to our post type
	 */
	protected $taxonomy_post_types = [];

	/**
	 * the meta key that stores the two way relationship in the post/term meta table
	 */
	protected $related_key = '_post_type_term';

	/**
	 * Should the react component preload all available terms
	 *
	 * @var boolean
	 */
	protected $preload = true;

	/**
	 * Help text for the react control
	 *
	 * @var string
	 */
	protected $help = '';

	/**
	 * the label for the search/filter input
	 *
	 * @var string
	 */
	protected $label = '';

	/**
	 * The placeholder text for the search/filter input- defaults to "Filter..." when preload is true, and 'Search..." when preload is false
	 *
	 * @var string
	 */
	protected $placeholder = '';

	/**
	 * The minimum characters required to perform a search
	 *
	 * @var string
	 */
	protected $min_chars = 2;

	/**
	 * Initialize trait hooks
	 *
	 * @return void
	 */
	public function __construct( string $post_type, string $taxonomy, array $object_type = [], array $taxonomy_args = [] ) {
		$this->post_type     = $post_type;
		$this->taxonomy      = $taxonomy;
		$this->object_type   = $object_type;
		$this->taxonomy_args = array_merge( $this->taxonomy_args, $taxonomy_args );

		$this->taxonomy_args['labels'] = array_merge(
			array_filter(
				[
					'name'          => $this->taxonomy_args['labels']['name'] ?? '',
					'singular_name' => $this->taxonomy_args['labels']['singular_name'] ?? '',
				]
			),
			$this->taxonomy_args['labels'] ?? [],
		);

		add_action( "save_post_{$this->post_type}", [ $this, 'save_post' ], 1, 3 );
		add_filter( "get_{$this->taxonomy}", [ $this, 'get_taxonomy_term' ], 1, 2 );
		add_filter( 'term_link', [ $this, 'term_link' ], 1, 3 );
		add_filter( 'before_delete_post', [ $this, 'before_delete_post' ] );
		add_action( 'set_object_terms', [ $this, 'set_object_terms' ], 10, 6 );
		add_action( 'wp_get_object_terms_args', [ $this, 'wp_get_object_terms_args' ], 1, 3 );
		add_action( 'get_terms_defaults', [ $this, 'get_terms_defaults' ], 1, 2 );
		add_filter( 'the_posts', [ $this, 'the_posts' ], 1, 2 );
		add_filter( 'duplicate_post_excludelist_filter', [ $this, 'duplicate_post_excludelist_filter' ] );
		did_action( 'init' ) ? $this->register_taxonomy() : add_action( 'init', [ $this, 'register_taxonomy' ] );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( "post-type-term {$this->taxonomy}", [ $this, 'command' ] );
		}
	}

	/**
	 * Register our taxonomy on init
	 *
	 * @return void
	 * @action init
	 */
	public function register_taxonomy() {
		if ( empty( $this->taxonomy ) || ! is_string( $this->taxonomy ) ) {
			throw new \Exception( sprintf( 'Invalid taxonomy argument: %s', esc_html( strval( $this->taxonomy ) ) ) );
		}

		$this->taxonomy_object = new Taxonomy( $this->taxonomy, $this->object_type, $this->taxonomy_args );

		register_term_meta(
			$this->taxonomy,
			$this->related_key,
			[
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_post_meta(
			$this->post_type,
			$this->related_key,
			[
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => fn( $allowed, $meta_key, $object_id, $user_id ) => user_can( $user_id, 'edit_post', $object_id ),
			]
		);
	}

	/**
	 * Create/update the related term
	 *
	 * @param int $post_id
	 * @param \WP_Post $post
	 * @param bool $update
	 * @return int - term id of the related term
	 */
	public function save_post( $post_id, $post ) {
		if ( 'cli' !== php_sapi_name() ) {
			if ( ! current_user_can( 'edit_posts' ) || 'publish' !== $post->post_status ) {
				return;
			}
		}

		$term_id = (int) get_post_meta( $post_id, $this->related_key, true );

		// see if the term_id exists
		$term_exists = ! empty( $term_id ) ? term_exists( $term_id, $this->taxonomy ) : false;

		// see if the term slug exists
		if ( empty( $term_exists ) ) {
			$term_slug_obj = get_term_by( 'slug', $post->post_name, $this->taxonomy );

			if ( ! empty( $term_slug_obj ) ) {
				$term_exists = true;
				$term_id     = $term_slug_obj->term_id;
			}
		}

		$term_name        = strlen( $post->post_title ) > 200 ? substr( $post->post_title, 0, 200 ) : $post->post_title;  // the post tiele isn't limited to 200 chars like the term name is
		$term_slug        = $post->post_name;
		$term_description = $post->post_excerpt ?: '';
		$term_parent      = 0;

		if ( is_post_type_hierarchical( $post->post_type ) && ! empty( $post->post_parent ) ) {
			$term_parent = (int) get_post_meta( $post->post_parent, $this->related_key, true );
		}

		if ( ! empty( $term_exists ) ) {
			// update term
			$term_tax = wp_update_term(
				$term_id,
				$this->taxonomy,
				[
					'name'        => $term_name,
					'slug'        => $term_slug,
					'description' => $term_description,
					'parent'      => $term_parent,
				]
			);
		} else {
			// create term
			$term_tax = wp_insert_term(
				$term_name,
				$this->taxonomy,
				[
					'slug'        => $term_slug,
					'description' => $term_description,
					'term_parent' => $term_parent,
				]
			);
		}

		if ( is_wp_error( $term_tax ) ) {
			return $term_tax;
		}

		$term_id = $term_tax['term_id'];

		update_post_meta( $post_id, $this->related_key, $term_id, true );
		update_term_meta( $term_id, $this->related_key, $post_id, true );

		return $term_id;
	}

	/**
	 * Fires after an object's terms have been set so we can enforce our sort order.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      An array of object terms.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 * @action set_object_terms
	 */
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append ) {
		global $wpdb;

		if ( $this->taxonomy === $taxonomy && ! $append ) {
			foreach ( $tt_ids as $tt_index => $tt_id ) {
				$wpdb->update(
					$wpdb->term_relationships,
					[
						'term_order' => $tt_index,
					],
					[
						'object_id'        => $object_id,
						'term_taxonomy_id' => $tt_id,
					],
					[
						'%d',
					],
					[
						'%d',
						'%d',
					]
				);
			}

			wp_cache_delete( $object_id, $taxonomy . '_relationships' );
		}
	}

	/**
	 * Sort by term order orderby argument if we are querying for our taxonomy (and only our taxonomy)
	 *
	 * @param array|null $args
	 * @param array $object_ids
	 * @param array $taxonomies
	 * @return array
	 */
	public function wp_get_object_terms_args( $args, $object_ids, $taxonomies ) {
		if ( is_array( $taxonomies ) && count( $taxonomies ) === 1 && current( $taxonomies ) === $this->taxonomy && empty( $args['orderby'] ) ) {
			$args['orderby'] = 'term_order';
		}

		return $args;
	}

	/**
	 * Sort by term order orderby argument if we are querying for our taxonomy (and only our taxonomy)
	 *
	 * @param array|null $args
	 * @param array $object_ids
	 * @param array $taxonomies
	 * @return array
	 */
	public function get_terms_defaults( $args, $taxonomies ) {
		if ( ! empty( $taxonomies ) && count( $taxonomies ) === 1 && current( $taxonomies ) === $this->taxonomy ) {
			$args['orderby'] = 'term_order';
		}

		return $args;
	}

	/**
	 * get_the_terms doesn't allow us to filter the orderby, so we need to
	 * pre-populate the post term object cache before the term cache is primed
	 * to match the order we want by default
	 *
	 * Using the_posts filter as it's the last available hook before
	 * update_post_caches is called
	 *
	 * @param array $posts
	 * @param \WP_Query $query
	 * @return array
	 * @filter the_posts
	 * @see update_object_term_cache for the subrouting we're overriding
	 */
	public function the_posts( $posts ) {
		$_posts = array_filter(
			$posts,
			function ( $post ) {
				return in_array( $post->post_type, $this->taxonomy_post_types, true );
			}
		);

		global $wpdb;

		$terms = wp_get_object_terms(
			array_column( $_posts, 'ID' ),
			[
				$this->taxonomy,
			],
			[
				'fields'                 => 'all_with_object_id',
				'orderby'                => $wpdb->term_relationships . '.term_order',
				'update_term_meta_cache' => false,
			]
		);

		$object_terms = [];

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! isset( $object_terms[ $term->object_id ] ) ) {
					$object_terms[ $term->object_id ] = [];
				}

				array_push( $object_terms[ $term->object_id ], $term->term_id );
			}

			foreach ( $object_terms as $object_id => $term_ids ) {
				wp_cache_add( $object_id, $term_ids, "{$this->taxonomy}_relationships" );
			}
		}

		return $posts;
	}

	/**
	 * Dynamically filter the term values in case it gets updated out of band
	 *
	 * @param \WP_Term $term
	 * @param string $taxonomy
	 * @return \WP_Term
	 * @filter get_{$this->taxonomy}
	 */
	public function get_taxonomy_term( $term, $taxonomy ) {
		$post_id = (int) get_term_meta( $term->term_id, $this->related_key, true );

		if ( ! empty( $post_id ) && $this->taxonomy === $taxonomy ) {
			$term->name        = get_post_field( 'post_title', $post_id, );
			$term->slug        = get_post_field( 'post_name', $post_id, );
			$term->description = get_post_field( 'post_excerpt', $post_id, );
		}

		return $term;
	}

	/**
	 * Return the URL of the post when getting the term link
	 *
	 * @filter term_link
	 */
	public function term_link( $link, $term, $taxonomy ) {
		if ( $this->taxonomy === $taxonomy ) {
			$post_id = (int) get_term_meta( $term->term_id, $this->related_key, true );

			if ( ! empty( $post_id ) && get_post_type( $post_id ) === $this->post_type ) {
				$link = get_the_permalink( $post_id );
			}
		}

		return $link;
	}

	/**
	 * Delete the related term when the post is deleted
	 *
	 * @param int $post_id
	 * @return bool|int|\WP_Error
	 */
	public function before_delete_post( $post_id ) {
		if ( 'cli' !== php_sapi_name() ) {
			if ( ! current_user_can( 'edit_posts' ) || get_post_type( $post_id ) !== $this->post_type ) {
				return;
			}
		}

		$term_id = (int) get_post_meta( $post_id, $this->related_key, true );

		if ( ! empty( $term_id ) ) {
			return wp_delete_term( $term_id, $this->taxonomy );
		}

		return false;
	}

	/**
	 * Get the posts related to a post
	 *
	 * @param int|\WP_Post $post
	 * @param string|array $stati
	 * @return array
	 */
	public function get_related_posts( $post = null, $stati = [ 'publish' ] ) : array {
		$post    = $post ?? get_post();
		$terms   = get_the_terms( $post, $this->taxonomy );
		$related = [];

		if ( is_string( $stati ) ) {
			$stati = (array) $stati;
		}

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$post_type_term = get_term_meta( $term->term_id, $this->related_key, true );

				if ( empty( $post_type_term ) || ! in_array( get_post_status( $post_type_term ), $stati, true ) ) {
					continue;
				}

				array_push( $related, $post_type_term );
			}
		}

		return array_map( 'get_post', $related );
	}

	/**
	 * Add an entry to duplicate-post to exclude our meta key from being copied when cloned
	 *
	 * @param array $excludelist
	 * @return array
	 */
	public function duplicate_post_excludelist_filter( $excludelist ) {
		if ( ! in_array( $this->related_key, $excludelist, true ) ) {
			array_push( $excludelist, $this->related_key );
		}

		return $excludelist;
	}

	/**
	 * WP CLI Command to synchronize terms with their associated post type
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function command( $args, $assoc_args ) {
		$query = new \WP_Query(
			[
				'post_type'      => $this->post_type,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		if ( empty( $query->posts ) ) {
			\WP_CLI::error( sprintf( 'No %s found', $this->post_type ) );
		}

		foreach ( $query->posts as &$post ) {
			$post->term = $this->save_post( $post->ID, $post, true );

			if ( is_wp_error( $post->term ) ) {
				$post->error = $post->term->get_error_message();
				$post->term  = '';
			} else {
				$post->error = '';
			}
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], $query->posts, [ 'post_title', 'ID', 'term', 'error' ] );
	}
}
