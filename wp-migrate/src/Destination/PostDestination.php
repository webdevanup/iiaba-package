<?php
/**
 * @file
 *
 * Migrates content as WordPress posts.
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\PostDestinationBase;
use WDG\Migrate\Output\OutputInterface;
use WDG\Migrate\Map\PostMetaMap;

class PostDestination extends PostDestinationBase {

	const FIELDS = [
		'id' => [
			'type' => 'id',
		],
		'author' => [
			'type' => 'post',
			'column' => 'post_author',
		],
		'date' => [
			'type' => 'post',
			'column' => 'post_date',
		],
		'date_gmt' => [
			'type' => 'post',
			'column' => 'post_date_gmt',
		],
		'title' => [
			'type' => 'post',
			'column' => 'post_title',
		],
		'name' => [
			'type' => 'post',
			'column' => 'post_name',
		],
		'content' => [
			'type' => 'post',
			'column' => 'post_content',
		],
		'excerpt' => [
			'type' => 'post',
			'column' => 'post_excerpt',
		],
		'modified' => [
			'type' => 'post',
			'column' => 'post_modified',
		],
		'modified_gmt' => [
			'type' => 'post',
			'column' => 'post_modified_gmt',
		],
		'type' => [
			'type'   => 'post',
			'column' => 'post_type',
		],
		'status' => [
			'type'   => 'post',
			'column' => 'post_status',
		],
		'name' => [
			'type'   => 'post',
			'column' => 'post_name',
		],
		'parent' => [
			'type'   => 'post',
			'column' => 'post_parent',
		],
		'featured_image' => [
			'type' => 'featured_image',
		],
		'original_url' => [
			'type' => 'meta_field',
			'key'  => '_original_url',
		],

		'original_data' => [
			'type' => 'meta_field',
			'key'  => '_original_data',
		],
		'yoast_meta_description' => [
			'type' => 'meta_field',
			'key'  => '_yoast_wpseo_metadesc',
		],
		'yoast_share_image' => [
			'type' => 'meta_field',
			'key'  => '_yoast_wpseo_opengraph-image',
		],
		'yoast_share_image_id' => [
			'type' => 'meta_field',
			'key'  => '_yoast_wpseo_opengraph-image-id',
		],
	];

	protected $base_url = '';

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = trailingslashit( $arguments['base_url'] );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function import( $row ) {
		$post_data = $this->post_data( $row );
		$title     = $this->post_title( $post_data );

		// Create or update
		if ( empty( $post_data['ID'] ) ) {
			unset( $post_data['ID'] );
			// Create post
			$post_id = wp_insert_post( $post_data, true );
			$new     = true;
		} else {
			// Update post
			$post_id = wp_update_post( $post_data, true );
			$new     = false;
		}
		if ( is_wp_error( $post_id ) ) {
			$this->output->error( $post_id->get_error_message(), 'Post "' . $title . '"' );
			return false;
		}
		$post_data['ID'] = $post_id;

		// trim title
		if ( ! empty( $title ) && strlen( $title ) > 50 ) {
			$title = substr( $title, 0, 50 ) . '...';
		}

		// Progress status
		if ( $new ) {
			$this->output->progress( 'Post "' . $title . '" (' . trim( $post_id ) . ') created', null, 2 );
		} else {
			$this->output->progress( 'Post "' . $title . '" (' . trim( $post_id ) . ') updated', null, 2 );
		}

		// Import terms
		$this->import_terms( $row, $post_id );

		// Import attachments
		$this->import_attachments( $row, $post_id );

		// Import metas
		$this->import_metas( $row, $post_id );

		// Set post and return
		$this->post = get_post( $post_id );
	}
}
