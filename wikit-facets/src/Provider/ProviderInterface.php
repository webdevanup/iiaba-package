<?php

namespace WDG\Facets\Provider;

use WDG\Facets\Facet;
use WP_Query;

interface ProviderInterface {

	/**
	 * Get a list of all configured facets
	 *
	 * @return array
	 */
	public function get_facets() : array;

	/**
	 * Get the list of facetable taxonomies
	 *
	 * @return array
	 */
	public function get_taxonomies() : array;

	/**
	 * Get the list of facetable meta keys
	 *
	 * @return array
	 */
	public function get_meta_keys() : array;

	/**
	 * Can post type be considered a facet
	 *
	 * @return bool
	 */
	public function can_facet_post_type() : bool;

	/**
	 * Can author be a facet
	 *
	 * @return bool
	 */
	public function can_facet_author() : bool;

	/**
	 * Given a WP_Query, get the facets that apply
	 *
	 * @param WP_Query $query - the query being executed
	 * @param ?array $facets - the list of facets requested
	 * @return array
	 */
	public function get_query_facets( WP_Query $query, ?array $facets = null ) : array;

	/**
	 * Get filters that are not matches from the existing results set
	 *
	 * @param Facet $facet
	 * @return array
	 */
	public function get_other_filters( Facet $facet ) : array;
}
