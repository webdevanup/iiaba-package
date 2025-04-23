<?php
/**
 * @file
 *
 * Base class for destinations.
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\DestinationInterface;
use WDG\Migrate\Output\OutputInterface;

abstract class DestinationBase implements DestinationInterface {

	/**
	 * @var WDG\Migrate\Output\OutputInterface
	 */
	protected $output;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		$this->output = $output;
	}

	/**
	 * {@inheritdoc}
	 */
	public function init() { }

	/**
	 * {@inheritdoc}
	 */
	abstract public function import( $row );

	/**
	 * {@inheritdoc}
	 */
	abstract public function current();

	/**
	 * {@inheritdoc}
	 */
	abstract public function key();

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() { }

}
