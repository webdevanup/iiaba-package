<?php
/**
 * @file
 *
 * Output interface
 */

namespace WDG\Migrate\Output;

interface OutputInterface {

	/**
	 * Display success message
	 *
	 * @param string $message
	 */
	public function success( $message );

	/**
	 * Display error message
	 *
	 * @param string $message
	 * @param mixed $label Sequence number
	 * @param int $level Level of message (indent)
	 */
	public function error( $message, $label = null, $level = null );

	/**
	 * Display progress message
	 *
	 * @param string $message
	 * @param mixed $label
	 * @param int $level
	 */
	public function progress( $message, $label = null, $level = null );

	/**
	 * Display debug message
	 *
	 * @param string $message
	 * @param mixed $label
	 * @param int $level
	 */
	public function debug( $message, $label = null, $level = null );

}
