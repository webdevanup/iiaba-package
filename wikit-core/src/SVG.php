<?php
/**
 * This class permits svg uploads and sanitizes them upon uploading
 *
 * @package wikit-core
 * @author kshaner
 */
namespace WDG\Core;

class SVG {

	use SingletonTrait;

	/**
	 * The list of icons to be included in the svg sprite
	 *
	 * - this list is added to as the render method is invoked
	 *
	 * @var array
	 */
	// phpcs:disable Squiz.PHP.CommentedOutCode.Found
	protected array $sprites = [
		// 'assets/svg/icon/angle-down.svg',
		// 'assets/svg/icon/search.svg',
		// 'assets/svg/icon/close.svg',
		// 'assets/svg/icon/menu.svg',
		// // 'assets/svg/logo.svg',
	];
	// phpcs:enable Squiz.PHP.CommentedOutCode.Found

	/**
	 * The arguments that sprites were rendered with
	 *
	 * @var array
	 */
	protected array $sprites_args = [];

	/**
	 * The list of sprites that have been rendered as symbols
	 *
	 * @var array
	 */
	protected array $rendered = [];

	/**
	 * List of allowed SVG tags
	 *
	 * @var array
	 * @see https://developer.mozilla.org/en-US/docs/Web/SVG/Element
	 */
	protected array $tags = [
		'a',
		'animate',
		'animateMotion',
		'animateTransform',
		'circle',
		'clipPath',
		'color-profile',
		'defs',
		'desc',
		'discard',
		'ellipse',
		'feBlend',
		'feColorMatrix',
		'feComponentTransfer',
		'feComposite',
		'feConvolveMatrix',
		'feDiffuseLighting',
		'feDisplacementMap',
		'feDistantLight',
		'feDropShadow',
		'feFlood',
		'feFuncA',
		'feFuncB',
		'feFuncG',
		'feFuncR',
		'feGaussianBlur',
		'feImage',
		'feMerge',
		'feMergeNode',
		'feMorphology',
		'feOffset',
		'fePointLight',
		'feSpecularLighting',
		'feSpotLight',
		'feTile',
		'feTurbulence',
		'filter',
		'foreignObject',
		'g',
		'hatch',
		'hatchpath',
		'image',
		'line',
		'linearGradient',
		'marker',
		'mask',
		'mesh',
		'meshgradient',
		'meshpatch',
		'meshrow',
		'metadata',
		'mpath',
		'path',
		'pattern',
		'polygon',
		'polyline',
		'radialGradient',
		'rect',
		'script',
		'set',
		'solidcolor',
		'stop',
		'style',
		'svg',
		'switch',
		'symbol',
		'text',
		'textPath',
		'title',
		'tspan',
		'unknown',
		'use',
		'view',
	];

