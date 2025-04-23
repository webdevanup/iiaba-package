<?php
/**
 * @file
 *
 * Base class for all migrations.
 *
 * Migrations contain source and destination classes, record maps and field mappings.
 *
 * Migations run in the following order:
 * - Map Init
 * - Source Init
 * - Process Rows
 *   - Prepare Source
 *   - Prepare Destination
 *   - Init
 *   - Import
 *   - Save
 *   - Complete
 *   - Cleanup
 * - Source Cleanup
 * - Migration Cleanup
 */

namespace WDG\Migrate;

use WDG\Migrate\MigrationInterface;
use WDG\Migrate\Output\OutputInterface;
use WDG\Migrate\Map\MapInterface;

abstract class MigrationBase implements MigrationInterface {

	/**
	 * @var \WDG\Migrate\Output\OutputInterface
	 */
	protected $output;

	/**
	 * @var \WDG\Migrate\Source\SourceInterface
	 */
	protected $source;

	/**
	 * @var \WDG\Migrate\Destination\DestinationInterface
	 */
	protected $destination;

	/**
	 * @var \WDG\Migrate\Map\MapInterface
	 */
	protected $map;

	/**
	 * Field maps, defaults, and migrations for storing mappings
	 * @var array
	 */
	protected $row_fields = [];

	/**
	 * Field maps, defaults, and migrations for storing mappings
	 * @var array
	 */
	protected $row_defaults = [];

	/**
	 * Field maps, defaults, and migrations for storing mappings
	 * @var array
	 */
	protected $row_maps = [];

	/**
	 * Migration User ID
	 * @var int
	 */
	protected $migration_user = 1;

	/**
	 * Migration post table name
	 * @var string
	 */
	protected $migration_post_table;

	/**
	 * Migration term table name
	 * @var string
	 */
	protected $migration_term_table;

	/**
	 * Prefix for Source Keys
	 * @var string
	 */
	protected $source_key_prefix = '';

	/**
	 * Data for migration records
	 * @var array
	 */
	protected $status_report = [];

	/**
	 * Constructor accepts arguments containing configuration data
	 * @param array $arguments
	 * @param \WDG\Migrate\Output\OutputInterface $output
	 */
	public function __construct( array $arguments, OutputInterface $output ) {

		$this->output = $output;

		// Make user and set ID
		$this->migration_user = \WDG\Migrate\MigrationUser::migrate_user_id();

		// Make Table and set name
		$this->migration_post_table = \WDG\Migrate\MigrationData::migrate_post_table();
		$this->migration_term_table = \WDG\Migrate\MigrationData::migrate_term_table();

	}

	/**
	 * Array of field mappings
	 * @param array  $mappings  Passed to add_field_mapping
	 */
	protected function add_field_mappings( array $mappings ) {
		foreach ( $mappings as $map ) {
			$this->add_field_mapping( ...$map );
		}
	}

	/**
	 * Map fields with optional default value
	 * @param string             $destination_field  Destination field to populate.
	 * @param string|null        $source_field       Optional. Source field for populating the destination field.
	 * @param mixed|null         $default_value      Optional. Default value if source field is missing.
	 * @param MapInterface|null  $field_map          Optional. Instantiated map class to translate source field values into destination field values.
	 */
	protected function add_field_mapping( $destination_field, $source_field = null, $default_value = null, MapInterface $field_map = null ) {
		// DEBUGGING
		$debug = $destination_field;

		if ( ! empty( $source_field ) ) {
			$debug                            .= ' <= ' . $source_field;
			$this->row_fields[ $source_field ] = $destination_field;

			if ( ! empty( $field_map ) ) {
				$this->row_maps[ $source_field ] = $field_map;
				$debug                          .= ' (map: ' . get_class( $field_map ) . ')';
			}
		}

		$this->row_defaults[ $destination_field ] = $default_value;
		if ( $default_value ) {
			if ( is_array( $default_value ) || is_object( $default_value ) ) {
				$debug .= ' (default: ' . print_r( $default_value, true ) . ')'; // phpcs:ignore
			} else {
				$debug .= ' (default: ' . $default_value . ')';
			}
		}

		$this->output->debug( $debug, 'add_field_mapping' );
	}

