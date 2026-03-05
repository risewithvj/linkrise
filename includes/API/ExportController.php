<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Database\Repository\LinkRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ExportController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/export/json', array(
			'methods' => 'GET',
			'permission_callback' => array( $this, 'admin_permission' ),
			'callback' => array( $this, 'json' ),
		) );
	}
	public function json() {
		$rows = ( new LinkRepository() )->list_paginated( 1, 1000 );
		return $this->ok( $rows['items'], array( 'total' => $rows['total'] ) );
	}
}
