<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Database\Repository\TeamRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TeamsController extends RestController {
	public function register() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() {
		register_rest_route( $this->namespace, '/teams', array(
			array( 'methods' => 'GET', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'index' ) ),
			array( 'methods' => 'POST', 'permission_callback' => array( $this, 'admin_permission' ), 'callback' => array( $this, 'create' ) ),
		) );
	}
	public function index() { return $this->ok( ( new TeamRepository() )->all() ); }
	public function create( \WP_REST_Request $request ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) { return $this->error( 'Team name is required.', 400 ); }
		$id = ( new TeamRepository() )->create( array(
			'name' => $name,
			'slug' => sanitize_title( (string) $request->get_param( 'slug' ) ?: $name ),
			'plan' => sanitize_key( (string) $request->get_param( 'plan' ) ?: 'free' ),
			'owner_id' => absint( $request->get_param( 'owner_id' ) ?: get_current_user_id() ),
			'settings' => wp_json_encode( (array) $request->get_param( 'settings' ) ),
			'created_at' => current_time( 'mysql' ),
		) );
		return $this->ok( array( 'id' => $id ) );
	}
}
