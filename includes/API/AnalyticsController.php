<?php
namespace LinkRiseEnterprise\API;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AnalyticsController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/analytics/realtime', array(
			'methods' => 'GET',
			'permission_callback' => array( $this, 'admin_permission' ),
			'callback' => array( $this, 'realtime' ),
		) );
	}
	public function realtime() {
		global $wpdb;
		$table = $wpdb->prefix . 'linkrise_clicks';
		$rows = $wpdb->get_results( "SELECT DATE_FORMAT(clicked_at,'%Y-%m-%d %H:%i:00') as t, COUNT(id) as c FROM {$table} WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) GROUP BY YEAR(clicked_at),MONTH(clicked_at),DAY(clicked_at),HOUR(clicked_at),FLOOR(MINUTE(clicked_at)/5) ORDER BY t ASC" ); // phpcs:ignore
		return $this->ok( array( 'buckets' => $rows ) );
	}
}
