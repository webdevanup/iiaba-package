<?php
/**
 * @file
 *
 * Output to CLI
 */

namespace WDG\Migrate\Output;

use WDG\Migrate\Output\OutputInterface;
use WP_CLI;

class CLIOutput implements OutputInterface {

	/**
	 * Show progress messages?
	 * @var bool
	 */
	protected $progress;

	/**
	 * Show progress bar
	 * @var WDG\Migrate\Output\ProgressBar
	 */
	protected $progress_bar;

	/**
	 * Persistent label
	 * @var string
	 */
	protected $label;

	/**
	 * Constructor
	 */
	public function __construct( $progress = true ) {
		$this->progress = $progress;
		$this->progress_bar = new ProgressBar();
	}

	/**
	 * {@inheritdoc}
	 */
	public function success( $message ) {
		WP_CLI::success( $message );
	}

	/**
	 * {@inheritdoc}
	 */
	public function error( $message, $label = null, $level = null ) {
		WP_CLI::warning( $this->format( $message, $label, $level ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function progress( $message, $label = null, $level = null ) {
		if ( $this->progress ) {
			$formatted_message = '';

			if ( ! empty( $level ) && is_int( $level ) ) {
				$formatted_message .= str_repeat( "\t", $level );
			}

			// Assignment intentional
			if ( $label || $label = $this->label ) { // phpcs:ignore
				$formatted_message .= WP_CLI::colorize( '%c' . $label . '%n' ) . ":\t";
			}

			$formatted_message .= $message;

			WP_CLI::line( $formatted_message );
		}
	}

	public function progress_bar_init( $total, $label = 'Migrate' ) {
		$this->progress_bar->set_total( $total );
		$this->progress_bar->set_label( $label );
		return $this->progress_bar;
	}

	public function progress_bar() {
		return $this->progress_bar;
	}

	/**
	 * {@inheritdoc}
	 */
	public function debug( $message, $label = null, $level = null ) {
		WP_CLI::debug( $this->format( $message, $label, $level ), 'migrate' );
	}

	/**
	 * Displays migration types available
	 *
	 * @param array $types Key-value migration types
	 */
	public function types( $types ) {
		WP_CLI::line( 'Available types:' );

		$table = array();
		foreach ( $types as $key => $value ) {
			$table[] = array(
				'Type' => $key,
				'Title' => $value,
			);
		}

		\WP_CLI\Utils\format_items( 'table', $table, array( 'Type', 'Title' ) );
	}


	/**
	 * Displays migration types available with counts
	 *
	 * @param array $types Key-value migration types
	 */
	public function status( $types, $blog_id = 1 ) {

		if ( $blog_id != 1 ) {
			\WP_CLI::line( \sprintf( 'Status - Blog ID %s:', $blog_id ) );
		} else {
			\WP_CLI::line( 'Status:' );
		}

		$table = array();

		foreach ( $types as $key => $value ) {

			$table[] = array(
				'Type' => $key,
				'Title' => $value['title'],
				'Total' => $value['total'],
				'Imported' => $value['imported'],
				'Unprocessed' => $value['total'] - ( is_numeric( $value['imported'] ) ? $value['imported'] : 0 ),
				'Last Imported' => $value['last'],
			);
		}

		$columns = [
			'Type',
			'Title',
			'Total',
			'Imported',
			'Unprocessed',
			// 'Last Imported'
		];

		\WP_CLI\Utils\format_items( 'table', $table, $columns );

	}

	/**
	 * Set progress state
	 *
	 * @param bool $progress
	 */
	public function setProgress( $progress ) {
		$this->progress = $progress;
	}

	/**
	 * Set progress bar state
	 *
	 * @param bool $progress_bar
	 */
	public function setProgressBar( $progress_bar ) {
		$this->progress_bar = $progress_bar;
	}

	/**
	 * Persistent label (if not set)
	 * @param mixed $label
	 */
	public function setLabel( $label ) {
		$this->label = $label;
	}

	/**
	 * Formats error and debug messages consistently
	 *
	 * @param mixed $message
	 * @param mixed $label
	 * @param int $level
	 * @return string
	 */
	protected function format( $message, $label, $level ) {
		$formatted_message = '';

		if ( $level ) {
			$formatted_message .= str_repeat( '-', $level ) . ' ';
		}

		if ( $label ) {
			$formatted_message .= $label . ': ';
		}

		if ( is_bool( $message ) ) {
			$formatted_message .= $message ? '(true)' : '(false)';
		} elseif ( is_null( $message ) ) {
			$formatted_message .= '(null)';
		} elseif ( ! is_int( $message ) && empty( $message ) ) {
			$formatted_message .= '(empty)';
		} elseif ( is_object( $message ) || is_array( $message ) ) {
			ob_start();
			var_dump( $message ); // phpcs:ignore
			$formatted_message .= ob_get_clean();
		} else {
			$formatted_message .= $message;
		}

		return $formatted_message;
	}
}
