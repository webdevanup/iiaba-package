<?php
/**
 * @file
 *
 * Uses options to store mappings.
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\MapBase;
use WDG\Migrate\Output\OutputInterface;

class OptionsMap extends MapBase {

	protected $prefix;
	protected $name;

	protected $map;

	/**
	 * Update after every save?
	 * Increase performance by disabling, increase reliability by enabling
	 * @var bool
	 */
	protected $update_after_save = false;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, $source_key = null, $destination_key = null, OutputInterface $output ) {
		parent::__construct( $arguments, $source_key, $destination_key, $output );

		$this->prefix            = 'MigrateMap_';
		$this->name              = $arguments['name'];
		$this->update_after_save = ! empty( $arguments['update_after_save'] ) ? true : false;

		if ( ! $this->update_after_save ) { // Update at the end
			// Register shutdown function
			add_action( 'shutdown', array( $this, 'update' ) );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		// Retrieve from options table
		$this->map = get_option( $this->prefix . $this->name, array() );

		$this->output->debug( 'Retrieved ' . count( $this->map ) . ' maps from options' );
	}

	/**
	 * @inheritDoc
	 */
	public function initialized() : bool {
		return isset( $this->map );
	}

	/**
	 * Update option
	 */
	public function update() {
		// Persist to options table
		update_option( $this->prefix . $this->name, $this->map, false );

		$this->output->debug( 'Saved ' . count( $this->map ) . ' maps to options' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( $source_key, $destination_key ) {
		// Store temporarily
		$this->map[ $source_key ] = $destination_key;

		$this->output->debug( $source_key . ' ==> ' . $destination_key, 'Save Options Map' );

		if ( $this->update_after_save ) { // Update after each save
			$this->update();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_source_key( $destination_key ) {
		$source_key = array_search( $destination_key, $this->map );

		return $source_key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_key( $source_key ) {
		if ( array_key_exists( $source_key, $this->map ) ) {
			return $this->map[ $source_key ];
		}
		return false;
	}

}
