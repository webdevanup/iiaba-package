<?php
/**
 * @file
 *
 * Migrates content as WordPress posts in a multisite configuration
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\PostDestination;
use WDG\Migrate\Output\OutputInterface;
use WDG\Migrate\Map\MSPostMetaMap;

class MSPostDestination extends PostDestination {

	protected $blog_field;
	protected $blog_id;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		// Separate fields
		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'blog_id',
				 */
				case 'blog_id':
					$this->blog_field = $field;
					break;
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		parent::init();
		$this->blog_id = null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function import( $row ) {
		// Get blog_id field, if available
		if ( ! empty( $this->blog_field ) && ! empty( $row->{$this->blog_field} ) ) {
			$this->blog_id = (int) $row->{$this->blog_field};
		}

		if ( $this->blog_id ) {
			// Switch to blog
			switch_to_blog( $this->blog_id );
			$this->output->debug( trim( get_blog_details( $this->blog_id )->blogname ) . ' (' . $this->blog_id . ')', 'Switched to blog' );

			// Regular import
			$return = parent::import( $row );

			// Restore blog
			restore_current_blog();
			$this->output->debug( trim( get_blog_details()->blogname ), 'Restored current blog' );
		} else {
			// Regular import
			$return = parent::import( $row );
		}

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() {
		if ( isset( $this->post->ID ) ) {
			if ( isset( $this->blog_id ) ) {
				return $this->blog_id . '-' . $this->post->ID;
			}
			return $this->post->ID;
		} else {
			return false;
		}
	}

	/**
	 * Current Blog ID
	 *
	 * @return int|null
	 */
	public function blog_id() {
		return $this->blog_id;
	}

}
