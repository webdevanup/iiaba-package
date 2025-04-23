<?php
/**
 * @file
 *
 * Migrates content as WordPress attachments (media library).
 */

namespace WDG\Migrate\Destination;

trait AttachmentTrait  {


	/**
	 * Attachment field aliases
	 * @var array
	 */
	protected $attachment_fields = [];

	/**
	 * Import attachments
	 *
	 * @param object $row
	 * @param int $object_id
	 */
	public function import_attachments( $row, $object_id ) {

		foreach ( $this->attachment_fields as $field => $key ) {

			if ( ! empty( $row->{ $field } ) ) {
				// Process field values with attachments
				if ( is_array( $row->{ $field } ) ) {
					$object_ids = array_fill( 0, count( $row->{ $field } ), $object_id ); // Array full of object_ids to pass to array_map
					$value    = array_map( [ $this, 'import_attachment' ], $row->{ $field }, $object_ids );
				} else {
					$value = $this->import_attachment( $row->{ $field }, $object_id );
				}
				// Import meta
				$this->import_meta( $object_id, $key, $value );
			}
		}
	}

	/**
	 * Import attachment, checking if it has been previously uploaded
	 *
	 * @param string $url
	 * @param int $object_id
	 * @return int|false Attachment ID
	 */
	public function import_attachment( $url, $object_id ) {
		// Check file map
		$attachment_id = $this->file_map->lookup_destination_key( $url );
		if ( empty( $attachment_id ) ) {
			// Valid file needing import
			$attachment_id = $this->import_file( $url, $object_id );
			if ( is_wp_error( $attachment_id ) ) {
				// Attachment import failed
				$this->output->error( $attachment_id->get_error_message(), 'Attachment "' . $url . '"' );
				return false;
			}

			// Save file map
			$this->file_map->save( $url, $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Sideload URL or file and return media library attachment_id
	 *
	 * @param string $url Can be a URL or filepath
	 * @param int $object_id
	 * @return int|WP_Error Attachment ID or error
	 */
	public function import_file( $url, $object_id = null, $post_data = [] ) {
		
		$file = $this->get_import_file( $url );

		if ( \is_wp_error( $file ) ) {
			return new \WP_Error( 'import_file', 'File is not readable..' );
		}

		if ( ! is_readable( $file ) ) {
			return new \WP_Error( 'import_file', 'File is not readable...' );
		}

		// Sideload file
		$file_array    = [
			'name' => basename( $url ),
			'tmp_name' => $file,
		];
		$attachment_id = media_handle_sideload( $file_array, $object_id, null, $post_data );
		// Check for sideload errors (bug in media_handle_sideload causes it to return 0 instead of wp_error in some cases)
		if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			// Delete the temp file
			@unlink( $file );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			} else {
				return new \WP_Error( 'media_handle_sideload', 'Unknown error inserting attachment' );
			}
		}
		$this->output->debug( 'Sideloaded attachment ' . $attachment_id, 'Import File' );

		return $attachment_id;
	}

	protected function get_import_file( $url ) {

		$file = \apply_filters( __METHOD__, null, $url );

		if ( empty( $file ) ) {

			if ( wp_http_validate_url( $url ) ) {
				$this->output->debug( 'Downloading URL: ' . $url, 'Import File' );

				// Download file
				$file = download_url( $url );


				// Check for download errors
				if ( is_wp_error( $file ) ) {
					return $file;
				}
				$this->output->debug( 'Downloaded ' . $file, 'Import File' );
			} else {
				$file = $url;
			}

		}

		return $file;
	}
	
}
