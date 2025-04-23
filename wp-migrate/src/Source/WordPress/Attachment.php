<?php

namespace WDG\Migrate\Source\WordPress;

class Attachment extends Post {

	protected $post_type = 'attachment';
	protected $base = 'posts';

	protected $post_status = [ 'publish', 'inherit' ];

}
