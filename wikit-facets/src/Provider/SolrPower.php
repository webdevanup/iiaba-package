<?php

namespace WDG\Facets\Provider;

use WP_Query;
use SolrPower_WP_Query;
use StdClass;

/**
 * The SolrPower provider implements the pantheon solr-power plugin
 *
 * - Does not need a custom source since it applies to WP_Query directly
 *
 * @package wdgdc/wikit-facets
 */
class SolrPower extends AbstractProvider implements ProviderInterface {

	/**
	 * @inheritDoc
	 */
	public function can_facet_post_type() : bool {
		if ( function_exists( 'solr_options' ) ) {
			$options = solr_options();

			return ! empty( $options['s4wp_facet_on_type'] );
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function can_facet_author() : bool {
		if ( function_exists( 'solr_options' ) ) {
			$options = solr_options();

			return ! empty( $options['s4wp_facet_on_author'] );
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function get_meta_keys() : array {
		if ( function_exists( 'solr_options' ) ) {
			$options = solr_options();

			if ( ! empty( $options['s4wp_facet_on_custom_fields'] ) ) {
				return array_filter( array_map( 'trim', (array) $options['s4wp_facet_on_custom_fields'] ) );
			}
		}

		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function get_taxonomies() : array {
		$taxonomies = [];

		if ( function_exists( 'solr_options' ) ) {
			$options = solr_options();

			if ( ! empty( $options['s4wp_facet_on_categories'] ) ) {
				$taxonomies[] = 'category';
			}

			if ( ! empty( $options['s4wp_facet_on_tags'] ) ) {
				$taxonomies[] = 'post_tag';
			}

			if ( ! empty( $options['s4wp_facet_on_taxonomy'] ) ) {
				$taxonomies = array_merge(
					$taxonomies,
					array_keys(
						get_taxonomies(
							[
								'_builtin' => false,
							],
							'names'
						)
					)
				);
			}

			$taxonomies = array_unique( $taxonomies );
		}

		return $taxonomies;
	}

	/**
	 * @inheritDoc
	 */
	public function pre_get_posts( $query ) : void {
		if ( ! is_admin() ) {
			parent::pre_get_posts( $query );

			$facets  = array_filter( (array) $query->get( 'facets', [] ) );
			$filters = $query->get( $this->query_var, [] );

			if ( ! empty( $facets ) ) {
				$query->set( 'solr_integrate', true );
			}

			// alias relevance to score
			if ( ! empty( $filters['orderby'] ) && 'relevance' === $filters['orderby'] ) {
				$query->set( 'orderby', 'score' );
			}

			if ( in_array( 'post_author', $facets, true ) && ! empty( $filters['post_author'] ) ) {
				$authors = $this->get_authors_by_display_name( $filters['post_author'] );

				if ( ! empty( $authors ) ) {
					$query->set( 'author__in', (array) array_column( $authors, 'ID' ) );

					// solr doesn't support authors with it's wp query integration, so manually add it to the query
					$solr_select_query = function ( $select ) use ( &$solr_select_query, $filters ) {
						remove_filter( 'solr_select_query', $solr_select_query, 1, 2 );

						$select['query'] = sprintf(
							'(%s AND (%s))',
							$select['query'],
							implode(
								' AND ',
								array_map(
									fn( $post_author ) => 'post_author:("' . addslashes( $post_author ) . '")',
									(array) $filters['post_author']
								)
							)
						);

						return $select;
					};

					add_filter( 'solr_select_query', $solr_select_query, 1, 2 );
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function get_query_facets( WP_Query $query, ?array $facets = null ) : array {
		$results = [];

		if ( ! class_exists( '\SolrPower_WP_Query' ) ) {
			return $results;
		}

		// note that SolrPower contains the facets for the latest solr integrated query
		$solr_facets     = SolrPower_WP_Query::get_instance()->facets;
		$facets          = $facets ?: $this->get_facets();
		$taxonomy_facets = $this->get_taxonomies();
		$meta_facets     = $this->get_meta_keys();

		foreach ( $facets as $facet ) {
			if ( 'post_type' === $facet ) {
				$facet_field = $solr_facets['post_type'] ?? null;
				$type        = 'post_type';
			} elseif ( 'post_author' === $facet ) {
				$facet_field = $solr_facets['post_author'] ?? null;
				$type        = 'author';
			} elseif ( 'category' === $facet ) {
				$facet_field = $solr_facets['categories'] ?? null;
				$type        = 'taxonomy';
			} elseif ( 'post_tag' === $facet ) {
				$facet_field = $solr_facets['tags'] ?? null;
				$type        = 'taxonomy';
			} elseif ( in_array( $facet, $taxonomy_facets, true ) ) {
				$facet_field = $solr_facets[ $facet . '_taxonomy_str' ] ?? null;
				$type        = 'taxonomy';
			} elseif ( in_array( $facet, $meta_facets, true ) ) {
				$facet_field = $solr_facets[ $facet . '_str' ] ?? null;
				$type        = 'meta';
			}

			if ( empty( $facet_field ) ) {
				continue;
			}

			$facet_filters = $facet_field->getValues();

			if ( empty( $facet_filters ) ) {
				continue;
			}

			$facet_filters = array_combine(
				array_map( fn( $value ) => rtrim( $value, '^' ), array_keys( $facet_filters ) ),
				array_values( $facet_filters ),
			);

			if ( 'taxonomy' === $type ) {
				$taxonomy_terms = $this->get_taxonomy_terms_by_name( $facet, array_keys( $facet_filters ) );
			} elseif ( 'author' === $type ) {
				$post_authors = $this->get_authors_by_display_name( array_keys( $facet_filters ) );
			}

			foreach ( $facet_filters as $value => $count ) {
				$result = new StdClass();

				$result->id     = sprintf( 'facet-%s-%s', $facet, sanitize_title( $value ) );
				$result->type   = $type;
				$result->facet  = $facet;
				$result->value  = $value;
				$result->label  = $value;
				$result->name   = sprintf( '%s[%s][]', $this->query_var, $facet );
				$result->parent = 0;
				$result->count  = $count;

				switch ( $type ) {
					case 'post_type':
						if ( post_type_exists( $value ) ) {
							$result->label = get_post_type_object( $value )->labels->singular_name;
						}
						break;
					case 'taxonomy':
						if ( ! empty( $taxonomy_terms[ $value ] ) ) {
							$result->label = $taxonomy_terms[ $value ]->name;
						}
						break;
					case 'meta':
						$result->label = $this->humanize( $value );
						break;
					case 'author':
						if ( ! empty( $post_authors[ $value ] ) ) {
							$result->label = $post_authors[ $value ]->display_name;
						}
						break;
				}

				$results[] = $result;
			}
		}

		return $results;
	}

	/**
	 * Get taxonomy terms by their taxonomy and name
	 *
	 * @param string $taxonomy
	 * @param array $terms
	 * @return array
	 */
	protected function get_taxonomy_terms_by_name( string $taxonomy, array $terms ) : array {
		static $cache = [];

		$cache_key = hash( 'sha1', json_encode( func_get_args() ) );

		if ( ! isset( $cache[ $cache_key ] ) ) {
			$taxonomy_terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'name'       => $terms,
					'hide_empty' => false,
				]
			);

			$cache[ $cache_key ] = array_combine( array_column( $taxonomy_terms, 'name' ), $taxonomy_terms );
		}

		return $cache[ $cache_key ];
	}
}