	/**
	 * List of allowed SVG attributes
	 *
	 * @var array
	 * @see https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute
	 */
	protected array $attributes = [
		'accent-height',
		'accumulate',
		'additive',
		'alignment-baseline',
		'alphabetic',
		'amplitude',
		'arabic-form',
		'ascent',
		'attributeName',
		'attributeType',
		'azimuth',
		'baseFrequency',
		'baseline-shift',
		'baseProfile',
		'bbox',
		'begin',
		'bias',
		'by',
		'calcMode',
		'cap-height',
		'class',
		'clip',
		'clipPathUnits',
		'clip-path',
		'clip-rule',
		'color',
		'color-interpolation',
		'color-interpolation-filters',
		'color-profile',
		'color-rendering',
		'contentScriptType',
		'contentStyleType',
		'crossorigin',
		'cursor',
		'cx',
		'cy',
		'd',
		'decelerate',
		'descent',
		'diffuseConstant',
		'direction',
		'display',
		'divisor',
		'dominant-baseline',
		'dur',
		'dx',
		'dy',
		'edgeMode',
		'elevation',
		'enable-background',
		'end',
		'exponent',
		'externalResourcesRequired',
		'fill',
		'fill-opacity',
		'fill-rule',
		'filter',
		'filterRes',
		'filterUnits',
		'flood-color',
		'flood-opacity',
		'font-family',
		'font-size',
		'font-size-adjust',
		'font-stretch',
		'font-style',
		'font-variant',
		'font-weight',
		'format',
		'from',
		'fr',
		'fx',
		'fy',
		'g1',
		'g2',
		'glyph-name',
		'glyph-orientation-horizontal',
		'glyph-orientation-vertical',
		'glyphRef',
		'gradientTransform',
		'gradientUnits',
		'hanging',
		'height',
		'hidden',
		'href',
		'hreflang',
		'horiz-adv-x',
		'horiz-origin-x',
		'id',
		'ideographic',
		'image-rendering',
		'in',
		'in2',
		'intercept',
		'k',
		'k1',
		'k2',
		'k3',
		'k4',
		'kernelMatrix',
		'kernelUnitLength',
		'kerning',
		'keyPoints',
		'keySplines',
		'keyTimes',
		'lang',
		'lengthAdjust',
		'letter-spacing',
		'lighting-color',
		'limitingConeAngle',
		'local',
		'marker-end',
		'marker-mid',
		'marker-start',
		'markerHeight',
		'markerUnits',
		'markerWidth',
		'mask',
		'maskContentUnits',
		'maskUnits',
		'mathematical',
		'max',
		'media',
		'method',
		'min',
		'mode',
		'name',
		'numOctaves',
		'offset',
		'opacity',
		'operator',
		'order',
		'orient',
		'orientation',
		'origin',
		'overflow',
		'overline-position',
		'overline-thickness',
		'panose-1',
		'paint-order',
		'path',
		'pathLength',
		'patternContentUnits',
		'patternTransform',
		'patternUnits',
		'ping',
		'pointer-events',
		'points',
		'pointsAtX',
		'pointsAtY',
		'pointsAtZ',
		'preserveAlpha',
		'preserveAspectRatio',
		'primitiveUnits',
		'r',
		'radius',
		'referrerPolicy',
		'refX',
		'refY',
		'rel',
		'rendering-intent',
		'repeatCount',
		'repeatDur',
		'requiredExtensions',
		'requiredFeatures',
		'restart',
		'result',
		'role',
		'rotate',
		'rx',
		'ry',
		'scale',
		'seed',
		'shape-rendering',
		'slope',
		'spacing',
		'specularConstant',
		'specularExponent',
		'speed',
		'spreadMethod',
		'startOffset',
		'stdDeviation',
		'stemh',
		'stemv',
		'stitchTiles',
		'stop-color',
		'stop-opacity',
		'strikethrough-position',
		'strikethrough-thickness',
		'string',
		'stroke',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-opacity',
		'stroke-width',
		'style',
		'surfaceScale',
		'systemLanguage',
		'tabindex',
		'tableValues',
		'target',
		'targetX',
		'targetY',
		'text-anchor',
		'text-decoration',
		'text-rendering',
		'textLength',
		'to',
		'transform',
		'transform-origin',
		'type',
		'u1',
		'u2',
		'underline-position',
		'underline-thickness',
		'unicode',
		'unicode-bidi',
		'unicode-range',
		'units-per-em',
		'v-alphabetic',
		'v-hanging',
		'v-ideographic',
		'v-mathematical',
		'values',
		'vector-effect',
		'version',
		'vert-adv-y',
		'vert-origin-x',
		'vert-origin-y',
		'viewBox',
		'viewTarget',
		'visibility',
		'width',
		'widths',
		'word-spacing',
		'writing-mode',
		'x',
		'x-height',
		'x1',
		'x2',
		'xChannelSelector',
		'xlink:actuate',
		'xlink:arcrole',
		'xlink:href',
		'xlink:role',
		'xlink:show',
		'xlink:title',
		'xlink:type',
		'xml:base',
		'xml:lang',
		'xml:space',
		'xmlns',
		'y',
		'y1',
		'y2',
		'yChannelSelector',
		'z',
		'zoomAndPan',
	];

