<?php
/**
 * @file
 *
 * Migrates content as WordPress terms.
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\DestinationBase;
use WDG\Migrate\Output\OutputInterface;
use WDG\Migrate\Map\PostMetaMap;

class TermDestination extends DestinationBase {

	use AttachmentTrait;

	CONST FIELDS = [
		'term_id' => [
			'type' => 'key',
		],
		'name' => [
			'type' => 'name',
		],
		'slug' => [
			'type'   => 'term',
			'column' => 'slug',
		],
		'parent' => [
			'type'   => 'term',
			'column' => 'parent',
		],
		'description' => [
			'type'   => 'term',
			'column' => 'description',
		],
		'original_data' => [
			'type'   => 'meta_field',
			'column' => '_original_data',
		],
		'original_url' => [
			'type'   => 'meta_field',
			'column' => '_original_url',
		],
		'original_url' => [
			'type'   => 'meta_field',
			'column' => '_original_url',
		],
	];

	/**
	 * Taxonomy
	 * @var string
	 */
	protected $taxonomy;

	/**
	 * Name field alias
	 * @var string
	 */
	protected $name_field;

	/**
	 * Term field aliases
	 * @var array
	 */
	protected $term_fields = [];

	/**
	 * Meta field (custom field) aliases
	 * @var array
	 */
	protected $meta_fields = [];

	/**
	 * Term
	 * @var object
	 */
	protected $term;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		if ( ! empty( $arguments['taxonomy'] ) ) {
			$this->taxonomy = $arguments['taxonomy'];
		}

		// Separate fields
		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'name',
				 */
				case 'name':
					$this->name_field = $field;
					break;
				/**
				 * 'type' => 'key',
				 */
				case 'key':
					$options['column'] = 'term_id';
					/**
					 * 'type' => 'term',
					 * 'column' => 'parent',
					 */
				case 'term':
					$this->term_fields[ $field ] = $options['column'];
					break;
				/**
				 * 'type' => 'meta_field',
				 * 'key' => 'original_value',
				 */
				case 'meta_field':
					$this->meta_fields[ $field ] = $options['key'] ?? $options['column'];
					break;

				// Pass through to attachment
				/**
				 * 'type' => 'attachment',
				 * 'key' => 'pdf',
				 */
				case 'attachment':
					$this->attachment_fields[ $field ] = $options['key'];
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
		$this->term = null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function import( $row ) {

		if ( isset( $row->{$this->name_field} ) && strlen( $row->{$this->name_field} ) > 0 ) {
			$name = $row->{$this->name_field};
		} else {
			return false; // Silently fail
		}

		$args = array();

		foreach ( $this->term_fields as $field => $column ) {
			if ( isset( $row->{$field} ) ) {
				$args[ $column ] = $row->{$field};
			}
		}

		// Sanitize name
		$name = sanitize_term_field( 'name', $name, 0, $this->taxonomy, 'db' );

		if ( ! empty( $args['term_id'] ) ) {
			// By ID
			$this->term = get_term( $args['term_id'], $this->taxonomy );
		} else {
			// By Slug
			if ( isset( $args['slug'] ) ) {
				$this->term = get_term_by( 'slug', $args['slug'], $this->taxonomy );
			} else {
				// By name
				$this->term = get_term_by( 'name', $name, $this->taxonomy );
			}
		}

		if ( empty( $this->term ) || is_wp_error( $this->term ) ) {
			// Create it
			$tid = wp_insert_term( $name, $this->taxonomy, $args );
			if ( is_wp_error( $tid ) ) {
				$this->output->error( $tid->get_error_message(), null, 2 );
				return false;
			} elseif ( empty( $tid ) ) {
				$this->output->error( 'Term "' . $name . '" could not be created - empty.', null );
				return false;
			}
			$this->term = get_term( $tid['term_id'], $this->taxonomy );
			$term_id    = $this->term->term_id;
			$new        = true;
		} else {
			wp_update_term( $this->term->term_id, $this->taxonomy, $args );
			$term_id = $this->term->term_id;
			$new     = false;
		}

		// Assemble termmeta
		$termmeta = array();
		foreach ( $this->meta_fields as $field => $key ) {
			if ( isset( $row->{$field} ) ) {
				$termmeta[ $key ] = $row->{$field};
			}
		}

		// Add new termmeta
		foreach ( $termmeta as $key => $value ) {
			update_term_meta( $term_id, $key, $value );
		}

		// Set post and return
		if ( $new ) {
			$this->output->progress( 'Term "' . $name . '" (' . trim( $term_id ) . ') created', null, 2 );
		} else {
			$this->output->progress( 'Term "' . $name . '" (' . trim( $term_id ) . ') updated', null, 2 );
		}

		// Import attachments
		$this->import_attachments( $row, $term_id );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function current() {
		return $this->term;
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() {
		if ( isset( $this->term->term_id ) ) {
			return $this->term->term_id;
		} else {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() { }



}

