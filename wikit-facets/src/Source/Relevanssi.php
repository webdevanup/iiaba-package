<?php

namespace WDG\Facets\Source;

use WP_Query;

/**
 * Relevanssi integration
 *
 * @package wdgdc/wikit-facets
 */
class Relevanssi extends AbstractSource implements SourceInterface {

	public function __construct() {
		parent::__construct();

		add_filter( 'relevanssi_search_params', [ $this, 'relevanssi_search_params' ], 1, 2 );
	}

	public function relevanssi_search_params( $params, $query ) {
		$relevanssi_query_filter = function ( $sql ) use ( $query, &$relevanssi_query_filter ) {
			$query->relevanssi_sql = $sql;

			remove_filter( 'relevanssi_query_filter', $relevanssi_query_filter, PHP_INT_MAX );

			return $sql;
		};

		add_filter( 'relevanssi_query_filter', $relevanssi_query_filter, PHP_INT_MAX, 2 );

		return $params;
	}

	/**
	 * Get sub-query clause from the relevanssi main query sql
	 *
	 * @param WP_Query $query
	 * @param array $query_facets
	 * @return array
	 */
	public function get_sub_query( string $sub_query, WP_Query $query ) : string {
		if ( ! empty( $query->relevanssi_sql ) ) {
			$sub_query = $query->relevanssi_sql;

			$sub_query = preg_replace( '/\sLIMIT.+$/s', '', $sub_query ); // remove limit on sub-query
			$sub_query = preg_replace( '/\srelevanssi\.\*,?\s?/', ' ', $sub_query ); // remove duplicate column selection not allowed in sub query
			$sub_query = "SELECT doc FROM ( $sub_query ) as doc";
		}

		return $sub_query;
	}
}
