<?php
namespace WDG\Core;

/**
 * Get Excerpt (how WordPress should)
 *
 * Modeled after the built-in filters for get_the_excerpt(), the_excerpt(), and wp_trim_excerpt()
 *
 * @uses Filters: excerpt_length, excerpt_more, the_content, wp_trim_excerpt, the_excerpt
 * @uses get_extended(), strip_shortcodes(), excerpt_remove_blocks(), wp_trim_words()
 * @param int|string|WP_Post $id
 * @return string
 */
function get_the_excerpt( $id, $excerpt_length = 55 ) {
	$post = get_post( $id );

	if ( empty( $post ) ) {
		return '';
	}

	$excerpt        = '';
	$excerpt_length = apply_filters( 'excerpt_length', $excerpt_length );
	$excerpt_more   = apply_filters( 'excerpt_more', '&hellip;' );

	if ( strlen( $post->post_excerpt ) ) {
		// Use an existing excerpt
		$excerpt = $post->post_excerpt;

	} else {
		// Create an excerpt from post content
		if ( preg_match( '/<!--more(.*?)?-->/', $post->post_content ) ) {
			$data    = get_extended( $post->post_content );
			$excerpt = $data['main'];
		} else {
			$excerpt = $post->post_content;
		}

		$excerpt = strip_shortcodes( $excerpt );
		$excerpt = excerpt_remove_blocks( $excerpt );

		$excerpt = apply_filters( 'the_content', $excerpt );
		$excerpt = str_replace( ']]>', ']]&gt;', $excerpt );
	}

	// let's use 0 as a "show all content" wildcard
	if ( $excerpt_length > 0 ) {
		$excerpt = wp_trim_words( $excerpt, $excerpt_length, $excerpt_more );
	}

	$excerpt = apply_filters( 'wp_trim_excerpt', $excerpt );

	$excerpt = apply_filters( 'the_excerpt', $excerpt );

	return $excerpt;
}

/**
 * Recursively get the descendants of a post
 *
 * @param WP_Post $parent_post
 * @param int $max_depth
 * @return array
 */
function get_descendants( $parent_post, $max_depth = 0 ) : array {
	$get = function ( $parent_post, $depth = 1 ) use ( &$get, $max_depth ) : array {
		$parent_post = get_post( $parent_post );
		$query       = new \WP_Query(
			[
				'post_type'      => $parent_post->post_type,
				'post_parent'    => $parent_post->ID,
				'order'          => 'ASC',
				'orderby'        => 'menu_order',
				'posts_per_page' => -1,
			]
		);

		foreach ( $query->posts as &$post ) {
			if ( $max_depth <= 0 || $depth < $max_depth ) {
				$post->children = $get( $post, $depth + 1 );
			}
		}

		return $query->posts;
	};

	return $get( $parent_post );
}

/**
 * Get the siblings of a post
 *
 * @param int|WP_Post $post
 * @return array
 */
function get_siblings( $post ) : array {
	$post = get_post( $post );

	return get_posts(
		[
			'post_type'      => $post->post_type,
			'post_parent'    => (int) $post->post_parent,
			'posts_per_page' => 100,
			'order'          => 'ASC',
			'orderby'        => 'menu_order',
		]
	);
}

/**
 * Get the primary term of a post in a certain taxonomy
 *
 * @param string $taxonomy
 * @param int $post_id
 * @return \WP_Term|null
 */
function primary_term( $taxonomy, $post_id = null ) {
	$post_id = $post_id ?? get_the_ID();

	$term = null;

	if ( function_exists( '\the_seo_framework' ) ) {
		$term = \the_seo_framework()->get_primary_term( $post_id, $taxonomy );
	} elseif ( function_exists( 'yoast_get_primary_term_id' ) ) {
		$term_id = \yoast_get_primary_term_id( $taxonomy, $post_id );

		if ( ! empty( $term_id ) ) {
			$term = get_term_by( 'id', $term_id, $taxonomy );
		}
	}

	if ( empty( $term ) ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy );

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $_term ) {
				if ( 'category' === $taxonomy && 'uncategorized' === $_term->slug ) {
					continue;
				}

				// grab the first not uncategorized term as fallback
				$term = $_term;
				break;
			}
		}
	}

	// check for uncategorized again if it's actually selected
	if ( ! empty( $term ) && 'category' === $taxonomy && 'uncategorized' === $term->slug ) {
		$term = null;
	}

	return $term;
}

/**
 * Format for the protected titles
 *
 * @return string
 */
function private_protected_title_format() : string {
	return '%s';
}
