<?php
/**
 * @file
 *
 * Migration User
 *
 */

namespace WDG\Migrate;

class MigrationUser extends \WP_User {

	protected static $migrate_email = 'migrate@wdg.co';

	protected static $migrate_username = 'migrate';

	public static function migrate_user_id( $username = null, $email = null ) {

		if ( defined( 'MIGRATE_USER_NAME' ) ) {
			self::$migrate_username = MIGRATE_USER_NAME;
		}

		if ( ! empty( $username ) ) {
			self::$migrate_username = $username;
		}

		if ( defined( 'MIGRATE_USER_EMAIL' ) ) {
			self::$migrate_email = MIGRATE_USER_EMAIL;
		}

		if ( ! empty( $email ) ) {
			self::$migrate_email = $email;
		}

		return self::add_migrate_user();

	}

	public static function add_migrate_user() {

		$user_name  = self::$migrate_username;
		$user_email = self::$migrate_email;

		$user_id = \username_exists( $user_name );

		if ( ! $user_id && false == \email_exists( $user_email ) ) {
			$random_password = \wp_generate_password( $length = 12, $include_standard_special_chars = true );
			$user_id         = \wp_create_user( $user_name, $random_password, $user_email );
			
			$user = new \WP_User( $user_id );
			$user->set_role( 'contributor' );
			
		}

		return $user_id;
	}
}
