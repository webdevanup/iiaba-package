<?php
/**
 * @file
 *
 * Base class for migrating library items from Ektron.
 */

namespace WDG\Migrate\Source\Ektron;

use WDG\Migrate\Source\Ektron\EktronSourceBase;
use WDG\Migrate\Output\OutputInterface;

class LibrarySource extends EktronSourceBase {

	protected $library_fields = [];
	protected $content_fields = [];

	protected $library_types = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct( array $arguments, OutputInterface $output ) {
		parent::__construct( $arguments, $output );

		foreach ( $arguments['fields'] as $field => $options ) {
			switch ( $options['type'] ) {
				/**
				 * 'type' => 'id',
				 */
				case 'id':
					$options['key']    = 'Id';
					$options['string'] = true;
					// Pass through
					/**
					 * 'type' => 'library',
					 * 'key' => 'Title' OR [ 'TemplateConfiguration', 'FileName' ]
					 */
				case 'library':
					$this->library_fields[ $field ] = [
						'key' => $options['key'],
						'string' => ! empty( $options['string'] ),
					];
					break;
				/**
				 * 'type' => 'content',
				 * 'key' => 'Title' OR [ 'TemplateConfiguration', 'FileName' ]
				 */
				case 'content':
					$this->content_fields[ $field ] = [
						'key' => $options['key'],
						'string' => ! empty( $options['string'] ),
					];
					break;
				/**
				 * 'type' => 'permissions',
				 */
				case 'permissions':
					$this->permissions = $field;
					break;
				/**
				 * 'type' => 'taxonomies',
				 */
				case 'taxonomies':
					$this->taxonomies = $field;
					break;
			}
		}

		if ( ! empty( $arguments['library_types'] ) ) {
			$this->library_types = (array) $arguments['library_types'];
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getFolderItems( $folderId ) {
		$ids = [];

		foreach ( $this->library_types as $type ) {
			// Assumption - less than 1000 items per type, per folder
			$request = '
				<GetAllChildLibItems xmlns="http://tempuri.org/">
					<Type>' . $type . '</Type>
					<ParentId>' . $folderId . '</ParentId>
					<OrderBy>libraryid</OrderBy>
					<currentPageNum>0</currentPageNum>
					<pageSize>1000</pageSize>
				</GetAllChildLibItems>
			';
			$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Library.asmx', $this->soapEnvelope( $request ), 'folderLibrary-' . $folderId . '-' . $type );

			if ( ! is_wp_error( $data ) && isset( $data->GetAllChildLibItemsResponse->GetAllChildLibItemsResult->LibraryData ) ) {
				foreach ( $data->GetAllChildLibItemsResponse->GetAllChildLibItemsResult->LibraryData as $library ) {
					$ids[] = (string) $library->Id;
				}
			}
		}

		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getItem( $itemId ) {
		$item = [];

		$library = $this->getLibrary( $itemId );
		if ( ! $library ) {
			$this->output->error( 'Unable to get library for item ' . $itemId . '!' );
			return false;
		}

		// Populate library fields
		foreach ( $this->library_fields as $field => $options ) {
			$item[ $field ] = $this->getXMLValue( $library, $options['key'], $options['string'] );
		}

		// Skip filtered items
		if ( $this->filter && ! call_user_func( $this->filter, (object) $item ) ) {
			return false;
		}

		if ( ! empty( $this->content_fields ) || false !== $this->permissions || false !== $this->taxonomies ) {
			$content = $this->getContent( (string) $library->ContentId );
			if ( ! $content ) {
				$this->output->error( 'Unable to get content for item ' . $itemId . '!' );
				return false;
			}

			// Populate content fields
			foreach ( $this->content_fields as $field => $options ) {
				$item[ $field ] = $this->getXMLValue( $content, $options['key'], $options['string'] );
			}
		}

		if ( false !== $this->permissions ) {
			$permissions = $this->getPermissions( (string) $content->Id );
			if ( $permissions ) {
				$item[ $this->permissions ] = $permissions;
			} else {
				//$this->output->error( 'Unable to get permissions for item ' . $itemId . '!' );
			}
		}

		if ( false !== $this->taxonomies ) {
			$taxonomies = $this->getTaxonomies( (string) $content->Id );
			if ( $taxonomies ) {
				$item[ $this->taxonomies ] = $taxonomies;
			} else {
				//$this->output->error( 'Unable to get taxonomies for item ' . $itemId . '!' );
			}
		}

		return (object) $item;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getFullItem( $itemId, $item ) {

		return (object) $item;
	}

	/**
	 * Get library item by id
	 *
	 * @param numeric $libraryId
	 * @return SimpleXMLElement|false
	 */
	protected function getLibrary( $libraryId ) {
		$request = '
			<GetLibraryItem_x0020_By_x0020_Id xmlns="http://tempuri.org/">
				<LibID>' . $libraryId . '</LibID>
			</GetLibraryItem_x0020_By_x0020_Id>
		';
		$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Library.asmx', $this->soapEnvelope( $request ), 'library-' . $libraryId );
		if ( is_wp_error( $data ) || empty( $data->GetLibraryItem_x0020_By_x0020_IdResponse->GetLibraryItem_x0020_By_x0020_IdResult ) ) {
			return false;
		}

		return $data->GetLibraryItem_x0020_By_x0020_IdResponse->GetLibraryItem_x0020_By_x0020_IdResult;
	}
}
