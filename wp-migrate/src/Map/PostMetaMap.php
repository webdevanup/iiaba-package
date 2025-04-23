<?php
/**
 * @file
 *
 * Uses post meta to store mappings.
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\MetaMapBase;
use WDG\Migrate\Output\OutputInterface;

class PostMetaMap extends MetaMapBase {

	/**
	 * {@inheritdoc}
	 */
	public function save_meta( $destination_key, $source_key ) {
		update_post_meta( $destination_key, $this->key, $source_key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_source_meta( $destination_key ) {
		return get_post_meta( $destination_key, $this->key, true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_meta( $source_key ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value=%s LIMIT 1", $this->key, $source_key ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function count_destination_keys( $ids ) {
		global $wpdb;
		
		if ( ! empty( $this->source_key_prefix ) &&  ! empty( $ids ) ) {
			$ids = array_map( function( $id ) {
				return $this->source_key_prefix . $id;
			}, $ids );
		}

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
		return $wpdb->get_var( $wpdb->prepare( "SELECT count(1) FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value in ($ids) LIMIT 1", $this->key ) );
	}



}
