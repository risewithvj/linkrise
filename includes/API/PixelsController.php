<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Database\Repository\PixelRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PixelsController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/pixels', array(
			array( 'methods' => 'GET', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'index' ) ),
			array( 'methods' => 'POST', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'create' ) ),
		) );
	}
	public function index() { return $this->ok( ( new PixelRepository() )->all() ); }
	public function create( \WP_REST_Request $request ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$pixel_id = sanitize_text_field( (string) $request->get_param( 'pixel_id' ) );
		if ( '' === $name || '' === $pixel_id ) { return $this->error( 'Name and pixel_id are required.', 400 ); }
		$id = ( new PixelRepository() )->create( array(
			'name' => $name,
			'type' => sanitize_key( (string) $request->get_param( 'type' ) ?: 'custom' ),
			'pixel_id' => $pixel_id,
			'event_name' => sanitize_text_field( (string) $request->get_param( 'event_name' ) ?: 'click' ),
			'fire_on' => sanitize_key( (string) $request->get_param( 'fire_on' ) ?: 'all' ),
			'created_by' => get_current_user_id(),
			'team_id' => absint( $request->get_param( 'team_id' ) ),
			'created_at' => current_time( 'mysql' ),
		) );
		return $this->ok( array( 'id' => $id ) );
	}
}
