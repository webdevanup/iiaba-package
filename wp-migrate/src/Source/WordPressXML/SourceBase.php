<?php
/**
 * @file
 *
 * Base class for migrating content from WordPress XML Export Files.
 */

namespace WDG\Migrate\Source\WordPressXML;

abstract class SourceBase extends \WDG\Migrate\Source\SourceBase {

	// Database
	public $data;

	// protected $db;
	protected $index   = 0;
	protected $results = [];

	protected $source_key_prefix = '';

	protected $included_stati = [
		'publish',
		// 'draft',
	];

	protected $process = true;

	protected $base_fields       = [];
	protected $meta_fields       = [];
	protected $term_fields       = [];
	protected $attachment_fields = [];

	protected $xml_path;

	/**
	 * @var \SimpleXMLElement
	 */
	protected $xml;
	
	protected $base_url;
	protected $post_type;
	protected $limit;
	protected $namespaces;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, \WDG\Migrate\Output\OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = trailingslashit( $arguments['base_url'] );
		}

		if ( ! empty( $arguments['type'] ) ) {
			$this->post_type = $arguments['type'];
		}

		if ( ! empty( $arguments['counts_only'] ) ) {
			$this->process = false;
		}

		if ( ! empty( $arguments['source_key_prefix'] ) ) {
			$this->source_key_prefix = $arguments['source_key_prefix'];
		}

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				case 'term':
				case 'term_field':
					$this->term_fields[ $options['key'] ?? $field ] = $field;
				break;
				case 'meta':
				case 'meta_field':
					$this->meta_fields[ $options['key'] ?? $field ] = $field;
				break;
				case 'attachment':
				case 'attachment_field':
					$this->attachment_fields[ $options['key'] ?? $field ] = $field;
				break;
				case 'base':
				default:
					$this->base_fields[ $options['key'] ?? $field ] = $field;
				break;
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {

		// Apply limit and offset
		if ( ! empty( $arguments['limit'] ) ) {
			$this->limit = $arguments['limit'] . ( ! empty( $arguments['offset'] ) ? ' OFFSET ' . $arguments['offset'] : '' );
		} elseif ( ! empty( $arguments['offset'] ) ) {
			$this->limit = $arguments['offset'] . ',' . PHP_INT_MAX;
		}
		// load data
		$this->load_xml( $arguments );

		if ( $this->process ) {
			$this->process_xml( $arguments );
		}

		$this->rewind();

		$this->output->progress( 'Queried ' . $this->count() . ' records.', null, 1 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function count() : int {
		if ( ! $this->process ) {
			static $results;
			$key = $this->base_url . $this->post_type;
			if ( empty( $results[ $key ] ) ) {
				$results[ $key ] = array_map(
					function( $item ) {
						$wp      = $item->children( $this->namespaces['wp'] );
						$post = new \StdClass();
						$post->ID = $this->source_key_prefix . (int) $wp->post_id;
						return $post;
					},
					array_filter(
						$this->process_posts( [] ),
						function( $item ){
							$wp = $item->children( $this->namespaces['wp'] );
							return in_array( (string) $wp->status, $this->included_stati, true );
						}
					)
				);
			}
			$this->results = $results[ $key ];
		}

		return count( $this->results );
	}

	/**
	 * {@inheritdoc}
	 */
	public function current() : mixed {
		return $this->results[ $this->index ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function key(): mixed {
		return $this->index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function next() : void {
		++$this->index;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewind() : void {
		$this->index = 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function valid() : bool {
		return isset( $this->results[ $this->index ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() {
		$this->results = array();
		$this->rewind();
	}

	/**
	 * Load XML from the source file
	 *
	 * - forked from wordpress-importer WXR_Parser_SimpleXML
	 *
	 * @access protected
	 */
	protected function load_xml( $arguments ) {
		if ( isset( $this->data ) ) {
			return;
		}

		$this->data = new \StdClass();

		$this->data->authors     = [];
		$this->data->terms       = [];
		$this->data->attachments = [];
		$this->data->posts       = [];

		$dom       = new \DOMDocument();
		$old_value = null;

		if ( function_exists( 'libxml_disable_entity_loader' ) && \LIBXML_VERSION < 20900 ) {
			$old_value = libxml_disable_entity_loader( true );
		}
		$success = $dom->loadXML( file_get_contents( $this->xml_path ) );

		if ( ! is_null( $old_value ) ) {
			libxml_disable_entity_loader( $old_value );
		}

		if ( ! $success || isset( $dom->doctype ) ) {
			$this->output->error( 'There was an error when reading this WXR file: %s', implode( "\n", (array) libxml_get_errors() ) );
			exit;
		}

		$this->xml = simplexml_import_dom( $dom );
		unset( $dom );

		if ( ! $this->xml ) {
			$this->output->error( 'There was an error when reading this WXR file: %s', implode( "\n", (array) libxml_get_errors() ) );
			exit;
		}

		$base_url       = $this->xml->xpath( '/rss/channel/wp:base_site_url' );
		$this->base_url = (string) trim( isset( $base_url[0] ) ? $base_url[0] : '' );

		$base_blog_url = $this->xml->xpath( '/rss/channel/wp:base_blog_url' );
		if ( $base_blog_url ) {
			$base_blog_url = (string) trim( $base_blog_url[0] );
		} else {
			$base_blog_url = $base_url;
		}

		$this->namespaces = $this->xml->getDocNamespaces();
		if ( ! isset( $this->namespaces['wp'] ) ) {
			$this->namespaces['wp'] = 'http://wordpress.org/export/1.1/';
		}
		if ( ! isset( $this->namespaces['excerpt'] ) ) {
			$this->namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';
		}
	}

	protected function process_xml( $arguments ) {
		$this->process_authors( $arguments );
		$this->process_taxomonies( $arguments );
		$this->process_attachments( $arguments );
		$this->process_posts( $arguments );
	}

	protected function process_attachments( $arguments ) {
		// grab authors
		foreach ( $this->xml->xpath( '/rss/channel/item[wp:post_type="attachment"]' ) as $attachment_xml ) {
			$attachment      = new \StdClass();
			$attachment_atts = $attachment_xml->children( $this->namespaces['wp'] );
			$attachment_id   = (string) $attachment_atts->post_id;

			foreach ( $attachment_atts as $attr => $attr_value ) {
				$attachment->$attr = (string) $attr_value;
			}

			unset( $attr, $attr_value );

			$this->data->attachments[ $attachment_id ] = $attachment;
		}
	}

	protected function process_authors( $arguments ) {
		// grab authors
		foreach ( $this->xml->xpath( '/rss/channel/wp:author' ) as $author_xml ) {
			$author      = new \StdClass();
			$author_atts = $author_xml->children( $this->namespaces['wp'] );
			$author_key  = (string) $author_atts->author_login;

			foreach ( $author_atts as $attr => $attr_value ) {
				$author->$attr = (string) $attr_value;
			}

			unset( $attr, $attr_value );

			$this->data->authors[ $author_key ] = $author;
		}
	}

	protected function process_taxomonies( $arguments ) {
		// grab categories
		$this->data->terms['category'] = [];
		foreach ( $this->xml->xpath( '/rss/channel/wp:category' ) as $cat_xml ) {
			$cat      = new \StdClass();
			$cat_atts = $cat_xml->children( $this->namespaces['wp'] );

			$cat->term_id     = (string) $cat_atts->term_id;
			$cat->slug        = (string) $cat_atts->category_nicename;
			$cat->parent      = (string) $cat_atts->category_parent;
			$cat->name        = (string) $cat_atts->cat_name;
			$cat->description = (string) $cat_atts->cat_description;
			$cat->termmeta    = [];

			if ( ! empty( $cat_atts->termmeta ) ) {
				foreach ( $cat_atts->termmeta as $termmeta ) {
					array_push(
						$cat->termmeta,
						[
							'key'   => (string) $termmeta->meta_key,
							'value' => (string) $termmeta->meta_value,
						]
					);
				}
			}

			// store the term under it's taxonomy and keyed by term slug
			$this->data->terms['category'][ $cat->term_id ] = $cat;
			unset( $cat_xml, $cat_atts, $cat );
		}

		// grab tags
		$this->data->terms['post_tag'] = [];
		foreach ( $this->xml->xpath( '/rss/channel/wp:tag' ) as $tag_xml ) {
			$tag      = new \StdClass();
			$tag_atts = $tag_xml->children( $this->namespaces['wp'] );

			$tag->term_id     = (string) $tag_atts->term_id;
			$tag->slug        = (string) $tag_atts->tag_slug;
			$tag->name        = (string) $tag_atts->tag_name;
			$tag->description = (string) $tag_atts->tag_description;
			$tag->termmeta    = [];

			if ( ! empty( $tag_atts->termmeta ) ) {
				foreach ( $tag_atts->termmeta as $termmeta ) {
					array_push(
						$tag->termmeta,
						[
							'key'   => (string) $termmeta->meta_key,
							'value' => (string) $termmeta->meta_value,
						]
					);
				}
			}

			// store the term under it's taxonomy and keyed by term slug
			$this->data->terms['post_tag'][ $tag->term_id ] = $tag;
			unset( $tag_xml, $tag_atts, $tag );
		}

		// grab terms
		foreach ( $this->xml->xpath( '/rss/channel/wp:term' ) as $term_xml ) {
			$term      = new \StdClass();
			$term_atts = $term_xml->children( $this->namespaces['wp'] );

			$term->term_id     = (string) $term_atts->term_id;
			$term->taxonomy    = (string) $term_atts->term_taxonomy;
			$term->slug        = (string) $term_atts->term_slug;
			$term->parent      = (string) $term_atts->term_parent;
			$term->name        = (string) $term_atts->term_name;
			$term->description = (string) $term_atts->term_description;
			$term->termmeta = [];

			if ( ! empty( $term_atts->termmeta ) ) {
				foreach ( $term_atts->termmeta as $termmeta ) {
					array_push(
						$term->termmeta,
						[
							'key'   => (string) $termmeta->meta_key,
							'value' => (string) $termmeta->meta_value,
						]
					);
				}
			}

			if ( ! isset( $this->data->terms[ $term->taxonomy ] ) ) {
				$this->data->terms[ $term->taxonomy ] = [];
			}

			// store the term under it's taxonomy and keyed by term slug
			$this->data->terms[ $term->taxonomy ][ $term->term_id ] = $term;
			unset( $term_xml, $term_atts, $term );
		}

	}

	protected function process_posts( $arguments ) {

		$selector = '/rss/channel/item';

		if ( ! empty( $this->post_type ) ) {
			$selector .= sprintf( '[wp:post_type="%s"]', $this->post_type );
		}

		if ( ! empty( $arguments['id'] ) ) {
			$selector .= sprintf(
				'[%s]',
				implode(
					' or ',
					array_map(
						function( $id ) {
							return sprintf( 'wp:post_id="%s"', $id );
						},
						$arguments['id']
					)
				)
			);
		}

		if ( ! empty( $this->included_stati ) ) {
			$selector .= sprintf(
				'[%s]',
				implode(
					' or ',
					array_map(
						function( $id ) {
							return sprintf( 'wp:status="%s"', $id );
						},
						$this->included_stati
					)
				)
			);
		}

		$this->output->debug( 'selector', $selector );

		$items = $this->xml->xpath( $selector );

		if ( ! $this->process ) {
			return $items;
		}

		if ( ! empty( $arguments['limit'] ) ) {
			$limit = intval( $arguments['limit'] );
			$start = 0;

			if ( ! empty( $arguments['offset'] ) ) {
				$offset = intval( $arguments['offset'] );
				$start  = $limit * $offset;
			}

			$items = array_slice( $items, $start, $limit );
		}

		// grab posts
		/**
		 * Post columns
		 *
		 * - ID
		 * - post_author
		 * - post_date
		 * - post_date_gmt
		 * - post_content
		 * - post_title
		 * - post_excerpt
		 * - post_status
		 * - comment_status
		 * - ping_status
		 * - post_password
		 * - post_name
		 * - to_ping
		 * - pinged
		 * - post_modified
		 * - post_modified_gmt
		 * - post_content_filtered
		 * - post_parent
		 * - guid
		 * - menu_order
		 * - post_type
		 * - post_mime_type
		 * - comment_count
		 */
		foreach ( $items as $item ) {
			$post = new \StdClass();

			$wp      = $item->children( $this->namespaces['wp'] );
			$dc      = $item->children( 'http://purl.org/dc/elements/1.1/' );
			$excerpt = $item->children( $this->namespaces['excerpt'] );
			$content = $item->children( 'http://purl.org/rss/1.0/modules/content/' );

			$post->ID             = (int) $wp->post_id;
			$post->post_author    = (string) $dc->creator;
			$post->post_date      = (string) $wp->post_date;
			$post->post_date_gmt  = (string) $wp->post_date_gmt;
			$post->post_content   = (string) $content->encoded;
			$post->post_title     = (string) $item->title;
			$post->post_excerpt   = (string) $excerpt->encoded;
			$post->post_status    = (string) $wp->status;
			$post->comment_status = (string) $wp->comment_status;
			$post->ping_status    = (string) $wp->ping_status;
			$post->post_password  = (string) $wp->post_password;
			$post->post_name      = (string) $wp->post_name;
			$post->guid           = (string) $item->guid;
			$post->post_parent    = (int) $wp->post_parent;
			$post->menu_order     = (int) $wp->menu_order;
			$post->post_type      = (string) $wp->post_type;
			$post->post_mime_type = (string) $wp->post_mime_type;
			$post->is_sticky      = (int) $wp->is_sticky;
			$post->link           = (string) $item->link;
			$post->featured_image = null;
			$post->postmeta       = [];
			$post->terms          = [];

			if ( 'attachment' === $post->post_type ) {
				$post->attachment_url = (string) $wp->attachment_url;
			}

			if ( ! in_array( $post->post_status, $this->included_stati, true ) ) {
				continue;
			}

			if ( ! empty( $this->data->authors[ $post->post_author ] ) ) {
				$post->post_author = $this->data->authors[ $post->post_author ];
			}

			foreach ( $wp->postmeta as $meta ) {
				$meta_key   = (string) $meta->meta_key;
				$meta_value = (string) $meta->meta_value;

				if ( isset( $post->postmeta[ $meta_key ] ) ) {
					if ( ! is_array( $post->postmeta[ $meta_key ] ) ) {
						$post->postmeta[ $meta_key ] = [ $post->postmeta[ $meta_key ] ];
					}

					array_push( $post->postmeta[ $meta_key ], $meta_value );
				} else {
					$post->postmeta[ $meta_key ] = $meta_value;
				}

				if ( ! empty( $this->meta_fields[ $meta_key ] ) ) {
					$post->$meta_key = $post->postmeta[ $meta_key ];
				}

				if ( ! empty( $this->attachment_fields[ $meta_key ] ) ) {
					$post_key = $this->attachment_fields[ $meta_key ] ?? $meta_key;

					if ( is_array( $post->postmeta[ $meta_key ] ) ) {
						$post->$post_key ??= [];

						foreach ( $post->postmeta[ $this->attachment_fields[ $meta_key ] ] as $meta_value ) {
							$media = $this->get_media( $meta_value );

							if ( ! empty( $media ) ) {
								$post->$post_key = $media->attachment_url;
							}
						}
					} else {
						$media = $this->get_media( $meta_value );

						if ( ! empty( $media ) ) {
							$post->$post_key = $media->attachment_url;
						}
					}
				}

				unset( $meta_key, $meta_value );
			}

			// the export stores all terms under the category attribute regardless of taxonomy
			foreach ( $item->category as $term ) {
				$term_atts = $term->attributes();
				$name      = (string) $term;
				$taxonomy  = (string) $term_atts['domain'];

				if ( ! empty( $taxonomy ) ) {
					if ( ! empty( $this->term_fields[ $taxonomy ] ) ) {
						$post->$taxonomy ??= [];
						$post->$taxonomy[] = $name;
					}

					array_push(
						$post->terms,
						(string) $term,
						[
							'name'     => $name,
							'slug'     => (string) $term_atts['nicename'],
							'taxonomy' => $taxonomy,
						]
					);
				}

				unset( $term_atts );
			}

			/* don't care about comments for now
			foreach ( $wp->comment as $comment ) {
				$meta = array();
				if ( isset( $comment->commentmeta ) ) {
					foreach ( $comment->commentmeta as $m ) {
						$meta[] = array(
							'key' => (string) $m->meta_key,
							'value' => (string) $m->meta_value
						);
					}
				}

				$post['comments'][] = array(
					'comment_id' => (int) $comment->comment_id,
					'comment_author' => (string) $comment->comment_author,
					'comment_author_email' => (string) $comment->comment_author_email,
					'comment_author_IP' => (string) $comment->comment_author_IP,
					'comment_author_url' => (string) $comment->comment_author_url,
					'comment_date' => (string) $comment->comment_date,
					'comment_date_gmt' => (string) $comment->comment_date_gmt,
					'comment_content' => (string) $comment->comment_content,
					'comment_approved' => (string) $comment->comment_approved,
					'comment_type' => (string) $comment->comment_type,
					'comment_parent' => (string) $comment->comment_parent,
					'comment_user_id' => (int) $comment->comment_user_id,
					'commentmeta' => $meta,
				);
			}
			*/

			if ( ! isset( $this->data->posts[ $post->post_type ] ) ) {
				$this->data->posts[ $post->post_type ] = [];
			}

			$this->data->posts[ $post->post_type ][ $post->ID ] = $post;
		}

		if ( ! empty( $this->post_type ) && ! empty( $this->data->posts[ $this->post_type ] ) ) {
			$this->results = array_values( $this->data->posts[ $this->post_type ] );
		}

		$this->output->debug( 'Loaded XML File: ' . $this->xml_path );
	}

	public function get_media( $id ) {
		$attachment_from_data = $this->data->attachments[ $id ] ?? null;

		if ( ! empty( $attachment_from_data ) ) {
			return $attachment_from_data;
		}

		$items = $this->xml->xpath( sprintf( '/rss/channel/item[wp:post_id="%d"]', intval( $id ) ) );

		if ( empty( $items ) ) {
			return null;
		}

		foreach ( $items as $item ) {
			$media = new \StdClass();

			foreach ( $item as $prop => $val ) {
				$media->{ (string) $prop } = (string) $val;
			}

			$wp = $item->children( $this->namespaces['wp'] );

			foreach ( $wp as $prop => $val ) {
				$media->{ (string) $prop } = (string) $val;
			}

			return $media;
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_keys() {
		return array_column( $this->results, 'ID' );
	}


}
