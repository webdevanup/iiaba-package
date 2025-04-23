<?php

namespace WDG\Facets\Source;

use WP_Query;

/**
 * SearchWP integration
 *
 * @package wdgdc/wikit-facets
 */
class SearchWP extends AbstractSource implements SourceInterface {

	public function __construct() {
		parent::__construct();

		add_filter( 'searchwp\native\args', [ $this, 'searchwp_native_args' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Save the searchwp sql to the query object so we can later query for it's facets
	 *
	 * @param array $args
	 * @param WP_Query $wp_query
	 * @return array
	 */
	public function searchwp_native_args( $args, $wp_query ) {
		$searchwp_query_sql = function ( $sql ) use ( &$wp_query, &$searchwp_query_sql ) {
			$wp_query->searchwp_sql = $sql;

			remove_filter( 'searchwp\query\sql', $searchwp_query_sql, PHP_INT_MAX );

			return $sql;
		};

		add_filter( 'searchwp\query\sql', $searchwp_query_sql, PHP_INT_MAX );

		return $args;
	}

	/**
	 * Get sub-query clause from the searchwp main query sql
	 *
	 * @param WP_Query $query
	 * @param array $query_facets
	 * @return array
	 */
	public function get_sub_query( string $sub_query, WP_Query $query ) : string {
		if ( ! empty( $query->searchwp_sql ) ) {
			$sub_query = $query->searchwp_sql;

			$sub_query = preg_replace( '/\sSQL_CALC_FOUND_ROWS\s/s', ' ', $sub_query );
			$sub_query = preg_replace( '/\sLIMIT\s\d+,\s\d+$/s', '', $sub_query );
			$sub_query = "SELECT id FROM ( $sub_query ) as id";
		}

		return $sub_query;
	}
}
