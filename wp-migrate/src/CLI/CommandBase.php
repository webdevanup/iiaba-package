<?php
/**
 * @file
 *
 * Base WP-CLI command for use in a project.
 */
namespace WDG\Migrate\CLI;

use WDG\Migrate\CLI\CommandType;
use WDG\Migrate\Output\CLIOutput;

if ( class_exists( '\WPCOM_VIP_CLI_Command' ) ) {
	class CLI_Command extends \WPCOM_VIP_CLI_Command {}
} else {
	class CLI_Command extends \WP_CLI_Command {}
}

/**
 * WP-CLI Migrate Command
 */
abstract class CommandBase extends CLI_Command {

	/**
	 * Output object
	 * @var WDG\Migrate\Output\CLIOutput
	 */
	protected $output;


	/**
	 * The types of migrations
	 * @var array WDG\Migrate\CLI\CommandType
	 */
	protected $types;

	/**
	 * Setup
	 */
	public function __construct() {
		$this->output = new CLIOutput();
		$this->types  = $this->getTypes();
		$this->output->debug( $this->types, 'Migration Commands' );
	}

	/**
	 * Migrate content
	 *
	 * ## OPTIONS
	 *
	 * [<type>]
	 * : Type of content to import
	 *
	 * [--update]
	 * : Update existing records
	 *
	 * [--id=<id>]
	 * : IDs of record to process (comma separated)
	 *
	 * [--ask=<ask>]
	 * : Ask for type if not supplied
	 *
	 * ---
	 * default: true
	 *
	 * [--blog_id=<blog_id>]
	 * : Blog ID for multisites
	 * ---
	 * default: '1'
	 *
	 * [--limit=<limit>]
	 * : Limit of records processed
	 *
	 * [--offset=<offset>]
	 * : Offset for records processed
	 *
	 * [--progress]
	 * : Show progress messages
	 *
	 * [--status]
	 * : print status of records
	 *
	 * [--force]
	 * : Force Updates
	 *
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$type    = ! empty( $args[0] ) ? $args[0] : false;
		$update  = ! empty( $assoc_args['update'] ) ? $assoc_args['update'] : false;
		$force   = ! empty( $assoc_args['force'] ) ? (bool) $assoc_args['force'] : false;
		$id      = ! empty( $assoc_args['id'] ) ? $assoc_args['id'] : null;
		$limit   = ! empty( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
		$offset  = ! empty( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$status  = ! empty( $assoc_args['status'] ) ? $assoc_args['status'] : false;
		$blog_id = ! empty( $assoc_args['blog_id'] ) ? $assoc_args['blog_id'] : 1;
		$ask     = ! empty( $assoc_args['ask'] ) ? $assoc_args['ask'] : true;

		if ( defined( 'WDG_MIGRATE_ASK_PROMPT' ) ) {
			$ask = WDG_MIGRATE_ASK_PROMPT;
		}

		$progress = ! empty( $assoc_args['progress'] ) ? (bool) $assoc_args['progress'] : false;
		$progress_bar = ! empty( $assoc_args['progress_bar'] ) ? (bool) $assoc_args['progress_bar'] : true;

		$this->output->setProgress( $progress );
		if ( false === $progress && true === $progress_bar ) {
			$this->output->progress_bar()->show();
		}

		$this->output->debug( 'CLI Args:' );
		$this->output->debug( $type, 'type', 2 );
		$this->output->debug( $update, 'update', 2 );
		$this->output->debug( $force, 'force', 2 );
		$this->output->debug( $id, 'id', 2 );
		$this->output->debug( $limit, 'limit', 2 );
		$this->output->debug( $offset, 'offset', 2 );
		$this->output->debug( $progress, 'progress', 2 );
		$this->output->debug( $progress_bar, 'progress_bar', 2 );
		$this->output->debug( $status, 'status', 2 );

		// Missing or invalid type
		if ( ! $type || ( ! array_key_exists( $type, $this->types ) && $type !== 'all' ) ) {
			// Output available types
			$this->availableTypes( $blog_id );

			if ( $ask === true ) {
				$type = $this->ask( "Run one of these migrations?" );
			}

			if ( $type === false ) {
				return;
			}

			if ( ! array_key_exists( $type, $this->types ) && $type !== 'all' ) {
				$this->output->error( 'Please specify a valid migration type.' );
				return;
			}
		}


		if ( $type === 'all' ) {
			$this->output->progress( 'Migrating all content.' );

			// Invoke self recursive
			foreach ( $this->types as $key => $type ) {
				$this(
					[ $key ],
					[
						'update'   => $update,
						'force'    => $force,
						'progress' => $progress,
					]
				);
				sleep( 2 ); // Small delay
			}
			return;
		}

		$this->output->progress( 'Migrating: ' . $this->types[ $type ]->getTitle() );

		// Migration arguments
		$arguments = [
			'blog_id' => $blog_id,
		];

		// Migration class name
		$migration_class = $this->types[ $type ]->getMigrationClass();

		$this->output->debug( $arguments, 'Migration Arguments' );
		$this->output->debug( $migration_class, 'Migration Class' );

		/**
		 * @var WDG\Migrate\Migration
		 */
		$migration = new $migration_class( $arguments, $this->output );
		$this->output->debug( 'Migration class instantiated.' );

		// Run migration!
		$migration->run( $update, $id, $limit, $offset, $force, $status );

		$this->output->success( 'Migration complete.' );
	}

	/**
	 * We are asking a question and returning an answer as a string.
	 *
	 * @param $question
	 *
	 * @return string
	 */
	protected function ask( $question ) {
		// Adding space to question and showing it.
		fwrite( STDOUT, $question . ' ' );

		return strtolower( trim( fgets( STDIN ) ) );
	}

	/**
	 * Helper function to list available types of migrations
	 */
	protected function availableTypes( $blog_id = 1 ) {

		$arr = [
			'all' => [
				'title' => 'All content',
				'total' => 0,
				'imported' => 0,
				'last' => '',
			],
		];

		foreach ( $this->types as $key => $type ) {

			$migration_class = $type->getMigrationClass();
			$migration       = new $migration_class( [ 'counts_only' => true, 'blog_id' => $blog_id ], $this->output );
			$stats           = $migration->get_stats();

			$arr['all']['total']    += $stats['total'];
			$arr['all']['imported'] += is_numeric( $stats['imported'] ) ? $stats['imported'] : 0;

			$arr[ $key ] = [
				'title' => $type->getTitle(),
				'total' => $stats['total'],
				'imported' => $stats['imported'],
				'last' => '-',
			];
		}

		$this->output->status( $arr, $blog_id );
	}

	/**
	 * Get available migration types
	 *
	 * FIXME: This should eventually be (auto-)discovered.
	 *
	 * @return array WDG\Migrate\CLI\CommandType
	 */
	abstract protected function getTypes();

}

// WP_CLI::add_command( 'XXX migrate', 'XXX_Migrate_Command' );
