<?php

namespace WDG\Facets\Native;

use WDG\Facets\Provider\Native;

class Post {

	protected int $id;

	/**
	 * The array of existing facets stored index
	 *
	 * @param array
	 */
	protected array $indexed = [];

	/**
	 * The array of processed facets to be inserted into the index
	 *
	 * @param array
	 */
	protected array $unindexed = [];

	/**
	 * The array of facets that were indexed but not longer applicable
	 *
	 * @param array
	 */
	protected array $deleted = [];

	/**
	 * The array of all facets
	 *
	 * @param array
	 */
	protected array $facets = [];

	public function __construct(
		$id,
		protected Native $provider,
	) {
		global $wpdb;

		$this->id = (int) $id;

		$indexed = $wpdb->get_results(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT id, object_id, object_type, type, facet, value, label, parent
				FROM {$this->provider->index->table}
				WHERE object_id = %d
				AND object_type = 'post'",
				$this->id
			),
			ARRAY_A
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$this->indexed = array_combine(
			array_column( $indexed, 'id' ),
			array_map( fn( $row ) => array_diff_key( $row, [ 'id' => null ] ), $indexed )
		);

		$this->facets[] = [
			'object_id'   => $this->id,
			'object_type' => 'post',
			'type'        => 'post_type',
			'facet'       => 'post_type',
			'value'       => get_post_type( $this->id ),
			'label'       => get_post_type_object( get_post_type( $this->id ) )->labels->name,
			'parent'      => '',
		];

		$author_id = get_post_field( 'post_author', $this->id, 'raw' );

		if ( ! empty( $author_id ) ) {
			$this->facets[] = [
				'object_id'   => $this->id,
				'object_type' => 'post',
				'type'        => 'post_author',
				'facet'       => 'post_author',
				'value'       => get_the_author_meta( 'user_login', $author_id ),
				'label'       => get_the_author_meta( 'display_name', $author_id ),
				'parent'      => '',
			];
		}

		$taxonomies = $this->provider->get_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $this->id, $taxonomy );

			foreach ( $terms as $term ) {
				$this->facets[] = [
					'object_id'   => $this->id,
					'object_type' => 'post',
					'type'        => 'taxonomy',
					'facet'       => $taxonomy,
					'value'       => $term->slug,
					'label'       => $term->name,
					'parent'      => $term->parent ? get_term_by( 'term_id', $term->parent, $term->taxonomy )->slug : '',
				];
			}
		}

		$meta = $this->provider->get_meta_keys();

		foreach ( $meta as $key ) {
			$values = array_filter( get_post_meta( $this->id, $key ), 'is_scalar' );

			foreach ( $values as $value ) {
				$this->facets[] = [
					'object_id'   => $this->id,
					'object_type' => 'post',
					'type'        => 'meta',
					'facet'       => $key,
					'value'       => $value,
					'label'       => $value,
					'parent'      => '',
				];
			}
		}

		$this->unindexed = array_udiff( $this->facets, $this->indexed, fn( $a, $b ) => $a <=> $b );
		$this->deleted   = array_udiff( $this->indexed, $this->facets, fn( $a, $b ) => $a <=> $b );
	}

	public function index() {
		if ( ! empty( $this->unindexed ) ) {
			$this->provider->index->insert( $this->unindexed );
		}

		if ( ! empty( $this->deleted ) ) {
			$this->provider->index->delete_multi( array_keys( $this->deleted ) );
		}
	}
}