	/**
	 * List of additional style properties to be added to safe_style_css filter
	 *
	 * @var array
	 * @see https://css-tricks.com/svg-properties-and-css/#svg-css-properties
	 */
	protected array $style_properties = [
		'alignment-baseline',
		'baseline-shift',
		'clip-path',
		'clip-rule',
		'clip',
		'color-interpolation-filters',
		'color-interpolation',
		'color-profile',
		'color-rendering',
		'display',
		'dominant-baseline',
		'enable-background',
		'fill-opacity',
		'fill-rule',
		'fill',
		'filter',
		'flood-color',
		'flood-opacity',
		'glyph-orientation-horizontal',
		'glyph-orientation-vertical',
		'image-rendering',
		'kerning',
		'lighting-color',
		'marker-end',
		'marker-mid',
		'marker-start',
		'marker',
		'mask',
		'mask-type',
		'opacity',
		'pointer-events',
		'shape-rendering',
		'stop-color',
		'stop-opacity',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-opacity',
		'stroke-width',
		'stroke',
		'text-anchor',
		'text-rendering',
	];

	/**
	 * SVG mime types
	 *
	 * @var array
	 */
	protected array $upload_mime_types = [
		'svg'  => 'image/svg+xml',
		'svgz' => 'image/svg+xml',
	];

