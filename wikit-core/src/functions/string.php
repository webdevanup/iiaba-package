<?php

namespace WDG\Core;

/**
 * Singleton instance of an Inflector object for manipulating word case
 *
 * @return Doctrine\Inflector\Inflector
 * @see https://www.doctrine-project.org/projects/doctrine-inflector/en/2.0/index.html
 */
function inflector() : \Doctrine\Inflector\Inflector {
	static $inflector;

	if ( ! isset( $inflector ) ) {
		$inflector = \Doctrine\Inflector\InflectorFactory::create()->build();
	}

	return $inflector;
}

/**
 * Convert a string to camelCase
 *
 * @param string $str
 * @return string
 */
function camelize( string $str ) : string {
	return inflector()->camelize( (string) $str );
}

/**
 * Takes a string and capitalizes all of the words, like PHP's built-in ucwords function. This extends that behavior, however, by allowing the word delimiters to be configured, rather than only separating on whitespace.
 *
 * @param string $str
 * @param string $delimeter
 * @return string
 */
function capitalize( string $str, string $delimeter = '' ) : string {
	return ! empty( $delimeter ) ? inflector()->capitalize( (string) $str, $delimeter ) : inflector()->capitalize( (string) $str );
}

/**
 * Returns a word in plural form.
 *
 * @param string $str
 * @return string
 */
function classify( string $str ) : string {
	return inflector()->classify( (string) $str );
}

/**
 * Alias for urlize
 *
 * @param string $str
 * @return string
 */
function dasherize( $str ) {
	return urlize( $str );
}

/**
 * Get a unique id from a string ensuring they're not used twice
 *
 * @param string $str
 * @return string
 */
function get_uid( string $str ) {
	static $counts = [];

	$counts[ $str ] ??= 0;
	$counts[ $str ]++;

	if ( $counts[ $str ] > 1 ) {
		$str .= '-' . $counts[ $str ];
	}

	return $str;
}

/**
 * Convert a camel/snake/kebab case string to human legible form
 *
 * @param string $str
 * @param boolean $capitalize
 * @return string
 */
function humanize( string $str, bool $capitalize = true ) : string {
	$str = trim( strtolower( $str ) );
	$str = preg_replace( '/[\-\_\.+]/', ' ', $str ); // replace common separators
	$str = preg_replace( '/[^a-z0-9\s+]/', '', $str ); //
	$str = preg_replace( '/\s+/', ' ', $str );
	$str = explode( ' ', $str );

	if ( $capitalize ) {
		$str = array_map( 'ucwords', $str );
	}

	return implode( ' ', $str );
}

/**
 * Returns a word in plural form.
 *
 * @param string $str
 * @return string
 */
function pluralize( $str ) {
	return inflector()->pluralize( (string) $str );
}

/**
 * Generate a URL friendly string from a string of text
 *
 * @param string $str
 * @return string
 */
function singularize( $str ) {
	return inflector()->singularize( (string) $str );
}

/**
 * Generate a URL friendly string from a string of text
 *
 * @param string $str
 * @return string
 */
function urlize( $str ) {
	return inflector()->urlize( (string) $str ) ?: '_' . md5( (string) $str );
}
