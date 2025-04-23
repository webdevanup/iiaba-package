<?php
/**
 * @file
 *
 * Interface for sources.
 */

namespace WDG\Migrate\Source;

use WDG\Migrate\Output\OutputInterface;

interface SourceInterface {

	/**
	 * Constructor accepts arguments containing fields and other configuration data
	 * @param array $arguments
	 * @param WDG\Migrate\Output\OutputInterface $output
	 */
	public function __construct( array $arguments, OutputInterface $output );

	/**
	 * Initialize source import based on arguments
	 * @param array $arguments
	 */
	public function init( array $arguments );

	/**
	 * Cleanup source after all rows have been iterated
	 * @return string Message
	 */
	public function cleanup();

}
