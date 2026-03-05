<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Database\Repository\ReportRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ReportsController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/reports', array(
			array( 'methods' => 'GET', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'index' ) ),
		) );
		register_rest_route( $this->namespace, '/reports/(?P<id>\d+)/dismiss', array(
			'methods' => 'PATCH',
			'permission_callback' => array( $this, 'admin_permission' ),
			'callback' => array( $this, 'dismiss' ),
		) );
	}
	public function index() {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		return $this->ok( ( new ReportRepository() )->all( $status ) );
	}
	public function dismiss( \WP_REST_Request $request ) {
		( new ReportRepository() )->set_status( absint( $request['id'] ), 'dismissed', get_current_user_id() );
		return $this->ok( array( 'updated' => true ) );
	}
}
