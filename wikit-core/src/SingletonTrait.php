<?php

namespace WDG\Core;

trait SingletonTrait {

	/**
	 * Get the instance of the called class
	 *
	 * @param mixed $arg[]
	 * @return static
	 */
	public static function instance() {
		static $instance;

		if ( ! isset( $instance ) ) {
			$instance = new static( ...func_get_args() );
		}

		return $instance;
	}

	/**
	 * Alias for instance
	 *
	 * @return static
	 */
	public static function get_instance() {
		return static::instance( ...func_get_args() );
	}
}
