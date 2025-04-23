<?php
/**
 * @file
 *
 * Abstract meta mapping (extended by PostMetaMap and TermMetaMap)
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\MapBase;
use WDG\Migrate\Output\OutputInterface;

abstract class MetaMapBase extends MapBase {

	protected $key;

	protected $map;

	protected $source_key_prefix = '';

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, $source_key = null, $destination_key = null, OutputInterface $output = null ) {
		parent::__construct( $arguments, $source_key, $destination_key, $output );

		$this->key = $arguments['key'];
		if ( ! empty( $arguments['source_key_prefix'] ) ) {
			$this->source_key_prefix = $arguments['source_key_prefix'];
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		$this->map = array();
	}

	/**
	 * @inheritDoc
	 */
	public function initialized() : bool {
		return isset( $this->map );
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( $source_key, $destination_key ) {
		$source_key = $this->source_key_prefix . $source_key;
		$this->save_meta( $destination_key, $source_key );
		$this->map[ $source_key ] = $destination_key;
		$this->output->debug( $source_key . ' ==> ' . $destination_key, 'Save Meta Map' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_source_key( $destination_key ) {
		if ( empty( $destination_key ) ) {
			return false;
		}

		$source_key = array_search( $destination_key, $this->map );

		// Fallback check of source_meta
		if ( $source_key === false ) {
			$source_key = $this->lookup_source_meta( $destination_key );

			// Ensure false is returned
			if ( empty( $source_key ) ) {
				$source_key = false;
			}
		}

		return $source_key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_key( $source_key ) {
		if ( empty( $source_key ) ) {
			return false;
		}

		$source_key = $this->source_key_prefix . $source_key;

		$destination_key = array_key_exists( $source_key, $this->map ) ? $this->map[ $source_key ] : false;

		// Fallback check of destination_meta
		if ( $destination_key === false ) {
			$destination_key = $this->lookup_destination_meta( $source_key );

			// Ensure false is returned
			if ( empty( $destination_key ) ) {
				$destination_key = false;
			}
		}

		return $destination_key;
	}

	/**
	 * Save meta keys encountered during migration
	 * @see save()
	 */
	abstract protected function save_meta( $destination_key, $source_key );

	/**
	 * Lookup destination's matching source key
	 * @see lookup_source_key()
	 */
	abstract protected function lookup_source_meta( $destination_key );

	/**
	 * Lookup source's matching destination key
	 * @see lookup_destination_key()
	 */
	abstract protected function lookup_destination_meta( $source_key );


}
