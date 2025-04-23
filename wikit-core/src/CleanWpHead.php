<?php

namespace WDG\Core;

class CleanWpHead {

	public array $hooks = [
		'rsd_link',
		'wlwmanifest_link',
		'index_rel_link',
		'parent_post_rel_link',
		'start_post_rel_link',
		'adjacent_posts_rel_link_wp_head',
		'wp_generator',
		'rest_output_link_wp_head',
		'wp_filter_oembed_result',
		'wp_oembed_add_discovery_links',
		'wp_oembed_add_host_js',
		'wp_shortlink_wp_head',
	];

	public function __construct() {
		did_action( 'template_redirect' ) ? $this->exec() : add_action( 'template_redirect', [ $this, 'exec' ] );
	}

	public function exec() {
		if ( in_array( 'rsd_link', $this->hooks, true ) ) {
			// really-simple-discovery link
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( in_array( 'wlwmanifest_link', $this->hooks, true ) ) {
			// manifest link
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( in_array( 'index_rel_link', $this->hooks, true ) ) {
			// rel link
			remove_action( 'wp_head', 'index_rel_link' );
		}

		if ( in_array( 'parent_post_rel_link', $this->hooks, true ) ) {
			// remove rel link
			remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
		}

		if ( in_array( 'start_post_rel_link', $this->hooks, true ) ) {
			// disable prev/next rel links in the head
			remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
		}

		if ( in_array( 'adjacent_posts_rel_link_wp_head', $this->hooks, true ) ) {
			// disable prev/next rel links in the head
			remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
		}

		if ( in_array( 'wp_generator', $this->hooks, true ) ) {
			// remove the wordpress generator tag
			remove_action( 'wp_head', 'wp_generator' );
		}

		if ( in_array( 'rest_output_link_wp_head', $this->hooks, true ) ) {
			// remove rest head tag
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10, 0 );
		}

		if ( in_array( 'wp_filter_oembed_result', $this->hooks, true ) ) {
			// Don't filter oEmbed results.
			remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		}

		if ( in_array( 'wp_oembed_add_discovery_links', $this->hooks, true ) ) {
			// Remove oEmbed discovery links.
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}

		if ( in_array( 'wp_oembed_add_host_js', $this->hooks, true ) ) {
			// Remove oEmbed-specific JavaScript from the front-end and back-end.
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		}

		if ( in_array( 'wp_shortlink_wp_head', $this->hooks, true ) ) {
			// Remove shortlink
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}

		if ( in_array( 'show_recent_comments_widget_style', $this->hooks, true ) ) {
			// remove recentcomments style
			add_filter( 'show_recent_comments_widget_style', '__return_false' );
		}
	}
}
