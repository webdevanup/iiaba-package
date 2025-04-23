<?php
namespace WDG\Core;

class PostType {

	/**
	 * The name (slug) of the post type
	 *
	 * @var string
	 * @access protected
	 */
	protected $post_type;

	/**
	 * The post type args used in the register_post_type call
	 *
	 * @var array
	 * @access protected
	 */
	protected $args = [];

	/**
	 * Default post type args to merge with the supplied args
	 *
	 * @var array
	 * @access private
	 */
	private $args_default = [
		'public'       => true,
		'show_in_rest' => true,
		'labels'       => [],
		'supports'     => [
			'author',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'thumbnail',
			'title',
		],
		'has_archive'  => true,
		'rewrite'      => [
			'with_front' => false,
		],
		'external_url' => false,
		'has_detail_page' => true,
	];

	/**
	 * A multidimensional array of meta fields to pass to register_meta and merged with default field arguments
	 *
	 * @var array
	 * @access protected
	 * @see https://developer.wordpress.org/reference/functions/register_meta/
	 *
	 * @example meta_key => [
	 *  'object_subtype' => $this->post_type,
	 *  'show_in_rest' => true,
	 *  'single' => true,
	 *  'type' => 'string',
	 * ]
	 */
	protected $meta = [];

	/**
	 * The default meta_field arguments to be merged with $this->meta
	 *
	 * @var array
	 * @access protected
	 */
	protected $meta_default = [
		'show_in_rest' => true,
		'single'       => true,
		'type'         => 'string',
	];

	/**
	 * The key that holds the page_for_post_type
	 * - set to false to disable the feature
	 *
	 * @var string|false
	 */
	protected $page_for;

	/**
	 * The value of the page_for option
	 *
	 * @var int
	 */
	protected $page_for_id;


	/**
	 * The posts_per_page for this post_type archive
	 *
	 * @var int
	 */
	protected $page_for_ppp;


	/**
	 * The string of the rewrite slug that is used when the page_for_post_type key is used
	 *
	 * @var string
	 */
	protected $option_rewrite_slug;

	/**
	 * The option key that holds the cached rewrite slug
	 *
	 * @var string
	 */
	protected $option_rewrite_slug_key = '';

	/**
	 * Construct the class and set any dynamic/computed properties and optionally add WordPress hooks
	 *
	 * @param array $props
	 * @param boolean $autoInit
	 * @throws \Exception
	 */
	public function __construct( ?string $post_type = null, ?array $args = null, $auto_init = true ) {
		if ( ! is_null( $post_type ) ) {
			$this->post_type = $post_type;
		}

		if ( ! is_null( $args ) ) {
			$this->args = array_merge( $this->args, $args );
		}

		if ( ! isset( $this->post_type ) ) {
			$this->post_type = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}

		if ( ! is_string( $this->post_type ) ) {
			throw new \Exception( 'invalid_argument_type - $this->post_type is not a String' );
		}

		if ( false !== $this->page_for ) {
			$this->page_for                = 'page_for_' . $this->post_type;
			$this->page_for_id             = (int) get_option( $this->page_for, 0 );
			$this->page_for_ppp            = (int) get_option( $this->page_for . '_ppp', get_option( 'posts_per_page', 10 ) );
			$this->option_rewrite_slug_key = $this->post_type . '_rewrite_slug';
			$this->option_rewrite_slug     = get_option( $this->option_rewrite_slug_key, '' );
		}

		if ( $auto_init ) {
			$this->init();
		}
	}

	public function __get( $property ) {
		if ( isset( $this->$property ) ) {
			return $this->$property;
		}
	}

