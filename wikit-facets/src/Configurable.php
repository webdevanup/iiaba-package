<?php

namespace WDG\Facets;

use ReflectionProperty;

/**
 * Configurable classes allow setting class properties by an initialization
 * array and enforcing types defined by the class property
 *
 * @package wdgdc/wikit-facets
 */
class Configurable {

	/**
	 * @param array $props - the class properties in key => value format
	 * @param bool $existing_only - whether to allow non-existent properties to be set
	 */
	public function __construct( $props = [], $existing_only = true ) {
		foreach ( $props as $prop => $val ) {
			if ( ! $existing_only || property_exists( $this, $prop ) ) {
				$ref_prop = new ReflectionProperty( $this, $prop );
				$type     = $ref_prop->getType()->getName();

				if ( ! is_object( $val ) ) {
					settype( $val, $type );
				}

				$this->$prop = $val;
			}
		}
	}
}
