<?php
/**
 * @file
 *
 * Interface for maps.
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Output\OutputInterface;

interface MapInterface {

	/**
	 * Constructor accepts arguments containing fields and other configuration data
	 *
	 * @param array $arguments
	 * @param string $source_key
	 * @param string $destination_key
	 * @param OutputInterface $output
	 */
	public function __construct( array $arguments, $source_key = null, $destination_key = null, OutputInterface $output = null );

	/**
	 * Initialize map (e.g. retrieve records from DB)
	 */
	public function init();

	/**
	 * Has this map been initialized?
	 *
	 * @return bool
	 */
	public function initialized() : bool;

	/**
	 * Get source key
	 * @return string
	 */
	public function get_source_key();

	/**
	 * Get destination key
	 * @return string
	 */
	public function get_destination_key();

	/**
	 * Map keys encountered during migration
	 * @param mixed $source_key
	 * @param mixed $destination_key
	 */
	public function save( $source_key, $destination_key );

	/**
	 * Using the map, lookup destination's matching source key
	 * @param mixed $destination_key
	 * @return mixed|false
	 */
	public function lookup_source_key( $destination_key );

	/**
	 * Using the map, lookup source's matching destination key
	 * @param mixed $source_key
	 * @return mixed|false
	 */
	public function lookup_destination_key( $source_key );

	/**
	 * Using the map, count source's matching destination keys
	 * @param array $ids
	 * @return int
	 */
	public function count_destination_keys( $ids );
}
