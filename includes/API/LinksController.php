<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Services\LinkService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LinksController extends RestController {
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}
	public function routes() {
		register_rest_route( $this->namespace, '/links', array(
			array( 'methods' => 'GET', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'index' ) ),
			array( 'methods' => 'POST', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'store' ) ),
		) );
	}
	public function index( \WP_REST_Request $r ) {
		global $wpdb;
		$page = max( 1, absint( $r->get_param( 'page' ) ?: 1 ) );
		$per  = min( 100, max( 1, absint( $r->get_param( 'per_page' ) ?: 20 ) ) );
		$off  = ( $page - 1 ) * $per;
		$table = $wpdb->prefix . 'linkrise_links';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS id,shortcode,long_url,status,click_count,created_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, $off ) );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		return $this->ok( $rows, array( 'page' => $page, 'per_page' => $per, 'total' => $total ) );
	}
	public function store( \WP_REST_Request $r ) {
		$res = ( new LinkService() )->create_short( (string) $r['long_url'], (string) $r['shortcode'] );
		if ( is_wp_error( $res ) ) { return $this->error( $res->get_error_message(), 400 ); }
		return $this->ok( $res );
	}
}
