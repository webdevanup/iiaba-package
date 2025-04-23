<?php

namespace WDG\Core;

trait Factory {

	public function set_props( array $props, bool $existing_only = true ) : void {
		foreach ( $props as $prop => $prop_value ) {
			if ( $existing_only && ! property_exists( $this, $prop ) ) {
				continue;
			}

			if ( is_array( $this->$prop ) && is_array( $prop_value ) ) {
				$this->$prop = array_merge( $this->$prop, $prop_value );
			} else {
				$this->$prop = $prop_value;
			}
		}
	}

	public static function factory( array $props = [], bool $existing_only = true ) : object {
		$called_class = get_called_class();

		$instance = new $called_class();
		$instance->set_props( $props, $existing_only );

		return $instance;
	}
}