	/**
	 * Using the map, get source_field's matching destination_field
	 * @param string $source_field
	 * @return string|false
	 */
	protected function get_destination_field( $source_field ) {
		if ( ! array_key_exists( $source_field, $this->row_fields ) ) {
			// There is no destination field
			return false;
		}

		$destination_field = $this->row_fields[ $source_field ];

		return $destination_field;
	}

	/**
	 * Get a field map to translate keys for a source field
	 * @param string $source_field
	 * @return object|false
	 */
	protected function get_field_map( $source_field ) {

		if ( ! array_key_exists( $source_field, $this->row_maps ) ) {
			// There is no field map
			return false;
		}

		$field_map = $this->row_maps[ $source_field ];

		if ( ! $field_map->initialized() ) {
			$field_map->init();
		}

		return $field_map;
	}

	/**
	 * Get the migration source object
	 * @return obj
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * Get the migration destination object
	 * @return obj
	 */
	public function get_destination() {
		return $this->destination;
	}

	/**
	 * Get the migration map object
	 * @return obj
	 */
	public function get_map() {
		return $this->map;
	}

	/**
	 * Extrapolate generation of the source key
	 *
	 * @param $source_row
	 *
	 * @return string
	 */
	public function generate_source_key( $source_row ) {
		if ( isset( $source_row->{ $this->map->get_source_key() } ) ) {
			$source_key = $source_row->{ $this->map->get_source_key() };
		} else {
			$source_key = md5( serialize( $source_row ) ); // phpcs:ignore
		}

		return $source_key;
	}

	/**
	 * Get source total and imported stats of current migration
	 * @return Array
	 */
	public function get_stats() {

		$this->source->init(
			[
				'id' => 0,
				'limit' => 0,
				'offset' => 0,
				'count' => true,
			]
		);

		$imported = 0;
		$keys     = $this->source->get_keys();
		$total    = $this->source->count();

		if ( ! empty( $keys ) ) {
			$imported = $this->map->count_destination_keys( $keys );
		}

		return [
			'total'    => $total,
			'imported' => $imported,
		];
	}

	/**
	 * Run migration process
	 * @param bool $update Allow existing records to be updated
	 * @param string $id Spcific ID(s) for source, use a comma delimited list of ints for multiple
	 * @param int $limit Limit for source
	 * @param int $offset Offset for source
	 */
	public function run( $update = true, $id = 0, $limit = 0, $offset = 0, $force = false, $status = false ) {
		// Define importing
		if ( ! defined('WP_IMPORTING') ) {
			define( 'WP_IMPORTING', true );
		}

		// Init map
		$this->map->init();

		// Call init on source (pass ids, limit, offset)
		if ( ! empty( $id ) ) {
			$id = explode( ',', $id ); // Split ids
		}
		$this->source->init(
			[
				'id' => $id,
				'limit' => $limit,
				'offset' => $offset,
			]
		);

		$this->output->progress( 'Run initialized.', null, 1 );
		sleep( 2 ); // Wait a small time

		// Iterate over source
		$total        = count( $this->source );
		$total_length = strlen( $total );

		do_action( 'wdg-migrate/run/start', $total );
		$this->output->progress_bar_init( $total, get_called_class() );

		foreach ( $this->source as $index => $source_row ) {
			$number = '(' . str_pad( $index + 1, $total_length, '0', STR_PAD_LEFT ) . '/' . $total . ')';
			$this->output->setLabel( $number );

			if ( $status ) {
				$this->process_row_status( $source_row );
			} else {
				$this->process_row( $source_row, $update, $number, $force );
			}

			do_action( 'wdg-migrate/run/process_row', $number, $total );
			$this->output->setLabel( null );
			$this->output->progress_bar()->tick();
		}
		do_action( 'wdg-migrate/run/end', $total );

		// Call cleanup on source
		$this->source->cleanup();

		// Clean up migration
		$this->cleanup();

		$this->output->progress( 'Run complete.', null, 1 );

		$this->output->progress_bar()->finish();
	}

