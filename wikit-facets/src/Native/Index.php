<?php

namespace WDG\Facets\Native;

class Index {

	public string $table;

	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'facets';
	}

	/**
	 * Does the index table exist
	 *
	 * @return bool
	 */
	public function exists() : bool {
		global $wpdb;

		return (bool) $wpdb->query( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
	}

	/**
	 * Create the table if it doesn't exist
	 *
	 * @param bool $force
	 * @return bool
	 */
	public function create( bool $force = false ) : bool {
		global $wpdb;

		if ( ! $force && $this->exists() ) {
			return true;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id BIGINT NOT NULL AUTO_INCREMENT,
			object_id BIGINT NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			type VARCHAR(20) NULL,
			facet VARCHAR(100) NULL,
			value VARCHAR(100) NULL,
			label VARCHAR(100) NULL,
			parent VARCHAR(100) NULL,
			PRIMARY KEY  (id),
			KEY facets_index (object_id,object_type,type,facet,value,label,parent)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return $this->exists();
	}

	/**
	 * Drop the index
	 *
	 * @return bool
	 */
	public function drop() : bool {
		global $wpdb;

		if ( $this->exists() ) {
			return (bool) $wpdb->query( "DROP TABLE {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return false;
	}

	/**
	 * Truncate the index
	 *
	 * @return bool
	 */
	public function truncate() : bool {
		global $wpdb;

		if ( $this->exists() ) {
			return (bool) $wpdb->query( sprintf( 'TRUNCATE TABLE %s', $this->table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return false;
	}

	/**
	 * Insert rows into the facets table
	 *
	 * @param array $facets
	 * @return int the number of rows entered
	 */
	public function insert( array $facets = [] ) : int {
		global $wpdb;

		$rows = 0;

		foreach ( $facets as $facet ) {
			$inserted = $wpdb->insert(
				$this->table,
				$facet,
				[
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
				]
			);

			if ( $inserted ) {
				$rows = $rows + $inserted;
			}
		}

		return $rows;
	}

	/**
	 * Get rows by object_id
	 *
	 * @param int $object_id
	 * @return array
	 */
	public function get_object( int $object_id ) : array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table WHERE object_id = %d", $object_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Delete a row from the database by row id
	 *
	 * @param int
	 * @return int|false
	 */
	public function delete( int $id ) {
		global $wpdb;

		return $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Delete multiple ids from the database in one query
	 *
	 * @param array $ids
	 * @return int|bool
	 */
	public function delete_multi( array $ids ) {
		global $wpdb;

		$ids        = array_filter( array_map( 'intval', $ids ) );
		$ids_format = implode( ',', array_fill( 0, count( $ids ), '%s' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE id IN ($ids_format)",  // phpcs:ignore WordPress.DB
				$ids
			)
		);
	}

	/**
	 * Delete rows by it's object id
	 *
	 * @param int $object_id
	 * @return int|false
	 */
	public function delete_object( int $object_id ) {
		global $wpdb;

		return $wpdb->delete( $this->table, [ 'object_id' => $object_id ], [ '%d' ] );
	}

	/**
	 * Delete rows by multiple object ids
	 *
	 * @param array $object_ids
	 * @return int|false
	 */
	public function delete_object_multi( array $object_ids ) {
		global $wpdb;

		$object_ids        = array_filter( array_map( 'intval', $object_ids ) );
		$object_ids_format = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE object_id IN ($object_ids_format)", // phpcs:ignore WordPress.DB
				$object_ids
			)
		);
	}

	/**
	 * Delete rows by key and value
	 *
	 * @param string $facet
	 * @param string $value
	 * @return int
	 */
	public function delete_facet_value( string $facet, string $value ) : int {
		global $wpdb;

		return (int) $wpdb->delete(
			$this->table,
			[
				'facet' => $facet,
				'value' => $value,
			],
			[
				'%s',
				'%s',
			]
		);
	}

	/**
	 * Update a label column by it's value
	 *
	 * @param string $value
	 * @param string $label
	 * @return int
	 */
	public function update_label( array $where, string $label ) : int {
		global $wpdb;

		return $wpdb->update(
			$this->table,
			[ 'label' => $label ],
			$where,
			[ '%s' ],
			[ '%s' ]
		);
	}

	/**
	 * Provide some syntax help for updating arbitrary rows of the index
	 *
	 * @param array $data
	 * @param array $where
	 * @return int
	 */
	public function update( array $data, array $where ) : int {
		global $wpdb;

		return $wpdb->update( $this->table, $data, $where, array_fill( 0, count( $data ), '%s' ) );
	}

	/**
	 * Get statistics for the current index
	 *
	 * @return array
	 */
	public function stats() {
		global $wpdb;

		$stats = [];

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$stats['total'] = (int) $wpdb->get_var( sprintf( 'SELECT count(*) FROM %s', $this->table ) );
		$stats['types'] = $wpdb->get_col( sprintf( 'SELECT DISTINCT facet FROM %s', $this->table ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$stats['exists'] = $this->exists();

		return $stats;
	}
}
