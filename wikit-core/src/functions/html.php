<?php
namespace WDG\Core;

/**
 * Append CSS classes as strings to for use on the <body> tag array
 * - does not need to be added via the body_class filter
 *
 * @param string|array $args,...
 * @return void
 */
function add_body_class() {
	$class_names = explode( ' ', classnames( func_get_args() ) );

	add_filter( 'body_class', fn( $classes ) => array_merge( $classes, $class_names ) );
}

/**
 * Generate a class list based by allowing any truthy value (any scalar value except false, 0, -0, 0.0, -0.0, '', '0', null, false) evaluated by the empty function
 *
 * @see https://www.php.net/manual/en/language.types.boolean.php#language.types.boolean.casting
 *
 * Data types accepted (other types will be ignored):
 * - string
 * - double
 * - integer
 * - array:
 *   * if an index is numeric, the value is add to the classlist if truthy
 *   * if an index is a non-numeric string, the index is added to the classlist if the value is truthy
 * - object:
 *   * if a __toString method exists on the object, it will be used and evaluated for truthiness
 *   * otherwise will iterate over public properties and the property name added if the property value is truthy
 *
 * ```php
 * classnames(
 *     'string-class',
 *     3,
 *     0,
 *     [
 *         'array',
 *         'of',
 *         'classes',
 *         '',
 *         0,
 *     ],
 *     [
 *         'array-key-truthy' => true,
 *         'array-key-falsy' => false
 *     ]
 *     (object) [
 *         'object-prop-truthy' => 1,
 *         'object-prop-falsy' => 0
 *     ]
 * );
 * ```
 *
 * -> 'string-class 3 array of classes array-key-truthy object-prop-truthy'
 *
 * @param mixed
 * @return string
 */
function classnames() {
	$args       = func_get_args();
	$classnames = [];

	foreach ( $args as $arg ) {
		if ( empty( $arg ) ) {
			continue;
		}

		$arg_type = gettype( $arg );

		if ( 'string' === $arg_type ) {
			array_push( $classnames, $arg );
			continue;
		}

		if ( 'integer' === $arg_type || 'double' === $arg_type || ( 'object' === $arg_type && is_callable( [ $arg, '__toString' ] ) ) ) {
			array_push( $classnames, (string) $arg );
			continue;
		}

		if ( 'object' === $arg_type || 'array' === $arg_type ) {
			foreach ( $arg as $key => $val ) {
				if ( is_array( $val ) || is_object( $val ) ) {
					array_push( $classnames, trim( call_user_func_array( __FUNCTION__, $arg ) ) );
					continue;
				}

				if ( is_numeric( $key ) && ! empty( $val ) ) {
					array_push( $classnames, $val );
					continue;
				}

				if ( ! empty( $val ) ) {
					array_push( $classnames, $key );
					continue;
				}
			}
			continue;
		}
	}

	return implode( ' ', array_unique( array_filter( $classnames ) ) );
}

/**
 * Creates HTML attributes from an array of key value pairs
 *
 * @param array $attributes
 * @param array $defaults
 * @return string
 */
function html_attributes( array $attributes = [], array $defaults = [] ) : string {
	// Merge and eliminate false values
	$attributes = array_filter( array_merge( $defaults, $attributes ), fn( $value ) => false !== $value );

	$html_attributes = '';

	foreach ( $attributes as $key => $value ) {
		if ( true === $value ) {
			$html_attributes .= ' ' . esc_attr( $key );
		} else {
			$html_attributes .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}

	return trim( $html_attributes );
}

/**
 * Remove spaces between HTML tags similar to Twig's spaceless filter
 *
 * @param string $html
 * @return string
 */
function spaceless( $html ) {
	return trim( preg_replace( '/>\s+</', '><', $html ) ); // phpcs:ignore -- removing whitespace (twig spaceless filter)
}
