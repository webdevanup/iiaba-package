<?php

namespace WDG\Facets\Native;

class Settings {

	/**
	 * The slug of the settings page
	 *
	 * @var string
	 */
	protected string $settings_page = 'wdg-facets';

	/**
	 * The slug of the settings page parent
	 *
	 * @var string
	 */
	protected string $settings_page_parent = 'options-general.php';

	/**
	 * The page title of the settings page
	 *
	 * @var string
	 */
	protected string $settings_page_title = 'Facets Settings';

	/**
	 * The menu title of the settings page
	 *
	 * @var string
	 */
	protected string $settings_menu_title = 'Facets';

	/**
	 * The capability required to modify the settings
	 *
	 * @var string
	 */
	protected string $settings_capability = 'manage_options';

	/**
	 * The admin menu icon
	 */
	protected $settings_icon = 'dashicons-filter';

	/**
	 * The default menu position for the settings page
	 *
	 * @var int
	 */
	protected int $settings_position = 99;

	/**
	 * An array of slug => label for sections within the page
	 *
	 * @var array
	 */
	protected array $settings_sections = [];

	/**
	 * Array of actual setting keyed by the option slug
	 *
	 * @var array
	 */
	protected array $settings = [
		'facets_orderby'    => [
			'label'   => 'Order By',
			'type'    => 'select',
			'options' => [
				'count' => 'Count',
				'value' => 'Value (name)',
			],
			'default' => 'count',
		],
		'facets_order'      => [
			'label'   => 'Order',
			'type'    => 'select',
			'options' => [
				'desc' => 'Descending (DESC)',
				'asc'  => 'Ascending (ASC)',
			],
			'default' => 'desc',
		],
		'facets_show_all'   => [
			'label'       => 'Show all facets',
			'description' => 'Always show all facets even if they do not apply to the results',
			'type'        => 'checkbox',
			'multiple'    => false,
		],
		'facets_post_type'  => [
			'label'    => 'Post Type Facet',
			'type'     => 'checkbox',
			'multiple' => false,
		],
		'facets_taxonomies' => [
			'label'    => 'Taxonomy Facets',
			'type'     => 'checkbox',
			'multiple' => true,
			'options'  => [],
			'default'  => [
				'category',
				'post_tag',
			],
		],
		'facets_meta_keys'  => [
			'label'    => 'Meta Key Facets',
			'type'     => 'checkbox',
			'multiple' => true,
			'options'  => [],
		],
	];

	/**
	 * The save button text
	 */
	protected string $settings_save = 'Save';

	public function __construct( protected Index $index ) {
		if ( ! isset( $this->settings_page ) ) {
			throw new \Exception( 'settings_page property is required' );
		}

		add_action( 'admin_init', [ $this, 'settings_admin_init' ] );
		add_action( 'admin_menu', [ $this, 'settings_admin_menu' ], 99 );

		$this->settings_sections = array_unique(
			array_merge(
				$this->settings_sections,
				array_combine(
					array_map( 'sanitize_key', array_column( $this->settings, 'section' ) ),
					array_column( $this->settings, 'section' ),
				)
			)
		);

		foreach ( $this->settings as $name => $setting ) {
			if ( defined( strtoupper( $name ) ) ) {
				add_filter( "pre_option_$name", [ $this, 'pre_option' ], 1, 3 );
			} elseif ( ! empty( $setting['default'] ) ) {
				add_filter( "default_option_$name", [ $this, 'default_option' ], 1, 3 );
				add_filter( "option_$name", [ $this, 'option_empty' ], 1, 2 );
			}
		}
	}

