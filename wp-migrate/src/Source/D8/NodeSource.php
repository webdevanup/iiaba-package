<?php
/**
 * @file
 *
 * Base class for migrating nodes from Drupal 8.
 */

namespace WDG\Migrate\Source\D8;

class NodeSource extends \WDG\Migrate\Source\D8\D8SourceBase {

	/**
	 * Key field alias
	 * @var string
	 */
	protected $nid_field;

	/**
	 * File field aliases (requires post-processing)
	 * @var array
	 */
	protected $file_fields = [];

	/**
	 * Media WYSIWYG field aliases (requires post-processing)
	 * @var array
	 */
	protected $media_wysiwyg_fields = [];

	/**
	 * Metatag field alias (requires post-processing)
	 * @var string
	 */
	protected $metatag_field;

	/**
	 * Field collection fields (requires processing data in $this->current)
	 *
	 * @var array
	 */
	protected $collection_fields = [];

	protected $term_fields = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
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

					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;
				/**
				 * 'type' => 'node',
				 * 'column' => 'status',
				 */
				case 'node':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;
				/**
				 * 'type' => 'node_data',
				 * 'column' => 'status',
				 */
				case 'node_data':
					$table                   = $this->node_data_join( $field );
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'node_data',
				 * 'column' => 'status',
				 */
				case 'term_data':
					$table                   = $this->term_data_join( $field );
					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'body',
				 */
				case 'body':
					$table                   = $this->body_join( 'body' );
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
				 * 'type' => 'field',
				 * 'name' => 'field_issue_volume',
				 * 'column' => 'field_issue_volume_value', // Optional
				 */
				case 'entity_field':
					$table = $this->entity_field_join( $options['name'] );

					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_target_id';
					}

					$this->columns[ $field ] = $this->column( $table, $options['column'] );
					break;
				/**
				 * 'type' => 'field_collection',
				 * 'name' => 'field_collection_name',
				 * 'fields' => [
				 *   'alias' => 'sub_field_name'
				 * ]
				 */
				case 'field_collection':
					$options['group_concat'] = true;
					$table                   = $this->entity_field_collection_join( $options['name'] );

					if ( empty( $options['column'] ) ) {
						$options['column'] = 'field_' . $options['name'] . '_value';
					}

					$this->columns[ $field ] = $this->column( $table, $options['column'] );

					$this->collection_fields[ $field ] = $options;
					break;
				/**
				 * To get term names
				 * 'type' => 'term_field',
				 * 'name' => 'field_issue_tags',
				 * 'column' => 'field_issue_tags_tid', // Optional
				 */
				case 'term_field':
					$field_table = $this->entity_field_join( $options['name'] );

					if ( empty( $options['column'] ) ) {
						$options['column'] = $options['name'] . '_target_id';
					}

					$this->columns[ $field ]     = $this->column( $field_table, $options['column'] );
					$this->term_fields[ $field ] = $options;
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
						$options['column'] = $options['name'] . '_target_id';
					}

					$file_table = $this->managed_file_join( $field_table, $options['column'] );

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
					$this->columns[ $field ] = "(
						SELECT
							alias
						FROM
							{$this->table_prefix}path_alias
						WHERE
							path = CONCAT('/node/', base.nid) ORDER BY id DESC LIMIT 1
					)";
					break;
				/**
				 * 'type' => 'redirects',
				 */
				case 'redirects':
					// Split Regex: /<(.+?)>(?:,|$)/
					// $this->columns[$field] = "(SELECT GROUP_CONCAT(CONCAT('<', source, '>')) FROM {$this->table_prefix}redirect WHERE redirect = CONCAT('node/', base.nid))";
					break;

				/**
				 * 'type' => 'menu_parent',
				 * 'menu' => 'main',
				 */
				case 'menu_parent':
					if ( empty( $options['menu'] ) ) {
						$options['menu'] = 'main';
					}

					$this->columns[ $field ] = sprintf(
						"(
							SELECT
								REPLACE ( parent_menu_tree.route_param_key, 'node=', '' )
							FROM
								menu_tree
							INNER JOIN
								menu_tree parent_menu_tree ON menu_tree.parent = parent_menu_tree.id
							WHERE
								menu_tree.menu_name = '%s'
								AND menu_tree.route_name = 'entity.node.canonical'
								AND menu_tree.route_param_key = CONCAT( 'node=', base.nid )
								AND parent_menu_tree.route_param_key <> CONCAT( 'node=', base.nid )
						)",
						$options['menu']
					);
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
				$separtor = is_string( $options['group_concat'] ) ? $options['group_concat'] : ',';
				$distinct = ! empty( $options['group_concat_distinct'] ) ? 'DISTINCT ' : '';

				$this->columns[ $field ] = 'GROUP_CONCAT(' . $distinct . $this->columns[ $field ] . " SEPARATOR '" . $separtor . "')";
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
			$table                   = $this->node_data_join( 'status' );
			$this->columns['status'] = $this->column( $table, 'status' );

			$this->where .= " AND {$table}.status = 1";
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
	public function current() {
		$current = parent::current();

		// populate collection field data
		foreach ( $this->collection_fields as $field => $options ) {
			if ( ! empty( $current->$field ) ) {
				$current->$field = $this->entity_field_collection_data( $current->$field, $options );
			}
		}

		// populate term field data
		foreach ( $this->term_fields as $field => $options ) {
			if ( ! empty( $current->$field ) ) {
				$sql = sprintf(
					'SELECT data.name
					FROM taxonomy_term_field_data data
					WHERE data.tid IN ( %1$s )
					ORDER BY FIELD( data.tid, %1$s )',
					$current->$field
				);

				$current->$field = $this->db->get_col( $sql );
			}
		}

		// don't allow empty strings for images
		foreach ( $this->file_fields as $file_field ) {
			if ( empty( $current->$file_field ) ) {
				$current->$file_field = null;
			}
		}

		// Replacements for stream wrappers (public://, private://)
		if ( ! empty( $this->file_fields ) ) {
			foreach ( $this->file_fields as $file_field ) {
				// Replace stream_wrapper protocols with paths and base_url
				foreach ( $this->stream_wrappers as $protocol => $path ) {
					$current->{$file_field} = preg_replace( '#^' . preg_quote( $protocol, '#' ) . '://#', $this->base_url . $path, $current->{$file_field} );
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
	 * Drupal8 node specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function node_data_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = sprintf(
				'LEFT JOIN %1$snode_field_data %2$s ON base.nid = %2$s.nid AND base.vid = %2$s.vid',
				$this->table_prefix,
				$alias
			);
		}

		return $alias;
	}

	/**
	 * Drupal8 node specific helper function for entity field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function entity_field_join( $field_name ) {
		$alias = $field_name;
		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = sprintf(
				'LEFT JOIN %1$snode__%3$s %2$s ON base.nid = %2$s.entity_id AND base.vid = %2$s.revision_id',
				$this->table_prefix,
				$alias,
				$field_name
			);
		}
		return $alias;
	}

	/**
	 * Drupal8 node specific helper function for entity collection field join
	 *
	 * @param string $field_name
	 * @return string $alias
	 */
	protected function entity_field_collection_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = sprintf(
				'LEFT JOIN %1$snode__field_%3$s %2$s ON base.nid = %2$s.entity_id AND base.vid = %2$s.revision_id',
				$this->table_prefix,
				$alias,
				$field_name
			);
		}

		return $alias;
	}

	protected function body_join( $field_name ) {
		$alias = $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $alias ] = sprintf(
				'LEFT JOIN %1$snode__body %2$s ON base.nid = %2$s.entity_id AND base.vid = %2$s.revision_id',
				$this->table_prefix,
				$alias
			);
		}

		return $alias;
	}

	/**
	 * Drupal7 node specific helper for term data join
	 *
	 * @param string $field_table Field table
	 * @param string $tid_column Field table column containing tid
	 * @return string $alias
	 */
	protected function taxonomy_term_data_join( $field_table, $tid_column ) {
		// var_dump( $field_table, $tid_column ); exit;
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
	 * Drupal 8 node specific helper for entity_field_collections
	 *
	 * @param array $entity_ids
	 * @param array $options
	 * @return array
	 */
	public function entity_field_collection_data( $entity_ids, $options ) {
		$primary_field_alias = array_key_first( $options['fields'] );
		$primary_field_name  = $options['fields'][ $primary_field_alias ];

		$primary_field[ $primary_field_alias ] = $options['fields'][ $primary_field_alias ];

		unset( $options['fields'][ $primary_field_alias ] );

		$sql = "SELECT {$primary_field_alias}.field_{$primary_field_name}_value {$primary_field_alias}";

		foreach ( $options['fields'] as $alias => $name ) {
			$sql .= ", {$alias}.field_{$name}_value {$alias}\n";
		}

		$sql .= "FROM field_collection_item__field_{$primary_field_name} {$primary_field_alias}\n";

		foreach ( $options['fields'] as $alias => $name ) {
			$sql .= "
				JOIN field_collection_item__field_{$name} {$alias}
				ON {$alias}.entity_id = {$primary_field_alias}.entity_id
				AND {$alias}.revision_id = {$primary_field_alias}.revision_id
			";
		}

		$sql .= "WHERE {$primary_field_alias}.entity_id IN ( $entity_ids )\n";
		$sql .= "ORDER BY FIELD( {$primary_field_alias}.entity_id, $entity_ids )\n";

		return $this->db->get_results( $sql );
	}

	/**
	 * Return array of keys
	 * @return array
	 */
	public function get_keys() {
		return array_column( $this->results, $this->nid_field );
	}

}
