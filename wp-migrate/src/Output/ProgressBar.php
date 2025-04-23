<?php
/**
 * @file
 *
 * Progress Bar for CLI
 */

namespace WDG\Migrate\Output;

use WP_CLI;

class ProgressBar {

	/**
	 * Use Progress
	 * @var bool
	 */
	protected $progress;

	/**
	 * Progress bar
	 * @var cli\progress\Bar[]
	 */
	protected $progress_bar = [];

	/**
	 * label
	 */
	protected $total;

	/**
	 * Label
	 */
	protected $label;

	/**
	 * Constructor
	 */
	public function __construct( $total = 0, $label = 'Migration' ) {
		$this->total = $total;
		$this->label = $label;
	}

	public function set_total( $total ) {
		$this->total = $total;
	}

	public function set_label( $label ) {
		$this->label = $label;
	}

	public function show() {
		$this->progress = true;
	}

	/**
	 * Tick
	 */
	public function tick() {
		if ( $this->progress ) {
			if ( empty( $this->progress_bar[ $this->label ] ) ) {
				$this->progress_bar[ $this->label ] = \WP_CLI\Utils\make_progress_bar( $this->label, $this->total );
			}

			// dd( $this->progress_bar );

			$this->progress_bar[ $this->label ]->tick();
		}
	}

	/**
	 * Finish
	 */
	public function finish() {
		if ( $this->progress && $this->progress_bar[ $this->label ] ) {
			$this->progress_bar[ $this->label ]->finish();
		}
	}

}
