<?php
namespace LinkRiseEnterprise\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class LinksController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/links', array('methods'=>'GET','permission_callback'=>array($this,'admin_permission'),'callback'=>array($this,'index')) );
	}
	public function index( \WP_REST_Request $r ) { return $this->ok( array() ); }
}
