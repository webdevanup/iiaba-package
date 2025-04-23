<?php

namespace WDG\Migrate\Source\WordPress;

class Post extends SourceBase {


	const FIELDS = [
		'id' => [
			'column' => 'ID',
			'type'   => 'key',
		],
		'post_author' => [
			'column' => 'post_author',
			'type'   => 'post',
		],
		'post_date' => [
			'column' => 'post_date',
			'type'   => 'post',
		],
		'post_date_gmt' => [
			'column' => 'post_date_gmt',
			'type'   => 'post',
		],
		'post_content' => [
			'column' => 'post_content',
			'type'   => 'post',
		],
		'post_title' => [
			'column' => 'post_title',
			'type'   => 'post',
		],
		'post_excerpt' => [
			'column' => 'post_excerpt',
			'type'   => 'post',
		],
		'post_status' => [
			'column' => 'post_status',
			'type'   => 'post',
		],
		'comment_status' => [
			'column' => 'comment_status',
			'type'   => 'post',
		],
		'ping_status' => [
			'column' => 'ping_status',
			'type'   => 'post',
		],
		'post_password' => [
			'column' => 'post_password',
			'type'   => 'post',
		],
		'post_name' => [
			'column' => 'post_name',
			'type'   => 'post',
		],
		'to_ping' => [
			'column' => 'to_ping',
			'type'   => 'post',
		],
		'pinged' => [
			'column' => 'pinged',
			'type'   => 'post',
		],
		'post_modified' => [
			'column' => 'post_modified',
			'type'   => 'post',
		],
		'post_modified_gmt' => [
			'column' => 'post_modified_gmt',
			'type'   => 'post',
		],
		'post_content_filtered' => [
			'column' => 'post_content_filtered',
			'type'   => 'post',
		],
		'post_parent' => [
			'column' => 'post_parent',
			'type'   => 'post',
		],
		'guid' => [
			'column' => 'guid',
			'type' => 'post',
		],
		'menu_order' => [
			'column' => 'menu_order',
			'type'   => 'post',
		],
		'post_type' => [
			'column' => 'post_type',
			'type'   => 'post',
		],
		'post_mime_type' => [
			'column' => 'post_mime_type',
			'type'   => 'post',
		],
		'comment_count' => [
			'column' => 'comment_count',
			'type'   => 'post',
		],
		'_thumbnail_id' => [
			'type' => 'attachment',
			'key' => '_thumbnail_id',
		],
	];

	protected $base       = 'posts';
	protected $base_id    = 'ID';

	protected $meta_id    = 'post_id';
	protected $meta_table = 'postmeta';

	protected $post_type = 'post';
	protected $post_status;

	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		$this->where .= " AND base.post_type = '{$this->post_type}'";

		if ( ! isset( $this->post_status ) ) {
			if ( ! empty( $arguments['post_status'] ) ) {
				$this->post_status = is_array( $arguments['post_status'] ) ? $arguments['post_status'] : [ $arguments['post_status'] ];
			} else {
				$this->post_status = ['publish'];
			}
		}

		foreach ( $arguments['fields'] as $field => $options ) {

			if ( ! empty( $options['type'] ) && 'key' === $options['type'] ) {
				$this->key_field = $field;
			}

			switch( $options['type'] ) {
				case 'meta_field':
				case 'featured_image':
				case 'attachment':
					$value_field = 'meta_value';
					$table = $this->meta_join( $field );
					$this->columns[ $field ] = $this->column( $table, $value_field );
				break;
				case 'id':
				case 'key':
				case 'post':
					$this->columns[ $field ] = $this->column( 'base', $options['column'] );
				break;
				case 'term':
					$value_field = 'name';
					if ( isset( $options['by'] ) ) {
						$value_field = $options['by'];
					}
					$table = $this->term_join( $field, $options['taxonomy'] );
					$options['group_concat'] = true;
					$this->columns[ $field ] = $this->column( $table, $value_field );
				break;
			}

			// Wrap in group_concat
			if ( ! empty( $options['group_concat'] ) ) {
				$this->columns[ $field ] = 'GROUP_CONCAT(' . $this->columns[ $field ] . ')';
			}

			// Wrap in group_concat
			if ( ! empty( $value_field ) && ! empty( $options['group_concat'] ) ) {
				global $wpdb;
				$distinct = ! empty( $options['distinct'] ) ? 'DISTINCT ' : '';
				$group_concat_separator = is_string( $options['group_concat'] ) ? $options['group_concat'] : ',';
				$this->columns[ $field ] = $wpdb->prepare( 'GROUP_CONCAT(' . $distinct . $this->column( $table, $value_field ) . ' SEPARATOR %s)', $group_concat_separator );
			}
		}

	}

	/**
	 * Post-process current item before sending
	 * {@inheritdoc}
	 */
	public function current(): mixed {
		$current = parent::current();

		// split multiple into array from group_concat
		foreach ( $current as $field => $value ) {
			if ( ! empty( $this->arguments['fields'][ $field ]['group_concat'] ) && is_string( $value ) ) {
				$current->$field = explode( ',', $value );
			}
		}

		return $current;
	}


}
