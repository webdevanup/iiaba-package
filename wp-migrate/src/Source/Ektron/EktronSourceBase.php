<?php
/**
 * @file
 *
 * Base class for migrating content from Ektron.
 */

namespace WDG\Migrate\Source\Ektron;

use WDG\Migrate\Source\SourceBase;
use WDG\Migrate\Output\OutputInterface;

abstract class EktronSourceBase extends SourceBase {

	// Data
	protected $items = [];

	// Fields
	protected $permissions = false;
	protected $taxonomies  = false;

	// Loop variables
	protected $limit  = null;
	protected $offset = 0;

	// Global settings
	protected $base_url    = '';
	protected $base_folder = 1; // Something?
	protected $filter      = null;

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		$required_constants = [
			'MIGRATE_EKTRON_USERNAME',
			'MIGRATE_EKTRON_PASSWORD',
			'MIGRATE_EKTRON_DOMAIN',
		];
		foreach ( $required_constants as $required_constant ) {
			if ( ! defined( $required_constant ) ) {
				$this->output->error( 'Missing required constant ' . $required_constant . '!', 'Source Construct' );
			}
		}

		if ( ! empty( $arguments['base_url'] ) ) {
			$this->base_url = $arguments['base_url'];
		} else {
			// Assume http because Ektron is old
			$this->base_url = 'http://' . MIGRATE_EKTRON_DOMAIN;
		}
		// No trailing slash on base_url
		$this->base_url = untrailingslashit( $this->base_url );

		// Starting Folder
		if ( ! empty( $arguments['base_folder'] ) ) {
			$this->base_folder = $arguments['base_folder'];
		}