	/**
	 * Process migration row
	 * @param object $source_row
	 * @param bool $update
	 * @param int $number
	 */
	protected function process_row( $source_row, $update, $number, $force = false ) {
		// Call prepare on source_row
		if ( $this->prepare_source( $source_row ) === false ) {
			$this->output->progress( 'Skip! Abort from prepare source', null, 2 );
			return false;
		}

		// Generate source key
		$source_key = $this->generate_source_key( $source_row );

		// Lookup destination key
		$destination_key = $this->map->lookup_destination_key( $source_key );

		// Proceed only if updates are allowed
		if ( false !== $destination_key && ! $update ) {
			$this->output->progress( \sprintf( 'Skip! Row already migrated (%s)', $source_key ), null, 2 );
			$this->output->debug( $this->map->get_source_key() . ' ' . $source_key . ' ==> ' . $this->map->get_destination_key() . ' ' . $destination_key, 'Skip row ' . $number );
			return false;
		}

		// if updates are allowed don't overwrite newer edits ( POSTS ONLY )
		if ( false !== $destination_key && $update && is_a( $this->destination, '\WDG\Migrate\Destination\PostDestination' ) ) {

			$dest = get_post( $destination_key );
			$json = get_post_meta( $destination_key, MigrationData::DATA_KEY, true );
			$data = \json_decode( $json );
			$last_update   = strtotime( $data->last_update );
			$post_modified = strtotime( $dest->post_modified ) - 60;

			if ( $this->validateDate( $update ) ) {
				$last_update   = strtotime( $update );
			}

			if ( empty( $post_modified ) || empty( $last_update ) ) {
				$this->output->progress( 'Skip! Can\'t detemine post edit dates', null, 2 );
				$this->output->debug( $this->map->get_source_key() . ' ' . $source_key . ' ==> ' . $this->map->get_destination_key() . ' ' . $destination_key, 'Skip row ' . $number );
				return false;
			}

			if ( $post_modified > $last_update ) {
				$this->output->progress( \sprintf( 'Skip! %d: Post has been edited since last migation date (%s)', $destination_key,  date( 'Y-m-d H:i:s', $post_modified ) ), null, 2 );
				$this->output->debug( $this->map->get_source_key() . ' ' . $source_key . ' ==> ' . $this->map->get_destination_key() . ' ' . $destination_key, 'Skip row ' . $number );
				return false;
			}
		}

		// Debugging
		if ( false !== $destination_key ) {
			$this->output->debug( $this->map->get_source_key() . ' ' . $source_key . ' ==> ' . $this->map->get_destination_key() . ' ' . $destination_key, 'Update row ' . $number );
		} else {
			$this->output->debug( $this->map->get_source_key() . ' ' . $source_key, 'New row ' . $number );
		}

		// Create destination_row from row defaults
		$destination_row = $this->row_defaults;

		// Map destination_row key
		$destination_row[ $this->map->get_destination_key() ] = $destination_key;

		// dd( $this->map->get_destination_key() );

		// Map fields
		foreach ( $source_row as $source_field => $value ) {
			// Get destination field
			if ( $destination_field = $this->get_destination_field( $source_field ) ) { // phpcs:ignore
				// Get field map (if applicable)
				if ( $field_map = $this->get_field_map( $source_field ) ) { // phpcs:ignore
					// Update value with destination key
					if ( is_array( $value ) ) {
						$value = array_map( [ $field_map, 'lookup_destination_key' ], $value );
					} else {
						$value = $field_map->lookup_destination_key( $value );
					}
				}
				// Save value in destination row
				$destination_row[ $destination_field ] = $value;
			}
		}

		// Convert to object
		$destination_row = (object) $destination_row;

		// Call prepare on destination_row
		if ( $this->prepare_destination( $destination_row ) === false ) {
			$this->output->progress( 'Skip! Abort from prepare destination', null, 2 );
			return false;
		}

		// Call init on destination
		$this->destination->init();

		// Call import on destination
		if ( false !== $this->destination->import( $destination_row ) ) {

			// Get references
			$destination_key    = $this->destination->key();
			$destination_record = $this->destination->current();

			// Add key mapping
			$this->map->save( $source_key, $destination_key );

			// Call complete on record, row
			$this->complete( $destination_record, $source_row );
		}

		// Call cleanup on destination
		$this->destination->cleanup();
	}

