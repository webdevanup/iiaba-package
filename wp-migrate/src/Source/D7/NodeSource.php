<?php
/**
 * @file
 *
 * Base class for migrating nodes from Drupal 7.
 */

namespace WDG\Migrate\Source\D7;

use WDG\Migrate\Source\D7\D7SourceBase;
use WDG\Migrate\Output\OutputInterface;

class NodeSource extends D7SourceBase {

	CONST FIELDS = [
		'nid' => [
			'type'   => 'key',
			'column' => 'nid',
		],
		'type' => [
			'type'   => 'node',
			'column' => 'type',
		],
		'title' => [
			'type' => 'node',
			'column' => 'title',
		],
		'created' => [
			'type'   => 'node',
			'column' => 'created',
		],
		'changed' => [
			'type'   => 'node',
			'column' => 'changed',
		],
		'alias' => [
			'type' => 'alias',
		],
		'status' => [
			'type'   => 'node',
			'column' => 'status',
		],

		'body' => [
			'type'   => 'custom_table',
			'table'  => 'field_data_body',
			'column' => 'body_value',
		],

		'summary' => [
			'type'  => 'custom_table',
			'table' => 'field_data_body',
			'column' => 'body_summary',
		],
	];

	/**
	 * Key field alias
	 * @var string
	 */
	protected $nid_field;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		// Base table is node
		$this->base = 'node';

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'key',
				 */
				case 'key':
					$options['column'] = 'nid';
					$this->nid_field   = $field;
					/**
					 * 'type' => 'node',
					 * 'column' => 'status',
					 */
				case 'node':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;
				/**
				 * 'type' => 'body',
				 */
				case 'body':
					$table                   = $this->entity_field_join( 'body' );
					$this->columns[ $field ] = $this->column( $table, 'body_value' );
					break;
				/**
				 * 'type' => 'summary'
				 */
				case 'summary':
					$table                   = $this->entity_field_join( 'body' );
					$this->columns[ $field ] = $this->column( $table, 'body_summary' );
					break;
				/**
				 * 'type' => 'media_wysiwyg_field',
				 * 'name' => 'field_issue_wysiwyg',
				 * 'column' => 'field_issue_wysiwyg_value', // Optional
				 */
				case 'media_wysiwyg_field':
					// Pass-through to field, marking for post-processing
					$this->media_wysiwyg_fields[] = $field;
				/**
				 * 'type' => 'field',
				 * 'name' => 'field_issue_volume',
				 * 'column' => 'field_issue_volume_value', // Optional
				 */
				case 'field':
					$table = $this->entity_field_join( $options['name'] );
					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_value';
					}
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'link_field',
				 * 'name' => 'field_url',
				 */
				case 'link_field':
					$this->link_fields[]     = $field;
					$this->columns[ $field ] = "(SELECT JSON_OBJECT(
						'url', {$options['name']}_url,
						'title', {$options['name']}_title,
						'attr', {$options['name']}_attributes
						) FROM {$this->table_prefix}field_data_{$options['name']}
						WHERE entity_type = 'node' AND entity_id = base.nid)";
					break;
				/**
				 * To get term names
				 * 'type'   => 'term_field',
				 * 'name'   => 'field_issue_tags',
				 * 'column' => 'field_issue_tags_tid', // Optional
				 * 'ids'    => true, // Optional - collect tids
				 */
				case 'term_field':
					$field_table = $this->entity_field_join( $options['name'] );
					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_tid';
					}

					$term_table             = $this->taxonomy_term_data_join( $field_table, $options['column'] );
					$this->columns[ $field ] = $this->column( $term_table, 'name' );
					if ( ! empty( $options['ids'] ) ) {
						$this->columns[ $field . '_tid' ] = $this->column( $term_table, 'tid' );
					}

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
				 * 'type' => 'metatag'
				 */
				case 'metatag':
					$table                   = $this->metatag_join();
					$this->columns[ $field ] = $this->column( $table, 'data' );
					$this->metatag_field     = $field;
					break;
				/**
				 * 'type' => 'alias',
				 */
				case 'alias':
					$this->columns[ $field ] = "(SELECT alias FROM {$this->table_prefix}url_alias WHERE source = CONCAT('node/', base.nid) ORDER BY pid DESC LIMIT 1)";
					break;
				/**
				 * 'type' => 'redirects',
				 */
				case 'redirects':
					// Split Regex: /<(.+?)>(?:,|$)/
					$this->columns[ $field ] = "(SELECT GROUP_CONCAT(CONCAT('<', source, '>')) FROM {$this->table_prefix}redirect WHERE redirect = CONCAT('node/', base.nid))";
					break;

				// /**
				//  * 'type' => 'menu_parent',
				//  */
				// case 'menu_parent':
				// 	$this->columns[$field] = "(SELECT REPLACE(link_path, 'node/', '') FROM dp_menu_links WHERE mlid = (SELECT plid FROM dp_menu_links WHERE link_path = CONCAT('node/', base.nid) LIMIT 1))";
				// break;

				/**
				 * 'type' => 'subquery',
				 * 'subquery' => '(SELECT count(*) FROM ' . MIGRATE_DB_PREFIX . 'node_access)',
				 */
				case 'subquery':
					$this->columns[ $field ] = $options['subquery'];
					break;

				/**
				 * 'type' => 'field_collection',
				 * 'name' => 'field_collection_name',
				 * 'fields' => [
				 *   'sub_field_name' => 'field_column'
				 * ]
				 */
				case 'field_collection':
					$options['group_concat'] = true;
					$options['distinct']     = true;
					$table                   = $this->entity_field_collection_join( $options['name'] );

					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_value';
					}

					$this->columns[ $field ] = $this->column( $table, $options['column'] );

					$this->collection_fields[ $field ] = $options;
					break;

				/**
				 * 'type' => 'field_collection',
				 * 'name' => 'field_collection_name',
				 * 'fields' => [
				 *   'alias' => 'sub_field_name'
				 * ]
				 */
				case 'paragraph':
					$options['group_concat'] = true;
					$options['distinct']     = true;
					$table                   = $this->entity_paragraphs_join( $options['name'] );

					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_value';
					}

					$this->columns[ $field ] = $this->column( $table, $options['column'] );

					$this->collection_fields[ $field ] = $options;
					break;

				/**
				 * 'type' => 'custom_table',
				 */
				case 'custom_table':
					$table                   = $this->custom_table_join( $options['table'] );
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;

				/**
				 * 'type' => 'entityreference',
				 * 'name'   => 'field_ref_programs',
				 * 'column' => 'field_ref_programs_target_id',
				 */
				case 'entityreference':
					$options['group_concat'] = true;
					$options['distinct']     = true;
					$table                   = $this->entity_field_join( $options['name'] );
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;

			}

			// Wrap in group_concat
			if ( ! empty( $options['group_concat'] ) ) {
				$distinct                = ! empty( $options['distinct'] ) && $options['distinct'] === true ? 'distinct ' : '';
				$this->columns[ $field ] = 'GROUP_CONCAT(' . $distinct . $this->columns[ $field ] . ')';
				if ( ! empty( $this->columns[ $field . '_tid' ] ) ) {
					$this->columns[ $field . '_tid' ] = 'GROUP_CONCAT(' . $distinct . $this->columns[ $field . '_tid' ] . ')';
				}
			}
		}

		// Type
		if ( ! empty( $arguments['type'] ) ) {
			$this->where .= ' AND base.type';
			if ( is_array( $arguments['type'] ) ) {
				$this->where .= " IN ('" . implode( "', '", $arguments['type'] ) . "')";
			} else {
				$this->where .= " = '" . $arguments['type'] . "'";
			}
		}

		// Published
		if ( ! empty( $arguments['published'] ) ) {
			$this->where .= ' AND base.status = 1';
		}

		// Group by to prevent dups
		// Handle multiple fields with 'group_concat' option
		$this->group_by = 'base.nid';

		// For consistent ordering
		$this->order_by = 'base.nid';
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		if ( ! empty( $arguments['id'] ) ) {
			$this->where .= ' AND base.nid';
			if ( is_array( $arguments['id'] ) ) {
				$this->where .= " IN ('" . implode( "', '", array_map( 'esc_sql', $arguments['id'] ) ) . "')";
			} else {
				$this->where .= " = '" . esc_sql( $arguments['id'] ) . "'";
			}
		}

		parent::init( $arguments );
	}

	/**
	 * Post-process current item before sending
	 * {@inheritdoc}
	 */
	public function current() : mixed {
		$current = parent::current();

		// split multiple into array from group_concat
		foreach ( $current as $field => $value ) {
			if ( ! empty( $this->arguments['fields'][ $field ]['group_concat'] ) && ! is_array( $value ) && ! empty( $value ) ) {
				$current->$field = explode( ',', $value );
			}
			// field_tids
			if ( strpos( $field, '_tid' ) !== false ) {
				$check = str_replace( '_tid', '', $field );
				if ( ! empty( $this->arguments['fields'][ $check ]['group_concat'] ) && ! is_array( $value ) && ! empty( $value ) ) {
					$current->$field = explode( ',', $value );
				}
			}

		}

		// populate collection field data // paragraphs
		if ( ! empty( $this->collection_fields ) ) {
			foreach ( $this->collection_fields as $field => $options ) {
				if ( ! empty( $current->$field ) ) {
					$current->$field = $this->entity_field_collection_data( $current->$field, $options );
				}
			}
		}

		// Clean up Link Fields
		if ( ! empty( $this->link_fields ) ) {
			foreach ( $this->link_fields as $link_field ) {
				if ( ! empty( $current->$link_field ) ) {
					$link                 = json_decode( $current->$link_field );
					$link->attr           = unserialize( $link->attr );
					$current->$link_field = $link;
				}
			}
		}

		// Replacements for stream wrappers (public://, private://)
		if ( ! empty( $this->file_fields ) ) {
			foreach ( $this->file_fields as $file_field ) {
				if ( ! empty( $current->{$file_field} ) ) {
					// Replace stream_wrapper protocols with paths and base_url
					foreach ( $this->stream_wrappers as $protocol => $path ) {
						if ( is_array( $current->{$file_field} ) ) {
							foreach ( $current->{$file_field} as $index => $value) {
								$current->{$file_field}[$index] = preg_replace( '#^' . preg_quote( $protocol, '#' ) . '://#', $this->base_url . $path, $value );
							}
						} else {

							$current->{$file_field} = preg_replace( '#^' . preg_quote( $protocol, '#' ) . '://#', $this->base_url . $path, $current->{$file_field} );
						}
					}
				}
			}
		}

		// Replacements for media WYSIWYG fields
		if ( ! empty( $this->media_wysiwyg_fields ) ) {
			foreach ( $this->media_wysiwyg_fields as $media_wysiwyg_field ) {
				$wysiwyg = $current->{$media_wysiwyg_field};
				// Based on Drupal 7 Media 2.x - media_wysiwyg.filter.inc
				preg_match_all( '/\[\[(.*?)\]\]/s', $wysiwyg, $matches, PREG_SET_ORDER );
				foreach ( $matches as $match ) {
					$tag      = $match[1]; // Capture
					$tag_info = json_decode( $tag, true );
					if ( ! isset( $tag_info['fid'] ) ) {
						$this->output->error( $tag_info, 'Media WYSIWYG tag missing fid' );
						continue;
					}
					// Get managed file URI
					$uri = $this->db->get_var(
						"
						SELECT uri
						FROM {$this->table_prefix}file_managed
						WHERE fid = '" . esc_sql( $tag_info['fid'] ) . "'
					"
					);
					if ( is_wp_error( $uri ) ) {
						$this->output->error( $uri->get_error_message() . ' (' . $tag_info['fid'] . ')', 'Media WYSIWYG tag fid lookup error' );
						continue;
					}
					// Replace stream_wrapper protocols with paths and base_url
					foreach ( $this->stream_wrappers as $protocol => $path ) {
						$uri = preg_replace( '#^' . preg_quote( $protocol, '#' ) . '://#', $this->base_url . $path, $uri );
					}

					// Combine attributes (create original data attribute, for debugging)
					$attributes                  = isset( $tag_info['attributes'] ) ? $tag_info['attributes'] : array();
					$attributes['src']           = esc_url( $uri );
					$attributes['data-original'] = $tag;

					// Create output
					// ASSUMPTION: We assume this is always an image
					$output = '<img ' . implode(
						' ',
						array_map(
							function ( $k, $v ) {
								return esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
							},
							array_keys( $attributes ),
							$attributes
						)
					) . '/>';

					// Replace media with output
					$wysiwyg = str_replace( $match[0], $output, $wysiwyg );
				}
				$current->{$media_wysiwyg_field} = $wysiwyg;
			}
		}

		// Parse metatag data
		if ( ! empty( $this->metatag_field ) && ! empty( $current->{$this->metatag_field} ) ) {
			$current->{$this->metatag_field} = maybe_unserialize( $current->{$this->metatag_field} );
		}

		return $current;
	}

	/**
	 * Drupal7 node specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function entity_field_join( $field_name ) {
		$alias = $field_name;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ]  = "LEFT JOIN {$this->table_prefix}field_data_{$field_name} {$alias}";
			$this->joins[ $alias ] .= " ON {$alias}.entity_type = 'node'";
			$this->joins[ $alias ] .= " AND {$alias}.deleted = 0";
			$this->joins[ $alias ] .= " AND base.nid = {$alias}.entity_id";
			$this->joins[ $alias ] .= " AND base.vid = {$alias}.revision_id";
		}
		return $alias;
	}

	/**
	 * Drupal7 node specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function custom_table_join( $table ) {

		if ( ! array_key_exists( $table, $this->joins ) ) {
			$this->joins[ $table ]  = "LEFT JOIN {$this->table_prefix}{$table} {$table}";
			$this->joins[ $table ] .= " ON {$table}.entity_type = 'node'";
			$this->joins[ $table ] .= " AND {$table}.deleted = 0";
			$this->joins[ $table ] .= " AND base.nid = {$table}.entity_id";
			$this->joins[ $table ] .= " AND base.vid = {$table}.revision_id";
		}
		return $table;
	}

	/**
	 * Drupal7 node specific helper for term data join
	 *
	 * @param string $field_table Field table
	 * @param string $tid_column Field table column containing tid
	 * @return string $alias
	 */
	protected function taxonomy_term_data_join( $field_table, $tid_column ) {
		$alias = 'taxonomy_term_data_' . $tid_column;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}taxonomy_term_data {$alias} ON {$field_table}.{$tid_column} = {$alias}.tid";
		}
		return $alias;
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

	/**
	 * Drupal7 node specific helper for metatag join
	 *
	 * @return string $alias
	 */
	protected function metatag_join() {
		$alias = 'metatag';
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}metatag {$alias} ON {$alias}.entity_type = 'node' AND base.nid = {$alias}.entity_id";
		}
		return $alias;
	}

	/**
	 * Drupal7 Field Collection join helper
	 *
	 * @return string $alias
	 */
	public function collection_join( $options ) {
		$alias = $options['name'];
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}field_data_{$options['name']} {$alias} ON {$alias}.entity_type = 'node' AND base.nid = {$alias}.entity_id";
		}
		return $alias;
	}

	/**
	 * Drupal7 node specific helper function for entity collection field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function entity_field_collection_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}field_data_{$field_name} {$alias} ON {$alias}.entity_type = 'node' AND base.nid = {$alias}.entity_id";
		}

		return $alias;
	}

	/**
	 * Drupal7 node specific helper function for paragraphs field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function entity_paragraphs_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}field_data_{$field_name} {$alias} ON {$alias}.entity_type = 'node' AND base.nid = {$alias}.entity_id";
		}

		return $alias;
	}

	/**
	 * Drupal 7 node specific helper for entity_field_collections
	 *
	 * @param array $entity_ids
	 * @param array $options
	 * @return array
	 */
	public function entity_field_collection_data( $entity_ids, $options ) {


		$sql = "SELECT collection.{$options['name']}_value";

		foreach ( $options['fields'] as $name => $type ) {
			if ( is_array( $type ) ) {
				foreach ( $type as $suffix ) {
					$sql .= ", {$name}.{$name}_{$suffix} {$name}_{$suffix}\n";
				}
			} else {
				$sql .= ", {$name}.{$name}_{$type} {$name}\n";
			}
		}

		$sql .= "FROM field_data_{$options['name']} collection\n";

		foreach ( $options['fields'] as $name => $type ) {
			$sql .= "
				LEFT JOIN field_data_{$name} {$name}
				ON {$name}.entity_id = collection.{$options['name']}_value
				AND {$name}.revision_id = collection.{$options['name']}_revision_id
			";
		}

		$sql .= "WHERE collection.{$options['name']}_value IN ( $entity_ids )\n";
		$sql .= "ORDER BY FIELD( collection.{$options['name']}_value, $entity_ids )\n";


		return $this->db->get_results( $sql );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_keys() {
		return array_column( $this->results, 'nid' );
	}

}