	/**
	 * Initialize hooks
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		add_action( "manage_{$this->post_type}_posts_columns", [ $this, 'manage_posts_columns' ] );
		add_action( "manage_{$this->post_type}_posts_custom_column", [ $this, 'manage_custom_column' ], 1, 2 );
		add_action( 'admin_head', [ $this, 'admin_head' ] );
		add_action( 'dashboard_glance_items', [ $this, 'dashboard_glance_items' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_filter( 'rest_prepare_post_type', [ $this, 'rest_prepare_post_type' ], 10, 3 );

		if ( false !== $this->page_for ) {
			add_action( "update_option_page_for_{$this->post_type}", [ $this, 'update_option_page_for' ], 10, 3 );
			add_action( 'save_post_page', [ $this, 'save_post_page_for' ], 10, 3 );
			add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );
			add_action( 'parse_query', [ $this, 'parse_query' ] );
			add_filter( 'wpseo_frontend_page_type_simple_page_id', [ $this, 'wpseo_frontend_page_type_simple_page_id' ] );
			add_filter( 'pre_get_posts', [ $this, 'pre_get_posts_for_page' ], 8 );
		}

		if ( isset( $this->args['external_url'] ) && true === $this->args['external_url'] ) {
			add_filter( 'post_type_link', [ $this, 'external_url_post_link' ], 10, 2 );
			add_action( 'template_redirect', [ $this, 'external_url_template_redirect' ], 99 );
			add_filter( 'the_content', [ $this, 'external_url_the_content' ], 20 );

			if ( empty( $this->meta['external_url'] ) ) {
				$this->meta['external_url'] = [
					'type' => 'string',
				];
			}
		}

		if ( isset( $this->args['has_detail_page'] ) && false === $this->args['has_detail_page'] ) {
			add_filter( 'post_type_link', [ $this, 'has_detail_page_post_link' ], 10, 2 );
			add_action( 'template_redirect', [ $this, 'has_detail_page_template_redirect' ], 99 );
			add_filter( 'the_content', [ $this, 'has_detail_page_the_content' ], 20 );
		}

		if ( ! did_action( 'init' ) ) {
			add_action( 'init', [ $this, 'register_post_type' ], 1 );
			add_action( 'init', [ $this, 'register_meta' ], 20 );
		} else {
			$this->register_post_type();
			$this->register_meta();
		}
	}

	/**
	 * Add our "page for X" setting
	 *
	 * @return void
	 * @action admin_init
	 */
	public function admin_init() {
		if ( false !== $this->page_for ) {
			add_settings_field(
				$this->page_for,
				'Page for ' . $this->args['labels']['name'],
				[ $this, 'render_settings_field' ],
				'reading'
			);
		}
	}

	/**
	 * Register our post type and generate labels
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_type() {
		$this->args = array_merge( $this->args_default, $this->args );

		$this->args['labels'] = self::default_labels( $this->post_type, $this->args['labels'] );

		if ( isset( $this->template ) ) {
			$this->args['template'] = $this->template;
		}

		if ( isset( $this->template_lock ) ) {
			$this->args['template_lock'] = $this->template_lock;
		}

		if ( ! empty( $this->args['hierarchical'] ) ) {
			array_push( $this->args['supports'], 'page-attributes' );
		}

		if ( ! empty( $this->option_rewrite_slug ) ) {
			if ( ! isset( $this->args['rewrite'] ) || ! is_array( $this->args['rewrite'] ) ) {
				$this->args['rewrite'] = [];
			}

			$this->args['rewrite']['slug'] = $this->option_rewrite_slug;
		}

		register_post_type( $this->post_type, $this->args );

		// register the setting for "page_for_post_type"
		register_setting(
			'reading',
			$this->page_for,
			[
				'type'         => 'integer',
				'description'  => sprintf( 'The page for the %s archive', $this->args['labels']['name'] ),
				'show_in_rest' => true,
			],
		);

		register_setting(
			'reading',
			$this->page_for . '_ppp',
			[
				'type'         => 'integer',
				'description'  => sprintf( 'The posts per page for the %s archive', $this->args['labels']['name'] ),
				'show_in_rest' => true,
			],
		);
	}

	/**
	 * Register meta keys from $this->meta with defaults
	 *
	 * @access public
	 * @action init
	 */
	public function register_meta() {
		if ( ! empty( $this->meta ) ) {
			foreach ( $this->meta as $key => $args ) {
				register_post_meta( $this->post_type, $key, array_merge( $this->meta_default, $args ) );
			}
		}
	}