	public function __construct() {
		add_filter( 'upload_mimes', [ $this, 'upload_mimes' ], 99 );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'wp_prepare_attachment_for_js' ], 10, 3 );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'wp_check_filetype_and_ext' ], 99, 4 );
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'wp_handle_upload_prefilter' ] );
		add_filter( 'wp_get_attachment_image_src', [ $this, 'wp_get_attachment_image_src' ], 99, 4 );
		add_filter( 'rest_prepare_attachment', [ $this, 'rest_prepare_attachment' ], 10, 3 );
		add_action( 'admin_head', [ $this, 'admin_head' ] );
		add_filter( 'safe_style_css', [ $this, 'safe_style_css' ] );
		add_filter( 'wp_kses_allowed_html', [ $this, 'wp_kses_allowed_html' ], 1, 2 );
		add_filter( 'wp_body_open', [ $this, 'render_sprite' ], -1 );
		add_filter( 'wp_footer', [ $this, 'render_sprite' ], PHP_INT_MAX - 1 );
		add_filter( 'in_admin_header', [ $this, 'render_sprite' ], -1 );
	}

	/**
	 * Add our svg mime types to uploads
	 *
	 * @param array $upload_mimes
	 * @return array
	 * @filter upload_mimes
	 */
	public function upload_mimes( $upload_mimes ) {
		return array_merge( $upload_mimes, $this->upload_mime_types );
	}

	/**
	 * Use the svg url as the icon in the media library
	 *
	 * @param array $response
	 * @param WP_Post $attachment
	 * @param array|false $meta
	 * @return array
	 * @filter wp_prepare_attachment_for_js
	 */
	public function wp_prepare_attachment_for_js( $response, $attachment ) {
		if ( in_array( $attachment->post_mime_type, $this->upload_mime_types, true ) ) {
			$response['icon'] = $response['url'];
		}
		return $response;
	}

	/**
	 * Property detect svg files when uploaded
	 *
	 * @param array $data
	 * @param string $file
	 * @param string $filename
	 * @param string[]|null $mimes
	 * @return array
	 * @filter wp_check_filetype_and_ext
	 */
	public function wp_check_filetype_and_ext( $data = null, $file = null, $filename = null ) {
		$ext = $data['ext'];

		if ( empty( $ext ) ) {
			$ext = substr( $filename, strrpos( $filename, '.' ) + 1 );
		}

		if ( in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
			$data['type'] = $this->upload_mime_types[ $ext ];
			$data['ext']  = $ext;
		}

		return $data;
	}

	/**
	 * Build a list of svg tags and attributes for wp_kses
	 *
	 * @return array
	 */
	public function get_svg_tags() {
		static $tags, $attributes;

		if ( ! isset( $attributes ) ) {
			// create a list of attributes and lowercase attributes as allowed
			$attributes = array_unique( array_merge( $this->attributes, array_map( 'strtolower', $this->attributes ) ) );
		}

		if ( ! isset( $tags ) ) {
			$tags = array_reduce(
				$this->tags,
				function ( $allowed, $tag ) use ( $attributes ) {
					$allowed[ $tag ] = array_fill_keys( $attributes, true );
					$tag_lower       = strtolower( $tag );

					if ( $tag_lower !== $tag ) {
						// wp_kses does an strtolower comparison so add a lowercase version for
						// camelcase tags (e.g linearGradient) to make sure that we pass that check
						$allowed[ $tag_lower ] = $allowed[ $tag ];
					}

					return $allowed;
				},
				[]
			);
		}

		return $tags;
	}

	/**
	 * Sanitize svg with wp_kses
	 *
	 * @param array $file
	 * @return array
	 * @filter wp_handle_upload_prefilter
	 */
	public function wp_handle_upload_prefilter( $file ) {
		if ( 'image/svg+xml' === $file['type'] ) {
			try {
				$contents = file_get_contents( $file['tmp_name'] );

				if ( empty( $contents ) ) {
					throw new \Exception( __( 'SVG file is empty and not uploaded.' ) );
				}

				$contents = wp_kses( $contents, $this->get_svg_tags() );

				file_put_contents( $file['tmp_name'], $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			} catch ( \Throwable $e ) {
				$file['error'] = sprintf( __( "Sorry, this file couldn't be sanitized so for security reasons wasn't uploaded. %s" ), $e->getMessage() );
			}
		}

		return $file;
	}

	/**
	 * Add additional SVG style properties to safe_style_css
	 *
	 * @param array $properties
	 * @return array
	 * @filter safe_style_css
	 */
	public function safe_style_css( $properties ) {
		return array_unique( array_merge( $properties, $this->style_properties ) );
	}

	/**
	 * Apply the image size dimensions to the svg
	 *
	 * @param array $image
	 * @param int $attachment_id
	 * @param string $size
	 * @param bool $icon
	 * @return array
	 * @filter wp_get_attachment_image_src
	 */
	public function wp_get_attachment_image_src( $image, $attachment_id, $size ) {
		if ( 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
			$sizes = wp_get_additional_image_sizes();

			$svg = simplexml_load_file( get_attached_file( $attachment_id ) );
			if ( ! $svg ) {
				return $image;
			}

			$svg_attributes = $svg->attributes();
			$svg_height     = (string) $svg_attributes->height;
			$svg_width      = (string) $svg_attributes->width;

			switch ( true ) {
				case is_array( $size ) && 2 === count( $size ):
					$image_size = [
						'width'  => $size[0],
						'height' => $size[1],
						'crop'   => false,
					];
					break;
				case 'string' === gettype( $size ) && isset( $sizes[ $size ] ):
					$image_size = $sizes[ $size ];
					break;
				case 'thumbnail' === $size:
					$image_size = [
						'width'  => (int) get_option( 'thumbnail_size_w' ),
						'height' => (int) get_option( 'thumbnail_size_h' ),
						'crop'   => true,
					];
					break;
				case 'medium' === $size:
					$image_size = [
						'width'  => (int) get_option( 'medium_size_w' ),
						'height' => (int) get_option( 'medium_size_h' ),
						'crop'   => false,
					];
					break;
				case 'medium_large' === $size:
					$image_size = [
						'width'  => (int) get_option( 'medium_large_size_w' ),
						'height' => (int) get_option( 'medium_large_size_h' ),
						'crop'   => false,
					];
					break;
				case 'large' === $size:
					$image_size = [
						'width'  => (int) get_option( 'large_size_w' ),
						'height' => (int) get_option( 'large_size_h' ),
						'crop'   => false,
					];
					break;
				default:
					$image_size = [
						'width'  => (float) $svg_width ?: 300,
						'height' => (float) $svg_height ?: 150,
						'crop'   => false,
					];
					break;
			}

			$image[1] = $image_size['width'];

			if ( ! empty( $image_size['crop'] ) ) {
				$image[2] = $image_size['height'];
			} elseif ( ! empty( $svg_width ) ) {
				// if we are not a hard crop size, parse the height to the ratio of the width
				$image[2] = (int) round( ( (int) $image_size['width'] * (float) $svg_height ) / (float) $svg_width );
			}
		}

		return $image;
	}

	/**
	 * Generate a height/width for attachment metadata so it populates the rest api
	 *
	 * @param array $metadata
	 * @param int $attachment_id
	 * @param string $context
	 * @return array
	 * @filter wp_generate_attachment_metadata
	 */
	public function rest_prepare_attachment( $response, $attachment ) {
		if ( 'image/svg+xml' === get_post_mime_type( $attachment->ID ) ) {
			if ( isset( $response->media_details ) && ! is_array( $response->media_details ) ) {
				$response->media_details = [];
			}

			try {
				$svg        = simplexml_load_file( get_attached_file( $attachment->ID ) );
				$attributes = $svg->attributes();

				$response->media_details['width']  = $attributes->width ?: 300;
				$response->media_details['height'] = $attributes->height ?: 150;
				$response->media_details['file']   = get_post_meta( $attachment->ID, '_wp_attached_file', true );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				//
			}
		}

		return $response;
	}

	/**
	 * CSS tweak for featured images
	 *
	 * @return void
	 * @action admin_head
	 */
	public function admin_head() {
		?>
		<style type="text/css">
			img.components-responsive-wrapper__content[src$=".svg"] { position: relative };
		</style>
		<?php
	}

	/**
	 * Add svg support to wp_kses_post
	 *
	 * @param array $tags
	 * @param string $context
	 * @return array
	 */
	public function wp_kses_allowed_html( $tags ) {
		$svg_tags = $this->get_svg_tags();

		foreach ( $svg_tags as $svg_tag => $svg_tag_props ) {
			if ( ! empty( $tags[ $svg_tag ] ) ) {
				$tags[ $svg_tag ] = array_merge( $tags[ $svg_tag ], $svg_tag_props );
			} else {
				$tags[ $svg_tag ] = $svg_tag_props;
			}
		}

		return $tags;
	}

	/**
	 * Render an SVG file by it's path
	 *
	 * - a path that does not begin with a / is assumed to be theme relative
	 *
	 * @param string $path
	 * @param array $args - see SVGFile->apply_args for supported arguments
	 * @return string
	 */
	public function render( string $path, array $args = [] ) : string {
		$file = SVGFile::get( $path );

		if ( $file->exists ) {
			if ( ! in_array( $path, $this->sprites, true ) ) {
				$this->sprites[]             = $path;
				$this->sprites_args[ $path ] = $args;
			}

			return $file->symbol( $args );
		}

		return '';
	}

	/**
	 * Compile and echo the sprite of all svg files that have been registered
	 *
	 * @return string
	 */
	public function sprite() : string {
		$nl = defined( 'WP_DEBUG' ) && WP_DEBUG ? "\n" : '';

		$sprite  = '';
		$sprites = array_diff( $this->sprites, $this->rendered );

		if ( ! empty( $sprites ) ) {
			$sprite .= sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" style="display: none;" hidden>%1$s%2$s%1$s</svg>' . "\n",
				$nl,
				implode(
					$nl,
					array_map(
						function ( $path ) {
							$this->rendered[] = $path;

							$file = SVGFile::get( $path );

							return $file->sprite( $this->sprites_args[ $path ] ?? [] );
						},
						$sprites
					)
				),
			);
		}

		return $sprite;
	}

	/**
	 * Output the compiled sprite on wp_body_open
	 *
	 * @return void
	 */
	public function render_sprite() : void {
		echo wp_kses_post( $this->sprite() );
	}
}
