<?php
/**
 * @file
 *
 * Base class for migrating content for TechnoServe.
 */

namespace WDG\Migrate\Source\ExpressionEngine;

class UserSource extends \WDG\Migrate\Source\SourceBase {

	// Database
	protected $db;
	protected $index   = 0;
	protected $results = array();

	// Query building variables
	protected $table_prefix = 'exp';
	protected $base         = 'members';
	protected $columns      = array();
	protected $joins        = array();
	protected $where        = '';
	protected $group_by     = '';
	protected $order_by     = 'username';
	protected $limit        = '';

	protected $key_field = 'member_id';


	// ExpressionEngine Site
	protected $site_id = 1;

	// ExpressionEngine Channel
	protected $channel;

	// ExpressionEngine Fields
	protected $fields = array();

	protected $base_url = '';

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		if ( ! empty( $arguments['site_id'] ) ) {
			$this->site_id = $arguments['site_id'];
		}

		if ( ! empty( $arguments['channel'] ) ) {
			$this->channel = $arguments['channel'];
		}

		if ( ! empty( $arguments['order_by'] ) ) {
			$this->order_by = $arguments['order_by'];
		}

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = trailingslashit( $arguments['base_url'] );
		}

		$this->table_prefix = MIGRATE_DB_PREFIX;

		foreach ( $arguments['fields'] as $field => $options ) {

			if ( ! empty( $options['type'] ) && 'key' === $options['type'] ) {
				$this->key_field = $options['column'];
			}

			$this->columns[ $field ] = $this->column( 'base', $options['column'] );

		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {

		if ( ! empty( $arguments['id'] ) ) {
			$this->where .= " AND `base`.`{$this->key_field}`";
			if ( is_array( $arguments['id'] ) ) {
				$this->where .= " IN ('" . implode( "', '", array_map( 'esc_sql', $arguments['id'] ) ) . "')";
			} else {
				$this->where .= " = '" . esc_sql( $arguments['id'] ) . "'";
			}
		}

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

		// var_dump($this->results); exit;
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
		return "`{$table}`.`{$column}`";
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
			$sql .= "GROUP BY `{$this->group_by}`\n";
		}

		if ( ! empty( $this->order_by ) ) {
			$sql .= "ORDER BY `{$this->order_by}`\n";
		}

		if ( ! empty( $this->limit ) ) {
			$sql .= "LIMIT {$this->limit}\n";
		}

		// echo $sql; exit;

		return $sql;
	}

	public function get_ids() {
		return array_map(
			function( $row ) {
				return $row->id;
			},
			$this->results
		);
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
}
