<?php

namespace WDG\Facets\Provider;

use WP_Query;
use StdClass;

/**
 * The EnterpriseSearch provider implements the WordPress VIP Enterprise Search mu-plugin (potentially support ElasticPress too)
 *
 * - Does not need a custom source since it applies to WP_Query directly
 *
 * @package wdgdc/wikit-facets
 */
class EnterpriseSearch extends AbstractProvider implements ProviderInterface {

	public function __construct( array $props = [], $existing_only = true ) {
		parent::__construct( $props, $existing_only );

		add_filter( 'ep_formatted_args', [ $this, 'ep_formatted_args' ], 1, 3 );
		add_filter( 'ep_facet_include_taxonomies', [ $this, 'ep_facet_include_taxonomies' ], PHP_INT_MAX, 2 );
		add_filter( 'ep_is_facetable', [ $this, 'ep_is_facetable' ], PHP_INT_MAX, 2 );
		add_filter( 'vip_search_post_taxonomies_allow_list', [ $this, 'sync_taxonomies' ], 1, 2 );
		add_filter( 'vip_search_post_meta_allow_list', [ $this, 'sync_post_meta_keys' ], 1, 2 );
	}

	/**
	 * @inheritDoc
	 */
	public function get_taxonomies() : array {
		return array_unique(
			array_merge(
				$this->taxonomies,
				array_keys(
					get_taxonomies(
						[
							'publicly_queryable' => true,
						],
						'names'
					)
				),
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_meta_keys() : array {
		return $this->meta_keys;
	}

	/**
	 * @inheritDoc
	 */
	public function can_facet_post_type() : bool {
		return $this->post_type;
	}

	/**
	 * @inheritDoc
	 */
	public function can_facet_author() : bool {
		return $this->post_author;
	}

	/**
	 * @inheritDoc
	 */
	public function get_query_facets( WP_Query $query, ?array $facets = null ) : array {
		global $ep_facet_aggs;

		$results = [];

		if ( empty( $ep_facet_aggs ) ) {
			return $results;
		}

		$facets          = $facets ?: $this->get_facets();
		$taxonomy_facets = $this->get_taxonomies();
		$meta_facets     = $this->get_meta_keys();

		foreach ( $facets as $facet ) {
			if ( empty( $ep_facet_aggs[ $facet ] ) ) {
				continue;
			}

			if ( 'post_type' === $facet ) {
				$type = 'post_type';
			} elseif ( 'post_author' === $facet ) {
				$type = 'post_author';
			} elseif ( in_array( $facet, $taxonomy_facets, true ) ) {
				$type = 'taxonomy';

				$taxonomy_terms = get_terms(
					[
						'taxonomy'   => $facet,
						'name'       => array_keys( $ep_facet_aggs[ $facet ] ),
						'hide_empty' => false,
					]
				);

				if ( ! empty( $taxonomy_terms ) ) {
					$taxonomy_terms = array_combine( array_column( $taxonomy_terms, 'slug' ), $taxonomy_terms );
				}
			} elseif ( in_array( $facet, $meta_facets, true ) ) {
				$type = 'meta';
			}

			foreach ( $ep_facet_aggs[ $facet ] as $value => $count ) {
				$result = new StdClass();

				$result->id     = sprintf( 'facet-%s-%s', $facet, sanitize_title( $value ) );
				$result->type   = $type;
				$result->facet  = $facet;
				$result->value  = $value;
				$result->label  = $value;
				$result->name   = sprintf( '%s[%s][]', $this->query_var, $facet );
				$result->parent = 0;
				$result->count  = $count;

				if ( 'taxonomy' === $type ) {
					if ( ! empty( $taxonomy_terms[ $value ] ) ) {
						$result->label = $taxonomy_terms[ $value ]->name;
					}
				} elseif ( 'meta' === $type ) {
					$result->label = $this->humanize( $result->label );
				}

				$results[] = $result;
			}
		}

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	public function pre_get_posts( $query ) : void {
		parent::pre_get_posts( $query );

		$facets = array_filter( (array) $query->get( 'facets', [] ) );

		if ( ! empty( $facets ) ) {
			$query->set( 'ep_integrate', true );
			$query->set( 'ep_facet', true );
		}
	}

	/**
	 * Add support for meta, post type, and author facets
	 *
	 * @param array $formatted
	 * @param array $args
	 * @param WP_Query $query
	 * @return array
	 */
	public function ep_formatted_args( $formatted, $args, $query ) {
		$facets = $query->get( 'facets', [] );

		if ( ! empty( $facets ) ) {
			foreach ( $facets as $facet ) {
				if ( taxonomy_exists( $facet ) ) {
					// handled natively
					continue;
				}

				$size = apply_filters( 'ep_facet_taxonomies_size', 10000, $facet );

				$formatted['aggs']                  ??= [];
				$formatted['aggs']['terms']         ??= [];
				$formatted['aggs']['terms']['aggs'] ??= [];

				if ( 'post_type' === $facet ) {
					$formatted['aggs']['terms']['aggs']['post_type'] = [
						'terms' => [
							'size'  => $size,
							'field' => 'post_type.raw',
						],
					];
				} elseif ( 'post_author' === $facet ) {
					$formatted['aggs']['terms']['aggs']['post_author'] = [
						'terms' => [
							'size'  => $size,
							'field' => 'post_author.display_name.raw',
						],
					];
				} elseif ( in_array( $facet, $this->meta_keys, true ) ) {
					$formatted['aggs']['terms']['aggs'][ $facet ] = [
						'terms' => [
							'size'  => $size,
							'field' => "meta.{$facet}.raw",
						],
					];
				}
			}
		}

		return $formatted;
	}

	/**
	 * Sync taxonomies to ElasticPress for indexing.
	 *
	 * Convenience filter for 'ep_sync_taxonomies'. Allows return of taxonomy names instead of taxonomy objects.
	 *
	 * @filter vip_search_post_taxonomies_allow_list
	 */
	public function sync_taxonomies( $taxonomies, $post ) {
		// Combine existing taxonomies with index taxonomies
		$taxonomies = array_unique( array_merge( $taxonomies, $this->get_taxonomies() ) );

		return $taxonomies;
	}

	/**
	 * Sync post meta keys to ElasticPress for indexing.
	 *
	 * @filter vip_search_post_meta_allow_list
	 */
	public function sync_post_meta_keys( $post_meta_keys, $post ) {
		$allowed_post_meta_keys = array_fill_keys( $this->get_meta_keys(), true );
		$post_meta_keys         = array_merge( $post_meta_keys, $allowed_post_meta_keys );

		return $post_meta_keys;
	}

	/**
	 * Mark a query as facetable when facets are available
	 *
	 * @param bool $facetable
	 * @param WP_Query $query
	 * @return bool
	 *
	 * @filter ep_is_facetable
	 */
	public function ep_is_facetable( $facetable, $query ) {
		if ( ! empty( $query->get( 'facets', [] ) ) ) {
			$facetable = true;
		}

		return $facetable;
	}

	/**
	 * Filter taxonomies made available for faceting
	 *
	 * @param array
	 * @return array
	 */
	public function ep_facet_include_taxonomies( $taxonomies ) {
		foreach ( $this->get_taxonomies() as $taxonomy ) {
			$taxonomies[ $taxonomy ] = get_taxonomy( $taxonomy );
		}

		return $taxonomies;
	}
}
