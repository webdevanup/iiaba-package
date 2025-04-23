<?php
/**
 * @file
 *
 * Base class for sources.
 */

namespace WDG\Migrate\Source;

use WDG\Migrate\Source\SourceInterface;
use WDG\Migrate\Output\OutputInterface;
use \Iterator;
use \Countable;

abstract class SourceBase implements SourceInterface, Iterator, Countable {

	/**
	 * @var WDG\Migrate\Output\OutputInterface
	 */
	protected $output;

	/**
	 * @var Array
	 */
	protected $results = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		$this->output = $output;
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) { }

	/**
	 * Count of total rows
	 * Implements Countable::count();
	 * @return int
	 */
	abstract public function count(): int;

	/**
	 * Current row
	 * Implements Iterator::current()
	 * @return object
	 */
	abstract public function current(): mixed;

	/**
	 * Current key
	 * Implements Iterator::key()
	 * @return mixed
	 */
	abstract public function key(): mixed;

	/**
	 * Moves index to next, returns next object
	 * Implements Iterator::next()
	 */
	abstract public function next(): void;

	/**
	 * Rewinds index to 0
	 * Implements Iterator::rewind()
	 */
	abstract public function rewind(): void;

	/**
	 * Returns whether the current index is valid
	 * Implements Iterator::valid()
	 * @return bool
	 */
	abstract public function valid(): bool;

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() { }

	/**
	 * Return array of keys
	 * @return array
	 */
	public function get_keys() {
		return array_column( $this->results, 'id' );
	}

}
