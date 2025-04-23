<?php
/**
 * @file
 *
 * Migrates content as WordPress users.
 */

namespace WDG\Migrate\Destination;

use WDG\Migrate\Destination\DestinationBase;
use WDG\Migrate\Output\OutputInterface;

class UserDestination extends DestinationBase {

	/**
	 * Key field alias
	 * @var string
	 */
	protected $id_field;

	/**
	 * Post field aliases
	 * @var array
	 */
	protected $user_fields = array();

	/**
	 * Meta field (custom field) aliases
	 * @var array
	 */
	protected $meta_fields = array();

	/**
	 * Current user object
	 * @var object
	 */
	protected $user;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );
		// Separate fields
		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'key',
				 */
				case 'key':
					$options['column'] = 'ID';
					$this->id_field    = $field;
					/**
					 * 'type' => 'user',
					 * 'column' => 'user_content',
					 */
				case 'user':
					$this->user_fields[ $field ] = $options['column'];
					break;
				// default:
					// $this->user_fields = $this->column( 'base', $options['column'] );
			}
		}

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = trailingslashit( $arguments['base_url'] );
		}

	}

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		$this->user = null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function import( $row ) {

		// Assemble user array
		$userdata = array();
		foreach ( $this->user_fields as $field => $column ) {
			if ( isset( $row->{$field} ) ) {
				$userdata[ $column ] = $row->{$field};
			}
		}

		// Get title for messages
		if ( isset( $userdata['user_nicename'] ) && strlen( $userdata['user_nicename'] ) > 0 ) {
			$title = $userdata['user_nicename'];
		} else {
			$title = null;
		}

		$user_id = false;

		// Create or update user
		if ( $new = empty( $userdata['ID'] ) ) {
			unset( $userdata['ID'] );
			$userdata['user_pass'] = null;
			$user_id               = wp_insert_user( $userdata );
		} else {
			$user_id = wp_update_user( $userdata );
		}

		// Ensure user_id is valid
		if ( is_wp_error( $user_id ) ) {
			$this->output->error( $user_id->get_error_message(), 'User save error "' . $title . '"' );
			return false;
		}

		// Assemble usermeta
		$usermeta = array();
		foreach ( $this->meta_fields as $field => $key ) {
			if ( isset( $row->{$field} ) ) {
				// $usermeta[$key] = $this->import_content( $row->{$field}, $post_id );
				$usermeta[ $key ] = $row->{$field};
			}
		}

		// Add new postmeta
		foreach ( $usermeta as $key => $value ) {
			if ( $value === false ) {
				delete_user_meta( $user_id, $key );
			} else {
				update_post_meta( $user_id, $key, $value );
			}
		}

		// Set user and return
		$this->user = get_user_by( 'id', $user_id );
	}


	/**
	 * {@inheritdoc}
	 */
	public function current() {
		return $this->user;
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() {
		if ( isset( $this->user->ID ) ) {
			return $this->user->ID;
		} else {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() { }

}