		// Items filter
		if ( ! empty( $arguments['filter'] ) ) {
			if ( ! is_callable( $arguments['filter'] ) ) {
				$this->output->error( 'Filter is not callable', 'Source Construct' );
			} else {
				$this->filter = $arguments['filter'];
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function init( array $arguments ) {
		// Apply limit and offset
		if ( ! empty( $arguments['limit'] ) ) {
			$this->limit = $arguments['limit'];
		}
		if ( ! empty( $arguments['offset'] ) ) {
			$this->offset = $arguments['offset'];
		}

		// Get items
		$this->items = [];
		if ( ! empty( $arguments['id'] ) ) {
			// Get just specified items
			if ( is_array( $arguments['id'] ) ) {
				$ids = $arguments['id'];
			} else {
				$ids = [ $arguments['id'] ];
			}
			$this->getItems( $ids );

		} else {
			$this->output->progress( 'Getting items in folder ' . $this->base_folder, 'Source Init' );
			// Query all folders
			$folders = $this->getAllFolders( $this->base_folder );
			if ( false === $folders ) {
				$this->output->error( 'Unable to retrieve item folders', 'Source Init' );
				return false;
			}
			$this->output->progress( 'Retrieved ' . count( $folders ) . ' folders', 'Source Init' );

			// Get all items
			$count = 0;
			foreach ( $folders as $folderId => $name ) {
				// Must define getFolderItems()
				$ids = $this->getFolderItems( $folderId );
				$this->output->debug( $name . ' (' . $folderId . '): Retrieved ' . count( $ids ) . ' ids', 'Folder' );

				$prevCount = $count;
				$this->getItems( $ids, $count );
				if ( $count > $prevCount ) {
					$this->output->progress( 'Retrieved ' . $count . ' items.', null, 2 );
				}

				// Break early if limit is reached
				if ( $this->limit && $count >= $this->limit + $this->offset ) {
					break;
				}
			}
		}

		// Finish init
		$this->rewind();
		$this->output->progress( 'Retrieved ' . $this->count() . ' items.', null, 1 );
	}

	/**
	 * Internal function to get filtered items
	 *
	 * @param array $ids
	 * @param int &$count
	 */
	protected function getItems( array $ids, &$count = 0 ) {
		foreach ( $ids as $id ) {
			$this->output->debug( $id, 'Item' );
			$item = $this->getItem( $id );

			// Skip false-y items
			if ( false === $item ) {
				continue;
			}

			// Count item
			$count++;

			// Skip items before the offset is reached
			if ( $count <= $this->offset ) {
				continue;
			}

			// Add item
			$this->items[] = $item;

			// Break early if limit is reached
			if ( $this->limit && $count >= $this->limit + $this->offset ) {
				break;
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function count() : int {
		return count( $this->items );
	}

	/**
	 * {@inheritdoc}
	 */
	public function current() : mixed {
		return $this->items[ $this->index ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function key() : mixed {
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
		return isset( $this->items[ $this->index ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function cleanup() {
		$this->items = [];
		$this->rewind();
	}

	/**
	 * Get all folders starting from a folder id
	 *
	 * @param numeric $folderId
	 * @return array Folders keyed by ID
	 */
	protected function getAllFolders( $folderId = 1 ) {
		$request = '
			<GetChildFolders xmlns="http://tempuri.org/">
				<FolderID>' . $folderId . '</FolderID>
				<Recursive>true</Recursive>
				<OrderBy>Id</OrderBy>
			</GetChildFolders>
		';
		$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Folder.asmx', $this->soapEnvelope( $request ), 'folders' );
		if ( is_wp_error( $data ) || empty( $data->GetChildFoldersResponse->GetChildFoldersResult->FolderData ) ) {
			return false;
		}

		return $this->getFolders( $data->GetChildFoldersResponse->GetChildFoldersResult->FolderData );
	}

	/**
	 * Get folders recursively
	 *
	 * @param SimpleXMLElement|false $folderData
	 * @param int $level
	 * @return array Folders keyed by ID
	 */
	protected function getFolders( $foldersData, $level = 0 ) {
		$folders = [];
		if ( $foldersData ) {
			foreach ( $foldersData as $folderData ) {
				$folders[ (string) $folderData->Id ] = str_repeat( '--', $level ) . $folderData->Name;

				$childFolders = [];
				if ( ! empty( $folderData->ChildFolders->FolderData ) ) {
					$childFolders = $this->getFolders( $folderData->ChildFolders->FolderData, $level + 1 );
				}
				$folders += $childFolders;
			}
		}
		return $folders;
	}

	/**
	 * Get content by id
	 *
	 * @param numeric $contentId
	 * @return SimpleXMLElement|false
	 */
	protected function getContent( $contentId ) {
		$request = '
			<GetContent xmlns="http://tempuri.org/">
				<ContentID>' . $contentId . '</ContentID>
				<ResultType>Published</ResultType>
			</GetContent>
		';
		$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Content/Content.asmx', $this->soapEnvelope( $request ), 'content-' . $contentId );
		if ( is_wp_error( $data ) || empty( $data->GetContentResponse->GetContentResult ) ) {
			return false;
		}

		return $data->GetContentResponse->GetContentResult;
	}

	/**
	 * Get permissions by id
	 *
	 * @param numeric $contentId
	 * @return SimpleXMLElement|false
	 */
	protected function getPermissions( $contentId ) {
		$request = '
			<GetUserPermissions xmlns="http://tempuri.org/">
				<Id>' . $contentId . '</Id>
				<ItemType>Content</ItemType>
			</GetUserPermissions>
		';
		$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Permissions.asmx', $this->soapEnvelope( $request ), 'permissions-' . $contentId );
		if ( is_wp_error( $data ) || empty( $data->GetUserPermissionsResponse->GetUserPermissionsResult->UserPermissionData ) ) {
			return false;
		}

		return $data->GetUserPermissionsResponse->GetUserPermissionsResult->UserPermissionData;
	}

	/**
	 * Get taxonomies by id
	 *
	 * @param numeric $contentId
	 * @return SimpleXMLElement|false
	 */
	protected function getTaxonomies( $contentId ) {
		$request = '
			<ReadAllAssignedCategory xmlns="http://tempuri.org/">
				<ContentId>' . $contentId . '</ContentId>
			</ReadAllAssignedCategory>
		';
		$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Taxonomy/Taxonomy.asmx', $this->soapEnvelope( $request ), 'taxonomy-' . $contentId );
		if ( is_wp_error( $data ) || empty( $data->ReadAllAssignedCategoryResponse->ReadAllAssignedCategoryResult->TaxonomyBaseData ) ) {
			return false;
		}

		return $data->ReadAllAssignedCategoryResponse->ReadAllAssignedCategoryResult->TaxonomyBaseData;
	}

	/**
	 * Soap Envelope wrapper (includes auth)
	 *
	 * @param string $body Soap body XML
	 * @return string
	 */
	protected function soapEnvelope( $body ) {
		return '<?xml version="1.0" encoding="utf-8"?>
			<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
				<soap12:Header>
					<AuthenticationHeader xmlns="http://tempuri.org/">
						<Username>' . MIGRATE_EKTRON_USERNAME . '</Username>
						<Password>' . MIGRATE_EKTRON_PASSWORD . '</Password>
						<Domain>' . MIGRATE_EKTRON_DOMAIN . '</Domain>
					</AuthenticationHeader>
					<RequestInfoParameters xmlns="http://tempuri.org/">
						<ContentLanguage>1033</ContentLanguage>
					</RequestInfoParameters>
				</soap12:Header>
				<soap12:Body>' . $body . '</soap12:Body>
			</soap12:Envelope>';
	}

	/**
	 * Makes a soap POST request and returns the parsed XML
	 *
	 * @param string $url
	 * @param string $request
	 * @return SimpleXMLElement|WP_Error
	 */
	protected function soapPost( $url, $request, $name = 'soap' ) {
		static $dir;
		if ( empty( $dir ) ) {
			$dir        = sys_get_temp_dir();
			$upload_dir = wp_get_upload_dir();
			if ( ! empty( $upload_dir['basedir'] ) ) {
				$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'ektron-cache';
				if ( ! file_exists( $cache_dir ) ) {
					wp_mkdir_p( $cache_dir );
				}
				if ( file_exists( $cache_dir ) ) {
					$dir = $cache_dir;
				}
			}
		}
		$file = trailingslashit( $dir ) . $name . '.xml';

		if ( file_exists( $file ) ) {
			$data = file_get_contents( $file );
		} else {
			$response = wp_remote_post(
				$url,
				[
					'headers' => [
						'Content-Type' => 'text/xml; charset=utf-8',
					],
					'body' => $request,
				]
			);

			$data = wp_remote_retrieve_body( $response );

			if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->output->debug( print_r( $data ) );
				$this->output->error( 'Non-200 response: ' . wp_remote_retrieve_response_message( $response ), 'SOAP Post' );
				return new \WP_Error( 'soap', 'Non-200 response', $response );
			}

			// Cache in ektron cache
			@file_put_contents( $file, $data );
		}

		$xml = $this->getXML( $data, true );
		if ( is_wp_error( $xml ) ) {
			// Rename file to debug
			$debug_file = trailingslashit( $dir ) . $name . '-debug.xml';
			rename( $file, $debug_file );
			$this->output->error( $debug_file . ' - ' . $xml->get_error_message(), 'SOAP Post' );
		}

		return $xml;
	}

	/**
	 * Parse string as XML
	 *
	 * @param string $data
	 * @param bool $soap Unwrap soap?
	 * @return SimpleXMLElement|WP_Error
	 */
	protected function getXML( $data, $soap = true ) {
		libxml_use_internal_errors();
		$xml = @simplexml_load_string( $data );
		if ( false === $xml ) {
			$message = '';
			if ( $errors = libxml_get_errors() ) {
				foreach ( $errors as $error ) {
					$type = '';
					switch ( $error->level ) {
						case LIBXML_ERR_WARNING:
							$type .= 'Warning';
							break;
						case LIBXML_ERR_FATAL:
							$type .= 'Fatal Error';
							break;
						case LIBXML_ERR_ERROR:
						default:
							$type .= 'Error';
							break;
					}
					if ( strlen( $message ) > 0 ) {
						$message .= PHP_EOL;
					}
					$message .= $type . ' ' . $error->code . ': ' . trim( $error->message );
					if ( ! empty( $error->line ) ) {
						$message .= ' Line: ' . $error->line;
					}
					if ( ! empty( $error->column ) ) {
						$message .= ' Column: ' . $error->column;
					}
				}
			}
			return new \WP_Error( 'xml', $message );
		}
		if ( $soap ) {
			if ( empty( $xml->children( 'soap', true )->Body ) ) {
				return new \WP_Error( 'soap', 'Missing SOAP body!' );
			}
			$xml = $xml->children( 'soap', true )->Body->children();
		}

		return $xml;
	}

	/**
	 * Standard method of traversing SimpleXMLElement for a value
	 *
	 * @param SimpleXMLElement $element
	 * @param string|array $key
	 * @param bool $string Cast value as string
	 * @return mixed
	 */
	protected function getXMLValue( $element, $key, $string = false ) {
		if ( ! is_object( $element ) ) {
			return false;
		}

		if ( is_array( $key ) ) {
			$keys  = array_values( $key );
			$count = count( $keys );
			foreach ( $keys as $index => $key ) {
				$element = $this->getXMLValue( $element, $key, ( $index === $count - 1 ? $string : false ) );
			}
			return $element;
		}

		if ( isset( $element->{ $key } ) ) {
			$value = $element->{ $key };
			if ( $string ) {
				return (string) $value;
			}
			return $value;
		}

		return false;
	}

	/**
	 * Get source item ids by folder
	 *
	 * @param numeric $folderId
	 * @return array of ids
	 */
	abstract protected function getFolderItems( $folderId );

	/**
	 * Get source item by id
	 *
	 * Should call $this->filter with $item in progress
	 *
	 * @param numeric $itemId
	 * @return object|false $item
	 */
	abstract protected function getItem( $itemId );

}