	/**
	 * Register the plugin settings with the wp-settings api
	 *
	 * @return void
	 * @action admin_init
	 */
	public function settings_admin_init() {
		if ( ! empty( $this->settings_sections ) ) {
			foreach ( $this->settings_sections as $key => $label ) {
				add_settings_section( $this->settings_page . '-' . $key, $label, '__return_empty_string', $this->settings_page );
			}
		}

		add_settings_section( 'default', '', '__return_empty_string', $this->settings_page );

		$sanitize_callbacks = [
			'code'     => [ $this, 'sanitize_code' ],
			'textarea' => 'sanitize_textarea_field',
			'text'     => 'sanitize_text_field',
			'checkbox' => [ $this, 'sanitize_checkbox_field' ],
		];

		$register_default = [
			'type'         => 'string',
			'description'  => '',
			'show_in_rest' => false,
			'default'      => '',
		];

		foreach ( $this->settings as $name => $setting ) {
			register_setting(
				$this->settings_page,
				$name,
				array_merge(
					$register_default,
					[
						'sanitize_callbacks' => $sanitize_callbacks[ $setting['field_type'] ?? 'text' ] ?? $sanitize_callbacks['text'],
					],
					array_intersect_key( $setting, $register_default ),
				)
			);

			add_settings_field(
				$name,
				$setting['label'] ?? $name,
				[ $this, 'render_field' ],
				$this->settings_page,
				! empty( $setting['section'] ) ? $this->settings_page . '-' . sanitize_key( $setting['section'] ) : 'default',
				array_merge( $setting, [ 'name' => $name ] )
			);

			add_action( "update_option_{$name}", [ $this, 'update_option' ], 10, 3 );
		}
	}

	/**
	 * Add our menu page with a custom svg icon
	 *
	 * @return void
	 * @action admin_menu
	 */
	public function settings_admin_menu() {
		if ( ! empty( $this->settings_page_parent ) ) {
			add_submenu_page( $this->settings_page_parent, $this->settings_page_title, $this->settings_menu_title, $this->settings_capability, $this->settings_page, [ $this, 'render_settings_page' ], $this->settings_position );
		} else {
			add_menu_page( $this->settings_page_title, $this->settings_menu_title, $this->settings_capability, $this->settings_page, [ $this, 'render_settings_page' ], $this->settings_icon, $this->settings_position );
		}
	}

	/**
	 * Actually render the settings page by page key
	 *
	 * @param array $args
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( $this->settings_capability ) ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_script( 'wp-api-fetch' );

		if ( ! empty( get_option( 'facets_index_required' ) ) ) {
			add_settings_error(
				$this->settings_page,
				$this->settings_page . '_index_required',
				__( 'The index needs to be rebuilt.' ),
				'error'
			);
		}

		if ( ! $this->index->exists() ) {
			add_settings_error(
				$this->settings_page,
				$this->settings_page . '_not_exists',
				sprintf(
					// translators: the url for index creation
					__( 'The Facet index does not exist. <a href="%s">Click here</a> to create.' ),
					add_query_arg(
						[
							'action'   => 'wdg_facets_index',
							'_wpnonce' => wp_create_nonce( 'wdg_facets_index' ),
						],
						admin_url( 'admin-post.php' )
					)
				),
				'error'
			);
		}

		settings_errors( $this->settings_page );
		?>
		<div class="wrap">
			<h1><?= esc_html( get_admin_page_title() ); ?></h1>

			<form action="<?= esc_url( $this->get_post_url() ) ?>" method="POST">
				<table class="form-table" role="presentation">
					<?php if ( $this->index->exists() ) : ?>
						<tr>
							<th>
								<?= esc_html__( 'Statistics' ); ?>
							</th>
							<td>
								<strong>Index Size</strong>: <span class="index-size"><?= esc_html( $this->index->stats()['total'] ); ?></span>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th>
							<?= esc_html__( 'Actions' ); ?>
						</th>
						<td>
							<fieldset>
								<button disabled data-action="create" type="button" class="components-button is-primary"<?= $this->index->exists() ? ' style="display: none"' : ''; ?>>
									<?= esc_html__( 'Create Index' ); ?>
								</button>
								<button disabled data-action="rebuild" type="button" class="components-button is-secondary"<?= ! $this->index->exists() ? ' style="display: none"' : ''; ?>>
									<?= esc_html__( 'Rebuild Index' ); ?>
								</button>
								<button disabled data-action="wipe" type="button" class="components-button is-secondary"<?= ! $this->index->exists() ? ' style="display: none"' : ''; ?>>
									<?= esc_html__( 'Wipe Index' ); ?>
								</button>
							</fieldset>

							<progress hidden value="0" max="1" style="width: 100%; margin-top: 1em;">
						</td>
					</tr>
				</table>

				<?php
					settings_fields( $this->settings_page );
					do_settings_sections( $this->settings_page );
					submit_button( $this->settings_save );
				?>
			</form>

			<?php
			global $wp_filesystem;
			WP_Filesystem();

			$script_path = realpath( __DIR__ . '/../../js/native-settings.js' );

			if ( file_exists( $script_path ) ) :
				?>
				<script>
					<?= $wp_filesystem->get_contents( $script_path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</script>
				<?php
			endif;
			?>
		</div>
		<?php
	}

	/**
	 * Get the URL the settings form should post to
	 *
	 * @return string
	 */
	protected function get_post_url() : string {
		return admin_url( 'options.php' );
	}

