<?php
namespace WDG\Facets\Source;

use WP_Query;

interface SourceInterface {

	/**
	 * Gets the sub query for a query source
	 *
	 * @param string $sub_query - the default sub query for the native source
	 * @param WP_Query $query
	 * @return string
	 */
	public function get_sub_query( string $sub_query, WP_Query $query ) : string;
}
