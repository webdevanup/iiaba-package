<?php

namespace WDG\Core;

use Walker_Nav_Menu_Checklist;
use WP_Customize_Control;
use WP_Customize_Manager;
use WP_Post;

/**
 * The authentication class applies to blocks, posts, nav menu items
 *
 * Override properties and/or methods for custom access rules if you child class
 */
class Authentication {

	/**
	 * The key that indicates if an object is restricted - used in attributes and meta
	 *
	 * @var string
	 */
	public string $status_key = 'authentication_status';

	/**
	 * The list of available statues
	 *
	 * @var array
	 */
	public array $statuses = [
		''                => 'Public',
		'authenticated'   => 'Authenticated',
		'unauthenticated' => 'Unauthenticated',
	];

	/**
	 * The key that indicates what type of restriction is applied
	 *
	 * @var string
	 */
	public string $restrictions_key = 'authentication_restrictions';

	/**
	 * The key => data list of restrictions
	 *
	 * data:
	 *   - label: the friendly name of the key
	 *   - type: the type of restriction used in processing
	 *
	 * @var array
	 */
	public array $restrictions = [];

	/**
	 * Should the wordpress roles be used as restrictions
	 *
	 * @var bool
	 */
	public bool $use_wp_roles = true;

	/**
	 * Authentication Menu Items to be added via nav menu
	 *
	 * @var array
	 */
	public array $menu_items = [
		'login'        => 'Login',
		'logout'       => 'Logout',
		'login-logout' => 'Login / Logout',
	];

	/**
	 * Customizer settings by key with type, label, default value
	 *
	 * @var array
	 * @see $this->customize_register
	 */
	public array $customize_settings = [
		'wdg_authentication_heading' => [
			'label'   => 'Access Denied Heading',
			'type'    => 'text',
			'default' => 'Access Denied',
		],
		'wdg_authentication_content' => [
			'label'   => 'Access Denied Content',
			'type'    => 'textarea',
			'default' => 'Sorry, but you do not have permission to view this content. Please contact the site administrator if this is an error.',
		],
	];

