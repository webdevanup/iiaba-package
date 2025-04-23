<?php
/**
 * @file
 *
 * Base class for migrating terms from Drupal 7.
 */

namespace WDG\Migrate\Source\D7;

use WDG\Migrate\Source\D7\D7SourceBase;
use WDG\Migrate\Output\OutputInterface;

class TermSource extends D7SourceBase {

	CONST FIELDS = [
		'tid' => [
			'type'   => 'key',
			'column' => 'tid',
		],
		'vid' => [
			'type'   => 'term',
			'column' => 'vid',
		],
		'name' => [
			'type'   => 'term',
			'column' => 'name',
		],
		'description' => [
			'type'   => 'term',
			'column' => 'description',
		],
		'format' => [
			'type'   => 'term',
			'column' => 'format',
		],
		'weight' => [
			'type'   => 'term',
			'column' => 'weight',
		],
		'hierarchy' => [
			'type'   => 'tax',
			'column' => 'hierarchy',
		],

		'alias' => [
			'type'   => 'alias',
			// 'column' => 'alias',
		],


		'path' => [
			'type'   => 'meta_field',
			'column' => 'path',
		],
		'redirect' => [
			'type'   => 'meta_field',
			'column' => 'redirect',
		],
		'xmlsitemap' => [
			'type'   => 'meta_field',
			'column' => 'xmlsitemap',
		],
		'metatags' => [
			'type'   => 'meta_field',
			'column' => 'metatags',
		],

	];

	/**
	 * Key field alias
	 * @var string
	 */
	protected $tid_field;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		// Base table is taxonomy_term_data
		$this->base = 'taxonomy_term_data';

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'key',
				 */
				case 'key':
					$options['column'] = 'tid';
					$this->tid_field   = $field;
					/**
					 * 'type' => 'node',
					 * 'column' => 'name',
					 */
				case 'term':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;
				/**
				 * 'type' => 'field',
				 * 'name' => 'field_title',
				 * 'column' => 'field_title_value', // Optional
				 */
				case 'field':
					$table = $this->entity_field_join( $options['name'] );
					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_value';
					}
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;

				/**
				 * To get file URIs
				 * 'type' => 'file_field',
				 * 'name' => 'field_issue_file',
				 * 'column' => 'field_issue_file_fid', // Optional
				 */
				case 'file_field':
					$field_table = $this->entity_field_join( $options['name'] );
					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_fid';
					}
					$file_table              = $this->managed_file_join( $field_table, $options['column'] );
					$this->columns[ $field ] = $this->column( $file_table, 'uri' );
					$this->file_fields[]     = $field;
					break;

				/**
				 * 'type' => 'alias',
				 */
				case 'alias':
					$this->columns[ $field ] = "(SELECT alias FROM {$this->table_prefix}url_alias WHERE source = CONCAT('taxonomy/term/', base.tid) ORDER BY pid DESC LIMIT 1)";
					break;
				/**
				 * 'type' => 'redirects',
				 */
				case 'redirects':
					// Split Regex: /<(.+?)>(?:,|$)/
					$this->columns[ $field ] = "(SELECT GROUP_CONCAT(CONCAT('<', source, '>')) FROM {$this->table_prefix}redirect WHERE redirect = CONCAT('taxonomy/term/', base.tid))";
					break;
				/**
				 * 'type' => 'subquery',
				 * 'subquery' => '(SELECT count(*) FROM ' . MIGRATE_DB_PREFIX . 'node_access)',
				 */
				case 'subquery':
					$this->columns[ $field ] = $options['subquery'];
					break;
			}

			// Wrap in group_concat
			if ( ! empty( $options['group_concat'] ) ) {
				$this->columns[ $field ] = 'GROUP_CONCAT(' . $this->columns[ $field ] . ')';
			}
		}

		// Vocabulary ( or type )
		foreach ( [ 'type', 'vocabulary' ] as $field ) {
			if ( ! empty( $arguments[ $field ] ) ) {

				if ( ! is_array( $arguments[ $field ] ) ) {
					$arguments[ $field ] = (array) $arguments[ $field ];
				}

				$vids = implode( "', '", $arguments[ $field ] );

				$alias = 'tv';
				$this->joins[ $alias ]  = "INNER JOIN {$this->table_prefix}taxonomy_vocabulary {$alias}";
				$this->joins[ $alias ] .= " ON {$alias}.vid = base.vid";
				$this->joins[ $alias ] .= " AND {$alias}.machine_name IN ('{$vids}')";

			}
		}

		// vid
		if ( ! empty( $arguments['vid'] ) ) {
			$this->where .= ' AND base.vid';
			if ( is_array( $arguments['vid'] ) ) {
				$this->where .= " IN ('" . implode( "', '", $arguments['vid'] ) . "')";
			} else {
				$this->where .= " = '" . $arguments['vid'] . "'";
			}

		}

		// Hierarchy
		$this->columns[ 'hierarchy' ] = $this->column( 'hierarchy', 'parent' );
		$this->joins[ 'hierarchy' ]  = "INNER JOIN {$this->table_prefix}taxonomy_term_hierarchy hierarchy";
		$this->joins[ 'hierarchy' ] .= " ON hierarchy.tid = base.tid";

		// Group by to prevent dups
		// Handle multiple fields with 'group_concat' option
		$this->group_by = 'base.tid';

		// For consistent ordering
		$this->order_by = 'hierarchy.parent, base.weight, base.tid';
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		if ( ! empty( $arguments['id'] ) ) {
			$this->where .= ' AND base.tid';
			if ( is_array( $arguments['id'] ) ) {
				$this->where .= " IN ('" . implode( "', '", array_map( 'esc_sql', $arguments['id'] ) ) . "')";
			} else {
				$this->where .= " = '" . esc_sql( $arguments['id'] ) . "'";
			}
		}

		parent::init( $arguments );
	}

	/**
	 * Drupal7 taxonomy term specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function entity_field_join( $field_name ) {
		$alias = $field_name;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			// Bundle not necessary because tids are unique
			$this->joins[ $alias ]  = "LEFT JOIN {$this->table_prefix}field_data_{$field_name} {$alias}";
			$this->joins[ $alias ] .= " ON {$alias}.entity_type = 'taxonomy_term'";
			$this->joins[ $alias ] .= " AND {$alias}.deleted = 0";
			$this->joins[ $alias ] .= " AND base.tid = {$alias}.entity_id";
			$this->joins[ $alias ] .= " AND base.tid = {$alias}.revision_id";
		}
		return $alias;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_keys() {
		return array_column( $this->results, 'tid' );
	}

	/**
	 * Drupal7 node specific helper for managed file join
	 *
	 * @param string $field_table Field table
	 * @param string $fid_column Field table column containing fid
	 * @return string $alias
	 */
	protected function managed_file_join( $field_table, $fid_column ) {
		$alias = 'file_managed_' . $fid_column;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}file_managed {$alias} ON {$field_table}.{$fid_column} = {$alias}.fid";
		}
		return $alias;
	}

}
