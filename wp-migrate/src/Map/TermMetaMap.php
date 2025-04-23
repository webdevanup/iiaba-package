<?php
/**
 * @file
 *
 * Uses term meta to store mappings.
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\MetaMapBase;
use WDG\Migrate\Output\OutputInterface;

class TermMetaMap extends MetaMapBase {

	/**
	 * {@inheritdoc}
	 */
	public function save_meta( $destination_key, $source_key ) {
		update_term_meta( $destination_key, $this->key, $source_key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_source_meta( $destination_key ) {
		return get_term_meta( $destination_key, $this->key, true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_meta( $source_key ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->termmeta WHERE meta_key=%s AND meta_value=%s LIMIT 1", $this->key, $source_key ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function count_destination_keys( $ids ) {
		global $wpdb;

		// spot check array and quote it as needed
		if ( ! is_int( current( $ids ) ) || ! is_int( end( $ids ) ) ) {

			array_walk(
				$ids,
				function( &$id ) {
					$id = "'" . esc_sql( $id ) . "'";
				}
			);
		} else {
			array_walk(
				$ids,
				function( &$id ) {
					$id = esc_sql( $id );
				}
			);
		}
		$ids = implode( ',', $ids );
		return $wpdb->get_var( $wpdb->prepare( "SELECT count(1) FROM $wpdb->termmeta WHERE meta_key=%s AND meta_value in ($ids) LIMIT 1", $this->key ) );
	}

}
