<?php
/**
 * @file
 *
 * Class encapulates complex data about CLI command types.
 */

namespace WDG\Migrate\CLI;

class CommandType {

	/**
	 * The migration type name
	 * @var string
	 */
	protected $title;

	/**
	 * The migration class
	 * @var string
	 */
	protected $migration_class;

	/**
	 * Constructor
	 *
	 * Optionally define title and migration class
	 */
	public function __construct( $title = '', $migration_class = '' ) {
		$this->setTitle( $title );
		$this->setMigrationClass( $migration_class );
	}

	public function getTitle() {
		return $this->title;
	}

	public function setTitle( $title ) {
		$this->title = $title;
	}

	public function getMigrationClass() {
		return $this->migration_class;
	}

	public function setMigrationClass( $migration_class ) {
		$this->migration_class = $migration_class;
	}
}
