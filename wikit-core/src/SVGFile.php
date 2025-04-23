<?php

namespace WDG\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

class SVGFile {

	/**
	 * Cache for the SVG get factory
	 *
	 * @param array
	 */
	protected static array $store = [];

	/**
	 * Get a new svg file by it's path:
	 *  - theme assets/svg relative path without the file extension
	 *  - or absolute path with file extension
	 *
	 * @param string $path
	 * @return static
	 */
	public static function get( string $path ) {
		if ( ! isset( static::$store[ $path ] ) ) {
			static::$store[ $path ] = new static( $path );
		}

		return static::$store[ $path ];
	}

	/**
	 * The prefix that is applied to the generated id
	 *
	 * @var string
	 */
	public static string $prefix = 'svg-';

	/**
	 * Generate an id for a path
	 *
	 * @param string $path
	 * @return string
	 */
	public static function get_path_id( string $path ) : string {
		if ( ! strlen( $path ) ) {
			return '';
		}

		$upload_path = wp_get_upload_dir()['basedir'];
		$theme_path  = get_theme_file_path();

		// treat absolute theme paths as relative so they get a cleaner id
		if ( str_starts_with( $path, $theme_path ) ) {
			$path = str_replace( rtrim( $theme_path, '/' ) . '/', '', $path );
		}

		// theme paths
		if ( ! str_starts_with( $path, '/' ) ) {
			return static::$prefix . sanitize_title( str_replace( [ $theme_path, '.svg' ], '', $path ) );
		}

		// uploads
		if ( str_starts_with( $path, $upload_path ) ) {
			return static::$prefix . sanitize_title( str_replace( [ $upload_path, '.svg' ], '', $path ) );
		}

		// other usage outside of uploads or theme directory
		return static::$prefix . hash( 'md5', $path );
	}

	/**
	 * Counters for how many times this has been used so we can uniquely label the title and labelledby attributes
	 *
	 * @var array
	 */
	protected static $counter = [];

	/**
	 * The full canonicalized path from the constructor $path argument
	 *
	 * @var string
	 */
	public string $path;

	/**
	 * The unique id of the file for reference in the sprite
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * Does the file exist at the canonicalized path
	 *
	 * @param bool
	 */
	public bool $exists = false;

	/**
	 * The parsed default height from the svg file
	 *
	 * @param int
	 */
	public int $height;

	/**
	 * The parsed default width from the svg file
	 *
	 * @param int
	 */
	public int $width;

	/**
	 * The parsed viewBox from the svg file
	 *
	 * @param string
	 */
	public string $viewBox;

	/**
	 * The DOMDocument doing the parsing of the svg
	 */
	public ?DOMDocument $doc;

	public function __construct( string $path ) {
		$this->doc  = new DOMDocument();
		$theme_path = get_theme_file_path();

		// treat absolute theme paths as relative so they get a cleaner id
		if ( str_starts_with( $path, $theme_path ) ) {
			$path = str_replace( rtrim( $theme_path, '/' ) . '/', '', $path );
		}

		$this->id   = static::get_path_id( $path );
		$this->path = str_starts_with( $path, '/' ) ? $path : get_theme_file_path( $path );

		if ( ! file_exists( $this->path ) ) {
			return;
		}

		$this->exists = true;

		static::$counter[ $this->id ] ??= 0;
		static::$counter[ $this->id ]++;

		$this->doc->preserveWhiteSpace = false;
		$this->doc->load( $this->path, LIBXML_BIGLINES | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOXMLDECL );
		$this->doc->normalize();

		static::clean( $this->doc );

		if ( ! isset( $this->height ) && ! isset( $this->width ) ) {
			$this->height = (int) $this->doc->documentElement->getAttribute( 'height' );
			$this->width  = (int) $this->doc->documentElement->getAttribute( 'width' );
		}

		$this->viewBox = $this->doc->documentElement->getAttribute( 'viewBox' ) ?: '';
	}

	/**
	 * Render the svg when echo'd
	 *
	 * @return string
	 */
	public function __toString() : string {
		return $this->render();
	}

