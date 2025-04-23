s<?php

namespace WDG\Migrate\Source\WordPress;

class Event extends Post {

	protected $post_type = 'event';

	public function get_permalink( $row ) {
		return '/' .$this->post_type . '/' . $row->post_name;
	}
}
