<?php

namespace WDG\Core;

/**
 * This class will cleanup rogue dashboard widgets that are not needed
 */
class AdminDashboard {

	/**
	 * An array of widget ids to be removed
	 *
	 * @var array
	 * @see $GLOBALS['wp_meta_boxes']['dashboard'] for available ids
	 */
	// phpcs:disable Squiz.PHP.CommentedOutCode.Found
	protected $disabled = [
		// 'dashboard_site_health',
		'dashboard_primary',
		// 'dashboard_secondary',
		// 'tribe_dashboard_widget',
		// 'dashboard_quick_press',
		// 'dashboard_right_now', // At a Glance
		// 'dashboard_activity',
		// 'dashboard_recent_drafts',
		// 'dashboard_recent_comments',
	];
	// phpcs:enable Squiz.PHP.CommentedOutCode.Found

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'wp_dashboard_setup' ] );
	}

	/**
	 * Disable a metabox (widget) by it's id
	 *
	 * @param string[]
	 * @return bool
	 */
	public function disable() : bool {
		$this->disabled = array_unique( array_merge( $this->disabled, array_map( 'strval', func_get_args() ) ) );

		return true;
	}

	/**
	 * Remove meta boxes (dashboard widgets) from both the normal and side context
	 *
	 * @action wp_dashboard_setup
	 */
	public function wp_dashboard_setup() {
		if ( ! empty( $this->disabled ) ) {
			foreach ( $this->disabled as $widget_name ) {
				foreach ( [ 'normal', 'side' ] as $context ) {
					remove_meta_box( $widget_name, 'dashboard', $context );
				}
			}
		}
	}
}