	/**
	 * Remove an XML declaration from a string
	 *
	 * @param string $str
	 * @return string
	 */
	public static function remove_xml_declaration( string $str ) : string {
		return trim( preg_replace( '/<\?xml[^>]*\?>/', '', $str ) );
	}

	/**
	 * Render the full icon
	 *
	 * @param array $args
	 * @return string
	 */
	public function render( array $args = [] ) : string {
		$doc = clone $this->doc;

		$this->apply_args( $doc, $args );

		return $this->remove_xml_declaration( $doc->saveXML( null, LIBXML_NOXMLDECL ) );
	}

	/**
	 * Get the sprite of the svg file
	 *
	 * @param array $args
	 * @return string
	 */
	public function sprite( array $args = [] ) : string {
		$doc    = new DOMDocument();
		$symbol = $doc->createElement( 'symbol' );

		$symbol->setAttribute( 'id', $this->id );
		$symbol->setAttribute( 'viewBox', $this->viewBox );
		$doc->appendChild( $symbol );

		foreach ( $this->doc->documentElement->childNodes as $childNode ) {
			$symbol->appendChild( $doc->importNode( $childNode, true ) );
		}

		if ( ! isset( $args['currentColor'] ) || ! empty( $args['currentColor'] ) ) {
			$this->set_current_color( $symbol );
		}

		return $doc->saveXML( $symbol );
	}

	/**
	 * Standardize arguments passed to the symbol and render methods
	 *
	 * @param array $args
	 * @return array
	 */
	protected function parse_args( array $args ) {
		$args = array_merge(
			[
				'title'     => '',
				'class'     => '',
				'focusable' => false,
				'role'      => 'img',
				'size'      => '',
				'height'    => null,
				'width'     => null,
			],
			$args
		);

		if ( ! empty( $args['size'] ) ) {
			$args = array_merge(
				$args,
				[
					'height' => $args['size'],
					'width'  => $args['size'],
				]
			);

			unset( $args['size'] );
		} else {
			$args = array_merge(
				[
					'height' => $this->height ?? null,
					'width'  => $this->width ?? null,
				],
				$args
			);
		}

		$args['class'] = classnames( 'svg', "svg--$this->id", $args['class'] ?? '' );

		if ( ! empty( $args['role'] ) ) {
			$attr['role']        = $args['role'];
			$args['aria-hidden'] = false;
		} else {
			$args['aria-hidden'] = true;
			$args['role']        = false;
		}

		if ( ! empty( $attr['focusable'] ) ) {
			$args['focusable'] = true;
			$args['tabindex']  = 0;

			unset( $args['focusable'] );
		} else {
			$args['focusable'] = false;
			$args['tabindex']  = false;
		}

		return $args;
	}

	/**
	 * Output the symbol to reference the sprite
	 *
	 * @param array $args
	 * @return string
	 */
	public function symbol( array $args = [] ) : string {
		$doc = new DOMDocument( '1.0', 'UTF-8' );

		if ( $doc->doctype ) {
			$doc->removeChild( $doc->doctype ); // remove doctype
		}

		$svg = $doc->createElement( 'svg' );
		$doc->appendChild( $svg );

		$use = $doc->createElement( 'use' );
		$use->setAttribute( 'xlink:href', '#' . $this->id );

		$svg->appendChild( $use );
		$this->apply_args( $doc, $args );

		$xml = $this->remove_xml_declaration( $doc->saveXML( null, LIBXML_NOXMLDECL ) );

		return $xml;
	}

