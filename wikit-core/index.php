<?php
namespace WDG\Core;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! defined( 'WDG_CORE_PATH' ) ) {
	define( 'WDG_CORE_PATH', __DIR__ );
}

if ( ! defined( 'WDG_CORE_URI' ) ) {
	define( 'WDG_CORE_URI', get_site_url( null, str_replace( ABSPATH, '', WDG_CORE_PATH ) ) );
}

/**
 * Register the core assets and ensure the wdg namespace is present
 *
 * @return void
 * @action admin_init
 */
function admin_init() : void {
	$scripts = glob( WDG_CORE_PATH . '/js/*.js' );
	$styles  = glob( WDG_CORE_PATH . '/css/*.css' );

	if ( ! empty( $scripts ) ) {
		foreach ( $scripts as $path ) {
			$name       = basename( $path, '.js' );
			$handle     = "wdg-core-{$name}";
			$dist_path  = WDG_CORE_PATH . "/dist/js/{$name}.js";
			$dist_src   = WDG_CORE_URI . "/dist/js/{$name}.js";
			$asset_path = WDG_CORE_PATH . "/dist/js/{$name}.json";

			if ( file_exists( $dist_path ) ) {
				$asset = file_exists( $asset_path ) ? json_decode( file_get_contents( $asset_path ) ) : null;

				wp_register_script( $handle, $dist_src, $asset->dependencies ?? [], $asset->version ?? filemtime( $dist_path ), [ 'strategy' => 'defer' ] );
			}
		}
	}

	if ( ! empty( $styles ) ) {
		foreach ( $styles as $path ) {
			$name   = basename( $path, '.css' );
			$handle = "wdg-core-{$name}";
			$src    = WDG_CORE_URI . "/css/{$name}.css";

			wp_register_style( $handle, $src, [ 'dashicons' ], filemtime( $path ), 'screen' );
		}
	}
}

/**
 * Ensure the wdg js namespace is present
 *
 * @return void
 * @action admin_enqueue_scripts
 */
function admin_enqueue_scripts() : void {
	echo "<script>this.wdg = {};</script>\n";
}

/**
 * Enqueue the editor assets
 *
 * @return void
 * @action enqueue_block_assets
 */
function enqueue_block_assets() : void {
	if ( is_admin() ) {
		wp_enqueue_style( 'wdg-core-components' );
		wp_enqueue_script( 'wdg-core-components' );

		$post          = get_post();
		$front_page_id = (int) get_option( 'page_on_front' );

		static $config;

		if ( ! isset( $config ) ) {
			$config = apply_filters(
				'wdg/block-editor',
				[
					'blocks' => [],
					'config' => apply_filters(
						'wdg/block-editor/config',
						[
							'postType'      => get_current_screen()->post_type ?? '',
							'isFrontPage'   => ! empty( $post->ID ) && $post->ID === $front_page_id,
							'templateUri'   => get_template_directory_uri(),
							'stylesheetUri' => get_stylesheet_directory_uri(),
						]
					),
				],
			);

			wp_add_inline_script(
				'wdg-core-components',
				sprintf(
					'this.wdg = Object.assign( this.wdg || {}, %s );',
					wp_json_encode(
						$config,
						defined( 'SCRIPT_DEBUG' ) && ! empty( constant( 'SCRIPT_DEBUG' ) ) ? JSON_PRETTY_PRINT : 0
					)
				),
				'before'
			);
		}
	}
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts', 0 );
add_action( 'admin_init', __NAMESPACE__ . '\\admin_init', 0 );
add_action( 'enqueue_block_assets', __NAMESPACE__ . '\\enqueue_block_assets', 0 );
