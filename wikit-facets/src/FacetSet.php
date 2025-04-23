<?php

namespace WDG\Facets;

use ArrayAccess;
use Iterator;

/**
 * The FacetSet class is a representation of a facet collection
 *
 * @package wdgdc/wikit-facets
 */
class FacetSet extends Configurable implements Iterator, ArrayAccess {

	/**
	 * The current key in the iterator
	 *
	 * @var string
	 */
	protected ?string $key = null;

	/**
	 * The data store for iterator and array access
	 */
	protected array $data = [];

	/**
	 * return the current item in the iterator
	 *
	 * @return mixed
	 */
	public function current() : mixed {
		return $this->data[ $this->key ];
	}

	/**
	 * return the current index in the iterator
	 *
	 * @return mixed
	 */
	public function key() : mixed {
		return $this->key;
	}

	/**
	 * move to the next item in the filters storage
	 *
	 * @return void
	 */
	public function next() : void {
		$keys  = array_keys( $this->data );
		$index = array_search( $this->key, $keys, true );

		$this->key = $keys[ $index + 1 ] ?? null;
	}

	/**
	 * reset the iterator to the beginning
	 *
	 * @return void
	 */
	public function rewind() : void {
		reset( $this->data );

		$this->key = array_keys( $this->data )[0];
	}

	/**
	 * Is the current index a valid item
	 *
	 * @return bool
	 */
	public function valid() : bool {
		return isset( $this->data[ $this->key ] );
	}

	/**
	 * Set a key and value in the object storage
	 *
	 * @param mixed $key
	 * @param mixed $value
	 * @return true
	 */
	public function set( mixed $key, mixed $value ) : bool {
		$this->data[ $key ] = $value;

		return true;
	}

	/**
	 * ArrayAccess for set
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet( mixed $offset, mixed $value ) : void {
		$this->set( $offset, $value );
	}

	/**
	 * Unset a key value from the object storage
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function unset( mixed $key ) : bool {
		if ( isset( $key ) ) {
			unset( $key );

			return true;
		}

		return false;
	}

	/**
	 * ArrayAccess for unset
	 *
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetUnset( mixed $offset ) : void {
		$this->unset( $offset );
	}

	/**
	 * Get a key value from object storage
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	public function get( mixed $key ) : mixed {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * ArrayAccess for get
	 *
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet( mixed $offset ) : mixed {
		return $this->get( $offset );
	}

	/**
	 * Does the key exist the object storage
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function exists( mixed $key ) : bool {
		return isset( $this->data[ $key ] );
	}

	/**
	 * ArrayAccess for exists
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists( mixed $offset ) : bool {
		return $this->exists( $offset );
	}

	/**
	 * Get the active filters from the current sets of Facets
	 *
	 * @return array
	 */
	public function get_active_filters() : array {
		$active_filters = [];

		foreach ( $this->data as $facet ) {
			$active_filters = array_merge( $active_filters, $facet->get_active_filters() );
		}

		return $active_filters;
	}

	/**
	 * Get the total number of active filters
	 *
	 * @return int
	 */
	public function get_active_filters_count() : int {
		return count( $this->get_active_filters() );
	}
}
