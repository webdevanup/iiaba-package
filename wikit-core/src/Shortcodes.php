<?php
/**
 * This class maps all public non-static methods to shortcodes
 */
namespace WDG\Core;

class Shortcodes {

	/**
	 * Legacy shortcodes that should not show on the front end
	 *
	 * @var array
	 * @access protected
	 */
	protected $legacy = [];

	public function __construct() {
		$reflection = new \ReflectionClass( $this );

		$methods = array_filter(
			$reflection->getMethods( \ReflectionMethod::IS_PUBLIC ),
			fn( $method ) => ! $method->isStatic(),
		);

		foreach ( $methods as $method ) {
			// allow exact/lower/upper/snake/kebab case
			$shortcode_names = [
				$method->name,
				strtolower( $method->name ),
				strtoupper( $method->name ),
				str_replace( '_', '-', $method->name ),
				strtoupper( str_replace( '_', '-', $method->name ) ),
			];

			foreach ( $shortcode_names as $shortcode ) {
				if ( ! shortcode_exists( $shortcode ) ) {
					add_shortcode( $shortcode, [ $this, $method->name ] );
				}
			}
		}

		if ( ! empty( $this->legacy ) ) {
			foreach ( $this->legacy as $legacy ) {
				add_shortcode( $legacy, '__return_empty_string' );
			}
		}
	}

	/**
	 * Year shortcode for use in copyright options
	 *
	 * @param array $atts
	 * @param string $content
	 * @param string $tag
	 * @return string
	 */
	public function year() {
		return current_time( 'Y' );
	}

	/**
	 * Current date shortcode
	 *
	 * @param array $atts - shortcode attributes
	 * @attr format - any php date compatible format - defaults to the date_format setting in WordPress
	 * @param string $content
	 * @param string $tag
	 * @return string
	 */
	public function date( $atts ) {
		$atts = array_merge(
			[
				'format' => get_option( 'date_format' ),
			],
			$atts
		);

		return current_time( $atts['format'] );
	}
}
