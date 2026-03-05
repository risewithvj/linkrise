<?php
namespace LinkRiseEnterprise\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class AnalyticsController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/analytics/realtime', array('methods'=>'GET','permission_callback'=>array($this,'admin_permission'),'callback'=>array($this,'realtime')) );
	}
	public function realtime() { return $this->ok( array( 'buckets' => array() ) ); }
}