	/**
	 * Process migration row
	 * @param object $source_row
	 * @param bool $update
	 * @param int $number
	 */
	protected function process_row_status( $source_row ) {

		// Generate source key
		$source_key = $this->generate_source_key( $source_row );

		// Lookup destination key
		$destination_key = $this->map->lookup_destination_key( $source_key );


		if ( $destination_key ) {
			$dest              = get_post( $destination_key );
			$json              = get_post_meta( $destination_key, MigrationData::DATA_KEY, true );
			$data              = \json_decode( $json );
			$last_update       = strtotime( $data->last_update ) - 60;
			$post_modified     = strtotime( $dest->post_modified );
			$updated           = $post_modified > $last_update;
			$last_migrate_date = ( new \DateTime() )->setTimestamp( $last_update )->format('Y-m-d H:i:s');
			$last_update_date  = ( new \DateTime() )->setTimestamp( $post_modified )->format('Y-m-d H:i:s');
		}

		array_push(
			$this->status_report,
			[
				'source_key'        => $source_key,
				'destination_key'   => $destination_key,
				'has_been_updated'  => $updated ?? 'n/a',
				'last_migrate_date' => $last_migrate_date ?? 'n/a',
				'last_update_date'  => $last_update_date ?? 'n/a',
			]
		);

	}


	/**
	 * Prepare source row
	 * @param object $row
	 * @return stdClass|bool False to abort processing and skip row
	 */
	public function prepare_source( &$source_row ) {
	}

	/**
	 * Prepare destination row, after mapping, before importing
	 * @param object $row
	 * @return stdClass|bool False to abort processing and skip row
	 */
	public function prepare_destination( &$destination_row ) {
	}

	/**
	 * Complete row, after committing destination row
	 * @param object $record
	 * @param object $row
	 */
	public function complete( $destination_row, $source_row ) {
	}

	/**
	 * Cleanup migrate process
	 */
	public function cleanup() {
		if ( ! empty( $this->status_report ) ) {

			return \WP_CLI\Utils\format_items( 'table', $this->status_report, array_keys( current( $this->status_report ) ) );

		}
	}

	/**
	 * Extract URLs filtered by host whitelist and/or relative
	 *
	 * @param string $content
	 * @param array|string|null $whitelist
	 * @param bool $relative Include relative links?
	 * @return array
	 */
	public function extract_urls( $content, $whitelist = null, $relative = true ) {
		$urls = [];

		// Extract all unique URLs
		$extracted_urls = wp_extract_urls( $content );

		$this->output->debug( $extracted_urls );

		if ( ! empty( $extracted_urls ) ) {
			if ( ! empty( $whitelist ) ) {
				if ( ! is_array( $whitelist ) ) {
					// Convert to array
					$whitelist = [ $whitelist ];
				}
				// Clean whitelist URLs
				$whitelist = array_map( [ $this, 'extract_url_host' ], $whitelist );
			}

			// URLs to check
			foreach ( $extracted_urls as $url ) {
				// Check for domain in each cleaned URL
				if ( ! empty( $whitelist ) && false !== array_search( $this->extract_url_host( $url ), $whitelist, true ) ) {
					$urls[] = $url;
					// Leading slash (but no protcol-less double-slash), assume relative
				} elseif ( ! empty( $relative ) && preg_match( '#^(?!//)/#', $url ) ) {
					$urls[] = $url;
				}
			}
		}

		return $urls;
	}

