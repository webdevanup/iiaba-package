<?php

namespace WDG\Core;

use WP_Query;

/**
 * This class implements the pinned posts parameter of WP_Query
 * by adding additional clauses to the query SQL
 *
 * Usage:
 * new WP_Query( [ 'pinned' => 1, 2, 3 ] );
 *
 * Note: this only applies to raw WP_Query requests.
 * It will not work with a search plugin that intercepts a query such as ElasticSearch, Solr, or SearchWP
 *
 * @package wikit-core
 */
class PinnedPosts {

	public function __construct() {
		add_filter( 'posts_where_request', [ $this, 'posts_where_request' ], 1, 2 );
		add_filter( 'posts_orderby_request', [ $this, 'posts_orderby_request' ], 1, 2 );
	}

	/**
	 * Get the sanitized pinned parameter for a query
	 *
	 * @param WP_Query $query
	 * @return array
	 */
	protected function get_query_pinned( WP_Query $query ) : array {
		$validate = fn( $ids ) => array_filter( array_map( 'intval', (array) $ids ) );

		$pinned = $validate( $query->get( 'pinned' ) );

		if ( empty( $pinned ) ) {
			// back-compat
			$pinned = $validate( $query->get( 'wdg_pinned' ) );
		}

		return $pinned;
	}

	/**
	 * Wrap the where clause of the query to include an OR condition of our pinned and the existing where clause
	 *
	 * @param string $where
	 * @param WP_Query $query
	 * @return string
	 * @uses $wpdb;
	 */
	public function posts_where_request( $where, $query ) {
		global $wpdb;

		$pinned = $this->get_query_pinned( $query );

		if ( ! empty( $pinned ) ) {
			$where = preg_replace( '/^AND\s/', '', trim( $where ) );

			$ids = $wpdb->prepare(
				implode( ',', array_fill( 0, count( $pinned ), '%d' ) ),
				$pinned
			);

			$where = " AND ( ( $wpdb->posts.ID IN ($ids) and $wpdb->posts.post_status = 'publish' ) OR ( $where ) )";
		}

		return $where;
	}

	/**
	 * Add an additional orderby clause prioritizing the order of our pinned posts
	 *
	 * @param string $orderby
	 * @param WP_Query $query
	 * @return string
	 * @uses $wpdb;
	 */
	public function posts_orderby_request( $orderby, $query ) {
		global $wpdb;

		$pinned = $this->get_query_pinned( $query );

		if ( ! empty( $pinned ) ) {
			$ids = $wpdb->prepare( implode( ',', array_fill( 0, count( $pinned ), '%d' ) ), array_reverse( $pinned ) );

			if ( ! empty( $ids ) ) {
				$orderby = "FIELD(ID, $ids) DESC, " . trim( $orderby );
			}
		}

		return $orderby;
	}
}
