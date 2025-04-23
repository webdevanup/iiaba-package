<?php

namespace WDG\Migrate\Source\WordPress;

class CustomPost extends Post {

	protected $post_type = 'post';

	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {

		if ( ! empty( $arguments['post_type'] ) ) {
			$this->post_type = $arguments['post_type'];
		}
		parent::__construct( $arguments, $output );
	}

	public function get_permalink( $row ) {
		return '/' .$this->post_type . '/' . $row->post_name;
	}
}
