<?php

namespace WDG\Facets;

use Iterator;
use WP_Term;

/**
 * The facet class is a representation of a facet and it's properties and filters
 *
 * - iterator implements iterating over the filters array
 *
 * @package wdgdc/wikit-facets
 */
class Facet extends Configurable implements Iterator {

	/**
	 * The current position in the iterator
	 *
	 * @var int
	 */
	private int $position = 0;

	/**
	 * The label of the facet
	 *
	 * @var string
	 */
	public string $label = '';

	/**
	 * The key/name of the facet
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * Filter storage
	 *
	 * @var array
	 */
	public array $filters = [];

	/**
	 * The list of active filters
	 *
	 * @var array
	 */
	public array $active_filters = [];

	/**
	 * Is the facet considered active or not
	 */
	public bool $active = false;

	/**
	 * Is the facet considered hierarchical
	 */
	public ?bool $hierarchical = null;

	/**
	 * The orderby of the filters
	 */
	public string $orderby = 'count';

	/**
	 * Allowed orderby enum
	 */
	protected array $orderby_enum = [
		'count',
		'value',
	];

	/**
	 * The order of the filters
	 */
	public string $order = 'DESC';

	/**
	 * Allowed order enum
	 */
	protected array $order_enum = [
		'desc',
		'asc',
	];

	/**
	 *
	 */
	protected string $query_var = 'filter';

	/**
	 * An injected reference to the current provider
	 *
	 * @var Provider\ProviderInterface
	 */
	public Provider\ProviderInterface $provider;

	/**
	 * @inheritDoc
	 */
	public function __construct( array $props, bool $existing_only = true ) {
		if ( ! empty( $props['name'] ) && empty( $props['label'] ) ) {
			if ( 'post_type' === $props['name'] ) {
				$props['label'] = __( 'Type' );
			} elseif ( taxonomy_exists( $props['name'] ) ) {
				$props['label'] = get_taxonomy( $props['name'] )->labels->singular_name;
			} else {
				$props['label'] = ucwords( $props['name'] );
			}
		}

		parent::__construct( $props, $existing_only );

		if ( ! in_array( $this->orderby, $this->orderby_enum, true ) ) {
			$this->orderby = $this->orderby_enum[0];
		}

		if ( ! in_array( $this->order, $this->order_enum, true ) ) {
			$this->order = $this->order_enum[0];
		}

		$this->hierarchical ??= taxonomy_exists( $this->name ) && is_taxonomy_hierarchical( $this->name );

		if ( $this->hierarchical ) {
			$this->build_tree();
		}
	}

	/**
	 * Add a filter to the filters storage
	 *
	 * @param object|array $facet
	 * @return array - the list of all filters
	 */
	public function add( $facet ) : array {
		$filter = new FacetFilter( $facet );

		if ( ! $this->active && $filter->active ) {
			$this->active = true;
		}

		$this->filters[] = $filter;

		return $this->filters;
	}

	public function show_all( bool $show_empty_tree = false ) {
		$missing = $this->provider->get_other_filters( $this );

		if ( ! empty( $missing ) ) {
			foreach ( $missing as $val => $label ) {
				$this->filters[] = new FacetFilter(
					[
						'id'       => sprintf( 'facet-%s-%s', $this->name, $val ),
						'type'     => $type ?? '',
						'facet'    => $this->name,
						'value'    => $val,
						'label'    => $label,
						'name'     => sprintf( '%s[%s][]', $this->query_var, $this->name ),
						'count'    => 0,
						'active'   => false,
						'provider' => $this->provider,
					]
				);
			}

			if ( $this->hierarchical ) {
				$this->build_tree( $show_empty_tree );
			}

			$this->sort();
		}
	}