	/**
	 * Render the field shell while deferring to other methods to render the actual field
	 *
	 * @param array
	 * @return void
	 */
	public function render_field( $args ) : void {
		$const   = strtoupper( $args['name'] );
		$defined = defined( $const );

		$prepare = ! empty( $args['prepare'] ) ? $args['prepare'] : [ $this, 'prepare_' . $args['name'] ];

		if ( is_callable( $prepare ) ) {
			$args = call_user_func( $prepare, $args );
		}

		if ( ! empty( $args['render'] ) ) {
			if ( is_callable( [ $this, $args['render'] ] ) ) {
				$render = [ $this, $args['render'] ];
			} elseif ( is_callable( $args['render'] ) ) {
				$render = $args['render'];
			}
		}

		if ( empty( $render ) && ! empty( $args['type'] ) && is_callable( [ $this, 'render_' . $args['type'] . '_field' ] ) ) {
			$render = [ $this, 'render_' . $args['type'] . '_field' ];
		}

		if ( empty( $render ) || ! is_callable( $render ) ) {
			$render = [ $this, 'render_text_field' ];
		}

		echo call_user_func( $render, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $args['description'] ) ) {
			echo wp_kses_post( wpautop( sprintf( '<em><small>%s</small></em>', $args['description'] ) ) );
		}

