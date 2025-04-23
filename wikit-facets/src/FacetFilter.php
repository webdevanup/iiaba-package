<?php

namespace WDG\Facets;

/**
 * The FacetFilter class is a representation of a filter (active or not) within a facet
 *
 * @package wdgdc/wikit-facets
 */
class FacetFilter extends Configurable {

	/**
	 * The HTML id for the facet filter
	 *
	 * @param string
	 */
	public string $id = '';

	/**
	 * The facet type (taxonomy, post_type, meta, etc)
	 *
	 * @var string
	 */
	public string $type = '';

	/**
	 * The facet name (taxonomy slug, meta key, etc)
	 *
	 * @param string
	 */
	public string $facet = '';

	/**
	 * The value of the facet (term slug, meta value, etc)
	 *
	 * @param string
	 */
	public string $value = '';

	/**
	 * The label of the value (term name, meta_key, etc)
	 *
	 * @param string
	 */
	public string $label = '';

	/**
	 * The html field name for the facet filter
	 *
	 * @param string
	 */
	public string $name = '';

	/**
	 * The count of many items match the filter
	 *
	 * @param int
	 */
	public int $count = 0;

	/**
	 * The parent slug of the filter
	 *
	 * @param string
	 */
	public string $parent = '';

	/**
	 * If the facet filter is current applied or not
	 *
	 * @param bool
	 */
	public bool $active = false;

	/**
	 * The child facets of this term
	 */
	public array $children;

	/**
	 * Generate a url to the current page with a modified parameter for this facet
	 *
	 * @param bool $add - should the filter be added or removed
	 * @param bool $multiple - should the link be appended or replaced if adding
	 * @return string
	 */
	public function link( bool $add = true, $multiple = true ) : string {
		global $wp, $wp_rewrite;

		$link = site_url( $wp->request );

		$query_vars = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query_var  = strtok( $this->name, '[' );

		$query_vars[ $query_var ] ??= [];

		$query_vars[ $query_var ][ $this->facet ] ??= [];

		$has_filter = in_array( $this->value, $query_vars[ $query_var ][ $this->facet ], true );

		if ( ! $multiple ) {
			$query_vars[ $query_var ][ $this->facet ] = [];
		}

		if ( $add && ! $has_filter ) {
			// add filter
			$query_vars[ $query_var ][ $this->facet ][] = $this->value;
		} elseif ( ! $add && $has_filter ) {
			// remove filter
			$query_vars[ $query_var ][ $this->facet ] = array_diff( $query_vars[ $query_var ][ $this->facet ], [ $this->value ] );
		}

		// remove filter pagination
		if ( isset( $query_vars[ $query_var ]['paged'] ) ) {
			unset( $query_vars[ $query_var ]['paged'] );
		}

		// remove paged parameter pagination
		if ( isset( $query_vars['paged'] ) ) {
			unset( $query_vars['paged'] );
		}

		// remove permalink pagination
		$link = preg_replace( sprintf( '/\/%s\/[\d]+\/?/', $wp_rewrite->pagination_base ), '/', $link );

		// rebuild url with parameters
		$link .= '?' . http_build_query( $query_vars );

		// remove un-necessary array indexes
		$link = preg_replace(
			[
				'/(%5B)(?:[\d])+(%5D)/',
				'/(\[)(?:[\d])+(\])/',
			],
			'$1$2',
			$link
		);

		return $link;
	}
}
