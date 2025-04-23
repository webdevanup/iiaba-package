<?php

namespace WDG\Migrate\Source\WordPress;

class User extends SourceBase {

	protected $base       = 'users';
	protected $base_id    = 'user_id';

	protected $meta_id    = 'user_id';
	protected $meta_table = 'usermeta';

}
