<?php

namespace WDG\Core;

class TaxonomyTermsControl {

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	/**
	 * Add a custom block editor plugin to replace the standard taxonomy UI
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script( 'wdg-core-taxonomy-terms-controls' );
	}

	/**
	 * Register the block_editor_control field so we can get it in the block editor
	 *
	 * @return void
	 */
	public static function rest_api_init() {
		register_rest_field(
			'taxonomy',
			'block_editor_control',
			[
				'get_callback' => [ __CLASS__, 'get_block_editor_control' ],
			]
		);
	}

	/**
	 * Gets the block editor control for the rest api
	 *
	 * @param array $data
	 * @param string $field
	 * @param WP_REST_Request $request
	 * @return string
	 */
	public static function get_block_editor_control( $data ) {
		if ( ! empty( $data['slug'] ) ) {
			$taxonomy = get_taxonomy( $data['slug'] );

			return $taxonomy->block_editor_control ?? '';
		}

		return '';
	}
}
