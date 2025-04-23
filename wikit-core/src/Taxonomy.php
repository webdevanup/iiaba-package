<?php
namespace WDG\Core;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

class Taxonomy {

	/**
	 * The slug of the taxonomy
	 *
	 * @var string
	 */
	protected string $taxonomy;

	/**
	 * The object types to attach the taxonomy to (usually post types)
	 *
	 * @var array
	 */
	protected array $object_type = [];

	/**
	 * The args of the taxonomy registration
	 *
	 * @var array
	 */
	protected array $args = [];

	/**
	 * The default taxonomy args
	 *
	 * @var array
	 */
	protected static array $default_args = [
		'labels'            => [],
		'show_in_rest'      => true,   // enables taxonomy with gutenberg editor
		'hierarchical'      => true,
		'show_admin_column' => true,
	];

	/**
	 * Register a taxonomy with default labels generated from the taxonomy name
	 *
	 * @param array $props
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_taxonomy/
	 */
	public function __construct( ?string $taxonomy = null, ?array $object_type = null, ?array $args = null ) {
		if ( ! is_null( $taxonomy ) ) {
			$this->taxonomy = $taxonomy;
		}

		if ( ! is_null( $object_type ) ) {
			$this->object_type = $object_type;
		}

		if ( ! is_null( $args ) ) {
			$this->args = $args;
		}

		if ( ! $this->taxonomy ) {
			$this->taxonomy = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}

		$this->args = array_merge( static::$default_args, $this->args );

		$singular = ! empty( $this->args['labels']['singular_name'] ) ? $this->args['labels']['singular_name'] : humanize( $this->taxonomy );
		$plural   = ! empty( $this->args['labels']['name'] ) ? $this->args['labels']['name'] : ucfirst( inflector()->pluralize( $singular ) );

		$this->args['labels'] = self::get_labels( $singular, $plural, $this->args['labels'] );

		did_action( 'init' ) ? $this->register() : add_action( 'init', [ $this, 'register' ] );
	}

	public function register() {
		register_taxonomy( $this->taxonomy, $this->object_type, $this->args );
	}

	/**
	 * Get labels to be used in a register_taxonomy call
	 *
	 * @param string $singular - the singular form of the taxonomy label
	 * @param string $plural - the plural form of the taxonomy label [ default: null ]
	 * @param array $override - override any particular generated label [ default: empty array ]
	 * @return array
	 */
	public static function get_labels( $singular, $plural = null, $override = [] ) {
		$labels = [
			'name'                       => '{PLURAL}',
			'singular_name'              => '{SINGULAR}',
			'menu_name'                  => '{PLURAL}',
			'all_items'                  => 'All {PLURAL}',
			'parent_item'                => 'Parent {SINGULAR}',
			'parent_item_colon'          => 'Parent {SINGULAR}:',
			'new_item_name'              => 'New {SINGULAR}',
			'add_new_item'               => 'Add New {SINGULAR}',
			'edit_item'                  => 'Edit {SINGULAR}',
			'update_item'                => 'Update {SINGULAR}',
			'view_item'                  => 'View {SINGULAR}',
			'separate_items_with_commas' => 'Separate {PLURAL_LOWER} with commas',
			'add_or_remove_items'        => 'Add or remove {PLURAL_LOWER}',
			'choose_from_most_used'      => 'Choose from the most used',
			'popular_items'              => 'Popular {PLURAL}',
			'search_items'               => 'Search {PLURAL}',
			'not_found'                  => 'No {PLURAL} Found',
			'no_terms'                   => 'No {PLURAL_LOWER}',
			'items_list'                 => '{PLURAL} list',
			'items_list_navigation'      => '{PLURAL} list navigation',
		];

		if ( empty( $plural ) ) {
			$plural = inflector()->pluralize( $singular );
		}

		foreach ( $labels as &$label ) {
			$label = str_replace( [ '{SINGULAR}', '{PLURAL}', '{PLURAL_LOWER}' ], [ $singular, $plural, strtolower( $plural ) ], $label );
		}

		return array_merge( $labels, $override );
	}
}
