<?php

namespace WDG\Facets;

/**
 * Facets is the controller for the package that initializes the provider and the source
 *
 * @package wdgdc/wikit-facets
 */
class Facets {

	/**
	 * Gets the first initialized instance of the class and attempt to auto-configure
	 *
	 * - should be called during the plugins_loaded hook or later to auto-configure
	 *
	 * @param null|string|Provider\ProviderInterface
	 * @param null|string|Source\SourceInterface
	 * @return static
	 */
	public static function instance( $provider = null, $source = null ) : static {
		static $instance;

		if ( ! isset( $instance ) ) {
			if ( empty( $provider ) ) {
				if ( class_exists( '\Automattic\VIP\Search\Search' ) ) {
					$provider = __NAMESPACE__ . '\\Provider\\EnterpriseSearch';
				} elseif ( class_exists( '\SolrPower' ) ) {
					$provider = __NAMESPACE__ . '\\Provider\\SolrPower';
				} else {
					$provider = __NAMESPACE__ . '\\Provider\\Native';
				}
			}

			if ( empty( $source ) ) {
				if ( class_exists( '\SearchWP' ) ) {
					$source = __NAMESPACE__ . '\\Source\SearchWP';
				} elseif ( function_exists( 'relevanssi_init' ) ) {
					$source = __NAMESPACE__ . '\\Source\Relevanssi';
				} else {
					$source = null;
				}
			}

			$instance = new static( $provider, $source );
		}

		return $instance;
	}

	/**
	 * The reference to the facets provider
	 *
	 * @var Provider\ProviderInterface
	 */
	public Provider\ProviderInterface $provider;

	/**
	 * The reference to the facets source
	 *
	 * @var Source\SourceInterface|null
	 */
	public ?Source\SourceInterface $source;

	/**
	 * Initialize the provider and source by full qualified class name or an instantiated class
	 *
	 * @param string|Provider\ProviderInterface
	 * @param null|string|Source\SourceInterface
	 */
	public function __construct( $provider = __NAMESPACE__ . '\Provider\Native', $source = null ) {
		if ( is_string( $provider ) && class_exists( $provider ) ) {
			$this->provider = new $provider();
		} elseif ( is_a( $provider, __NAMESPACE__ . '\Provider\ProviderInterface' ) ) {
			$this->provider = $provider;
		}

		if ( ! empty( $source ) ) {
			if ( is_string( $source ) && class_exists( $source ) ) {
				$this->source = new $source();
			} elseif ( is_a( $source, __NAMESPACE__ . '\Source\SourceInterface' ) ) {
				$this->source = $source;
			}
		}

		add_filter( 'paginate_links', [ $this, 'sanitize_filter_array_indexes' ], 1 );
		add_filter( 'get_pagenum_link', [ $this, 'sanitize_filter_array_indexes' ], 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 0 );
	}

	/**
	 * Sanitize array indexes for cleaner pagination links
	 *
	 * @param string
	 * @return string
	 */
	public function sanitize_filter_array_indexes( $link ) : string {
		$link = preg_replace(
			[
				'/(%5B)(?:[\d])+(%5D)/',
				'/(\[)(?:[\d])+(\])/',
			],
			'$1$2',
			$link
		);

		return $link;
	}

	/**
	 * Get a link to the current page that removes all filters
	 *
	 * @return string
	 */
	public function reset_link() : string {
		global $wp;

		$query_vars = $wp->query_vars;

		if ( isset( $query_vars[ $this->provider->query_var ] ) ) {
			unset( $query_vars[ $this->provider->query_var ] );
		}

		$link = site_url( $wp->request ) . '?' . http_build_query( $query_vars );

		$link = $this->sanitize_filter_array_indexes( $link );

		return $link;
	}

	/**
	 * Expose configuration to javascript
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() : void {
		$config = [
			'facets' => $this->provider->get_facets(),
		];

		printf( "<script>this.wdg = this.wdg || {}; this.wdg.facets = %s;</script>\n", json_encode( $config ) );
	}
}
