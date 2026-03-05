<?php
namespace LinkRiseEnterprise\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WebhooksController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/webhooks/health', array(
			'methods' => 'GET',
			'permission_callback' => array( $this, 'admin_permission' ),
			'callback' => array( $this, 'health' ),
		) );
	}
	public function health() {
		return $this->ok( array( 'queue' => 'ok', 'time' => current_time( 'mysql' ) ) );
	}
}
