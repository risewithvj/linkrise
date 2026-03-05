<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Database\Repository\CampaignRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CampaignsController extends RestController {
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route( $this->namespace, '/campaigns', array(
			array(
				'methods' => 'GET',
				'permission_callback' => array( $this, 'admin_permission' ),
				'callback' => array( $this, 'index' ),
			),
			array(
				'methods' => 'POST',
				'permission_callback' => array( $this, 'admin_permission' ),
				'callback' => array( $this, 'create' ),
			),
		) );
	}

	public function index() {
		$rows = ( new CampaignRepository() )->all();
		return $this->ok( $rows );
	}

	public function create( \WP_REST_Request $request ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return $this->error( 'Campaign name is required.', 400 );
		}
		$id = ( new CampaignRepository() )->create( array(
			'name' => $name,
			'slug' => sanitize_title( (string) $request->get_param( 'slug' ) ?: $name ),
			'description' => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
			'color' => sanitize_hex_color( (string) $request->get_param( 'color' ) ) ?: '#0363fc',
			'goal_clicks' => absint( $request->get_param( 'goal_clicks' ) ),
			'start_date' => sanitize_text_field( (string) $request->get_param( 'start_date' ) ),
			'end_date' => sanitize_text_field( (string) $request->get_param( 'end_date' ) ),
			'status' => sanitize_key( (string) $request->get_param( 'status' ) ?: 'active' ),
			'created_by' => get_current_user_id(),
			'team_id' => absint( $request->get_param( 'team_id' ) ),
			'created_at' => current_time( 'mysql' ),
		) );
		return $this->ok( array( 'id' => $id ) );
	}
}
