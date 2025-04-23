<?php

namespace WDG\Facets\Native;

use WDG\Facets\Provider\Native;
use WP_Query;

/**
 * The indexer handles the batch process of indexing items in bulk
 *
 * @package wdgdc/wikit-facets
 */
class Indexer {

	/**
	 * The key the indexer stores it's temporary results in
	 *
	 * @var string
	 */
	const OPTION_KEY = 'facets_batch_indexer';

	/**
	 * Default query args for a batch query
	 *
	 * @var array
	 */
	protected $defaults = [
		'order'          => 'ASC',
		'orderby'        => 'ID',
		'paged'          => 0,
		'post__in'       => null,
		'post_type'      => null,
		'posts_per_page' => 100,
	];

	/**
	 * Holds the options loaded from the database
	 *
	 * @var array
	 */
	protected array $options = [];

	public function __construct(
		protected Index $index,
		protected Native $provider,
	) {
		$this->options = get_option( static::OPTION_KEY, [] );

		if ( ! empty( $this->options ) ) {
			$this->options = array_merge( $this->defaults, $this->options );
		} else {
			$this->options = $this->defaults;
		}

		if ( ! empty( $props ) ) {
			$this->options = array_merge( $this->options, $props );
		}

		if ( empty( $this->options['post_type'] ) ) {
			$this->options['post_type'] = array_values( get_post_types( [ 'public' => true ] ) );
		}
	}

	/**
	 * Set the page to 0 and call next
	 *
	 * @return array
	 */
	public function start() : array {
		$this->options['paged'] = 0;

		return $this->next();
	}

	/**
	 * Process the batch and return stats for the process
	 *
	 * @return array
	 */
	public function next() : array {
		$this->options['paged']++;

		$query = new WP_Query( $this->options );
		$total = $query->found_posts;

		if ( $total < 1 ) {
			$this->delete_option();

			return [
				'page'      => 0,
				'remaining' => 0,
				'total'     => $this->index->stats()['total'],
			];
		}

		$start = microtime( true );

		foreach ( $query->posts as $post ) {
			$result = new Post( $post->ID, $this->provider );
			$result->index();

			$indexed[] = $result;
		}

		$duration = microtime( true ) - $start;

		$results = [
			'page'     => $this->options['paged'],
			'max_page' => $query->max_num_pages,
			'complete' => $this->options['paged'] >= $query->max_num_pages,
			'duration' => $duration,
			'total'    => $this->index->stats()['total'],
		];

		if ( $results['complete'] ) {
			$this->delete_option();
			delete_option( 'facets_index_required' );
		} else {
			$this->save_option();
		}

		return $results;
	}

	/**
	 * Does the options indicate that the current batch is active or not
	 *
	 * @return bool
	 */
	public function active() : bool {
		return ! empty( $this->options ) && $this->options['paged'] > 0;
	}

	/**
	 * Update the option in the database with the current mutated options state
	 *
	 * @return bool
	 */
	public function save_option() {
		return update_option( static::OPTION_KEY, $this->options, false );
	}

	/**
	 * Delete the option the database (usually after all batches are complete)
	 *
	 * @return bool
	 */
	public function delete_option() {
		unset( $this->options );

		return delete_option( static::OPTION_KEY );
	}

	/**
	 * Get the current option state (potentially mutated)
	 *
	 * @return array
	 */
	public function get_option() : array {
		return (array) $this->options;
	}
}
