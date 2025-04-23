<?php

namespace WDG\Core;

/**
 * Display an admin toolbar with the environment information for debugging and alerting.
 */
class EnvironmentToolbarBase {

	use SingletonTrait;

	/**
	 * Role for basic viewing
	 *
	 * @var string
	 */
	protected $view_role = 'level_0';

	/**
	 * Role for full environment stats
	 *
	 * @var string
	 */
	protected $admin_role = 'manage_options';

	/**
	 * Environment Toolbar Colors
	 *
	 * @var array
	*/
	protected $identity = [];

	/**
	 * Environment Toolbar Colors
	 *
	 * @var array
	*/
	protected $colors = [
		'background' => '#f56e28',
		'text'       => '#fff',
	];


	/**
	 * Initialize feature classes, add static hooks, init WP CLI commands
	 *
	 * @access protected
	 */
	protected function __construct() {
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 100 ); // Try to be towards the end

		$this->identity = $this->get_identity();

		$this->colors = $this->get_colors();
	}


	/**
	 * Display environment information in the admin bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {

		// Require at least dashboard level access
		$view_role = apply_filters( 'environment_toolbar_view_role', $this->view_role );
		if ( ! current_user_can( $view_role ) ) {
			return;
		}

		$identity = $this->identity;

		// CSS ID
		$id = 'environment-toolbar';

		// Admin bar for all users
		$wp_admin_bar->add_node(
			[
				'id'    => $id,
				'title' => esc_html( $identity['label'] ),
			]
		);

		$wp_admin_bar->add_group(
			[
				'parent' => $id,
				'id'     => $id . '-current',
			]
		);

		if ( $identity['environment'] ) {
			$wp_admin_bar->add_node(
				[
					'parent' => $id . '-current',
					'id'     => $id . '-app-environment',
					'title'  => 'App: ' . esc_html( $identity['environment'] ),
				]
			);
		}

		if ( $identity['version'] ) {
			$wp_admin_bar->add_node(
				[
					'parent' => $id . '-current',
					'id'     => $id . '-version',
					'title'  => 'Version: ' . esc_html( $identity['version'] ),
				]
			);
		}

		// Admin bar for administrators (restricted information)
		$admin_role = apply_filters( 'environment_toolbar_admin_role', $this->admin_role );

		if ( current_user_can( $admin_role ) ) {
			$wp_admin_bar->add_group(
				[
					'parent' => $id,
					'id'     => $id . '-link',
				]
			);
			foreach ( $identity['links'] as $environment => $url ) {
				$wp_admin_bar->add_node(
					[
						'parent' => $id . '-link',
						'id'     => $id . '-link-' . esc_attr( $environment ),
						'title'  => 'Open in ' . esc_html( ucwords( $environment ) ) . ' &rarr;',
						'href'   => esc_url( $url ),
						'meta'   => [
							'target' => '_blank',
							'title'  => esc_attr( $url ),
						],
					]
				);
			}

			$wp_admin_bar->add_group(
				[
					'parent' => $id,
					'id'     => $id . '-meta',
				]
			);

			$wp_admin_bar->add_node(
				[
					'parent' => $id . '-meta',
					'id'     => $id . '-server',
					'title'  => 'Server: ' . esc_html( $identity['server'] ),
				]
			);

			$wp_admin_bar->add_node(
				[
					'parent' => $id . '-meta',
					'id'     => $id . '-host',
					'title'  => 'Host: ' . esc_html( $identity['host'] ),
				]
			);

			$wp_admin_bar->add_node(
				[
					'parent' => $id . '-meta',
					'id'     => $id . '-proxies',
					'title'  => 'Proxies:' . ( empty( $identity['proxies'] ) ? ' None' : '' ),
				]
			);

			foreach ( $identity['proxies'] as $index => $proxy ) {
				$wp_admin_bar->add_node(
					[
						'parent' => $id . '-meta',
						'id'     => $id . '-proxy-' . esc_attr( $index ),
						'title'  => str_repeat( '&nbsp;', 4 ) . esc_html( $proxy ),
					]
				);
			}
		}

		$colors = $this->colors;
		$style  = sprintf( '%1$s > .ab-item { background-color: %2$s !important; color: %3$s !important; }', esc_attr( '#wp-admin-bar-' . $id ), esc_attr( $colors['background'] ), esc_attr( $colors['text'] ) );
		$style .= sprintf( 'ul%1$s { background-color: %2$s; }', esc_attr( '#wp-admin-bar-' . $id . '-link' ), '#40464d' );
		$style .= sprintf( 'ul%1$s { background-color: %2$s; }', esc_attr( '#wp-admin-bar-' . $id . '-meta' ), '#555d66' );
		printf( '%2$s<style>%1$s</style>%2$s', wp_strip_all_tags( $style ), "\n" );
	}

	/**
	 * Environment Toolbar Identity
	 *
	 * @return array
	 */
	protected function get_identity() {

		if ( ! class_exists( '\WDG\App' ) ) {
			die( 'Error: missing app.php <a href="https://webdevelopmentgroup.atlassian.net/wiki/spaces/WTS/pages/321160018/WDG+App+-+App.php+and+CHANGELOG.md">https://webdevelopmentgroup.atlassian.net/wiki/spaces/WTS/pages/321160018/WDG+App+-+App.php+and+CHANGELOG.md</a>' );
		}

		$identity = [
			'type'        => wp_get_environment_type(),
			'environment' => ucwords( wp_get_environment_type() ),
			'label'       => ucwords( wp_get_environment_type() ),
			'version'     => \WDG\App::VERSION,
			'links'       => [],
			'server'      => gethostname(), // Server name
			'host'        => $_SERVER['HTTP_HOST'] ?? 'Unknown', // Host
			'proxies'     => [], // HAProxy, Varnish, Akamai, etc.
		];

		// HAProxy, Varnish, Fastly, Akamai
		if ( ! empty( $_SERVER['HTTP_X_PROXY'] ) && false !== stripos( $_SERVER['HTTP_X_PROXY'], 'haproxy' ) ) {
			$identity['proxies'][] = 'HAProxy';
		}
		if ( ! empty( $_SERVER['HTTP_X_VARNISH'] ) ) {
			$identity['proxies'][] = 'Varnish';
		}
		if ( ! empty( $_SERVER['HTTP_FASTLY_CLIENT'] ) ) {
			$identity['proxies'][] = 'Fastly';
		}
		if ( ! empty( $_SERVER['CF-RAY'] ) ) {
			$identity['proxies'][] = 'Cloudflare';
		}
		if ( ! empty( $_SERVER['HTTP_VIA'] ) && false !== stripos( $_SERVER['HTTP_VIA'], 'akamai' ) ) {
			$identity['proxies'][] = 'Akamai';
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$identity['proxies'][] = 'IPs: ' . $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		/**
		 * Environment Toolbar Identity
		 *
		 * @param array $identitiy
		 * @return array
		 */
		$this->identity = apply_filters( 'environment_toolbar_identity', $identity );

		return $this->identity;
	}

	/**
	 * Environment Toolbar Colors
	 *
	 * @return array
	 */
	protected function get_colors() {

		/**
		 * Environment Toolbar Colors
		 *
		 * @link https://codepen.io/hugobaeta/pen/RNOzoV
		 *
		 * @param array $colors Default: Orange
		 * @param array $identity
		 * @return array
		 */
		$this->colors = apply_filters( 'environment_toolbar_colors', $this->colors, $this->identity );

		return $this->colors;
	}
}
