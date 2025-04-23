<?php
namespace WDG\Facets\Source;

use WP_Query;

abstract class AbstractSource implements SourceInterface {

	public function __construct() {
		add_filter( 'wdg/facets/sub_query', [ $this, 'get_sub_query' ], 1, 2 );
	}

	/**
	 * @inheritDoc
	 */
	abstract public function get_sub_query( string $sub_query, WP_Query $query ) : string;
}
