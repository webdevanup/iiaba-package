<?php

namespace WDG\Core;

class RestApi {

	public function __construct() {
		add_filter( 'rest_authentication_errors', [ $this, 'rest_authentication_errors' ] );
	}

	/**
	 * disable the rest api without authentication
	 *
	 * @param \WP_Error|null|bool
	 * @return \WP_Error|null|bool
	 * @access public
	 * @filter rest_authentication_errors
	 */
	public function rest_authentication_errors( $access ) {
		if ( ! is_user_logged_in() ) {
			$access = new \WP_Error( 'unauthorized', __( '401 Unauthorized' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return $access;
	}
}
