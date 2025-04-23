<?php
/**
 * @file
 *
 * Uses options to store mappings.
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\OptionsMap;
use WDG\Migrate\Output\OutputInterface;

class MSOptionsMap extends OptionsMap {

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		// Retrieve from options table
		$this->map = get_site_option( $this->prefix . $this->name, array() );

		$this->output->debug( 'Retrieved ' . count( $this->map ) . ' maps from multi-site options' );
	}

	/**
	 * Update option
	 */
	public function update() {
		// Persist to options table
		update_site_option( $this->prefix . $this->name, $this->map, false );

		$this->output->debug( 'Saved ' . count( $this->map ) . ' maps to multi-site options' );
	}

}
