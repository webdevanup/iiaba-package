<?php

namespace WDG\Core;

class Related {

	public static array $cache = [];

	/**
	 * Get the items related to a specific post by common terms
	 *
	 * @param int|WP_Post $post
	 * @param ?array $pinned - array of post_ids to exclude
	 * @param ?array $terms - which terms to consider instead of
	 * @param ?array $taxonomies - which taxonomies to consider with the query
	 * @param ?array $post_types - which post_types to consider as related for this query
	 * @param int $posts_per_page - the maximum number of items to be returned
	 * @return array
	 */
	public static function posts( $post, ?array $pinned = null, ?array $terms = null, ?array $taxonomies = null, ?array $post_type = null, int $posts_per_page = 10 ) : array {
		$cache_key = hash( 'sha1', json_encode( func_get_args() ) );

		if ( ! isset( static::$cache[ $cache_key ] ) ) {
			$post = get_post( $post );

			if ( empty( $post ) ) {
				return [];
			}

			$post_type  ??= get_post_types( [ 'public' => true ] );
			$taxonomies ??= [ 'category' ];

			$query_args = [
				'post_type'      => $post_type,
				'posts_per_page' => $posts_per_page,
				'tax_query'      => [],
				'pinned'         => $pinned ?? [],
				'post__not_in'   => [ $post->ID ],
			];

			if ( empty( $terms ) ) {
				$terms = wp_get_post_terms( $post->ID, $taxonomies );
			} else {
				$terms = get_terms(
					[
						'taxonomy' => $taxonomies,
						'include'  => (array) $terms,
						'orderby'  => 'include',
					]
				);
			}

			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$query_args['tax_query'][] = [
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => [ $term->term_id ],
					];
				}

				if ( count( $query_args['tax_query'] ) > 1 ) {
					$query_args['tax_query'] = array_merge(
						[ 'relation' => 'OR' ],
						$query_args['tax_query']
					);
				}

				$query = new \WP_Query( $query_args );

				static::$cache[ $cache_key ] = $query->posts;
			} else {
				static::$cache[ $cache_key ] = [];
			}
		}

		return static::$cache[ $cache_key ];
	}
}
