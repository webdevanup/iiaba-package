<?php

namespace WDG\Facets\Provider;

use WDG\Facets\Configurable;
use WDG\Facets\Facet;
use WDG\Facets\FacetSet;
use WDG\Facets\Source\SourceInterface;
use WP_Query;

abstract class AbstractProvider extends Configurable implements ProviderInterface {

	/**
	 * Is post type facet allowed
	 *
	 * @param bool
	 */
	protected bool $post_type = true;

	/**
	 * Is author facet allowed
	 *
	 * @param bool
	 */
	protected bool $post_author = false;

	/**
	 * The list of meta facets allowed
	 *
	 * @param array
	 */
	protected array $meta_keys = [];

	/**
	 * The list of taxonomies allowed
	 *
	 * @param array
	 */
	protected array $taxonomies = [];

	/**
	 * The name of the public query var to auto filter the query
	 *
	 * @param string
	 */
	public string $query_var = 'filter';

	/**
	 * A reference to the source
	 */
	protected ?SourceInterface $source;

	public function __construct( array $props = [], bool $existing_only = true ) {
		parent::__construct( $props, $existing_only );

		add_action( 'init', [ $this, 'init' ], 11 );
		add_filter( 'query_vars', [ $this, 'query_vars' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ] );
		add_filter( 'the_posts', [ $this, 'the_posts' ], 1, 2 );
		add_filter( 'redirect_canonical', [ $this, 'redirect_canonical' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Set a reference to the source
	 *
	 * @param SourceInterface $source
	 * @return void
	 */
	public function set_source( SourceInterface $source ) : void {
		$this->source = $source;
	}

	/**
	 * @inheritDoc
	 */
	public function get_facets() : array {
		$facets = [];

		if ( $this->can_facet_post_type() ) {
			$facets['post_type'] = __( 'Type' );
		}

		if ( $this->can_facet_author() ) {
			$facets['post_author'] = __( 'Author' );
		}

		$taxonomies = $this->get_taxonomies();

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( taxonomy_exists( $taxonomy ) ) {
					$facets[ $taxonomy ] = get_taxonomy( $taxonomy )->label;
				}
			}
		}

		$meta_keys = $this->get_meta_keys();

		if ( ! empty( $meta_keys ) ) {
			foreach ( $meta_keys as $meta_key ) {
				$facets[ $meta_key ] = ucwords( $meta_key );
			}
		}

		return $facets;
	}

	/**
	 * @inheritDoc
	 */
	abstract public function get_taxonomies() : array;

	/**
	 * @inheritDoc
	 */
	abstract public function get_meta_keys() : array;

	/**
	 * @inheritDoc
	 */
	public function can_facet_post_type() : bool {
		return $this->post_type;
	}

	/**
	 * @inheritDoc
	 */
	public function can_facet_author() : bool {
		return $this->post_author;
	}

	/**
	 * @inheritDoc
	 */
	abstract public function get_query_facets( WP_Query $query, ?array $facets = null ) : array;

	/**
	 * Get the post type filters that are not applicable to the current Facet results
	 *
	 * @param Facet $facet
	 * @return array
	 */
	public function get_other_post_type_filters( Facet $facet ) : array {
		$values = array_unique( array_column( $facet->filters, 'value' ) );

		if ( 'post_type' === $facet->name ) {
			// post types
			$keys    = array_values( array_diff( get_post_types( [ 'public' => true ] ), $values ) );
			$missing = [];

			foreach ( $keys as $key ) {
				$missing[ $key ] = get_post_type_object( $key )->labels->name;
			}

			return $missing;
		}
	}

	/**
	 * Get the other post_author filters not in the facet
	 *
	 * @param Facet $facet
	 * @return array
	 */
	public function get_other_post_author_filters( Facet $facet ) : array {
		return [];
	}

	/**
	 * Get the other taxonomy filters not in the facet
	 *
	 * @param Facet $facet
	 * @return array
	 */
	public function get_other_taxonomy_filters( Facet $facet ) : array {
		global $wpdb;

		$values           = $facet->values();
		$placeholders     = implode( ',', array_fill( 0, count( $values ), '%s' ) );
		$missing_term_ids = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT tt.term_id
					FROM $wpdb->terms t
					JOIN $wpdb->term_taxonomy tt on t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					AND t.slug NOT IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array_merge( [ $facet->name ], $values )
				)
			)
		);

		if ( empty( $missing_term_ids ) ) {
			return [];
		}

		$missing_terms = get_terms(
			[
				'taxonomy'   => $facet->name,
				'include'    => $missing_term_ids,
				'hide_empty' => false,
			]
		);

		return array_column( $missing_terms, 'name', 'slug' );
	}

	/**
	 * Get the other meta filters not in the facet
	 *
	 * @param Facet $facet
	 * @return array
	 */
	public function get_other_meta_filters( Facet $facet ) : array {
		global $wpdb;

		$values = $facet->values();

		// meta keys
		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );

		$missing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value
				FROM $wpdb->postmeta
				WHERE meta_key = %s
				AND meta_value NOT IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( [ $facet->name ], $values )
			),
		);

		return array_combine( $missing, $missing );
	}

	/**
	 * Fill in missing filters for a Facet object in case we want to show all filters even if they don't apply
	 *
	 * - default to using the WordPress database to backfill but providers should override this for a more efficient solution
	 *
	 * @param Facet $facet
	 * @return array
	 */
	public function get_other_filters( Facet $facet ) : array {
		if ( 'post_type' === $facet->name ) {
			return $this->get_other_post_type_filters( $facet );
		}

		if ( 'post_author' === $facet->name ) {
			return $this->get_other_post_author_filters( $facet );
		}

		if ( taxonomy_exists( $facet->name ) ) {
			return $this->get_other_taxonomy_filters( $facet );
		}

		return $this->get_other_meta_filters( $facet );
	}

	/**
	 * Delay the filter for changing the query var until plugins/themes have loaded
	 *
	 * @return void
	 */
	public function init() : void {
		/**
		 * Allow customizing the root query var for faceted queries
		 *
		 * @param string $this->query_var
		 * @return string
		 */
		$this->query_var = (string) apply_filters( 'wdg/facets/query_var', $this->query_var );
	}

	/**
	 * Add our query var to be recognized by WP_Query
	 *
	 * @param array
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = $this->query_var;

		return $vars;
	}

	/**
	 * Mark a query as facetable and apply the facets filters
	 *
	 * @param WP_Query
	 * @return void
	 */
	public function pre_get_posts( $query ) : void {
		if ( is_admin() ) {
			return;
		}

		$facets = array_filter( (array) $query->get( 'facets', [] ) );

		if ( empty( $facets ) ) {
			$can_facet = $query->is_main_query() && ( $query->is_search() || $query->is_archive() || $query->is_home() || $query->is_tax() );

			/**
			 * Allow customizing which queries have the facets query auto applied
			 * - Defaults to true for search and archives
			 *
			 * @param bool $can_facet
			 * @param WP_Query $query
			 * @return bool
			 */
			$can_facet = (bool) apply_filters( 'wdg/facets/auto', $can_facet, $query );

			if ( $can_facet ) {
				$facets = array_keys( $this->get_facets() );
			}
		}

		/**
		 * Allow customizing the facets for other configuration (like a custom block)
		 *
		 * @param array $facets
		 * @param WP_Query $query
		 * @return array
		 */
		$facets = array_filter( (array) apply_filters( 'wdg/facets/facets', $facets, $query ) );

		if ( ! empty( $facets ) ) {
			$query->set( 'facets', $facets );
		}

		$filters = $query->get( $this->query_var, [] );

		if ( ! empty( $filters ) ) {
			$taxonomies = $this->get_taxonomies();
			$meta_keys  = $this->get_meta_keys();
			$tax_query  = [];
			$meta_query = [];

			foreach ( $filters as $facet => $filter ) {
				if ( 'search' === $facet ) {
					$query->set( 's', sanitize_text_field( (string) $filter ) );
				} elseif ( 'paged' === $facet ) {
					$query->set( 'paged', sanitize_text_field( (int) $filter ) );
				} elseif ( 'order' === $facet ) {
					$filter = strtolower( sanitize_text_field( (string) $filter ) );

					if ( in_array( $filter, [ 'asc', 'desc' ], true ) ) {
						$query->set( 'order', $filter );
					}
				} elseif ( 'orderby' === $facet ) {
					$filter = strtolower( sanitize_text_field( (string) $filter ) );

					if ( in_array( $filter, [ 'relevance', 'date', 'title' ], true ) ) {
						$query->set( 'orderby', $filter );
					}
				} elseif ( 'post_type' === $facet ) {
					$query->set( 'post_type', (array) $filter );
				} elseif ( in_array( $facet, $taxonomies, true ) && taxonomy_exists( $facet ) ) {
					$tax_query[] = [
						'taxonomy' => $facet,
						'field'    => 'slug',
						'terms'    => array_map( 'sanitize_text_field', (array) $filter ),
					];
				} elseif ( in_array( $facet, $meta_keys, true ) ) {
					$meta_query[] = [
						'key'     => $facet,
						'value'   => array_map( 'sanitize_text_field', (array) $filter ),
						'compare' => 'IN',
					];
				}
			}

			if ( ! empty( $tax_query ) ) {
				$query->set( 'tax_query', $tax_query );
			}

			if ( ! empty( $meta_query ) ) {
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Apply the facet results from provider to the WP_Query
	 *
	 * @param array $posts
	 * @param WP_Query $query
	 * @return array
	 */
	public function the_posts( $posts, $query ) {
		$query_facets     = (array) $query->get( 'facets', [] );
		$available_facets = array_keys( $this->get_facets() );
		$facets           = array_intersect( $query_facets, $available_facets );

		if ( ! empty( $facets ) ) {
			$results = $this->get_query_facets( $query, $query->get( 'facets' ) ?: null );

			if ( ! empty( $results ) ) {
				$filters = (array) $query->get( $this->query_var, [] );

				$query->facets = new FacetSet();

				foreach ( $results as &$result ) {
					if ( ! empty( $result->facet ) ) {
						if ( ! empty( $filters[ $result->facet ] ) && in_array( $result->value, $filters[ $result->facet ], true ) ) {
							$result->active = true;
						}

						if ( ! isset( $query->facets[ $result->facet ] ) ) {
							$query->facets[ $result->facet ] = new Facet(
								[
									'name'      => $result->facet,
									'query_var' => $this->query_var,
									'provider'  => $this,
								]
							);
						}

						$query->facets[ $result->facet ]->add( $result );
					}
				}

				foreach ( $query->facets as $facet ) {
					if ( $facet->hierarchical ) {
						$facet->build_tree();
					}

					$facet->get_active_filters();
				}
			}
		}

		return $posts;
	}

	/**
	 * Utility function get authors by their display name
	 *
	 * @param array $display_names
	 * @return array
	 */
	protected function get_authors_by_display_name( array $display_names ) : array {
		global $wpdb;

		static $cache = [];

		$cache_key = hash( 'sha1', json_encode( func_get_args() ) );

		if ( ! isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ] = [];

			if ( ! empty( $display_names ) ) {
				$formats = implode( ',', array_fill( 0, count( array_keys( $display_names ) ), '%s' ) );

				// phpcs doesn't support array formatted placeholders
				// phpcs:disable WordPress.DB
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, display_name
						FROM $wpdb->users
						WHERE display_name IN ($formats)",
						$display_names
					),
				);
				// phpcs:enable WordPress.DB

				if ( ! empty( $results ) ) {
					$author_map = array_column( $results, 'ID', 'display_name' );
					$author_ids = array_map( 'intval', array_values( $author_map ) );
					$users      = get_users( [ 'include' => $author_ids ] );

					if ( ! empty( $users ) ) {
						$cache[ $cache_key ] = array_combine( array_column( $users, 'display_name' ), $users );
					}
				}
			}
		}

		return $cache[ $cache_key ];
	}

	/**
	 * Convert a string to a human readable label
	 *
	 * @param string $str
	 * @return string
	 */
	protected function humanize( string $str ) : string {
		$str = trim( strtolower( $str ) );
		$str = preg_replace( '/[\-\_\.+]/', ' ', $str ); // replace common separators
		$str = preg_replace( '/[^a-z0-9\s+]/', '', $str ); // only normal characters
		$str = preg_replace( '/\s+/', ' ', $str ); // consecutive white space
		$str = implode( ' ', array_map( 'ucwords', explode( ' ', $str ) ) );

		return $str;
	}

	/**
	 * Don't redirect_canonical when the query filter paged is set
	 *
	 * @param string $redirect_url
	 * @param string $requested_url
	 * @return string
	 */
	public function redirect_canonical( $redirect_url, $requested_url ) {
		if ( $redirect_url !== $requested_url ) {
			parse_str( parse_url( $requested_url, PHP_URL_QUERY ), $query_string ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			if ( ! empty( $query_string[ $this->query_var ]['paged'] ) ) {
				return $requested_url;
			}
		}

		return $redirect_url;
	}
}