	public function __construct() {
		add_action( 'current_screen', [ $this, 'current_screen' ] );
		add_action( 'customize_register', [ $this, 'customize_register' ] );
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_block_assets' ] );
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'render_block_wdg/gated-else', [ $this, 'render_block_gated' ], 1, 3 );
		add_action( 'render_block_wdg/gated-if', [ $this, 'render_block_gated' ], 1, 3 );
		add_action( 'render_block', [ $this, 'render_block' ], 1, 3 );
		add_action( 'template_redirect', [ $this, 'template_redirect' ] );
		add_filter( 'register_block_type_args', [ $this, 'register_block_type_args' ], 1, 2 );
		add_filter( 'wp_nav_menu_objects', [ $this, 'wp_nav_menu_objects' ], 1, 4 );
		add_filter( 'wp_setup_nav_menu_item', [ $this, 'wp_setup_nav_menu_item' ], 1 );
	}

	/**
	 * Replace the content of the post with an access denied message
	 *
	 * Use a template part in the theme to customize the template more than the default heading + paragraph
	 *
	 * - views/access-denied.php
	 * - parts/access-denied.php
	 * - template-parts/access-denied.php
	 * - access-denied.php
	 *
	 * @param string $content
	 * @return string
	 */
	public function access_denied_content( $content ) {
		if ( get_post() !== get_queried_object() ) {
			return $content;
		}

		$auth_heading = get_option( 'wdg_authentication_heading', $this->customize_settings['wdg_authentication_heading']['default'] ?? '' );
		$auth_content = wpautop( do_shortcode( get_option( 'wdg_authentication_content', $this->customize_settings['wdg_authentication_content']['default'] ?? '' ) ) );

		$views = [
			'views/access-denied',
			'parts/access-denied',
			'template-parts/access-denied',
			'access-denied',
		];

		foreach ( $views as $view ) {
			if ( file_exists( get_theme_file_path( $view . '.php' ) ) ) {
				ob_start();

				get_template_part(
					$view,
					null,
					[
						'heading' => $auth_heading,
						'content' => $auth_content,
					]
				);

				return ob_get_clean();
			}
		}

		return sprintf(
			'<div class="wdg-access-denied"><h1>%s</h1>%s</div>',
			esc_html( $auth_heading ),
			wp_kses_post( $auth_content )
		);
	}

	/**
	 * Register the auth meta box on the nav-menus screen
	 *
	 * @param WP_Screen $screen
	 * @return void
	 */
	public function current_screen() : void {
		add_meta_box( 'authentication-menu-items', 'Authentication', [ $this, 'nav_menu_meta_box' ], 'nav-menus', 'side', 'default' );
	}

	/**
	 * Add our customizer fields
	 *
	 * @param WP_Customize_Manager $wp_customize - a reference to the global customizer object
	 * @return void
	 * @see https://developer.wordpress.org/reference/hooks/customize_register/
	 * @action customize_register
	 */
	public function customize_register( WP_Customize_Manager $wp_customize ) : void {
		$wp_customize->add_section(
			'wdg-authentication',
			[
				'title'      => 'Authentication',
				'priority'   => 40,
				'capability' => 'manage_options',
			]
		);

		foreach ( $this->customize_settings as $name => $setting ) {
			$wp_customize->add_setting(
				$name,
				[
					'title'      => $setting['label'] ?? $name,
					'capability' => 'manage_options',
					'default'    => $setting['default'] ?? '',
					'type'       => 'option',
				]
			);

			$wp_customize->add_control(
				new WP_Customize_Control(
					$wp_customize,
					$name,
					[
						'section' => 'wdg-authentication',
						'label'   => $setting['label'] ?? $name,
						'type'    => $setting['type'] ?? 'text',
					]
				)
			);
		}
	}

	/**
	 * Get configuration for the block editor
	 *
	 * @return array
	 */
	public function get_config() : array {
		return apply_filters(
			'wdg/authentication/config',
			[
				'status_key'       => $this->status_key,
				'restrictions_key' => $this->restrictions_key,
				'statuses'         => $this->statuses,
				'restrictions'     => $this->restrictions,
			]
		);
	}

	/**
	 * Get the login url
	 *
	 * @param string $redirect - where to go after logging in
	 * @return string
	 */
	public function get_login_url( string $redirect = '' ) : string {
		return apply_filters( 'wdg/authentication/login_url', wp_login_url( $redirect, false ) );
	}

	/**
	 * Get the logout url
	 *
	 * @param string $redirect - where to go after logging out
	 * @return string
	 */
	public function get_logout_url( string $redirect = '' ) : string {
		return apply_filters( 'wdg/authentication/logout_url', wp_logout_url( $redirect ) );
	}

	/**
	 * Load wp roles and create the menu item fields
	 *
	 * @return void
	 */
	public function init() : void {
		if ( $this->use_wp_roles ) {
			// fill in restrictions with wp_roles
			foreach ( wp_roles()->roles as $key => $role ) {
				$this->restrictions[ $key ] = [
					'label' => $role['name'],
					'type'  => 'role',
				];
			}
		}

		MenuItemField::create(
			$this->status_key,
			[
				'label'   => 'Authentication',
				'type'    => 'select',
				'options' => $this->statuses,
			]
		);

		MenuItemField::create(
			$this->restrictions_key,
			[
				'label'   => 'Authenticated Restrictions',
				'type'    => 'checkboxes',
				'options' => array_combine( array_keys( $this->restrictions ), array_column( $this->restrictions, 'label' ) ),
			]
		);

		do_action( 'wdg/authentication/init', $this );
	}

	/**
	 * Configure the core authentication script
	 *
	 * @return void
	 */
	public function admin_init() : void {
		wp_add_inline_script( 'wdg-core-authentication', sprintf( 'this.wdg.config = this.wdg.config || {}; this.wdg.config.authentication = %s;', wp_json_encode( $this->get_config() ) ), 'before' );
	}

	/**
	 * Enqueue the authentication components
	 *
	 * @return void
	 * @action enqueue_block_assets
	 */
	public function enqueue_block_assets() : void {
		if ( is_admin() ) {
			wp_enqueue_script( 'wdg-core-authentication' );
		}
	}

	/**
	 * Is the current user considered to be logged in - useful for logins not based on wordpress users
	 *
	 * @param bool $include_wp_user - should the wordpress login status be considered
	 * @return bool
	 */
	public function is_user_logged_in( bool $include_wp_user = true ) : bool {
		if ( $include_wp_user && is_user_logged_in() ) {
			return true;
		}

		return false;
	}

	/**
	 * Render the meta box of the authentication
	 *
	 * @return void
	 */
	public function nav_menu_meta_box() : void {
		global $nav_menu_selected_id;

		$menu_items = array_map(
			'wp_setup_nav_menu_item',
			array_map(
				function ( $key ) {
					return (object) [
						'db_id'            => 0,
						'object'           => 'authentication',
						'object_id'        => $key,
						'menu_item_parent' => 0,
						'type'             => 'authentication',
						'title'            => $this->menu_items[ $key ],
						'url'              => '',
						'target'           => '',
						'attr_title'       => '',
						'classes'          => [],
						'xfn'              => '',
					];
				},
				array_keys( $this->menu_items )
			)
		);

		?>
		<div id="authentication-links" class="authenticationlinksdiv">
			<div id="tabs-panel-authentication-links-all" class="tabs-panel tabs-panel-view-all tabs-panel-active">
				<ul id="authentication-linkschecklist" class="list:authentication-links categorychecklist form-no-clear">
					<?= walk_nav_menu_tree( $menu_items, 0, (object) array( 'walker' => new Walker_Nav_Menu_Checklist( [] ) ) ); ?>
				</ul>
			</div>
			<p class="button-controls">
				<span class="add-to-menu">
					<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'lolmi' ); ?>" name="add-authentication-links-menu-item" id="submit-authentication-links" />
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Register the gated blocks
	 *
	 * @return void
	 */
	public function register_blocks() : void {
		register_block_type_from_metadata( WDG_CORE_PATH . '/js/blocks/gated-block.json' );
		register_block_type_from_metadata( WDG_CORE_PATH . '/js/blocks/gated-if-block.json' );
		register_block_type_from_metadata( WDG_CORE_PATH . '/js/blocks/gated-else-block.json' );
	}

	/**
	 * Register server side support for block authentication attributes
	 *
	 * @param array $args
	 * @param string $name
	 * @return array
	 */
	public function register_block_type_args( $args ) {
		if ( ! isset( $args['supports']['authentication'] ) ) {
			$args['supports']['authentication'] = true;
		}

		if ( ! empty( $args['supports']['authentication'] ) ) {
			$args['attributes'] = array_merge(
				$args['attributes'],
				[
					$this->status_key       => [
						'type'    => 'string',
						'default' => '',
						'enum'    => array_keys( $this->statuses ),
					],
					$this->restrictions_key => [
						'type'    => 'array',
						'items'   => [
							'type' => 'string',
							'enum' => array_keys( $this->restrictions ),
						],
						'default' => [],
					],
				]
			);
		}

		$args['uses_context'] = array_merge(
			$args['uses_context'] ?? [],
			[
				'wdg/gated/context',
				'wdg/gated/restrictions',
			]
		);

		return $args;
	}

	/**
	 * Register the authentication meta values for a post
	 *
	 * @return void
	 */
	public function register_meta() : void {
		register_meta(
			'post',
			$this->status_key,
			[
				'type'         => 'string',
				'default'      => '',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
						'enum' => array_column( $this->statuses, 'key' ),
					],
				],
			]
		);

		register_meta(
			'post',
			$this->restrictions_key,
			[
				'type'         => 'array',
				'default'      => [],
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'string',
							'enum' => array_keys( $this->restrictions ),
						],
					],
				],
			]
		);
	}

	/**
	 * Disable block content if the user does not pass restriction checks
	 *
	 * @param string $content
	 * @param array $block
	 * @param WP_Block_Type $block_type
	 * @return string
	 */
	public function render_block( $content, $block ) : string {
		if ( ! empty( $block['attrs'][ $this->status_key ] ) ) {
			if ( ! $this->user_has_access( $block['attrs'][ $this->status_key ], $block['attrs'][ $this->restrictions_key ] ?? [] ) ) {
				$content = '';
			}
		}

		return (string) $content;
	}

	/**
	 * Apply the gated if/else permissions based on the parent block context
	 *
	 * @param string $content
	 * @param array $parsed_block
	 * @param WP_Block $block
	 */
	public function render_block_gated( $content, $parsed_block, $block ) {
		if ( ! empty( $block->context ) ) {
			$has_access = $this->user_has_access( $block->context['wdg/gated/status'] ?? '', $block->context['wdg/gated/restrictions'] ?? [] );

			if ( 'wdg/gated-if' === $block->name && ! $has_access ) {
				$content = '';
			}

			if ( 'wdg/gated-else' === $block->name && $has_access ) {
				$content = '';
			}
		}

		return $content;
	}

	/**
	 * Redirect restricted content to the login page
	 *
	 * @return void
	 */
	public function template_redirect() : void {
		$queried_object = get_queried_object();

		if ( ! $queried_object instanceof WP_Post ) {
			return;
		}

		$status       = get_post_meta( $queried_object->ID, $this->status_key, true );
		$restrictions = array_filter( (array) get_post_meta( $queried_object->ID, $this->restrictions_key, true ) );
		$has_access   = $this->user_has_access( $status, $restrictions );

		if ( $has_access ) {
			return;
		}

		if ( ! $has_access && is_user_logged_in() ) {
			add_body_class( 'wdg-access-denied' );
			add_filter( 'the_content', [ $this, 'access_denied_content' ] );
		}
	}

	/**
	 * Does the current user have access to a status and restriction
	 *
	 * @param string $status
	 * @param array $restrictions
	 * @return bool
	 */
	public function user_has_access( string $status, array $restrictions = [] ) : bool {
		$access = true;

		if ( 'cli' !== php_sapi_name() && ! empty( $status ) ) {
			$logged_in = $this->is_user_logged_in();

			if ( 'unauthenticated' === $status ) {
				$access = ! $logged_in;
			} elseif ( 'authenticated' === $status ) {
				if ( ! $logged_in ) {
					$access = false;
				} elseif ( empty( $restrictions ) ) {
					$access = true;
				} else {
					$user   = wp_get_current_user();
					$roles  = (array) $user->roles;
					$access = false;

					foreach ( $restrictions as $restriction ) {
						if ( isset( $this->restrictions[ $restriction ] ) ) {
							if ( 'role' === ( $this->restrictions[ $restriction ]['type'] ?? '' ) && in_array( $restriction, $roles, true ) ) {
								$access = true;
								break;
							}
						}
					}
				}
			}
		}

		return apply_filters( 'wdg/authentication/user_has_access', $access, $status, $restrictions );
	}

	/**
	 * Filter out menu items based on their settings
	 *
	 * @param array $items
	 * @param \StdClass $args
	 * @return array
	 */
	public function wp_nav_menu_objects( $items ) {
		$items = array_values(
			array_filter(
				$items,
				function ( $item ) {
					$status = get_post_meta( $item->ID, $this->status_key, true );

					if ( empty( $status ) ) {
						return true;
					}

					$restrictions = array_filter( (array) get_post_meta( $item->ID, $this->restrictions_key, true ) );
					$has_access   = $this->user_has_access( $status, $restrictions );

					return $has_access;
				}
			),
		);

		return $items;
	}

	/**
	 * Modify the dynamic nav menu items
	 *
	 * @param WP_Post $item
	 * @return WP_Post
	 */
	public function wp_setup_nav_menu_item( $item ) {
		if ( 'authentication' === $item->type ) {
			$item->type_label = 'Authentication';

			if ( ! empty( $item->post_title ) ) {
				if ( 'Login' === $item->post_title ) {
					$item->url = $this->get_login_url();
				} elseif ( 'Logout' === $item->post_title ) {
					$item->url = $this->get_logout_url();
				} elseif ( 'Login / Logout' === $item->post_title && ! is_admin() ) {
					if ( $this->is_user_logged_in() ) {
						$item->url   = $this->get_logout_url();
						$item->title = 'Logout';
					} else {
						$item->url   = $this->get_login_url();
						$item->title = 'Login';
					}
				}
			}
		}

		return $item;
	}
}
