<?php
/**
 * @file
 *
 * Migrates content into posts (common functions).
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\DestinationBase;
use WDG\Migrate\Output\OutputInterface;
use WDG\Migrate\Map\PostMetaMap;

abstract class PostDestinationBase extends DestinationBase {

	use AttachmentTrait;
	
	/**
	 * Current post object
	 * @var object
	 */
	protected $post;

	/**
	 * Post ID field alias
	 * @var bool
	 */
	protected $id_field = false;

	/**
	 * Post field aliases
	 * @var array
	 */
	protected $post_fields = array();

	/**
	 * Term field aliases
	 * @var array
	 */
	protected $term_fields = array();

	/**
	 * Attachment field aliases
	 * @var array
	 */
	protected $attachment_fields = array();

	/**
	 * File map for attachment or content fields
	 * @var PostMetaMap
	 */
	protected $file_map;

	/**
	 * Meta field (custom field) aliases
	 * @var array
	 */
	protected $meta_fields = array();

	/**
	 * Meta fields that have multiple values
	 * @var array
	 */
	protected $meta_fields_multiple = array();

	/**
	 * WP SEO fields
	 * @var string
	 */
	protected $wpseo_field = false;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		// Separate fields
		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'id',
				 */
				case 'id':
					$options['column'] = 'ID';
					// Pass through to 'post'
					/**
					 * 'type' => 'post',
					 * 'column' => 'post_content',
					 */
				case 'post':
					$this->post_fields[ $field ] = $options['column'];
					break;
				/**
				 * 'type' => 'term',
				 * 'taxonomy' => 'category',
				 */
				case 'term':
					$this->term_fields[ $field ] = $options['taxonomy'];
					break;
				/**
				 * 'type' => 'featured_image',
				 */
				case 'featured_image':
					$options['key'] = '_thumbnail_id';
					// Pass through to attachment
					/**
					 * 'type' => 'attachment',
					 * 'key' => 'pdf',
					 */
				case 'attachment':
					$this->attachment_fields[ $field ] = $options['key'] ?? $options['column'];
					break;
				/**
				 * 'type' => 'meta',
				 * 'key' => 'original_value',
				 */
				case 'meta_field':
					$this->meta_fields[ $field ] = $options['key'] ?? $options['column'];
					if ( true === ( $options['multiple'] ?? false ) ) {
						$this->meta_fields_multiple[] = $field;
					}
					break;
			}
		}

		if ( ! empty( $this->attachment_fields ) ) {
			$this->file_map = new PostMetaMap(
				[
					'key' => 'original_url',
				],
				'original_url',
				'attachment_id',
				$this->output
			);
			$this->file_map->init();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		$this->post = null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function current() {
		return $this->post;
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() {
		if ( isset( $this->post->ID ) ) {
			return $this->post->ID;
		} else {
			return false;
		}
	}

	/**
	 * Get post data from row
	 *
	 * @param object $row
	 * @return array
	 */
	public function post_data( $row ) {
		$post_data = [];

		// Assemble post array
		foreach ( $this->post_fields as $field => $column ) {
			if ( isset( $row->{ $field } ) ) {
				$post_data[ $column ] = $row->{ $field };
			}
		}

		return $post_data;
	}

	/**
	 * Get post title from post_data
	 *
	 * @param array $post_data
	 * @return string|null
	 */
	public function post_title( $post_data ) {
		// Get title for messages
		if ( isset( $post_data['post_title'] ) && strlen( $post_data['post_title'] ) > 0 ) {
			return $post_data['post_title'];
		}

		return null;
	}

	/**
	 * Import terms
	 *
	 * @param object $row
	 * @param int $post_id
	 */
	public function import_terms( $row, $post_id ) {
		// Update terms (wholesale replace)



		foreach ( $this->term_fields as $field => $taxonomy ) {
			if ( isset( $row->{ $field } ) ) {
				wp_set_object_terms( $post_id, $row->{ $field }, $taxonomy );
			}
		}
	}

	/**
	 * Import post metas
	 *
	 * @param object $row
	 * @param int $post_id
	 */
	public function import_metas( $row, $post_id ) {
		foreach ( $this->meta_fields as $field => $key ) {
			if ( isset( $row->{ $field } ) ) {
				// Import meta
				$this->import_meta( $post_id, $key, $row->{ $field }, in_array( $field, $this->meta_fields_multiple ), );
			}
		}
	}

	/**
	 * Create, Update, or Delete individual post meta
	 *
	 * @param int $post_id
	 * @param string $key
	 * @param mixed $value
	 * @param bool $multiple
	 */
	public function import_meta( $post_id, $key, $value, $multiple = false ) {
		if ( false === $value ) {
			delete_post_meta( $post_id, $key );
		} else {

			if ( $multiple && is_array( $value ) ) {

				$current_values = get_post_meta( $post_id, $key, false );
				$removed_values = array_diff( $current_values, $value );
				$added_values   = array_diff( $value, $current_values );

				if ( ! empty( $removed_values ) ) {
					foreach ( $removed_values as $value ) {
						delete_post_meta( $post_id, $key, $value );
					}
				}

				if ( ! empty( $added_values ) ) {
					foreach ( $added_values as $value ) {
						add_post_meta( $post_id, $key, $value, false );
					}
				}
			} else {
				update_post_meta( $post_id, $key, $value );
			}

		}
	}

}
