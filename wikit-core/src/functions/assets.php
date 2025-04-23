<?php
namespace WDG\Core;

/**
 * Setup a script handle to have the defer attribute
 *
 * @param string $handle
 * @param string $strategy (default: defer)
 * @return void
 */
function defer_script( string $handle, string $strategy = 'defer' ) : void {
	if ( ! did_action( 'wp_enqueue_scripts' ) ) {
		$fn = __FUNCTION__;
		add_action( 'wp_enqueue_scripts', fn() => call_user_func_array( $fn, func_get_args() ) );
	} else {
		wp_scripts()->add_data( $handle, 'strategy', $strategy ?? 'defer' );
	}
}

/**
 * automatic-versioning with filemtime for theme assets but allowing for explicit versions
 *
 * @param string $url
 * @param string $base_path
 * @param string $base_uri
 * @return string
 */
function get_modified_asset_url( string $url, string $base_path = ABSPATH, ?string $base_uri = null ) : string {
	$base_uri ??= site_url();

	if ( str_starts_with( $url, $base_uri ) ) {
		$params          = parse_url( $url );
		$src             = $params['scheme'] . '://' . $params['host'] . $params['path'];
		$default_version = get_bloginfo( 'version' );

		parse_str( $params['query'] ?? '', $query );

		if ( ! empty( $query['ver'] ) && $query['ver'] === $default_version ) {
			$path = rtrim( $base_path, '/' ) . '/' . ltrim( str_replace( $base_uri, '', $src ) );

			if ( file_exists( $path ) ) {
				$url = $src . '?' . http_build_query( array_merge( $query, [ 'ver' => filemtime( $path ) ] ) );
			}
		}
	}

	return $url;
}

/**
 * Modify stylesheet tags
 * - remove un-necessary ids when debug is off and the user is logged in
 *
 * @param string $tag
 * @param string $handle
 * @param string $src
 * @return string
 */
function style_loader_tag( string $tag, string $handle, string $src ) : string {
	if ( ! WP_DEBUG || ! is_user_logged_in() ) {
		$tag = str_replace( sprintf( " id='%s-css'", $handle ), '', $tag );
	}

	// multiple spaces between attributes
	$tag = preg_replace( '/([\'"]\s)\s+/', '$1', $tag );

	// auto-version
	if ( str_starts_with( $src, get_site_url() ) ) {
		$tag = str_replace( $src, get_modified_asset_url( $src ), $tag );
	}

	// self closing tags
	$tag = preg_replace( '/\s+\/>$/', '>', $tag );

	return $tag;
}

/**
 * Modify script tags
 * - remove un-necessary ids when debug is off and the user is logged in
 *
 * @param string $tag
 * @param string $handle
 * @param string $src
 * @return string
 */
function script_loader_tag( string $tag, string $handle, string $src ) : string {
	if ( ! WP_DEBUG || ! is_user_logged_in() ) {
		$tag = str_replace( sprintf( " id='%s-js'", $handle ), '', $tag );
	}

	// auto-version
	if ( str_starts_with( $src, get_site_url() ) ) {
		$tag = str_replace( $src, get_modified_asset_url( $src ), $tag );
	}

	// remove type attribute if un-necessary
	$tag = str_replace( ' type="text/javascript"', '', $tag );

	return $tag;
}