	/**
	 * Convert the filters list to a hierarchical structure if the facet is a hierarchical taxonomy
	 *
	 * @param bool $show_empty_branches - if empty branches should be hidden
	 * @return static
	 */
	public function build_tree( $show_empty_branches = false ) : array {
		if ( ! $this->hierarchical || ! taxonomy_exists( $this->name ) ) {
			// only supporting hierarchical taxonomies
			return $this->filters;
		}

		$terms = get_terms(
			[
				'taxonomy'   => $this->name,
				'hide_empty' => false,
				'orderby'    => $this->orderby,
				'order'      => $this->order,
			]
		);

		$terms              = array_combine( array_column( $terms, 'term_id' ), $terms );
		$parents            = array_values( array_filter( $terms, fn( $term ) => empty( $term->parent ) ) );
		$filters_slug_index = array_combine( array_column( $this->filters, 'value' ), $this->filters );
		$hierarchy          = _get_term_hierarchy( $this->name );

		$build = function ( WP_Term $term ) use ( $filters_slug_index, $hierarchy, $terms, &$build ) {
			if ( isset( $filters_slug_index[ $term->slug ] ) ) {
				$filter = clone $filters_slug_index[ $term->slug ];
			} else {
				$filter = new FacetFilter(
					[
						'id'     => sprintf( 'facet-%s-%s', $term->taxonomy, $term->slug ),
						'type'   => 'taxonomy',
						'facet'  => $term->taxonomy,
						'value'  => $term->slug,
						'label'  => $term->name,
						'name'   => sprintf( '%s[%s][]', $this->query_var, $term->taxonomy ),
						'count'  => 0,
						'active' => false,
					]
				);
			}

			if ( ! empty( $hierarchy[ $term->term_id ] ) ) {
				$children         = array_filter( $terms, fn( $term_obj ) => in_array( $term_obj->term_id, $hierarchy[ $term->term_id ], true ) );
				$filter->children = [];

				foreach ( $children as $child ) {
					$filter->children[] = $build( $child );
				}
			}

			return $filter;
		};

		$this->filters = array_map( $build, $parents );

		if ( ! $show_empty_branches ) {
			$reduce_total = function ( int $total, FacetFilter $filter ) use ( &$reduce_total ) {
				$total = $filter->count;

				if ( ! empty( $filter->children ) ) {
					$total += array_reduce( $filter->children, $reduce_total, 0 );
				}

				return $total;
			};

			$filter_tree = function ( FacetFilter $filter ) use ( &$filter_tree, &$reduce_total ) {
				if ( ! empty( $filter->children ) ) {
					$filter->children = array_filter( $filter->children, $filter_tree );
					$filter->count    = array_reduce( $filter->children, $reduce_total, $filter->count );
				}

				return $filter->count > 0;
			};

			$this->filters = array_values( array_filter( $this->filters, $filter_tree ) );
		}

		return $this->filters;
	}

	/**
	 * Count the number of active filters
	 *
	 * @return int
	 */
	public function active_count() : int {
		$reduce_total = function ( $total, FacetFilter $filter ) use ( &$reduce_total ) {
			$total += $filter->active ? 1 : 0;

			if ( ! empty( $filter->children ) ) {
				$total += array_reduce( $filter->children, $reduce_total, 0 );
			}

			return $total;
		};

		$count = array_reduce( $this->filters, $reduce_total, 0 );

		return $count;
	}

	/**
	 * re-sort the filters
	 *
	 * @param string $sort
	 * @param string $order
	 * @return void
	 */
	public function sort( string $orderby = null, $order = null ) : void {
		if ( ! empty( $orderby ) && in_array( $orderby, $this->orderby_enum, true ) ) {
			$this->orderby = $orderby;
		}

		if ( ! empty( $order ) && in_array( $order, $this->order_enum, true ) ) {
			$this->order = $order;
		}

		$sort_compare = 'count' === $this->orderby ? SORT_NUMERIC : SORT_NATURAL;
		$sort_order   = 'asc' === $this->order ? SORT_ASC : SORT_DESC;

		$sort = function ( &$filters ) use ( &$sort, $sort_compare, $sort_order ) {
			array_multisort( array_column( $filters, $this->orderby ), $sort_compare, $sort_order, $filters );

			foreach ( $filters as &$filter ) {
				if ( ! empty( $filter->children ) ) {
					$sort( $filter->children );
				}
			}
		};

		$sort( $this->filters );
	}

	/**
	 * Get the unique values of the current filters
	 *
	 * @return array
	 */
	public function values() : array {
		$reduce_values = function ( $values, $filter ) use ( &$reduce_values ) {
			$values[] = $filter->value;

			if ( ! empty( $filter->children ) ) {
				$values = array_merge( $values, array_reduce( $filter->children, $reduce_values, [] ) );
			}

			return $values;
		};

		$values = array_unique( array_reduce( $this->filters, $reduce_values, [] ) );

		return $values;
	}

	/**
	 * Calculate and get a flat list of explicitly checked filters
	 *
	 * @return array
	 */
	public function get_active_filters() : array {
		$reduce_active_filters = function ( $active_filters, $filter ) use ( &$reduce_active_filters ) {
			if ( $filter->active ) {
				$active_filters[] = $filter;
			}

			if ( ! empty( $filter->children ) ) {
				$active_filters = array_reduce( $filter->children, $reduce_active_filters, $active_filters );
			}

			return $active_filters;
		};

		$this->active_filters = array_reduce( $this->filters, $reduce_active_filters, [] );

		return $this->active_filters;
	}

	/**
	 * return the current item in the iterator
	 *
	 * @return mixed
	 */
	public function current() : mixed {
		return $this->filters[ $this->position ];
	}

	/**
	 * return the current index in the interator
	 *
	 * @return mixed
	 */
	public function key() : mixed {
		return $this->position;
	}

	/**
	 * move to the next item in the filters storage
	 *
	 * @return void
	 */
	public function next() : void {
		$this->position++;
	}

	/**
	 * reset the iterator to the beginning
	 *
	 * @return void
	 */
	public function rewind() : void {
		$this->position = 0;
	}

	/**
	 * Is the current index a valid item
	 *
	 * @return bool
	 */
	public function valid() : bool {
		return isset( $this->filters[ $this->position ] );
	}
}
