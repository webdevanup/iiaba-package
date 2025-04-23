<?php
/**
 * @file
 *
 * Interface for migrations.
 */

namespace WDG\Migrate;

use WDG\Migrate\Output\OutputInterface;

interface MigrationInterface {

	/**
	 * Constructor accepts arguments containing configuration data
	 * @param array $arguments
	 * @param WDG\Migrate\Output\OutputInterface $output
	 */
	public function __construct( array $arguments, OutputInterface $output );

	/**
	 * Run migration process
	 * @param bool $update Allow existing records to be updated
	 * @param int $id Spcific ID for source
	 * @param int $limit Limit for source
	 * @param int $offset Offset for source
	 */
	public function run( $update = true, $id = 0, $limit = 0, $offset = 0 );

}
