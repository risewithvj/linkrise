<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Api {
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}
	public function register_routes() {
		register_rest_route( 'linkrise/v1', '/health', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => function() { return array( 'ok' => true, 'version' => LINKRISE_VERSION ); },
		) );
	}
}
