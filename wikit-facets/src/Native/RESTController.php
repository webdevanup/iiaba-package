<?php

namespace WDG\Facets\Native;

use WDG\Facets\Provider\Native;
use WP_REST_Response;

class RESTController {

	public function __construct(
		protected Index $index,
		protected Native $provider,
	) {
		register_rest_route(
			'wdg/v1',
			'/facets-index',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_index' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_index' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_index' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_index' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				'allow_batch' => false,
			]
		);
	}

	public function get_index() {
		if ( ! $this->index->exists() ) {
			return new WP_REST_Response( null, 404 );
		}

		return $this->index->stats();
	}

	public function create_index() {
		$this->index->create( true );

		if ( $this->index->exists() ) {
			$response = new WP_REST_Response( [ 'success' => true ], 200 );
		} else {
			$response = new WP_REST_Response( [ 'success' => false ], 500 );
		}

		return $response;
	}

	public function update_index() {
		$indexer = new Indexer( $this->index, $this->provider );

		if ( $indexer->active() ) {
			$result = $indexer->next();
		} else {
			$result = $indexer->start();
		}

		return $result;
	}

	public function delete_index() {
		$this->index->truncate();

		return new WP_REST_Response( [ 'success' => 0 === $this->index->stats()['total'] ], 200 );
	}

	public function permission_callback() {
		return current_user_can( 'manage_options' );
	}
}