	/**
	 * Extract lowercase URL host
	 *
	 * @param string $url
	 * @return string
	 */
	public function extract_url_host( $url ) {
		return strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Clean String
	 *
	 * @param string $string
	 * @return string
	 */
	protected static function remove_invisible_chars( $string ) {
		return preg_replace( '/[^A-Za-z0-9\ ,&]/', '', $string );
	}

	/**
	 * Convert special punctuation to regulard
	 * @link https://stackoverflow.com/a/21491305
	 * @param string $string
	 * @return normalized string
	 */
	protected function utf8_to_regular( $string ) {
		$chr_map = array(
			// Windows codepage 1252
			"\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
			"\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
			"\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
			"\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
			"\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
			"\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
			"\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
			"\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark

			// Regular Unicode		// U+0022 quotation mark (")
									// U+0027 apostrophe     (')
			"\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
			"\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
			"\xE2\x80\x98" => "'", // U+2018 left single quotation mark
			"\xE2\x80\x99" => "'", // U+2019 right single quotation mark
			"\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
			"\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
			"\xE2\x80\x9C" => '"', // U+201C left double quotation mark
			"\xE2\x80\x9D" => '"', // U+201D right double quotation mark
			"\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
			"\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
			"\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
			"\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
		);
		$chr = array_keys( $chr_map ); // but: for efficiency you should
		$rpl = array_values( $chr_map ); // pre-calculate these two arrays
		return str_replace( $chr, $rpl, html_entity_decode( $string, ENT_QUOTES, 'UTF-8' ) );
	}

	protected function map_terms( $terms, $map ) {
		if ( ! empty( $terms ) || empty( $map ) ) {
			$terms = array_filter(
				array_map(
					function( $term ) use ( $map ) {
						$term = preg_replace( '/[;]/', '', $term );
						return isset( $map[ $term ] ) ? $map[ $term ] : null;
					},
					$terms
				)
			);

			$flattened = [];

			foreach ( $terms as $term ) {
				if ( is_string( $term ) ) {
					array_push( $flattened, $term );
					continue;
				}

				if ( is_array( $term ) ) {
					$flattened = array_merge( $flattened, $term );
					continue;
				}
			}

			$terms = array_unique( $flattened );
		}

		return $terms;
	}

	protected function replaceURL( $url, $post_id = false ) {

		// Build URL
		$parts = parse_url( $url );
		$original_url = '';
		if ( isset( $parts['path'] ) ) {
			$original_url .= rtrim( $parts['path'], '/' );
		}

		// fix for server absolute urls
		$absolute_url = ( strpos( $url, '/', 0 ) === 0 ) ? $this->base_url . $url : $url;

		$this->output->debug(
			[
				'url'          => $url,
				'absolute_url' => $absolute_url,
			]
		);

		$file_ext = wp_check_filetype_and_ext( $original_url, basename( $original_url ) );

		if ( ! empty( $file_ext ) && ! empty( $file_ext['ext'] ) ) {
			// is a file of some kind (image, document, etc)
			if ( ! empty( $this->attachment_map ) ) {

				$id = $this->attachment_map->lookup_destination_key( $original_url );

				if ( empty( $id ) ) {
					// Valid file needing import
					$id = $this->destination->import_file( $absolute_url, $post_id );

					if ( is_wp_error( $id ) ) {
						// Attachment import failed
						$this->output->error( $id->get_error_message(), 'Attachment "' . $url . '"' );
						return false;
					}

					// Save file map
					$this->attachment_map->save( $original_url, $id );
				}

				// Get new URL
				$new_url = wp_get_attachment_url( $id );

			}

		} else {
			if ( ! empty( $this->url_map ) ) {
				// Lookup path
				$id = $this->url_map->lookup_destination_key( $original_url );

				// Get new URL
				if ( ! empty( $id ) ) {
					$new_url = get_the_permalink( $id );
				}
			}
		}

		return $new_url ?? '';

	}

	/**
	 * Replace URLs in content and create new attachments if needed
	 *
	 * @param string $content
	 * @param int $post_id
	 * @return string
	 */
	protected function replaceURLs( $content, $post_id ) {
		$urls = array_filter(
			array_map(
				function( $url ) {
					// remove query args from attachment urls
					$parsed = wp_parse_url( $url );

					// probably a home page link
					if ( empty( $parsed['path'] ) ) {
						return null;
					}

					// absolute url
					if ( ! empty( $parsed['scheme'] ) && ! empty( $parsed['host'] ) && ! empty( $parsed['path'] ) ) {
						return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
					}

					// server relative
					return $parsed['path'];
				},
				$this->extract_urls(
					$content,
					[
						$this->base_url,
						str_replace( 'https://www.', 'https://', $this->base_url ),
						str_replace( 'https://www.', 'http://', $this->base_url ),
						str_replace( 'https://www.', 'http://www.', $this->base_url ),
						$this->alt_url,
						str_replace('https://www.', 'https://', $this->alt_url),
						str_replace('https://www.', 'http://', $this->alt_url),
						str_replace('https://www.', 'http://www.', $this->alt_url),
					],
					true
				)
			)
		);

		// Replace all known URLs
		foreach ( $urls as $url ) {

			$new_url = $this->replaceURL( $url, $post_id );

			if ( empty( $new_url ) ) {
				continue;
			}

			// Append on previous fragment
			if ( isset( $parts['fragment'] ) ) {
				$new_url .= '#' . $parts['fragment'];
			}

			// Replace URL in content
			$content = str_replace( $url, $new_url, $content );
		}

		return $content;
	}

	public function validateDate($date, $format = 'Y-m-d H:i:s') {
		$d = \DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
	}

	public function get_output() {
		return $this->output;
	}

}
