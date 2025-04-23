<?php

namespace WDG\Migrate\Source\D6;

use WDG\Migrate\Source\SourceBase;
use WDG\Migrate\Output\OutputInterface;

class D6SourceBase extends SourceBase {

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

	protected $base_url;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments );

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = $arguments['base_url'];
		}

		$this->table_prefix = MIGRATE_DB_PREFIX;
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( $arguments ) {
		if ( ! empty( $arguments['limit'] ) ) {
			$this->limit = $arguments['limit'] . ( ! empty( $arguments['offset'] ) ? ' OFFSET ' . $arguments['offset'] : '' );
		}

		// Group by to prevent dups
		// Handle multiple fields with 'group_concat' option
		$this->group_by = 'base.nid';

		// Connect to DB
		$this->db_connect();

		// Build query
		$query = $this->build_query();
		//echo $query; exit();

		// Get results to iterate over
		$this->results = $this->db->get_results( $query );
		$this->rewind();

		return 'Queried ' . $this->count() . ' records.';
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
	public function current() {
		return $this->results[ $this->index ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() {
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
	}
}
