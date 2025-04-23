<?php

namespace WDG\Core;

use DOMDocument;
use WP_Customize_Manager;

class Login {

	public function __construct() {
		add_action( 'customize_register', [ $this, 'customize_register' ] );
		add_action( 'login_init', [ $this, 'login_init' ] );
	}

	public function login_init() {
		add_filter( 'login_headerurl', [ $this, 'login_headerurl' ] );
		add_filter( 'login_headertext', [ $this, 'login_headertext' ] );
		add_action( 'login_head', [ $this, 'login_head' ] );
		add_filter( 'login_message', [ $this, 'login_message' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'login_enqueue_scripts' ], 1 );
	}

	/**
	 * Add our customizer fields
	 * @param WP_Customize_Manager $wp_customize - a reference to the global customizer object
	 *
	 * @action customize_register
	 * @see https://developer.wordpress.org/reference/hooks/customize_register/
	 */
	public function customize_register( WP_Customize_Manager $wp_customize ) : void {
		$capability = 'manage_options';

		/**
		 * Login Section
		 */
		$wp_customize->add_section(
			'login',
			[
				'title'       => 'Login',
				'priority'    => 100,
				'capability'  => $capability,
				'description' => 'Customize login options',
			]
		);

		// login_logo setting
		$wp_customize->add_setting(
			'login_logo',
			[
				'title'      => 'Logo',
				'capability' => 'manage_options',
				'type'       => 'option',
			]
		);

		// login_logo image control
		$wp_customize->add_control(
			new \WP_Customize_Image_Control(
				$wp_customize,
				'login_logo',
				[
					'label'       => 'Logo',
					'section'     => 'login',
					'settings'    => 'login_logo',
					'description' => 'Defaults to the logo from the Site Identity section.',
				]
			)
		);

		// login_logo_url setting
		$wp_customize->add_setting(
			'login_logo_url',
			[
				'title'      => 'Logo URL',
				'capability' => $capability,
				'type'       => 'option',
			]
		);

		// login_logo_url image control
		$wp_customize->add_control(
			new \WP_Customize_Control(
				$wp_customize,
				'login_logo_url',
				[
					'type'        => 'text',
					'label'       => 'Logo URL',
					'section'     => 'login',
					'settings'    => 'login_logo_url',
					'description' => 'Logo url - defaults to the home page.',
				]
			)
		);

		// login_background setting
		$wp_customize->add_setting(
			'login_background',
			[
				'title'      => 'Background Color',
				'capability' => $capability,
				'default'    => '',
				'type'       => 'option',
			]
		);

		// login_background control
		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'login_background',
				[
					'label'   => 'Background Color',
					'section' => 'login',
				]
			)
		);

		// login_text_color setting
		$wp_customize->add_setting(
			'login_text_color',
			[
				'title'      => 'Text Color',
				'capability' => $capability,
				'default'    => '',
				'type'       => 'option',
			]
		);

		// login_text_color control
		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'login_text_color',
				[
					'label'   => 'Text Color',
					'section' => 'login',
				]
			)
		);

		// login_link_color setting
		$wp_customize->add_setting(
			'login_link_color',
			[
				'title'      => 'Link Color',
				'capability' => $capability,
				'default'    => '',
				'type'       => 'option',
			]
		);

		// login_link_color control
		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'login_link_color',
				[
					'label'   => 'Link Color',
					'section' => 'login',
				]
			)
		);

		// login_button_color setting
		$wp_customize->add_setting(
			'login_button_color',
			[
				'title'      => 'Button Color',
				'capability' => $capability,
				'default'    => '',
				'type'       => 'option',
			]
		);

		// login_button_color control
		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'login_button_color',
				[
					'label'   => 'Button Color',
					'section' => 'login',
				]
			)
		);

		// login_button_color setting
		$wp_customize->add_setting(
			'login_button_text_color',
			[
				'title'      => 'Button Text Color',
				'capability' => $capability,
				'default'    => '',
				'type'       => 'option',
			]
		);

		// login_button_color control
		$wp_customize->add_control(
			new \WP_Customize_Color_Control(
				$wp_customize,
				'login_button_text_color',
				[
					'label'   => 'Button Text Color',
					'section' => 'login',
				]
			)
		);

		// login_message setting
		$wp_customize->add_setting(
			'login_message',
			[
				'title'      => 'Message',
				'capability' => $capability,
				'type'       => 'option',
			]
		);

		// login_message control
		$wp_customize->add_control(
			new \WP_Customize_Control(
				$wp_customize,
				'login_message',
				[
					'label'       => 'Message',
					'section'     => 'login',
					'type'        => 'textarea',
					'rows'        => 3,
					'description' => __( 'Place a fixed message on the login page.' ),
				]
			)
		);
	}

	/**
	 * Get the url of the header logo
	 *
	 * @return string
	 */
	public function login_headerurl() : string {
		$url = (string) get_option( 'login_logo_url' );

		return esc_url( $url ?: get_site_url() );
	}

	/**
	 * Get the text of the header
	 *
	 * @return string
	 */
	public function login_headertext() : string {
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Enqueue the css for the login scheme
	 *
	 * @return void
	 */
	public function login_enqueue_scripts() : void {
		wp_enqueue_style( 'wdg-core-login', WDG_CORE_URI . '/css/login.css', [], filemtime( WDG_CORE_PATH . '/css/login.css' ), 'screen' );
	}

	/**
	 * Get the configuration values
	 *
	 * @return array
	 */
	protected function get_css_vars() : array {
		$vars = [
			'logo'              => '',
			'logo-width'        => 0,
			'logo-height'       => 0,
			'background-color'  => get_option( 'login_background' ),
			'text-color'        => get_option( 'login_text_color' ),
			'link-color'        => get_option( 'login_link_color' ),
			'button-color'      => get_option( 'login_button_color' ),
			'button-text-color' => get_option( 'login_button_text_color' ),
		];

		$custom_logo = (int) get_theme_mod( 'custom_logo' );
		$login_logo  = (string) get_option( 'login_logo' ) ? attachment_url_to_postid( get_option( 'login_logo' ) ) : '';
		$logo_id     = $login_logo ?: $custom_logo ?: 0;

		if ( ! empty( $logo_id ) ) {
			$metadata  = wp_get_attachment_metadata( $logo_id );
			$logo_path = get_attached_file( $logo_id );

			if ( ! empty( $metadata ) && ! empty( $metadata['height'] ) && ! empty( $metadata['width'] ) ) {
				if ( ! empty( $metadata['sizes']['medium'] ) ) {
					$logo_width   = $metadata['sizes']['medium']['width'];
					$logo_height  = $metadata['sizes']['medium']['height'];
					$vars['logo'] = sprintf( 'url(%s)', wp_get_attachment_image_url( $logo_id, 'medium' ) );
				} else {
					$logo_width   = $metadata['width'];
					$logo_height  = $metadata['height'];
					$vars['logo'] = sprintf( 'url(%s)', wp_get_attachment_image_url( $logo_id ) );
				}
			} elseif ( file_exists( $logo_path ) && 'svg' === pathinfo( $logo_path, PATHINFO_EXTENSION ) ) {
				$doc = new DOMDocument( '1.0', 'UTF-8' );
				$doc->load( $logo_path );

				if ( $doc->documentElement ) {
					$logo_width  = (int) $doc->documentElement->getAttribute( 'width' );
					$logo_height = (int) $doc->documentElement->getAttribute( 'height' );
				}

				$vars['logo'] = sprintf( 'url(%s)', wp_get_attachment_image_url( $logo_id ) );
			}

			if ( ! empty( $logo_width ) && ! empty( $logo_height ) ) {
				$vars['logo-width']  = sprintf( '%dpx', $logo_width );
				$vars['logo-height'] = sprintf( '%dpx', $logo_height );
			}
		}

		return array_filter( $vars );
	}

	/**
	 * Output css variables in the head
	 *
	 * @return void
	 */
	public function login_head() : void {
		$vars = $this->get_css_vars();

		if ( ! empty( array_filter( $vars ) ) ) {
			echo "<style>\nbody {\n\t";
			foreach ( $vars as $var => $val ) {
				if ( ! empty( $val ) ) {
					printf( '--wdg-login-%s: %s;', esc_attr( $var ), esc_attr( $val ) );
				}
			}
			echo "\n}\n</style>\n";
		}
	}

	/**
	 * Get the message if a custom one is applied
	 *
	 * @param string $message
	 * @return null|string
	 */
	public function login_message( $message ) : ?string {
		$login_message = (string) get_option( 'login_message' );

		if ( ! empty( $login_message ) ) {
			$message = sprintf( '<div class="login-message">%s</div>', wp_kses_post( wpautop( $login_message ) ) );
		}

		return $message;
	}
}
