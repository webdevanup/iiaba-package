<?php

namespace WDG\Facets\Native;

use Exception;
use WDG\Facets\Provider\Native;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Formatter;
use WP_Query;

use function WP_CLI\Utils\make_progress_bar;

/**
 * Manage the WDG native faceting index
 *
 * @package wdgdc/
 */
class Command extends WP_CLI_Command {

	public function __construct(
		protected Native $provider,
	) {
		// no-op
	}

	/**
	 * Create the database index
	 *
	 * @subcommand create
	 */
	public function create( array $args = [], array $assoc_args = [] ) : void {
		$index = new Index();

		try {
			$result = $index->create( $assoc_args['force'] ?? false );

			if ( ! $result ) {
				throw new Exception( 'Error creating index' );
			}

			WP_CLI::success( sprintf( 'Index table created: %s', $index->table ) );
		} catch ( \Throwable $fault ) {
			WP_CLI::error( $fault->getMessage() );
		}
	}

	/**
	 * Delete the database index
	 *
	 * @subcommand delete
	 */
	public function delete( array $args = [], array $assoc_args = [] ) : void {
		$index = new Index();
		$index->drop();

		if ( $index->exists() ) {
			WP_CLI::error( 'Error deleting index' );
		}

		WP_CLI::success( 'Index deleted' );
	}

	/**
	 * Truncate the database index
	 *
	 * @subcommand truncate
	 */
	public function truncate( array $args = [], array $assoc_args = [] ) : void {
		$index = new Index();

		$truncated = $index->truncate();

		if ( ! $truncated ) {
			WP_CLI::error( 'Error truncating index' );
		}

		WP_CLI::success( 'Index truncated' );
	}

	/**
	 * Index a post or all posts
	 *
	 * [<post_id>...]
	 * : Post ids to index
	 *
	 * [--progress=<progress>]
	 * : Format progress as a bar or a log
	 * ---
	 * default: bar
	 * options:
	 *   - ''
	 *   - bar
	 *   - log
	 * ---
	 *
	 * [--post-type=<post-type>]
	 * : Only index post types

	 * [--batch=<batch>]
	 * : batch size
	 * ---
	 * default: 500
	 * ---
	 */
	public function index( array $args = [], array $assoc_args = [] ) : void {
		global $wp_object_cache, $wpdb;

		$query_args = [
			'posts_per_page' => (int) $assoc_args['batch'],
			'paged'          => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		if ( ! empty( $assoc_args['post-type'] ) ) {
			$query_args['post_type'] = $assoc_args['post-type'];
		} else {
			$query_args['post_type'] = array_values( get_post_types( [ 'public' => true ] ) );
		}

		if ( ! empty( $args ) ) {
			$query_args['post__in'] = array_filter( array_map( 'intval', $args ) );
		}

		$query = new WP_Query( $query_args );

		if ( ! empty( $assoc_args['progress'] ) ) {
			$msg = sprintf( 'Indexing %d posts', $query->found_posts );

			switch ( $assoc_args['progress'] ) {
				case 'bar':
					$progress_bar = make_progress_bar( $msg, $query->found_posts );
					break;
				case 'log':
					WP_CLI::line( $msg );
					break;
			}
		}

		$index_start = microtime( true );

		while ( ! empty( $query->found_posts ) ) {
			foreach ( $query->posts as $post ) {
				$result = new Post( $post->ID, $this->provider );
				$result->index();

				WP_CLI::debug( print_r( $post, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( ! empty( $assoc_args['progress'] ) ) {
					$msg = sprintf( 'Indexed ID: %d', $post->ID );

					switch ( $assoc_args['progress'] ) {
						case 'bar':
							$progress_bar->tick( 1, $msg );
							break;
						case 'log':
							WP_CLI::line( $msg );
							break;
					}
				}

				$indexed[] = $result;
			}

			$wpdb->queries                   = [];
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->memcache_debug = [];
			$wp_object_cache->cache          = [];

			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}

			$query_args['paged']++;

			$query = new \WP_Query( $query_args );
		}

		if ( ! empty( $assoc_args['progress'] ) ) {
			$msg = sprintf( 'Indexing Complete in %d seconds', number_format( microtime( true ) - $index_start, 4 ) );

			switch ( $assoc_args['progress'] ) {
				case 'bar':
					$progress_bar->finish( $msg );
					break;
				case 'log':
					WP_CLI::line();
					WP_CLI::success( $msg );
					break;
			}
		}

		delete_option( 'facets_index_required' );
	}

	/**
	 * Display statistics for the facets index
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: 'table'
	 * options:
	 *   - ''
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Render output in a particular format.
	 * ---
	 * default: 'total,types'
	 * ---
	 */
	public function stats( $args = [], $assoc_args = [] ) : void {
		$index = new Index();

		$formatter = new Formatter( $assoc_args );
		$formatter->display_item( $index->stats() );
	}
}
