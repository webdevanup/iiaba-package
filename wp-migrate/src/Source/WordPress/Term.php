<?php

namespace WDG\Migrate\Source\WordPress;

class Term extends SourceBase {

	const FIELDS = [
		'term_id' => [
			'type'   => 'key',
			'column' => 'term_id',
		],

		'name' => [
			'type'   => 'term',
			'column' => 'name',
		],
		'slug' => [
			'type'   => 'term',
			'column' => 'slug',
		],

		'parent' => [
			'type'   => 'tax',
			'column' => 'parent',
		],

		'description' => [
			'type'   => 'tax',
			'column' => 'description',
		],
	];

	protected $base       = 'terms';
	protected $base_id    = 'term_id';

	protected $meta_id    = 'term_id';
	protected $meta_table = 'termmeta';

	protected $group_by   = "term_id";
	protected $order_by   = "term_id";

	protected $key_field  = "term_id";

	protected $taxonomy;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'key',
				 */
				case 'key':
					$options['column'] = 'term_id';
					// $this->tid_field   = $field;
				/**
				 * 'type' => 'term',
				 * 'column' => 'name',
				 */
				case 'term':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;
				/**
				 * 'type' => 'tax',
				 * 'column' => 'description',
				 */
				case 'tax':
					$this->columns[ $field ] = $this->column( 'tax', $options['column'] );
					break;
				/**
				 * 'type' => 'field',
				 * 'name' => 'field_title',
				 * 'column' => 'field_title_value', // Optional
				 */
				case 'meta_field':
					$value_field = 'meta_value';
					$table = $this->meta_join( $field );
					$this->columns[ $field ] = $this->column( $table, $value_field );
				/**
				 * 'type' => 'field',
				 * 'name' => 'field_title',
				 * 'column' => 'field_title_value', // Optional
				 */
				// case 'field':
				// 	$table = $this->entity_field_join( $options['name'] );
				// 	if ( empty( $options['column'] ) ) {
				// 		$options['column'] = $options['name'] . '_value';
				// 	}
				// 	$this->columns[ $field ] = $this->column( $table, $options['column'] );
				// 	break;

			}

			// Wrap in group_concat
			if ( ! empty( $options['group_concat'] ) ) {
				$this->columns[ $field ] = 'GROUP_CONCAT(' . $this->columns[ $field ] . ')';
			}
		}


		// Tax
		if ( ! empty( $arguments['taxonomy'] ) ) {
			$this->taxonomy = $arguments['taxonomy'];
		}

		$tax = $this->taxonomy;
		if ( ! is_array( $tax ) ) {
			$tax = (array) $tax;
		}

		$taxes = implode( "', '", $tax );

		$alias = 'tax';
		$this->joins[ $alias ]  = "INNER JOIN {$this->table_prefix}term_taxonomy {$alias}";
		$this->joins[ $alias ] .= " ON {$alias}.term_id = base.term_id";
		$this->joins[ $alias ] .= " AND {$alias}.taxonomy IN ('{$taxes}')";


		// // vid
		// if ( ! empty( $arguments['vid'] ) ) {
		// 	$this->where .= ' AND base.vid';
		// 	if ( is_array( $arguments['vid'] ) ) {
		// 		$this->where .= " IN ('" . implode( "', '", $arguments['vid'] ) . "')";
		// 	} else {
		// 		$this->where .= " = '" . $arguments['vid'] . "'";
		// 	}

		// }



		$this->columns[ 'description' ] = $this->column( 'tax', 'description' );


	}


	/**
	 * Count sql query results
	 * @return string
	 */
	protected function count_query() {
		$sql = "SELECT\n";

		$columns = array();
		foreach ( $this->columns as $alias => $column ) {
			if ( strpos( $column, '`base`' ) === false ) {
				continue;
			}
			$columns[] = "{$column} AS {$alias}";
		}
		$sql .= implode(",\n", $columns) . "\n";

		$sql .= "FROM\n";
		$sql .= "{$this->table_prefix}{$this->base} base\n";

		$sql .= $this->joins['tax'];
		$sql .= "\n";

		if ( !empty($this->where) ) {
			$sql .= "WHERE 1=1 {$this->where}\n";
		}

		$this->output->debug($sql);

		return $sql;
	}

	/**
	 * Build sql query
	 * @return string
	 */
	protected function build_query() {

		$query = parent::build_query();

		return $query;

	}

}
