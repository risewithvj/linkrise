<?php
namespace LinkRiseEnterprise\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SettingsController extends RestController {
	private $keys = array(
		'linkrise_rate_limit',
		'linkrise_bulk_max',
		'linkrise_admin_only',
		'linkrise_countdown',
		'linkrise_tos_url',
		'linkrise_generator_url',
		'linkrise_landing_url',
	);
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/settings', array(
			array( 'methods' => 'GET', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'get_settings' ) ),
			array( 'methods' => 'POST', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'save_settings' ) ),
		) );
	}
	public function get_settings() {
		$data = array();
		foreach ( $this->keys as $k ) { $data[ $k ] = get_option( $k ); }
		return $this->ok( $data );
	}
	public function save_settings( \WP_REST_Request $request ) {
		foreach ( $this->keys as $k ) {
			if ( null === $request->get_param( $k ) ) { continue; }
			$value = $request->get_param( $k );
			if ( in_array( $k, array( 'linkrise_rate_limit', 'linkrise_bulk_max', 'linkrise_countdown' ), true ) ) { $value = absint( $value ); }
			if ( in_array( $k, array( 'linkrise_tos_url', 'linkrise_generator_url', 'linkrise_landing_url' ), true ) ) { $value = esc_url_raw( (string) $value ); }
			if ( 'linkrise_admin_only' === $k ) { $value = (bool) $value; }
			update_option( $k, $value );
		}
		return $this->ok( array( 'saved' => true ) );
	}
}
