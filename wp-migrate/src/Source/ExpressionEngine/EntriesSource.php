<?php
/**
 * @file
 *
 * Base class for migrating content for TechnoServe.
 */

namespace WDG\Migrate\Source\ExpressionEngine;

class EntriesSource extends \WDG\Migrate\Source\SourceBase {

	// Database
	protected $db;
	protected $index   = 0;
	protected $results = [];

	// Query building variables
	protected $table_prefix = 'exp';
	protected $base         = 'channel_titles';
	protected $columns      = [];
	protected $joins        = [];
	protected $where        = '';
	protected $group_by     = '';
	protected $order_by     = 'title';
	protected $limit        = '';

	// ExpressionEngine Site
	protected $site_id = 1;

	// ExpressionEngine Channel
	protected $channel;

	// ExpressionEngine Fields
	protected $fields = [];

	// ExpressionEngine Pages
	protected $pages = [];

	// ExpressionEngine Templates
	protected $templates = [];

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

	public function get_site_pages() {

		if ( $this->pages ) {
			return $this->pages;
		}

		$query = "SELECT s.site_pages
			FROM exp_sites s
			WHERE s.site_id = '{$this->site_id}';";

		$var = $this->db->get_var( $query );

		// dd($var);

		if ( ! $var ) {
			return false;
		}

		$pages = unserialize( base64_decode( $var ) );

		$this->pages = $pages[ $this->site_id ];

		return $pages[ $this->site_id ];

	}

	public function get_templates() {

		if ( $this->templates ) {
			return $this->templates;
		}

		$query = "SELECT t.template_id, concat(g.group_name, '/', t.template_name) name
			FROM exp_templates t
			JOIN exp_template_groups g ON t.group_id = g.group_id
			WHERE t.site_id = {$this->site_id};";

		$templates = $this->db->get_results( $query );

		if ( ! $templates ) {
			return false;
		}

		foreach ( $templates as $key => $value ) {
			$this->templates[ $value->template_id ] = $value->name;
		}

		return $this->templates;
	}

	public function get_channel_fields() {

		$query = "SELECT c.channel_name, f.*
			FROM exp_channel_fields f
			JOIN exp_channels c ON c.field_group = f.group_id
			WHERE c.channel_name = '{$this->channel}'
			AND c.site_id = {$this->site_id};";

		$fields = $this->db->get_results( $query );

		if ( ! $fields ) {
			return false;
		}

		foreach ( $fields as $field ) {

			$this->columns[ $field->field_name ]           = $this->column( 'data', "field_id_{$field->field_id}" );
			$this->fields[ "field_id_{$field->field_id}" ] = $field->field_name;
		}

		$this->joins[] = 'JOIN exp_channel_data data on data.entry_id = base.entry_id';

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

		//  get all channel fields
		$this->get_channel_fields();

		// get site pages
		$this->get_site_pages();

		// limit to selected channel
		$this->joins[] = 'JOIN exp_channels channels on channels.channel_id = base.channel_id';
		$this->where  .= " AND channels.channel_name = '{$this->channel}'";

		$this->where .= " AND base.site_id = {$this->site_id}";

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
		$this->results = [];
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

		$columns = [];
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
