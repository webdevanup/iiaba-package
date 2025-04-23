<?php

namespace WDG\Core;

class Console {

	private static $methods = [
		'log',
		'error',
		'warn',
		'table',
		'info',
	];

	public function __call( $name, $arguments ) {
		return self::__callStatic( $name, $arguments );
	}

	public static function __callStatic( $name, $arguments ) {
		if ( in_array( $name, self::$methods, true ) ) {
			printf(
				'<script>console.%s(%s)</script>',
				$name, // phpcs:ignore
				implode(
					', ',
					array_map( 'json_encode', (array) $arguments )
				)
			);
		}
	}
}