	/**
	 * Default columns to add to the list table
	 *
	 * @var array
	 * @access protected
	 */
	protected $list_table_columns = [
		'featured-image' => 'Featured Image',
	];

	/**
	 * Add custom columns in the list table
	 *
	 * @filter manage_{$this->post_type}_posts_columns
	 *
	 * @param array $columns
	 * @return array
	 * @access public
	 */
	public function manage_posts_columns( $columns ) {
		if ( ! empty( $this->list_table_columns ) ) {
			$keys = array_keys( $columns );
			$vals = array_values( $columns );

			$index = array_search( $this->list_table_columns_before, $keys, true );

			if ( $index > -1 ) {
				array_splice( $keys, $index, 0, array_keys( $this->list_table_columns ) );
				array_splice( $vals, $index, 0, array_values( $this->list_table_columns ) );

				$columns = array_combine( $keys, $vals );
			} else {
				$columns = array_merge( $columns, $this->list_table_columns );
			}
		}

		return $columns;
	}

	/**
	 * Get and store the new rewrite slug when the page_for_post_type is updated
	 *
	 * @param int $old_value
	 * @param int $value
	 * @param string $option
	 * @return void
	 * @action update_option_page_for_{$this->post_type}
	 */
	public function update_option_page_for( $old_value, $value ) {
		$value = (int) $value;

		// remove the rewrite rules so they get generated on the next request
		// - flush_rewrite_rules won't work since we're dynamically updating our post type rewrite slug
		update_option( 'rewrite_rules', '' );

		if ( empty( $value ) || 'page' !== get_post_type( $value ) || 'publish' !== get_post_status( $value ) ) {
			delete_option( $this->option_rewrite_slug_key );
			return;
		}

		update_option( $this->option_rewrite_slug_key, static::get_rewrite_slug( $value ), true );
	}

	/**
	 * Update the rewrite slug when the page_for_post_type/ancestor page is saved
	 *
	 * @param int $post_id
	 * @param \WP_Post $post
	 * @param bool $update
	 * @return void
	 */
	public function save_post_page_for( $post_id ) {
		if ( empty( $this->page_for ) || empty( $this->page_for_id ) ) {
			return;
		}

		$ancestor_ids = get_ancestors( $this->page_for_id, get_post_type( $this->page_for_id ), 'post_type' );

		array_push( $ancestor_ids, $post_id );

		// not the page for or a parent page - nothing to do
		if ( ! in_array( $post_id, $ancestor_ids, true ) ) {
			return;
		}

		$rewrite_slug = static::get_rewrite_slug( $this->page_for_id );

		// no change - flushing rewrite rules not recessary
		if ( $rewrite_slug === $this->option_rewrite_slug ) {
			return;
		}

		update_option( $this->option_rewrite_slug_key, static::get_rewrite_slug( $this->page_for_id ), true );

		// remove the rewrite rules so they get generated on the next request
		update_option( 'rewrite_rules', '' );
	}

	/**
	 * Get the url slug of a page when it used as the page_for a post_type
	 *
	 * @param int $post_id
	 * @return string
	 * @static
	 */
	public static function get_rewrite_slug( $post_id ) : string {
		$post_type = get_post_type( $post_id );

		if ( 'page' !== $post_type ) {
			return '';
		}

		$slugs     = [ get_post_field( 'post_name', $post_id ) ];
		$ancestors = get_ancestors( (int) $post_id, get_post_type( $post_id ) );

		if ( ! empty( $ancestors ) ) {
			foreach ( $ancestors as $ancestor ) {
				array_unshift( $slugs, get_post_field( 'post_name', $ancestor ) );
			}
		}

		$rewrite_slug = implode( '/', $slugs );

		return $rewrite_slug;
	}

