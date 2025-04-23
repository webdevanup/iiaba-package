<?php
/**
 * @file
 *
 * Migrates content as WordPress attachments (media library).
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\PostDestination;
use WDG\Migrate\Output\OutputInterface;

class AttachmentDestination extends PostDestination {

	/**
	 * URL Field
	 * @var bool
	 */
	protected $url_field = false;

	/**
	 * Attachment post_date for setting the correct upload dir
	 *
	 * @var string
	 */
	protected $attachment_post_date = null;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		// Separate fields
		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'url',
				 */
				case 'url':
					$this->url_field = $field;
					break;
			}
		}

		if ( ! $this->url_field ) {
			$this->output->error( 'Missing URL field!', 'Attachment Destination Construct' );
		}
	}

	/**
	 * Fix attachment upload directory to use the attachment post_date
	 *
	 * @filter upload_dir
	 */
	public function attachment_upload_dir( $uploads ) {
		// Remove after first use (prevent recursion)
		remove_filter( 'upload_dir', [ $this, 'attachment_upload_dir' ] );
		// Return an upload directory using the
		return wp_upload_dir( $this->attachment_post_date );
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
			if ( ! empty( $post_data['post_date'] ) ) {
				$this->attachment_post_date = $post_data['post_date'];
				add_filter( 'upload_dir', [ $this, 'attachment_upload_dir' ] );
			}
			// Import file (and create attachment)
			$post_id = $this->import_file( $row->{ $this->url_field }, null, $post_data );
			$new     = true;
		} else {
			// Update post
			$post_id = wp_update_post( $post_data, true );
			$new     = false;
		}
		if ( is_wp_error( $post_id ) ) {
			$this->output->error( $post_id->get_error_message(), 'Attachment "' . $title . '"' );
			return false;
		}

		$post_data['ID'] = $post_id;

		// trim title
		if ( strlen( $title ) > 50 ) {
			$title = substr( $title, 0, 50 ) . '...';
		}

		// Progress status
		if ( $new ) {
			$this->output->progress( 'Attachment "' . $title . '" (' . trim( $post_id ) . ') created', null, 2 );
		} else {
			$this->output->progress( 'Attachment "' . $title . '" (' . trim( $post_id ) . ') updated', null, 2 );
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
