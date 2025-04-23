<?php
/**
 * @file
 *
 * Uses post meta to store mappings.
 */

namespace WDG\Migrate\Map;

use WDG\Migrate\Map\MetaMapBase;

class UserMetaMap extends MetaMapBase {

	/**
	 * {@inheritdoc}
	 */
	public function save_meta( $destination_key, $source_key ) {
		update_user_meta( $destination_key, $this->key, $source_key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_source_meta( $destination_key ) {
		return get_user_meta( $destination_key, $this->key, true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_meta( $source_key ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key=%s AND meta_value=%s LIMIT 1", $this->key, $source_key ) );
	}

		/**
	 * {@inheritdoc}
	 */
	public function count_destination_keys( $ids ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT count(1) FROM $wpdb->usermeta WHERE meta_key=%s AND meta_value in (%1s) LIMIT 1", $this->key, $ids ) );
	}

}
