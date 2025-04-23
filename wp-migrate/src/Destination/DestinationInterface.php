<?php
/**
 * @file
 *
 * Interface for destinations.
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Output\OutputInterface;

interface DestinationInterface {

	/**
	 * Constructor accepts arguments containing fields and other configuration data
	 * @param array $arguments
	 * @param WDG\Migrate\Output\OutputInterface $output
	 */
	public function __construct( array $arguments, OutputInterface $output );

	/**
	 * Prepare to recieve import data
	 */
	public function init();

	/**
	 * Import row using fields provided in __construct()
	 * @param object $row
	 * @return bool false if there was error importing
	 */
	public function import( $row );

	/**
	 * Current created destination object with key information
	 * @return object
	 */
	public function current();

	/**
	 * Current created destination object key
	 * @return mixed
	 */
	public function key();

	/**
	 * Cleanup destination after row has been imported
	 */
	public function cleanup();

}
