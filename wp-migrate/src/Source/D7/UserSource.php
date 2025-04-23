<?php
/**
 * @file
 *
 * Base class for migrating nodes from Drupal 7.
 */

namespace WDG\Migrate\Source\D7;

use WDG\Migrate\Source\D7\D7SourceBase;
use WDG\Migrate\Output\OutputInterface;

class UserSource extends D7SourceBase {

	const FIELDS = [
		'uid' => [
			'type'   => 'key',
			'column' => 'uid',
		],
		'name' => [
			'type'   => 'user',
			'column' => 'name',
		],
		'mail' => [
			'type' => 'user',
			'column' => 'mail',
		],
		'created' => [
			'type'   => 'user',
			'column' => 'created',
		],
		'changed' => [
			'type'   => 'user',
			'column' => 'changed',
		],
		'status' => [
			'type'   => 'user',
			'column' => 'status',
		],
		'timezone' => [
			'type'   => 'user',
			'column' => 'timezone',
		],
		'alias' => [
			'type' => 'alias',
		],
		'metatag' => [
			'type' => 'metatag',
		],
	];

	/**
	 * Key field alias
	 * @var string
	 */
	protected $uid_field;


	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		// Base table is node
		$this->base = 'users';

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'key',
				 */
				case 'key':
					$options['column'] = 'uid';
					$this->uid_field   = $field;
				/**
				 * 'type' => 'user',
				 * 'column' => 'status',
				 */
				case 'user':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
					break;

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
				 * 'type' => 'metatag'
				 */
				case 'metatag':
					$table                   = $this->metatag_join();
					$this->columns[ $field ] = $this->column( $table, 'data' );
					$this->metatag_field     = $field;
					break;

				/**
				 * 'type' => 'redirects',
				 */
				case 'redirects':
					// Split Regex: /<(.+?)>(?:,|$)/
					$this->columns[ $field ] = "(SELECT GROUP_CONCAT(CONCAT('<', source, '>')) FROM {$this->table_prefix}redirect WHERE redirect = CONCAT('node/', base.uid))";
					break;

				/**
				 * 'type' => 'alias',
				 */
				case 'alias':
					$this->columns[ $field ] = "(SELECT alias FROM {$this->table_prefix}url_alias WHERE source = CONCAT('user/', base.uid) ORDER BY pid DESC LIMIT 1)";
					break;

				// /**
				//  * 'type' => 'menu_parent',
				//  */
				// case 'menu_parent':
				// 	$this->columns[$field] = "(SELECT REPLACE(link_path, 'node/', '') FROM dp_menu_links WHERE mlid = (SELECT plid FROM dp_menu_links WHERE link_path = CONCAT('node/', base.uid) LIMIT 1))";
				// break;

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

		// Published
		if ( ! empty( $arguments['published'] ) ) {
			$this->where .= ' AND base.status = 1';
		}

		// Group by to prevent dups
		// Handle multiple fields with 'group_concat' option
		$this->group_by = 'base.uid';

		// For consistent ordering
		$this->order_by = 'base.uid';
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		if ( ! empty( $arguments['id'] ) ) {
			$this->where .= ' AND base.uid';
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
			$this->joins[ $alias ] .= " ON {$alias}.entity_type = 'user'";
			$this->joins[ $alias ] .= " AND {$alias}.deleted = 0";
			$this->joins[ $alias ] .= " AND base.uid = {$alias}.entity_id";
			// $this->joins[ $alias ] .= " AND base.vid = {$alias}.revision_id";
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
			$this->joins[ $alias ] = "LEFT JOIN {$this->table_prefix}metatag {$alias} ON {$alias}.entity_type = 'node' AND base.uid = {$alias}.entity_id";
		}
		return $alias;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_keys() {
		return array_column( $this->results, 'uid' );
	}

}
