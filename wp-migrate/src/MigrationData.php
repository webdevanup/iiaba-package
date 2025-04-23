<?php
/**
 * @file
 *
 * Migration Data
 *
 */

namespace WDG\Migrate;

class MigrationData {

	const DATA_KEY = '_original_data';

	protected static $migrate_post_table_name = 'wp_migrate_post';
	protected static $migrate_term_table_name = 'wp_migrate_term';

	public function __construct() {
		add_filter( 'update_post_metadata', [ $this, 'update_post_metadata' ], 10, 5 );
		add_filter( 'get_post_metadata', [ $this, 'get_metadata' ], 10, 5 );

		add_filter( 'update_term_metadata', [ $this, 'update_term_metadata' ], 10, 5 );
		add_filter( 'get_term_metadata', [ $this, 'get_metadata' ], 10, 5 );


		// get_{$meta_type}_metadata ( mixed $value, int $object_id, string $meta_key, bool $single, string $meta_type )
		// update_{$meta_type}_metadata ( null|bool $check, int $object_id, string $meta_key, mixed $meta_value, mixed $prev_value )

	}

	// deprecated
	public static function migrate_table( $table_name = null ) {
		return self::migrate_post_table( $table_name );
	}

	public static function migrate_post_table( $table_name = null ) {

		if ( defined( 'MIGRATE_TABLE_NAME' ) ) {
			self::$migrate_post_table_name = \MIGRATE_TABLE_NAME;
		}

		if ( defined( 'MIGRATE_POST_TABLE_NAME' ) ) {
			self::$migrate_post_table_name = \MIGRATE_POST_TABLE_NAME;
		}

		if ( ! empty( $table_name ) ) {
			global $wpdb;
			self::$migrate_post_table_name = $wpdb->prefix . $table_name;
		}

		return self::create_table( self::$migrate_post_table_name );

	}

	public static function migrate_term_table( $table_name = null ) {

		if ( defined( 'MIGRATE_TERM_TABLE_NAME' ) ) {
			self::$migrate_term_table_name = \MIGRATE_TERM_TABLE_NAME;
		}

		if ( ! empty( $table_name ) ) {
			global $wpdb;
			self::$migrate_term_table_name = $wpdb->prefix . $table_name;
		}

		return self::create_table( self::$migrate_term_table_name );

	}

	protected static function create_table( $table_name ) {

		// Define schema and create the table.
		$schema = "CREATE TABLE `{$table_name}` (
			`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`object_id` bigint(20) unsigned NOT NULL UNIQUE,
			`source_data` longtext,
			`last_log` longtext,
			PRIMARY KEY (`ID`)
		) ENGINE=InnoDB;\n";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\maybe_create_table( $table_name, $schema );

		return $table_name;
	}

	public function update_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value, $table_name ) {
		if ( $meta_key == self::DATA_KEY ) {
			$this->add_source_data( $object_id, $meta_key, $meta_value, $prev_value, $table_name );
			return false;
		}

		// $stuff = get_post_meta( $object_id );

		return $check;
	}

	public function get_metadata( $check, $object_id, $meta_key, $single, $meta_type ) {

		$table_name = self::$migrate_post_table_name;
		if ( 'term' === $meta_type ) {
			$table_name = self::$migrate_term_table_name;
		}

		static $lookup = false;

		if ( empty( $meta_key ) && $lookup === false ) {
			$lookup = true;

			$data = get_metadata_raw( $meta_type, $object_id, $meta_key, $single );

			$data[ self::DATA_KEY ][] = $this->get_source_data( $object_id, $table_name );
			return $data;
		}

		if ( $meta_key ===  self::DATA_KEY ) {
			$data = $this->get_source_data( $object_id, $table_name );
			if ( !empty( $data ) ) {
				return $data;
			}
		}

		return $check;
	}

	public function add_source_data( $object_id, $meta_key, $meta_value, $prev_value, $table_name ) {
		global $wpdb;

		$data = [
			'object_id' => $object_id,
			'source_data' => $meta_value
		];

		return $wpdb->replace(
			$table_name,
			$data
		);

	}

	public function get_source_data( $object_id, $table_name ) {
		global $wpdb;

		$sql = sprintf( "SELECT source_data FROM %s WHERE object_id = %d LIMIT 1;", $table_name, $object_id );

		$data = $wpdb->get_col( $sql );

		if ( ! empty( $data )) {
			return current( $data );
		}

	}

	public function update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		return $this->update_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value, self::$migrate_post_table_name );
	}

	public function update_term_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		return $this->update_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value, self::$migrate_term_table_name );
	}

}
