<?php
/**
 * This class manages redirects from migrated content
 *
 * @file Redirects.php
 */
namespace WDG\Migrate;

/**
 * Redirects
 */
class Redirects {

	public static $keys = [
		'original_url',
		'original_attachment_url',
	];

	public static function template_redirect() {
		global $wpdb;

		// Only redirect posts on 404
		if ( is_404() ) {
			$request_url  = trim( urldecode( $_SERVER['REQUEST_URI'] ), '/' );  // Strip off leading and trailing slash, and decode entities (aliases didn't have it)
			$original_url = trim( strtok( $request_url, '?' ), '/' ); //
			$query        = parse_url( $request_url, PHP_URL_QUERY );

			$keys = sprintf( "'%s'", implode( "','", static::$keys ) );

			// Check original_url and original_attachment_url (from migration)
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key IN ( {$keys} ) AND meta_value LIKE '%%%s' LIMIT 1", $original_url ) );

			if ( $post_id ) {
				$status = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 302 : 301;
				wp_safe_redirect( get_permalink( $post_id ) . ( $query ? "?{$query}" : '' ), $status, 'WDG-Migrate' );
				exit;
			}

			// Nothing found, carry on
		}
	}

}
