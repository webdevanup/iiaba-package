<?php
/**
 * @file
 *
 * Base class for migrating terms from Drupal 7.
 */

namespace WDG\Migrate\Source\D8;

class TermSource extends \WDG\Migrate\Source\D8\D8SourceBase {

	/**
	 * Key field alias
	 * @var string
	 */
	protected $tid_field;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
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
				 */
				case 'term_data':
					$table = $this->term_data_join( $options['name'] );

					$this->columns[ $field ] = $this->column( $table, $options['name'] );
					break;
				/**
				 * 'type' => 'term_parent',
				 */
				case 'term_parent':
					$table             = $this->term_parent_join( $options['name'] );
					$options['column'] = 'parent_target_id';

					$this->columns[ $field ] = $this->column( $table, $options['column'] );
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

		if ( ! empty( $arguments['taxonomy'] ) ) {
			$this->where = "AND base.vid = '{$arguments['taxonomy']}'";
		}

		// Group by to prevent dups
		// Handle multiple fields with 'group_concat' option
		$this->group_by = 'base.tid';

		// For consistent ordering
		$this->order_by = 'base.tid';
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
	 * Drupal8 taxonomy term specific helper function for entity field join
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
	 * Drupal8 taxonomy term specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function term_data_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = sprintf(
				'LEFT JOIN %1$staxonomy_term_field_data %3$s ON base.tid = %3$s.tid AND base.revision_id = %3$s.revision_id',
				$this->table_prefix,
				$field_name,
				$alias
			);
		}

		return $alias;
	}

	/**
	 * Drupal8 taxonomy term specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function term_parent_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = sprintf(
				'LEFT JOIN %1$staxonomy_term__parent %3$s ON base.tid = %3$s.entity_id AND base.revision_id = %3$s.revision_id',
				$this->table_prefix,
				$field_name,
				$alias
			);
		}

		return $alias;
	}


	/**
	 * Return array of keys
	 * @return array
	 */
	public function get_keys() {
		return array_column( $this->results, $this->tid_field );
	}

}