		if ( $defined ) {
			echo wp_kses_post( static::defined_msg( $const ) );
		} elseif ( ! empty( $args['default'] ) ) {
			echo wp_kses_post( wpautop( sprintf( '<em><small>Default: %s</small></em>', implode( ', ', (array) $args['default'] ) ) ) );
		}
	}

	/**
	 * Callback for rendering a text field
	 *
	 * @param array $args
	 * @return string
	 */
	public static function render_text_field( $args ) : string {
		$const   = strtoupper( $args['name'] );
		$defined = defined( $const );

		return sprintf(
			'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" class="widefat"%4$s>',
			esc_attr( $args['type'] ?? 'text' ),
			esc_attr( $args['name'] ),
			esc_attr( static::get_option( $args['name'] ) ),
			$defined ? ' disabled' : ''
		);
	}

	/**
	 * Callback for rendering a textarea field
	 *
	 * @param array $args
	 * @return void
	 */
	public static function render_textarea_field( $args ) {
		$const   = strtoupper( $args['name'] );
		$defined = defined( $const );

		return sprintf(
			'<textarea id="%1$s" name="%1$s" cols="40" rows="10" class="widefat"%3$s>%2$s</textarea>',
			esc_attr( $args['name'] ),
			esc_textarea( static::get_option( $args['name'] ) ),
			$defined ? ' disabled' : ''
		);
	}

	/**
	 * Callback for rendering a code field with codemirror
	 *
	 * @param array $args
	 * @return void
	 */
	public static function render_code_field( $args ) {
		wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
		wp_enqueue_style( 'code-editor' );
		wp_add_inline_script(
			'code-editor',
			sprintf(
				"wp.codeEditor.initialize( document.getElementById( '%s' ), { codemirror: { lineNumbers: true, lineWrapping: true } } );",
				$args['name']
			)
		);

		return sprintf(
			'<textarea id="%s" name="%s" cols="%s" rows="%s">%s</textarea>',
			esc_attr( $args['name'] ),
			esc_attr( $args['name'] ),
			esc_attr( $args['cols'] ?? 70 ),
			esc_attr( $args['rows'] ?? 70 ),
			esc_textarea( wp_unslash( static::get_option( $args['name'] ) ) )
		);
	}

	/**
	 * Callback for rendering a select field
	 *
	 * @param array $args
	 * @return void
	 */
	public static function render_select_field( $args ) {
		$value = static::get_option( $args['name'] );

		$options = '';

		foreach ( $args['options'] as $option => $label ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>' . "\n",
				esc_attr( $option ),
				selected( $option, $value, false ),
				esc_attr( $label )
			);
		}

		return sprintf(
			'<select class="widefat" id="%1$s" name="%1$s"%2$s>%3$s</select>',
			esc_attr( $args['name'] ),
			defined( strtoupper( $args['name'] ) ) ? ' disabled' : '',
			$options
		);
	}

	/**
	 * Render a checkbox field
	 *
	 * @param array args
	 * @return void
	 */
	public static function render_checkbox_field( $args ) {
		if ( empty( $args['name'] ) ) {
			return;
		}

		$value = (array) static::get_option( $args['name'] );

		if ( $args['multiple'] ) {
			$mrkp = '';

			foreach ( $args['options'] as $option => $label ) {
				$mrkp .= sprintf(
					'<label><input type="checkbox" name="%s[]" value="%s" %s%s> %s</label><br>',
					esc_attr( $args['name'] ),
					esc_attr( $option ),
					checked( in_array( $option, $value, true ), true, false ),
					! empty( $args['disabled'] ) ? ' disabled' : '',
					esc_html( $label )
				);
			}

			return $mrkp;
		}

		return sprintf(
			'<label><input type="checkbox" name="%s"%s%s> %s</label>',
			esc_attr( $args['name'] ),
			checked( (bool) static::get_option( $args['name'] ), true, false ),
			! empty( $args['disabled'] ) ? ' disabled' : '',
			esc_html( $args['label'] )
		);
	}

	/**
	 * Sanitize a code based textarea by adding some additional tags to $allowedposttags and wp_kses
	 *
	 * @param string $code
	 * @return string
	 */
	public static function sanitize_code( $code ) {
		global $allowedposttags;

		$allowedcodetags = array_merge(
			$allowedposttags,
			[
				'input' => [
					'type'  => true,
					'name'  => true,
					'value' => true,
					'id'    => true,
				],
				'form'  => [
					'action' => true,
					'method' => true,
					'id'     => true,
				],
			]
		);

		return wp_kses( $code, $allowedcodetags );
	}

	/**
	 * Get a note for a defined constant
	 *
	 * @param string
	 * @return string
	 */
	public static function defined_msg( $constant, $pattern = 'This value is defined in config as %s' ) {
		return sprintf( '<br><small><em>' . $pattern . '</em></small>', $constant );
	}

	/**
	 * Get the value of the option, deferring to the constant
	 *
	 * @param string $key
	 * @param mixed $default_value
	 * @return mixed
	 */
	public static function get_option( $key, $default_value = false ) {
		return get_option( $key, $default_value );
	}

	/**
	 * Filter any get_option calls with the defined value
	 *
	 * @param mixed $value
	 * @param string $option
	 * @param mixed $default_value
	 * @return mixed
	 */
	public static function pre_option( $value, $option, $default_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$const = strtoupper( $option );

		if ( defined( $const ) ) {
			$value = constant( $const );
		}

		return $value;
	}

	/**
	 * Filter any default_option calls with the default values
	 *
	 * @param mixed $value
	 * @param string $option
	 * @param mixed $default_value
	 * @return mixed
	 */
	public function default_option( $value, $option, $default_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $passed ) && ! empty( $this->settings[ $option ]['default'] ) ) {
			$value = $this->settings[ $option ]['default'];
		}

		return $value;
	}

	/**
	 * Replace any empty values with the defaults
	 *
	 * @param mixed $value
	 * @param string $option
	 * @param mixed $default
	 * @return mixed
	 */
	public function option_empty( $value, $option ) {
		if ( empty( $value ) && ! empty( $this->settings[ $option ]['default'] ) ) {
			$value = $this->settings[ $option ]['default'];
		}

		return $value;
	}

	/**
	 * Get distinct meta keys from the postmeta table
	 *
	 * @param array
	 * @return array
	 */
	public function prepare_facets_meta_keys( $args ) {
		global $wpdb;

		$options         = $wpdb->get_col( "SELECT DISTINCT meta_key from $wpdb->postmeta WHERE meta_key NOT LIKE '\_%'" );
		$args['options'] = array_combine( $options, $options );

		return $args;
	}

	/**
	 * Get the list of available taxonomies for the settings
	 *
	 * @param array @args
	 * @return array
	 */
	public function prepare_facets_taxonomies( $args ) {
		$args['options'] = array_column( get_taxonomies( [], 'objects' ), 'label', 'name' );

		return $args;
	}

	/**
	 * Toggle a flag in the database to indicate re-indexing might be necessary
	 *
	 * @return void
	 */
	public function update_option() : void {
		update_option( 'facets_index_required', true );
	}
}
