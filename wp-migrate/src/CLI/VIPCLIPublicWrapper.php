<?php
/**
 * @file
 *
 * VIP CLI Command Public Wrapper exposes protected functions for use.
 *
 * Recommended use:
 * ```
add_action('wdg-migrate/run/process_row', function ( $number, $total ) {
	if ( 0 === $number % 50 ) {
		$vip_cli_public_wrapper = new \WDG\Migrate\CLI\VIPCLIPublicWrapper();
		$vip_cli_public_wrapper->public_stop_the_insanity();
	}
}, 10, 2 );
 * ```
 */
namespace WDG\Migrate\CLI;

class VIPCLIPublicWrapper extends \WPCOM_VIP_CLI_Command {

	public function public_stop_the_insanity() {
		$this->stop_the_insanity();
	}
}