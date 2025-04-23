<?php
/**
 * @file
 *
 * Base class for migrating content from Drupal 7.
 */

namespace WDG\Migrate\Source\D7;

use WDG\Migrate\Source\SourceBase;
use WDG\Migrate\Output\OutputInterface;

abstract class D7SourceBase extends SourceBase {

	// Database
	protected $db;
	protected $index   = 0;
	protected $results = array();

	// Query building variables
	protected $table_prefix = '';
	protected $base         = '';
	protected $columns      = array();
	protected $joins        = array();
	protected $where        = '';
	protected $group_by     = '';
	protected $order_by     = '';
	protected $limit        = '';

	// Drupal settings
	protected $stream_wrappers; // Defaults in __construct()
	protected $base_url = '';

	// arguments
	protected $arguments = [];

	/**
	 * File field aliases (requires post-processing)
	 * @var array
	 */
	protected $file_fields = array();

	/**
	 * Media WYSIWYG field aliases (requires post-processing)
	 * @var array
	 */
	protected $media_wysiwyg_fields = array();

	/**
	 * Metatag field alias (requires post-processing)
	 * @var string
	 */
	protected $metatag_field;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		$this->arguments = $arguments;
		parent::__construct( $arguments, $output );

		// Stream wrapper defaults
		$this->stream_wrappers = array(
			'public' => trailingslashit( 'sites/default/files' ),
			'private' => trailingslashit( 'sites/default/files/private' ),
		);

		// Initialize drupal settings
		$this->get_drupal_settings();

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = trailingslashit( $arguments['base_url'] );
		}

		$this->table_prefix = MIGRATE_DB_PREFIX;
	}

	public function get_stream_wrappers() {
		return $this->stream_wrappers;
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		// Source by "id" not supported at this level

		// Apply limit and offset
		if ( ! empty( $arguments['limit'] ) ) {
			$this->limit = $arguments['limit'] . ( ! empty( $arguments['offset'] ) ? ' OFFSET ' . $arguments['offset'] : '' );
		} elseif ( ! empty( $arguments['offset'] ) ) {
			$this->limit = $arguments['offset'] . ',' . PHP_INT_MAX;
		}

		// Connect to DB
		$this->db_connect();

		// Build query
		$query = $this->build_query();
		$this->output->debug( "\n" . $query, 'Source Query' );

		// Get results to iterate over
		$this->results = $this->db->get_results( $query );
		$this->rewind();

		$this->output->progress( 'Queried ' . $this->count() . ' records.', null, 1 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function count() : int {
		return count( $this->results );
	}

	/**
	 * {@inheritdoc}
	 */
	public function current() : mixed {
		return $this->results[ $this->index ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() : mixed {
		return $this->index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function next() : void {
		++$this->index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewind() : void {
		$this->index = 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function valid() : bool {
		return isset( $this->results[ $this->index ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() {
		$this->results = array();
		$this->rewind();
	}

	/**
	 * Helper column reference
	 * @param string $table
	 * @param string $column
	 * @return string
	 */
	protected function column( $table, $column ) {
		return "{$table}.{$column}";
	}

	/**
	 * Build sql query
	 * @return string
	 */
	protected function build_query() {
		$sql = "SELECT\n";

		$columns = array();
		foreach ( $this->columns as $alias => $column ) {
			$columns[] = "{$column} AS {$alias}";
		}
		$sql .= implode( ",\n", $columns ) . "\n";

		$sql .= "FROM\n";
		$sql .= "{$this->table_prefix}{$this->base} base\n";
		$sql .= implode( "\n", $this->joins );
		$sql .= "\n";

		if ( ! empty( $this->where ) ) {
			$sql .= "WHERE 1=1 {$this->where}\n";
		}

		if ( ! empty( $this->group_by ) ) {
			$sql .= "GROUP BY {$this->group_by}\n";
		}

		if ( ! empty( $this->order_by ) ) {
			$sql .= "ORDER BY {$this->order_by}\n";
		}

		if ( ! empty( $this->limit ) ) {
			$sql .= "LIMIT {$this->limit}\n";
		}

		return $sql;
	}

	/**
	 * Connect to external DB
	 */
	protected function db_connect() {
		if ( isset( $this->db ) ) {
			return;
		}

		$this->db = new \wpdb( MIGRATE_DB_USER, MIGRATE_DB_PASSWORD, MIGRATE_DB_NAME, MIGRATE_DB_HOST );

		$this->output->debug( 'Connected to database: ' . MIGRATE_DB_NAME );
	}

	protected function get_drupal_settings() {
		// Connect to DB
		$this->db_connect();

		// Get stream_wrapper variables
		$variables = $this->db->get_results(
			"
			SELECT name, value
			FROM {$this->table_prefix}variable
			WHERE name IN ('file_public_path', 'file_private_path')
		"
		);

		foreach ( $variables as $row ) {
			switch ( $row->name ) {
				case 'file_public_path':
					$this->stream_wrappers['public'] = trailingslashit( maybe_unserialize( $row->value ) );
					break;
				case 'file_private_path':
					$this->stream_wrappers['private'] = trailingslashit( maybe_unserialize( $row->value ) );
					break;
			}
		}

		$this->output->debug( 'Stream Wrappers: ' . print_r( $this->stream_wrappers, true ), 'Drupal Settings' );
	}
}
