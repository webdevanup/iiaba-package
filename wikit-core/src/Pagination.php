<?php

namespace WDG\Core;

class Pagination {

	/**
	 * The total number of pages - defaults to the main query max_num_pages
	 */
	public int $total;

	/**
	 * The current page - defaults to the paged query var
	 */
	public int $page;

	/**
	 * The href attribute for the first page link
	 */
	public string $first;

	/**
	 * The href attribute for the previous page link
	 */
	public string $prev;

	/**
	 * The href attribute for the next page link
	 */
	public string $next;

	/**
	 * The href attribute for the last page link
	 */
	public string $last;

	/**
	 * The url parts of the url
	 */
	protected array $url;

	/**
	 * The format of the pagination parameter
	 */
	protected ?string $format;

	/**
	 * The list of links
	 */
	public array $links = [];

	/**
	 * Build a pagination object with references to notable links
	 *
	 * @param ?int $total - default: main query max_num_pages
	 * @param ?int $page - default: global wp_query paged parameter
	 * @param ?string $url - the url to use as base for other links ( default: get_pagenum_link() )
	 * @param int $link_size - how many pagination links should be generated (default: 5)
	 * @param ?string $format - the pagination format param
	 */
	public function __construct( ?int $total = null, ?int $page = null, ?string $url = null, int $link_size = 5, ?string $format = null ) {
		global $wp_query;

		$this->total  = ! is_null( $total ) ? $total : $wp_query->max_num_pages;
		$this->page   = ! is_null( $page ) ? $page : max( intval( $wp_query->get( 'paged', 1 ) ), 1 );
		$this->url    = parse_url( ! is_null( $url ) ? $url : get_pagenum_link( 1, false ) );
		$this->format = ! is_null( $format ) ? $format : null;

		if ( $this->total > 1 ) {
			if ( $this->page > 1 ) {
				$this->first = $this->build( 1 );
				$this->prev  = $this->build( $this->page - 1 );
			}

			if ( $this->page < $this->total ) {
				$this->next = $this->build( $this->page + 1 );
				$this->last = $this->build( $this->total );
			}

			$this->links = $this->get_links( $link_size );
		}
	}

	/**
	 * Build a paginated URL
	 *
	 * @param int $page
	 * @return string
	 */
	public function build( int $page ) : string {
		global $wp_rewrite;

		$url = $this->url['scheme'] . '://' . $this->url['host'] . $this->url['path'];

		if ( ! empty( $this->url['query'] ) ) {
			parse_str( $this->url['query'], $query );
		} else {
			$query = [];
		}

		if ( ! empty( $this->format ) ) {
			$page_str = sprintf( $this->format, $page );

			if ( ! str_contains( $this->format, '=' ) ) {
				// custom rewrite pagination format e.g. /custom-page-slug/3/
				if ( $page > 1 ) {
					$url .= $page_str;
				}
			} else {
				// custom (possibly nested array) parameter
				parse_str( $page_str, $page_query );

				// use a custom recursive merge so we overwrite the last parameter instead of add an additional value
				$merge_query = function( array $query, array $page_query ) use ( &$merge_query, $page ) {
					foreach ( $page_query as $k => $v ) {
						$query[ $k ] ??= [];

						if ( is_array( $v ) ) {
							$query[ $k ] = $merge_query( $query[ $k ], $v );
						} else {
							$query[ $k ] = $page > 1 ? $v : null;
						}
					}

					return $query;
				};

				$query = $merge_query( $query, $page_query );
			}
		} elseif ( $page > 1 ) {
			if ( $wp_rewrite->using_permalinks() ) {
				$url .= $wp_rewrite->pagination_base . '/' . $page . '/';
			} else {
				$query['paged'] = $page;
			}
		}

		if ( ! empty( $query ) ) {
			$query_string = http_build_query( $query );

			if ( ! empty( $query_string ) ) {
				// remove un-necessary array indexes
				$query_string = preg_replace(
					[
						'/(%5B)(?:[\d])+(%5D)/',
						'/(\[)(?:[\d])+(\])/',
					],
					'$1$2',
					$query_string
				);

				$url .= '?' . $query_string;
			}
		}

		return $url;
	}

	/**
	 * Get links for pagination with a set number of links
	 * - the current page will be anchored in the middle link if the first and last pages aren't visible
	 *
	 * @param int $size
	 * @return array
	 */
	public function get_links( int $size = 5 ) : array {
		$links = [];

		$start = min(
			max( 1, $this->page - intval( floor( $size / 2 ) ) ),
			max( 1, $this->total - $size + 1 )
		);

		$end = min( $this->total, $start + $size - 1 );

		for ( $i = $start; $i <= $end; $i++ ) {
			$links[ $i ] = $this->build( $i );
		}

		return $links;
	}
}
