<?php

namespace WDG\Migrate\Source\D6;

use WDG\Migrate\Source\D6\D6SourceBase;

class NodeSource extends D6SourceBase {

	/**
	 * {@inheritdoc}
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		// Base table is node
		$this->base = 'node';

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'node',
				 * 'column' => 'status',
				 */
				case 'key':
					$options['column'] = 'nid';
				case 'node':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;
				/**
				 * 'type' => 'node_revision',
				 * 'column' => 'body',
				 */
				case 'node_revision':
					$table                   = $this->node_revisions_join();
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'content_type',
				 * 'content_type' => 'aerosafety_world_issue',
				 * 'column' => 'field_read_issue_link_url',
				 */
				case 'content_type':
					$table                   = $this->content_type_join( $options['content_type'] );
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'content_field',
				 * 'content_field' => 'issue_volume',
				 * 'column' => 'field_issue_volume_value',
				 */
				case 'content_field':
					$table                   = $this->content_field_join( $options['content_field'] );
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'content_field_file',
				 * 'content_field' => 'issue_file',
				 * 'column' => 'field_issue_file_fid',
				 */
				case 'content_field_file':
					$field_table             = $this->content_field_join( $options['content_field'] );
					$file_table              = $this->file_join( $field_table, $options['column'] );
					$this->columns[ $field ] = "CONCAT('" . $this->base_url . "', " . $this->column( $file_table, 'filepath' ) . ')';
					break;
				/**
				 * 'type' => 'term',
				 * 'vocabulary' => 7 (optional)
				 */
				case 'term':
					$table                   = $this->term_join( isset( $options['vocabulary'] ) ? $options['vocabulary'] : null );
					$this->columns[ $field ] = $this->column( $table, 'tid' );
					break;
				/**
				 * 'type' => 'alias',
				 */
				case 'alias':
					$this->columns[ $field ] = "(SELECT dst FROM {$this->table_prefix}url_alias WHERE src = CONCAT('node/', base.nid) ORDER BY pid DESC LIMIT 1)";
					break;
				/**
				 * 'type' => 'menu_parent',
				 */
				case 'menu_parent':
					$this->columns[ $field ] = "(SELECT REPLACE(link_path, 'node/', '') FROM dp_menu_links WHERE mlid = (SELECT plid FROM dp_menu_links WHERE link_path = CONCAT('node/', base.nid) LIMIT 1))";
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

		// Type
		if ( ! empty( $arguments['type'] ) ) {
			if ( is_array( $arguments['type'] ) ) {
				$this->where .= " AND base.type IN ('" . implode( "', '", $arguments['type'] ) . "')";
			} else {
				$this->where .= " AND base.type = '" . $arguments['type'] . "'";
			}
		}

		// Published
		if ( ! empty( $arguments['published'] ) ) {
			$this->where .= ' AND base.status = 1';
		}

		$this->order = 'base.nid';
	}

	/**
	 * Drupal6 node revisions table join
	 * @return string $alias
	 */
	protected function node_revisions_join() {
		$alias = 'node_revisions';
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}node_revisions {$alias} ON base.nid = {$alias}.nid AND base.vid = {$alias}.vid";
		}
		return $alias;
	}

	/**
	 * Drupal6 node specific helper function for content type join
	 * @param string $content_type
	 * @return string $alias
	 */
	protected function content_type_join( $content_type ) {
		$alias = 'content_type_' . $content_type;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}content_type_{$content_type} {$alias} ON base.nid = {$alias}.nid AND base.vid = {$alias}.vid";
		}
		return $alias;
	}

	/**
	 * Drupal6 node specific helper function for content field join
	 * @param string $content_field
	 * @return string $alias
	 */
	protected function content_field_join( $content_field ) {
		$alias = 'content_field_' . $content_field;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}content_field_{$content_field} {$alias} ON base.nid = {$alias}.nid AND base.vid = {$alias}.vid";
		}
		return $alias;
	}

	/**
	 * Drupal6 node specific helper for managed file join
	 * @param string $table Field table
	 * @param string $fid_column Field table column containing fid
	 * @return string $alias
	 */
	protected function file_join( $table, $fid_column ) {
		$alias = 'file_' . $fid_column;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}files {$alias} ON {$table}.{$fid_column} = {$alias}.fid";
		}
		return $alias;
	}

	/**
	 * Drupal6 node specific helper for taxonomy term join
	 * @param int $vocabulary VID
	 * @return string $alias
	 */
	protected function term_join( $vocabulary = null ) {
		$alias = 'term_node' . ( $vocabulary ? '_' . $vocabulary : '' );
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}term_node {$alias} ON base.nid = {$alias}.nid AND base.vid = {$alias}.vid";
			if ( $vocabulary ) { // Require certain vocabulary
				$this->joins[ $alias . '_data' ] = "INNER JOIN {$this->table_prefix}term_data {$alias}_data ON {$alias}.tid = {$alias}_data.tid AND {$alias}_data.vid = {$vocabulary}";
			}
		}
		return $alias;
	}
}