	/**
	 * Output the content of list table column by key
	 *
	 * @filter manage_{$this->post_type}_custom_column
	 *
	 * @param array $columns
	 * @param int $post_id
	 * @return void
	 * @access public
	 */
	public function manage_custom_column( $column, $post_id ) {
		if ( ! in_array( $column, array_keys( $this->list_table_columns ), true ) ) {
			return;
		}

		switch ( $column ) {
			case 'featured-image':
				if ( has_post_thumbnail( $post_id ) ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( get_edit_post_link( $post_id ) ),
						wp_get_attachment_image(
							get_post_thumbnail_id( $post_id ),
							'thumbnail',
							true,
							[
								'style' => 'height:40px; width: 40px;',
							]
						)
					);
				}
				break;
			default:
				echo esc_html( wp_strip_all_tags( implode( ', ', get_post_meta( $post_id, $column ) ) ) );
				break;
		}
	}

	/**
	 * Fix the width of the photo column in the list table
	 *
	 * @return void
	 * @action admin_head
	 * @access public
	 */
	public function admin_head() {
		$screen = get_current_screen();

		if ( ! empty( $screen ) && $this->post_type === $screen->post_type ) :
			?>
			<style>.column-featured-image { width: 100px }</style>
			<?php
		endif;
	}

	/**
	 * Add the link and count to the at a glance dashboard widget
	 *
	 * @param array $items
	 * @return array
	 * @filter dashboard_glance_items
	 */
	public function dashboard_glance_items( $items ) {
		if ( current_user_can( get_post_type_object( $this->post_type )->cap->edit_posts ) ) {
			$count = wp_count_posts( $this->post_type );

			array_push(
				$items,
				sprintf(
					'<a class="count-%1$s" href="%2$s"><span class="dashicons %5$s"></span> %3$d %4$s</a><style>#dashboard_right_now li a.count-%1$s::before { content: none; }</style>',
					esc_attr( $this->post_type ),
					esc_url( admin_url( 'edit.php?post_type=' . $this->post_type ) ),
					$count->publish,
					( 1 === $count->publish ? $this->args['labels']['singular_name'] : $this->args['labels']['name'] ),
					$this->args['menu_icon'] ?? 'dashicons-admin-post'
				)
			);
		}

		return $items;
	}

	/**
	 * Which field should be place the admin columns before
	 *
	 * @var string
	 */
	protected $list_table_columns_before = 'date';

	/**
	 * Get the default labels for a post type modifying the post type slug to be singular/plural by default
	 * - if $args['name'] (singular) or $args['singular_name'] (plural) are provided, they will be used instead of generated
	 *
	 * @param string $post_type
	 * @param array $args
	 * @return array
	 */
	protected static function default_labels( $post_type, $args = array() ) {
		$humanized = humanize( $post_type );
		$plural    = '';
		$singular  = '';

		if ( ! empty( $args['name'] ) ) {
			$plural = trim( $args['name'] );
		} else {
			$plural = ucfirst( pluralize( $humanized ) );
		}

		if ( ! empty( $args['singular_name'] ) ) {
			$singular = $args['singular_name'];
		} else {
			$singular = ucfirst( singularize( $plural ) );
		}

		$defaults = array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'add_new'               => 'Add New',
			'add_new_item'          => 'Add New ' . $singular,
			'edit_item'             => 'Edit ' . $singular,
			'new_item'              => 'New ' . $singular,
			'view_item'             => 'View ' . $singular,
			'search_items'          => 'Search ' . $plural,
			'not_found'             => 'No ' . $plural . ' found',
			'not_found_in_trash'    => 'No ' . $plural . ' found in Trash',
			'parent_item_colon'     => 'Parent ' . $singular . ':',
			'all_items'             => 'All ' . $plural,
			'archives'              => $singular . ' Archives',
			'insert_into_item'      => 'Insert into ' . $singular,
			'uploaded_to_this_item' => 'Uploaded to this ' . $singular,
		);

		return array_merge( $defaults, $args );
	}

	/**
	 * Render the pages dropdown in the settings api
	 *
	 * @return void
	 */
	public function render_settings_field() {
		wp_dropdown_pages(
			[
				'name'              => esc_attr( $this->page_for ),
				'echo'              => true,
				'show_option_none'  => '&mdash; Select &mdash;',
				'option_none_value' => '0',
				'selected'          => esc_attr( $this->page_for_id ),
			]
		);

		printf(
			'<input name="%s" value="%d">',
			esc_attr( $this->page_for . '_ppp' ),
			esc_attr( $this->page_for_ppp ),
		);
	}

	/**
	 * Display an indicated that the page is a post type archive
	 *
	 * @param array $post_states
	 * @param \WP_Post $post
	 * @return array
	 * @access public
	 */
	public function display_post_states( $post_states, $post ) {
		if ( 'page' === $post->post_type && $this->page_for_id === $post->ID ) {
			$post_states[ $this->post_type ] = $this->args['labels']['name'] . ' Page';
		}

		return $post_states;
	}

	/**
	 * Set the queried object to be the archive page when the the page_for is being viewed
	 *
	 * @param \WP_Query $query
	 * @return void
	 * @access public
	 * @action parse_query
	 */
	public function parse_query( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_archive && $query->get( 'post_type' ) === $this->post_type ) {
			if ( empty( $this->page_for_id ) ) {
				return;
			}

			$page = get_post( $this->page_for_id );

			if ( empty( $page ) ) {
				return;
			}

			$query->queried_object    = $page;
			$query->queried_object_id = $this->page_for_id;
		}
	}

	/**
	 * Add our page_for data so it can be used in the editor
	 *
	 * @param \WP_REST_Response $response
	 * @param \WP_Post_Type $post_type
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 * @filter rest_prepare_post_type
	 */
	public function rest_prepare_post_type( $response, $post_type ) {
		if ( $this->post_type === $post_type->name ) {
			$data = $response->get_data();

			$data['page_for'] = [
				'id'    => $this->page_for_id,
				'title' => get_the_title( $this->page_for_id ),
				'link'  => get_the_permalink( $this->page_for_id ),
			];

			// add meta schema fields for the block editor
			$data['meta_schema'] = [];

			$meta_fields = get_registered_meta_keys( 'post', $this->post_type );

			foreach ( array_keys( $this->meta ) as $meta_key ) {
				// get values that might have been filtered
				$data['meta_schema'][ $meta_key ] = array_filter(
					$meta_fields[ $meta_key ],
					function ( $key ) {
						return in_array( $key, [ 'type', 'description', 'single' ], true );
					},
					ARRAY_FILTER_USE_KEY
				);

				// add in custom data
				foreach ( $data['meta_schema'] as $meta_key => &$meta_schema ) {
					$meta_schema = array_merge(
						$meta_schema,
						array_filter(
							$this->meta[ $meta_key ],
							fn( $key ) => in_array( $key, [ 'label', 'enum', 'options' ], true ),
							ARRAY_FILTER_USE_KEY
						)
					);

					if ( empty( $meta_schema['label'] ) ) {
						$meta_schema['label'] = humanize( $meta_key );
					}
				}
			}

			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Allow yoast to use our page for the meta descriptions instead of using the post type archive settings
	 *
	 * @param int $page_id
	 * @return int
	 */
	public function wpseo_frontend_page_type_simple_page_id( $page_id ) {
		if ( ! is_admin() && is_post_type_archive( $this->post_type ) && ! empty( $this->page_for_id ) ) {
			$page_id = $this->page_for_id;
		}

		return $page_id;
	}

	/**
	 * Get the page for this post type archive
	 *
	 * @return \WP_Post|null
	 */
	public function post_type_page() {
		return static::get_post_type_page( $this->post_type );
	}

	/**
	 * Get the post type page for a post type
	 *
	 * @var string $post_type
	 * @return \WP_Post|null
	 */
	public static function get_post_type_page( $post_type = null ) {
		$post_type_page = null;

		switch ( $post_type ) {
			case 'page':
				break;
			case 'post':
				$option_key = 'page_for_posts';
				break;
			default:
				$option_key = 'page_for_' . $post_type;
		}

		if ( ! empty( $option_key ) ) {
			$option = get_option( $option_key );

			if ( ! empty( $option ) ) {
				$post_type_page = get_post( $option );
			}
		}

		return $post_type_page;
	}

	/**
	 * Set posts_per_page for this post_type on the archive page
	 *
	 * @param \WP_Query $query
	 * @return void
	 * @access public
	 * @action pre_get_posts
	 */
	public function pre_get_posts_for_page( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( $this->post_type ) ) {
			$query->set( 'posts_per_page', (int) $this->page_for_ppp );
		}
	}

	/**
	 * Add note to content that it will redirect for non editors
	 *
	 * @filter template_redirect
	 *
	 * @param string $content
	 * @return string
	 * @access public
	 */
	public function external_url_the_content( $content ) {
		$post = get_queried_object_id();

		if ( get_post_type( $post ) !== $this->post_type ) {
			return $content;
		}

		$external_url = get_post_meta( $post, 'external_url', true );

		if ( $external_url && current_user_can( 'edit_post', $post ) ) {
			$external_url_text = sprintf(
				'<div style="border: 1px solid gray; padding: 20px; margin: 5px auto;"><p>This page will redirect to this URL: <a href="%s">%s</a></p></div>',
				$external_url,
				$external_url
			);

			$content = $external_url_text . $content;
		}

		return $content;
	}

	/**
	 * Redirect to External Url if used
	 *
	 * @filter template_redirect
	 *
	 * @return void
	 * @access public
	 */
	public function external_url_template_redirect() {
		$post = get_queried_object_id();

		if ( empty( $post ) ) {
			return;
		}

		if ( get_post_type( $post ) !== $this->post_type ) {
			return;
		}

		if ( current_user_can( 'edit_post', $post ) ) {
			return;
		}

		$external_url = get_post_meta( $post, 'external_url', true );

		if ( $external_url ) {
			wp_redirect( $external_url, 301, 'External URL' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	/**
	 * Swap url to external_url if used
	 *
	 * @filter post_type_link
	 *
	 * @param string $url
	 * @param \WP_Post $post
	 * @return string
	 * @access public
	 */
	public function external_url_post_link( $url, $post ) {

		if ( \is_admin() || $this->post_type !== $post->post_type ) {
			return $url;
		}

		$external_url = \get_post_meta( $post->ID, 'external_url', true );

		if ( ! empty( $external_url ) ) {
			return $external_url;
		}

		return $url;
	}

	/**
	 * Remove url if has_detatail_page is false
	 *
	 * @filter post_type_link
	 *
	 * @param string $url
	 * @param \WP_Post $post
	 * @return string
	 * @access public
	 */
	public function has_detail_page_post_link( $url, $post ) {

		if ( is_admin() || $this->post_type !== $post->post_type ) {
			return $url;
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return $url;
		}

		return '';

	}

	/**
	 * Redirect to Archive Page if has_detatail_page is false
	 *
	 * @filter template_redirect
	 *
	 * @return void
	 * @access public
	 */
	public function has_detail_page_template_redirect() {

		$post_id = get_queried_object_id();

		if ( empty( $post_id ) ) {
			return;
		}

		if ( $this->post_type !== \get_post_type( $post_id ) ) {
			return;
		}

		if ( current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		\wp_redirect( get_post_type_archive_link( $this->post_type ), 301, 'Archive Page URL' );
		exit;

	}

	/**
	 * Add note to content that there is no detail page for non editors
	 *
	 * @filter template_redirect
	 *
	 * @param string $content
	 * @return string
	 * @access public
	 */
	public function has_detail_page_the_content( $content ) {

		$post_id = get_queried_object_id();

		if ( $this->post_type !== \get_post_type( $post_id ) ) {
			return $content;
		}

		if (  is_singular() && current_user_can( 'edit_post', $post_id ) ) {
			$text = '<div style="border: 1px solid gray; padding: 20px; margin: 5px auto;"><p>This page is not set up to have a detail page.</p></div>';
			$content =  $text . $content;
		}

		return $content;

	}

}