	/**
	 * Apply the args from render/symbol to the DOMDocument
	 *
	 * Supported Args:
	 *   - class - html class to add to the svg element - default empty string
	 *   - size - height / width parameter - default null
	 *   - height - ignored when using size - default null
	 *   - width - ignored when using size - default null
	 *   - title - used for accessability - default empty string
	 *   - focusable - should the svg element be focusable (adds tabIndex and focusable attribute) - default false
	 *   - currentColor - replaces any fill or stroke value with currentColor - default true
	 *
	 * @param DOMDocument &$doc (reference)
	 * @param array $args
	 * @return void
	 */
	protected function apply_args( DOMDocument &$doc, array $args = [] ) : void {
		$args = array_merge(
			[
				'class'        => '',
				'focusable'    => false,
				'height'       => null,
				'role'         => 'img',
				'size'         => null,
				'title'        => '',
				'width'        => null,
				'currentColor' => true,
			],
			$args
		);

		if ( empty( $doc->documentElement ) ) {
			return;
		}

		$svg = $doc->documentElement;

		$svg->setAttribute(
			'class',
			classnames(
				'svg',
				"svg--$this->id",
				$args['class'] ?? ''
			)
		);

		// Add role for accessibility
		if ( ! empty( $args['role'] ) ) {
			$svg->setAttribute( 'role', $args['role'] );
			$svg->removeAttribute( 'aria-hidden' );
		} else {
			$svg->setAttribute( 'aria-hidden', 'true' );
			$svg->removeAttribute( 'role' );
		}

		// Add focusable
		if ( $args['focusable'] ) {
			$svg->setAttribute( 'focusable', 'true' );
			$svg->setAttribute( 'tabindex', 0 );
		} else {
			$svg->setAttribute( 'focusable', 'false' );
			$svg->removeAttribute( 'tabindex' );
		}

		if ( ! empty( $args['size'] ) && is_numeric( $args['size'] ) ) {
			$svg->setAttribute( 'height', $args['size'] );
			$svg->setAttribute( 'width', $args['size'] );
		} else {
			if ( ! empty( $args['height'] ) && is_numeric( $args['height'] ) ) {
				$svg->setAttribute( 'height', $args['height'] );
			}

			if ( ! empty( $args['width'] ) && is_numeric( $args['width'] ) ) {
				$svg->setAttribute( 'width', $args['width'] );
			}
		}

		if ( empty( $svg->getAttribute( 'height' ) ) && empty( $svg->getAttribute( 'width' ) ) ) {
			$svg->setAttribute( 'height', $this->height );
			$svg->setAttribute( 'width', $this->width );
		}

		$args['title'] = trim( (string) ( $args['title'] ?? '' ) );

		if ( ! empty( $args['title'] ) ) {
			// Remove existing title elements
			$titles = $svg->getElementsByTagName( 'title' );

			if ( $titles->length ) {
				foreach ( $titles as $title ) {
					$title->parentNode->removeChild( $title );
				}
			}

			// Insert our title at the top
			$title    = $doc->createElement( 'title', htmlentities( $args['title'] ) );
			$title_id = get_uid( $this->id . '-title' );

			if ( static::$counter[ $this->id ] > 1 ) {
				$title_id .= '-' . static::$counter[ $this->id ];
			}

			$svg->setAttribute( 'aria-labelledby', $title_id );
			$title->setAttribute( 'id', $title_id );
			$svg->insertBefore( $title, $svg->firstChild );
		}

		if ( ! empty( $args['currentColor'] ) ) {
			$this->set_current_color( $doc->documentElement );
		}
	}

	/**
	 * Set any fill or stroke to match have the currentColor value instead of a hex (or other format)
	 *
	 * @param DOMNode $node
	 * @return void
	 */
	protected static function set_current_color( DOMNode &$node ) : void {
		if ( $node instanceof DOMElement ) {
			foreach ( [ 'fill', 'stroke' ] as $prop ) {
				if ( ! empty( $node->getAttribute( $prop ) ) && ! in_array( $node->getAttribute( $prop ), [ 'none' ], true ) ) {
					$node->setAttribute( $prop, 'currentColor' );
				}
			}

			if ( $node->childNodes->length ) {
				foreach ( $node->childNodes as $childNode ) {
					static::set_current_color( $childNode );
				}
			}
		}
	}

	/**
	 * Recursively remove comment nodes from the a dom node
	 *
	 * @param DOMNode $node
	 * @return void
	 */
	protected static function clean( DOMNode &$node ) : void {
		if ( $node->childNodes->length ) {
			foreach ( $node->childNodes as $childNode ) {
				// @see https://www.php.net/manual/en/class.domnode.php#domnode.props.nodetype
				if ( XML_COMMENT_NODE === $childNode->nodeType ) {
					$childNode->parentNode->removeChild( $childNode );
				} elseif ( $childNode->childNodes->length ) {
					static::clean( $childNode );
				}
			}
		}
	}
}
