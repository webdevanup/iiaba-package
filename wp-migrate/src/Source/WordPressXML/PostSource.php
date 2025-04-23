<?php
/**
 * @file
 *
 * Base class for migrating posts from a WordPress XML Export.
 */

namespace WDG\Migrate\Source\WordPressXML;

class PostSource extends SourceBase {

	protected $xml_path;
	protected $post_type;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
		if ( ! empty( $arguments['xml_path'] ) ) {
			$this->xml_path = $arguments['xml_path'];
		} else {
			$this->xml_path = MIGRATE_WORDPRESS_XML_PATH;
		}

		if ( ! empty( $arguments['post_type'] ) ) {
			$this->post_type = $arguments['post_type'];
		}

		parent::__construct( $arguments, $output );
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		parent::init( $arguments );
	}

	/**
	 * Post-process current item before sending
	 * {@inheritdoc}
	 */
	public function current() : mixed {
		$current = parent::current();

		return $current;
	}

}
