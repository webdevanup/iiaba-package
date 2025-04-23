<?php

namespace WDG\Facets\Provider;

use WDG\Facets\Native\Index;
use WDG\Facets\Native\Settings;
use WDG\Facets\Native\Command;
use WDG\Facets\Native\Post;
use WDG\Facets\Native\RESTController;
use WDG\Facets\Source\SourceInterface;
use WP_Query;
use WP_Term;

/**
 * The Native provider is required for
 */
class Native extends AbstractProvider implements ProviderInterface {

	/**
	 * Hold the reference to the native index methods
	 *
	 * @param Index
	 */
	public $index;

	/**
	 * Holds the reference to the native settings
	 *
	 * @param Settings
	 */
	public $settings;

	/**
	 * A reference to the source interface
	 *
	 * @param SourceInterface
	 */
	protected ?SourceInterface $source;

	/**
	 * Holds the reference to the native rest controller
	 *
	 * @param RESTController
	 */
	protected $rest_controller;

	protected array $ignored_post_types = [
		'nav_menu_item',
		'revision',
		'oembed_cache',
		'revision',
		'wp_global_styles',
		'wp_navigation',
	];

	public function __construct( array $props = [], $existing_only = true ) {
		parent::__construct( $props, $existing_only );

		$this->index    = new Index();
		$this->settings = new Settings( $this->index );

		if ( ( is_admin() || 'cli' === php_sapi_name() ) && ! $this->index->exists() ) {
			$this->index->create();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'wdg facets', new Command( $this ) );
		}

		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_action( 'posts_clauses_request', [ $this, 'posts_clauses_request' ], PHP_INT_MAX, 2 );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
		add_action( 'delete_post', [ $this, 'deleted_post' ], 10, 2 );
		add_action( 'delete_term', [ $this, 'delete_term' ], 10, 5 );
		add_action( 'edit_term', [ $this, 'edit_term' ], 10, 4 );
		add_action( 'wp_update_user', [ $this, 'wp_update_user' ], 10, 3 );
		add_action( 'set_object_terms', [ $this, 'set_object_terms' ], 10, 6 );
	}

	/**
	 * Initialize the REST Controller
	 *
	 * @return void
	 */
	public function rest_api_init() : void {
		$this->rest_controller = new RESTController( $this->index, $this );
	}

	/**
	 * Store the clauses on a query so we can use it in our facets sub query
	 *
	 * @param string $where
	 * @param WP_Query $query
	 * @return string
	 */
	public function posts_clauses_request( $clauses, $query ) {
		if ( ! empty( $query->get( 'facets', [] ) ) ) {
			$query->clauses = $clauses;
		}

		return $clauses;
	}

	/**
	 * @inheritDoc
	 */
	public function get_query_facets( WP_Query $query, ?array $facets = null ) : array {
		global $wpdb;

		if ( empty( $query->clauses['where'] ) ) {
			return [];
		}

		$join  = $query->clauses['join'] ?? '';
		$where = '1 = 1 AND ' . preg_replace( '/^AND\s+/i', '', trim( $query->clauses['where'] ) );

		$sub_query = "SELECT ID FROM $wpdb->posts $join WHERE $where";

		/**
		 * Replace the sub-query with a custom query if using a different data source for the query
		 *
		 * @param string $sub_query
		 * @param WP_Query $query
		 * @return string
		 */
		$sub_query = apply_filters( 'wdg/facets/sub_query', $sub_query, $query );

		if ( empty( $sub_query ) ) {
			return [];
		}

		if ( empty( $facets ) ) {
			$facets = $this->get_facets();
		}

		$facet_formats = implode( ',', array_fill( 0, count( $facets ), '%s' ) );

		$facet_orderby = $query->get(
			'facet_orderby',
			apply_filters(
				'wdg/facets/facet_orderby',
				get_option( 'facet_orderby', 'count' ),
			)
		);

		if ( ! in_array( $facet_orderby, [ 'count', 'value' ], true ) ) {
			$facet_orderby = 'count';
		}

		$facet_order = strtoupper(
			(string) $query->get(
				'facet_order',
				(string) apply_filters(
					'wdg/facets/facet_order',
					get_option( 'facet_order', 'DESC' ),
					$query
				)
			)
		);

		if ( ! in_array( $facet_order, [ 'ASC', 'DESC' ], true ) ) {
			$facet_order = 'DESC';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CONCAT_WS( '-', 'facet', facet, value ) as id,
					type,
					facet,
					value,
					label,
					CONCAT( '$this->query_var', '[', facet, '][]' ) as name,
					parent,
					COUNT(*) as count
				FROM {$this->index->table}
				WHERE object_type = 'post'
				AND object_id IN ($sub_query)
				AND facet IN ($facet_formats)
				GROUP BY type, facet, value
				ORDER BY $facet_orderby $facet_order;", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$facets
			),
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $results as &$result ) {
			$result->parent = (string) $result->parent;
			$result->count  = (int) $result->count;
		}
		unset( $result );

		return $results;
	}

	/**
	 * Can post type be used as a facet
	 *
	 * @return bool
	 */
	public function can_facet_post_type() : bool {
		return (bool) apply_filters( 'wdg/facets/post_type', true );
	}

	/**
	 * @inheritDoc
	 */
	public function can_facet_author() : bool {
		return (bool) apply_filters( 'wdg/facets/author', false );
	}

	/**
	 * @inheritDoc
	 */
	public function get_taxonomies() : array {
		$taxonomies = (array) apply_filters( 'wdg/facets/taxonomies', array_filter( (array) get_option( 'facets_taxonomies', [] ) ) );

		return $taxonomies;
	}

	/**
	 * @inheritDoc
	 */
	public function get_meta_keys() : array {
		return (array) apply_filters( 'wdg/facets/meta_keys', array_filter( (array) get_option( 'facets_meta_keys', [] ) ) );
	}

	/**
	 * Update the native index when a post is saved
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 * @param bool $update
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) : void {
		if ( ! in_array( $post->post_type, $this->ignored_post_types, true ) ) {
			$post = new Post( $post_id, $this );
			$post->index();
		}
	}

	/**
	 * Update the native index when a term relationship is saved
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      An array of object term IDs or slugs.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 * @return void
	 */
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) : void {
		if ( ! in_array( get_post_type( $object_id ), $this->ignored_post_types, true ) ) {
			$post = new Post( $object_id, $this );
			$post->index();
		}
	}

	/**
	 * Remove a post from the index when it is deleted
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 * @return int - the number of rows deleted
	 */
	public function deleted_post( $post_id, $post ) : int {
		return (int) $this->index->delete_object( $post_id );
	}

	/**
	 * Remove any references to a term when the term is deleted
	 *
	 * @param int $term_id
	 * @param int $tt_id
	 * @param string $taxonomy
	 * @param WP_Term $term
	 * @param array $object_ids
	 * @return int - the number of rows deleted
	 */
	public function delete_term( $term_id, $tt_id, $taxonomy, $term, $object_ids ) : int {
		return (int) $this->index->delete_facet_value( $taxonomy, $term->slug );
	}

	/**
	 * Updates user labels when their display name is changed
	 *
	 * @param int @user_id
	 * @param array $data
	 * @param array $raw
	 * @return int - the number of rows updated
	 */
	public function wp_update_user( $user_id, $data, $raw ) : int {
		return (int) $this->index->update_label( [ 'value' => $data['user_login'] ], $data['display_name'] );
	}

	/**
	 * Update the index when a term is edited,
	 *
	 * - runs before the object cache is cleared so we have a reference to the old term
	 *
	 * @param int $term_id
	 * @param int $tt_id
	 * @param string $taxonomy
	 * @param array $args
	 */
	public function edit_term( $term_id, $tt_id, $taxonomy, $args ) {
		$old_term = get_term_by( 'id', $term_id, $taxonomy );

		if ( empty( $old_term ) ) {
			return;
		}

		$parent_term = $args['parent'] ? get_term_by( 'term_id', $args['parent'], $taxonomy ) : null;

		$data = [
			'value'  => $args['slug'],
			'label'  => $args['name'],
			'parent' => $parent_term->slug ?? '',
		];

		$old_parent_term = get_term_by( 'term_id', $old_term->parent, $taxonomy );

		$old_data = [
			'value'  => $args['slug'],
			'label'  => $args['name'],
			'parent' => $old_parent_term->slug ?? '',
		];

		$diff = array_diff_assoc( $data, $old_data );

		if ( ! empty( $diff ) ) {
			$where = array_merge(
				[
					'type'  => 'taxonomy',
					'facet' => $taxonomy,
				],
				$old_data
			);

			$this->index->update( $data, $where );
		}
	}
}
