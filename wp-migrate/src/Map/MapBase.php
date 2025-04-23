<?php
/**
 * @file
 *
 * Base class for migration maps.
 *
 * Maps connect source and destination records together by key
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\MapInterface;
use WDG\Migrate\Output\OutputInterface;

abstract class MapBase implements MapInterface {

	/**
	 * Keys
	 * @var string
	 */
	protected $source_key, $destination_key;

	/**
	 * @var WDG\Migrate\Output\OutputInterface
	 */
	protected $output;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, $source_key = null, $destination_key = null, OutputInterface $output = null ) {
		$this->source_key      = $source_key;
		$this->destination_key = $destination_key;
		$this->output          = $output;
	}

	/**
	 * {@inheritdoc}
	 */
	abstract public function init();

	/**
	 * {@inheritdoc}
	 */
	public function get_source_key() {
		return $this->source_key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_destination_key() {
		return $this->destination_key;
	}

	/**
	 * {@inheritdoc}
	 */
	abstract public function save( $source_key, $destination_key );

	/**
	 * {@inheritdoc}
	 */
	abstract public function lookup_source_key( $destination_key );

	/**
	 * {@inheritdoc}
	 */
	abstract public function lookup_destination_key( $source_key );

	/**
	 * {@inheritdoc}
	 */
	abstract public function count_destination_keys( $ids );

}
