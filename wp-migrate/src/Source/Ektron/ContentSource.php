<?php
/**
 * @file
 *
 * Base class for migrating content from Ektron.
 */

namespace WDG\Migrate\Source\Ektron;

use WDG\Migrate\Source\Ektron\EktronSourceBase;
use WDG\Migrate\Output\OutputInterface;

class ContentSource extends EktronSourceBase {

	protected $content_fields = [];
	protected $html_fields    = [];

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
				 * 'type' => 'html',
				 * 'key' => 'Body' OR [ 'SponsorImage', 'img' ]
				 */
				case 'html':
					$this->html_fields[ $field ] = [
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
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getFolderItems( $folderId ) {
		$ids = [];

		$request = '
			<GetChildContent xmlns="http://tempuri.org/">
				<FolderID>' . $folderId . '</FolderID>
				<Recursive>false</Recursive>
				<OrderBy>Id</OrderBy>
			</GetChildContent>
		';
		$data    = $this->soapPost( $this->base_url . '/Workarea/webservices/WebServiceAPI/Content/Content.asmx', $this->soapEnvelope( $request ), 'folderContent-' . $folderId );

		if ( ! is_wp_error( $data ) && isset( $data->GetChildContentResponse->GetChildContentResult->ContentData ) ) {
			foreach ( $data->GetChildContentResponse->GetChildContentResult->ContentData as $content ) {
				if ( 'false' === (string) $content->IsPublished ) {
					continue;
				}
				$ids[] = (string) $content->Id;
			}
		}

		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getItem( $itemId ) {
		$item = [];

		$content = $this->getContent( $itemId );
		if ( ! $content ) {
			$this->output->error( 'Unable to get content for item ' . $itemId . '!' );
			return false;
		}

		// Populate content fields
		foreach ( $this->content_fields as $field => $options ) {
			$item[ $field ] = $this->getXMLValue( $content, $options['key'], $options['string'] );
		}

		// Skip filtered items
		if ( $this->filter && ! call_user_func( $this->filter, (object) $item ) ) {
			return false;
		}

		if ( ! empty( $this->html_fields ) ) {
			$html = $this->getXMLValue( $content, 'Html', true );
			if ( $html ) {
				// Ingest as XML (NOTE: Html contents are htmlspecialchars decoded but contain remaining html entities)
				$data = $this->getXML( $html, false );
				if ( ! is_wp_error( $data ) ) {
					// Populate html fields
					foreach ( $this->html_fields as $field => $options ) {
						$item[ $field ] = $this->getXMLValue( $data, $options['key'], $options['string'] );
					}
				} else {
					//$this->output->error( 'Unable to parse HTML for item ' . $itemId . '!' );
				}
			} else {
				//$this->output->error( 'Unable to get HTML for item ' . $itemId . '!' );
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

}
