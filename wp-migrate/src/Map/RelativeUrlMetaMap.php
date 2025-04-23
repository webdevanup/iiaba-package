<?php
/**
 * @file
 *
 * Uses post meta to store mappings.
 */

namespace WDG\Migrate\Map;

class RelativeUrlMetaMap extends PostMetaMap {

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_key( $source_key ) {
		return parent::lookup_destination_key( parse_url( $source_key, PHP_URL_PATH ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function lookup_destination_meta( $source_key ) {
		return parent::lookup_destination_meta( parse_url( $source_key, PHP_URL_PATH ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function save_meta( $destination_key, $source_key ) {
		update_post_meta( $destination_key, $this->key, parse_url( $source_key, PHP_URL_PATH ) );
	}

}
