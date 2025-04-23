<?php

namespace WDG\Migrate\Source\WordPress;

class SourceBase extends \WDG\Migrate\Source\SourceBase {

	// Database
	protected $db;
	protected $index = 0;
	protected $results = array();

	// Query building variables
	protected $table_prefix = '';
	protected $base = '';
	protected $columns = array();
	protected $joins = array();
	protected $where = "";
	protected $group_by = "ID";
	protected $order_by = "ID";
	protected $limit = "";

	protected $base_id;
	protected $meta_table = '';
	protected $meta_id = '';
	protected $key_field = 'id';

	protected $blog_id = 1;
	protected $base_url = '';
	protected $permalink_structure;

	protected $arguments = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
		$this->arguments = $arguments;
		parent::__construct( $arguments, $output );

		if ( ! empty( $arguments['base'] ) ) {
			$this->base = $arguments['base'];
		}

		if ( ! empty( $arguments['order_by'] ) ) {
			$this->order_by = $arguments['order_by'];
		}

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = trailingslashit( $arguments['base_url'] );
		}


		$this->table_prefix = ! empty( $arguments['table_prefix'] ) ? $arguments['table_prefix'] : MIGRATE_DB_PREFIX;

		if ( ! empty( $arguments['blog_id'] ) ) {

			if ( $arguments['blog_id'] > 1 ) {
				$this->table_prefix .= $arguments['blog_id'] . '_';
			}

			$this->blog_id = intval( $arguments['blog_id'] );
		}

	}

	/**
	 * Close the db connection on delete
	 */
	public function __destruct() {
		if ( $this->db instanceof \wpdb ) {
			// $this->output->debug( get_called_class() . ' destruct. Closing DB.' );
			$this->db->close();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		if ( ! empty($arguments['id']) ) {
			$this->where .= " AND `base`.`{$this->key_field}`";
			if ( is_array( $arguments['id'] ) ) {
				$this->where .= " IN ('" . implode("', '", array_map( 'esc_sql', $arguments['id'] ) ) . "')";
			} else {
				$this->where .= " = '" . esc_sql( $arguments['id'] ) . "'";
			}
		} else if ( ! empty( $this->post_status ) ) {
			$this->where .= sprintf( " AND `base`.`post_status` IN ('%s')", implode("', '", array_map( 'esc_sql', $this->post_status ) ) );
		}

		// Apply limit and offset
		if ( ! empty( $arguments['limit'] ) ) {
			$this->limit = $arguments['limit'] . ( ! empty( $arguments['offset'] ) ? " OFFSET " . $arguments['offset'] : "" );
		} elseif ( ! empty( $arguments['offset'] ) ) {
			$this->limit = $arguments['offset'] . ',' . PHP_INT_MAX;
		}

		// Connect to DB
		$this->db_connect();

		// get the permalink structure
		$this->get_permalink_structure();

		// Build query
		$count_only = ! empty( $arguments['count'] ) ? true : false;
		if ( $count_only ) {
			$query = $this->count_query();
		} else {
			$query = $this->build_query();
		}


		$this->output->debug( "\n" . $query, 'Source Query' );

		// Get results to iterate over
		$this->results = $this->db->get_results( $query );

		$this->rewind();

		$this->output->progress( 'Queried ' . $this->count() . ' records.', null, 1 );
	}

	protected function meta_join( $field_name ) {
		$alias = 'pm_' . $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $field_name ] = implode( "\n", [
				"LEFT JOIN {$this->table_prefix}{$this->meta_table} {$alias}",
				"ON base.{$this->base_id} = `{$alias}`.`{$this->meta_id}`",
				"AND `{$alias}`.`meta_key` = '{$field_name}'"
			] ) . "\n";
		}

		return $alias;
	}

	protected function term_join( $field_name, $taxonomy ) {
		$alias = 'term_' . $field_name;

		if ( ! array_key_exists( $alias, $this->joins ) ) {
			$this->joins[ $field_name ] = sprintf( implode( "\n", [
				'LEFT JOIN %1$sterm_relationships term_relationships_%2$s ON `base`.`ID` = `term_relationships_%2$s`.`object_id`',
				'LEFT JOIN %1$sterm_taxonomy term_tax_%2$s ON `term_relationships_%2$s`.`term_taxonomy_id` = `term_tax_%2$s`.`term_taxonomy_id` AND `term_tax_%2$s`.`taxonomy` = \'%2$s\'',
				'LEFT JOIN %1$sterms %3$s ON `term_tax_%2$s`.`term_id` = `%3$s`.`term_id`'
			] ), $this->table_prefix, $taxonomy, $alias ) . "\n";
		}

		return $alias;
	}

	public function table_prefix() {
		return $this->table_prefix;
	}

	/**
	 * {@inheritdoc}
	 */
	public function count( ): int {
		return count( $this->results );
	}

	/**
	 * {@inheritdoc}
	 */
	public function current( ): mixed {
		return $this->results[ $this->index ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function key( ) : mixed {
		return $this->index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function next( ): void {
		++$this->index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewind( ): void {
		$this->index = 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function valid( ): bool {
		return isset( $this->results[ $this->index ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup( ) {
		$this->results = array();
		$this->rewind();
	}

	/**
	 * Helper column reference
	 * @param string $table
	 * @param string $column
	 * @return string
	 */
	protected function column( $table, $column ) {
		return "`{$table}`.`{$column}`";
	}

	/**
	 * Count sql query results
	 * @return string
	 */
	protected function count_query() {
		$sql = "SELECT\n";

		$columns = array();
		foreach ( $this->columns as $alias => $column ) {
			if ( strpos( $column, '`base`' ) === false ) {
				continue;
			}
			$columns[] = "{$column} AS {$alias}";
		}
		$sql .= implode(",\n", $columns) . "\n";

		$sql .= "FROM\n";
		$sql .= "{$this->table_prefix}{$this->base} base\n";

		if ( !empty($this->where) ) {
			$sql .= "WHERE 1=1 {$this->where}\n";
		}

		$this->output->debug($sql);

		return $sql;
	}

	/**
	 * Build sql query
	 * @return string
	 */
	protected function build_query() {
		$sql = "SELECT\n";

		$columns = array();
		foreach ( $this->columns as $alias => $column ) {
			$columns[] = "{$column} AS {$alias}";
		}
		$sql .= implode(",\n", $columns) . "\n";

		$sql .= "FROM\n";
		$sql .= "{$this->table_prefix}{$this->base} base\n";

		$sql .= implode("\n", $this->joins);
		$sql .= "\n";


		if ( !empty($this->where) ) {
			$sql .= "WHERE 1=1 {$this->where}\n";
		}

		if ( !empty($this->group_by) ) {
			$sql .= "GROUP BY `{$this->group_by}`\n";
		}

		if ( !empty($this->order_by) ) {
			$sql .= "ORDER BY `{$this->order_by}`\n";
		}

		if ( !empty($this->limit) ) {
			$sql .= "LIMIT {$this->limit}\n";
		}

		$this->output->debug($sql);

		return $sql;
	}

	/**
	 * Connect to external DB
	 */
	public function db_connect() {
		if ( ! isset( $this->db ) ) {
			$this->db = new \wpdb( MIGRATE_DB_USER, MIGRATE_DB_PASSWORD, MIGRATE_DB_NAME, MIGRATE_DB_HOST );
			$this->output->debug('Connected to database: ' . MIGRATE_DB_NAME);
		}

		return $this->db;
	}

	protected function get_permalink_structure() {
		if ( ! isset( $this->permalink_structure ) ) {
			$this->permalink_structure = $this->db->get_var( "SELECT option_value FROM {$this->table_prefix}options WHERE option_name = 'permalink_structure'" );
		}

		return $this->permalink_structure;
	}

	public function get_attachment_url( $id ) {
		if ( ! is_numeric( $id ) ) {
			return null;
		}

		$sql = trim( "
			SELECT meta_value
			FROM {$this->table_prefix}postmeta
			JOIN {$this->table_prefix}posts
			ON {$this->table_prefix}postmeta.post_id = {$this->table_prefix}posts.ID
			WHERE {$this->table_prefix}postmeta.post_id = %d
			AND {$this->table_prefix}postmeta.meta_key = '_wp_attached_file'
			AND {$this->table_prefix}posts.post_type = 'attachment'
			LIMIT 1
		" );

		$attached_file = $this->db->get_var( $this->db->prepare( $sql, intval( $id ) ) );

		if ( empty( $attached_file ) ) {
			return null;
		}

		$attachment_url = trailingslashit( $this->base_url ) . 'wp-content/uploads/';

		if ( $this->blog_id > 1 ) {
			$attachment_url .= 'sites/' . $this->blog_id . '/';
		}

		$attachment_url .= $attached_file;

		$this->output->debug( $attachment_url, 'get_attachment_url');

		return $attachment_url;
	}

	/**
	 * Get a row from the posts table by id, with selected columns
	 *
	 * @param int $id
	 * @param array $columns default to all
	 * @return StdClass|null
	 */
	protected function get_row( $id, Array $columns = [] ) {
		$columns = empty( $columns ) ? '*' : implode( ',', array_map( [ $this->db, '_real_escape' ], $columns ) );
		return $this->db->get_row( $this->db->prepare( "SELECT $columns FROM {$this->table_prefix}posts WHERE ID = %d LIMIT 1", $id ) );
	}

	/**
	 * Caching permalink calls
	 *
	 * @var array
	 */
	private $permalink_cache = [];

	/**
	 * Get the permalink for any post type
	 *
	 * @param int|string|\StdClass $row
	 * @return string|null
	 */
	public function get_permalink( $row ) {
		if ( is_numeric( $row ) ) {
			$row = $this->get_row( $row );
		}

		if ( empty( $row ) ) {
			return null;
		}

		if ( empty( $this->permalink_cache[ $row->{$this->key_field} ] ) ) {

			if ( is_a( $this, 'WDG\Migrate\Source\WordPress\Post') ) {

				switch( $row->post_type ) {
					case 'post':
						$this->permalink_cache[ $row->{$this->key_field} ] = $this->get_permalink_post( $row );
					break;
					case 'page':
						$this->permalink_cache[ $row->{$this->key_field} ] = $this->get_permalink_page( $row );
					break;
					case 'attachment':
						$this->permalink_cache[ $row->{$this->key_field} ] = $this->get_permalink_attachment( $row );
					break;
					default:
						// custom post types default
						$this->permalink_cache[ $row->{$this->key_field} ] = $this->get_permalink_custom( $row );
					break;
				}
			}

			if ( is_a( $this, 'WDG\Migrate\Source\WordPress\Term') ) {
				$this->permalink_cache[ $row->{$this->key_field} ] = $this->get_term_link( $row->{$this->key_field}, $this->taxonomy );
			}
		}

		return $this->permalink_cache[ $row->{$this->key_field} ];
	}

	protected $default_category;

	/**
	 * Get the default category from the wordpress source for permalinks generation
	 *
	 * @return \StdClass
	 */
	protected function get_default_category() {
		if ( ! isset( $this->default_category ) ) {
			$this->default_category = $this->db->get_row( trim( "
				SELECT `{$this->table_prefix}terms`.*
				FROM `{$this->table_prefix}terms`
				JOIN `{$this->table_prefix}options`
				ON `{$this->table_prefix}terms`.`term_id` = `{$this->table_prefix}options`.`option_value` AND `{$this->table_prefix}options`.`option_name` = 'default_category'"
			) );

			// make sure we have a default object if a default category isn't set
			if ( empty( $this->default_category ) ) {
				$this->default_category = new \StdClass();
				$this->default_category->term_id = '1';
				$this->default_category->name = 'Uncategorized';
				$this->default_category->slug = 'uncategorized';
				$this->default_category->term_group = '0';
			}
		}

		return $this->default_category;
	}

	/**
	 * Cache for author slugs
	 *
	 * @var array
	 */
	private $author_slugs_cache = [];

	/**
	 * Post permalink generation
	 *
	 * @param \StdClass $row
	 * @return string
	 */
	protected function get_permalink_post( \StdClass $row ) {
		if ( empty( $this->permalink_structure ) ) {
			return add_query_arg( 'p', $row->ID, $this->base_url );
		}

		$rewritecode = array(
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			'%postname%',
			'%post_id%',
			'%category%',
			'%author%',
			'%pagename%',
		);

		$date = explode( ' ', date( 'Y m d H i s', strtotime( $row->post_date ) ) );
		$author = '';
		$default_category = $this->get_default_category();
		$category = $default_category->slug;

		// author
		if ( strpos( $this->permalink_structure, '%author%' ) !== FALSE ) {
			if ( $row->post_author > 0 ) {
				if ( empty( $this->author_slugs_cache[ $row->post_author ] ) ) {
					$author_sql = trim( "
						SELECT user_nicename
						FROM `{$this->table_prefix}authors`
						WHERE `{$this->table_prefix}.ID` = %d
						LIMIT 1
					" );

					$this->author_slugs_cache[ $row->post_author ] = $this->db->get_var( $this->db->prepare( $author_sql, $row->post_author ) );
				}

				$author = $this->author_slugs_cache[ $row->post_author ];
			}
		}

		// category
		if ( strpos( $this->permalink_structure, '%category%' ) !== FALSE ) {
			$category_sql = trim( "
				SELECT `{$this->table_prefix}terms`.`slug` from {$this->table_prefix}terms
				JOIN `{$this->table_prefix}term_taxonomy` ON `{$this->table_prefix}terms`.`term_id` = `{$this->table_prefix}term_taxonomy`.term_id AND `{$this->table_prefix}term_taxonomy`.taxonomy = 'category'
				JOIN `{$this->table_prefix}term_relationships` ON `{$this->table_prefix}term_taxonomy`.`term_taxonomy_id` = `{$this->table_prefix}term_relationships`.term_taxonomy_id
				WHERE `{$this->table_prefix}term_relationships`.`object_id` = %d
				LIMIT 50
			" );


			$categories = $this->db->get_col( $this->db->prepare( $category_sql, $row->ID ) );

			if ( ! empty( $categories ) ) {
				if ( count( $categories ) > 1 ) {
					// yoast primary category
					$primary_category_placeholders = implode( ',', array_fill( 0, count( $categories ) , '%s' ) );
					$primary_category_sql_args     = array_merge( [ $row->ID ], $categories );

					// join the meta_value on the term_id to get the term slug
					$primary_category_sql = trim( "
						SELECT {$this->table_prefix}terms.slug
						FROM `{$this->table_prefix}postmeta`
						JOIN `{$this->table_prefix}terms`
						ON `{$this->table_prefix}postmeta`.`meta_value` = `{$this->table_prefix}terms`.`term_id`
						WHERE `{$this->table_prefix}postmeta`.`post_id` = %d
						AND `{$this->table_prefix}terms`.`slug` IN ($primary_category_placeholders)
						LIMIT 1
					" );

					$primary_category_slug = $this->db->get_var( $this->db->prepare( $primary_category_sql, $primary_category_sql_args ) );

					if ( ! empty( $primary_category_slug ) ) {
						$category = $primary_category_slug;
					}
				}

				// if no primary was found, use the first in the list
				if ( $default_category->slug === $category ) {
					$category = current( $categories );
				}
			}
		}

		$rewritereplace = [
			$date[0],
			$date[1],
			$date[2],
			$date[3],
			$date[4],
			$date[5],
			$row->post_name,
			$row->ID,
			$category,
			$author,
			$row->post_name,
		];

		$permalink = str_replace( $rewritecode, $rewritereplace , $this->permalink_structure );

		return rtrim( $permalink, '/' );
	}

	/**
	 * Page permalink generation, use this function in `get_permalink` of your post type source if hierarchical
	 *
	 * @param \StdClass $row
	 * @return string
	 */
	protected function get_permalink_page( \StdClass $row ) {
		if ( empty( $this->permalink_structure ) ) {
			return add_query_arg( 'page_id', $row->ID, $this->base_url );
		}

		$slugs = [ $row->post_name ];

		while( ! empty( $row ) && intval( $row->post_parent ) > 0 ) {
			$row = $this->get_row( $row->post_parent, [ 'post_parent', 'post_name' ] );

			if ( ! empty( $row ) ) {
				array_push( $slugs, $row->post_name );
			}
		}

		return '/' . implode( '/', $slugs );
	}

	/**
	 * Attachment permalinks modified from \get_attachment_link
	 *
	 * @param \StdClass $row
	 * @return string
	 */
	public function get_permalink_attachment( \StdClass $row ) {
		$link = false;

		$parent = ( $row->post_parent > 0 && $row->post_parent != $row->ID ) ? $this->get_row( $row->post_parent ) : false;

		if ( ! empty( $this->permalink_structure ) && $parent ) {
			$parentlink = $this->get_permalink( $parent );

			$this->output->debug( 'parentlink', $parentlink );

			if ( is_numeric( $row->post_name ) || false !== strpos( $this->permalink_structure, '%category%' ) ) {
				$name = 'attachment/' . $row->post_name; // <permalink>/<int>/ is paged so we use the explicit attachment marker
			} else {
				$name = $row->post_name;
			}

			if ( strpos( $parentlink, '?' ) === false ) {
				$link = user_trailingslashit( trailingslashit( $parentlink ) . '%postname%' );
			}

			$link = str_replace( '%postname%', $name, $link );
		} elseif ( ! empty( $permalink_structure ) ) {
			$link = home_url( user_trailingslashit( $row->post_name ) );
		}

		if ( ! $link ) {
			$link = home_url( '/?attachment_id=' . $row->ID );
		}

		return $link;
	}

	/**
	 * Custom post type default permalink, override this for a custom rewrite rule implementation in your Source class
	 *
	 * @param \StdClass $row
	 * @return string
	 */
	protected function get_permalink_custom( \StdClass $row ) {
		return '/' .$this->post_type . '/' . $row->post_name;
	}

	/**
	 * Term permalink generation, use this function in `get_term_link` of your term type source if hierarchical
	 *
	 * @param \StdClass $row
	 * @return string
	 */
	protected function get_term_link( $row ) {
		return '';
		if ( empty( $this->permalink_structure ) ) {
			return add_query_arg( 'page_id', $row->ID, $this->base_url );
		}

		$slugs = [ $row->post_name ];

		while( ! empty( $row ) && intval( $row->post_parent ) > 0 ) {
			$row = $this->get_row( $row->post_parent, [ 'post_parent', 'post_name' ] );

			if ( ! empty( $row ) ) {
				array_push( $slugs, $row->post_name );
			}
		}

		return '/' . implode( '/', $slugs );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_keys() {
		return array_column( $this->results, $this->key_field );
	}


}
