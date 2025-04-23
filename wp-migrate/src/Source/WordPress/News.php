<?php

namespace WDG\Migrate\Source\WordPress;

class News extends Post {

	protected $post_type = 'news';

	public function get_permalink( $row ) {
		return '/' .$this->post_type . '/' . $row->post_name;
	}
}
