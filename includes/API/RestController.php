<?php
namespace LinkRiseEnterprise\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class RestController {
	protected $namespace = 'linkrise/v1';
	abstract public function register();
	protected function admin_permission( \WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'x_wp_nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_header( 'x-wp-nonce' );
		}
		return current_user_can( 'manage_options' ) && '' !== $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}
	protected function ok( $data, $meta = array() ) { return new \WP_REST_Response( array( 'success' => true, 'data' => $data, 'meta' => $meta ), 200 ); }
	protected function error( $message, $code = 400 ) { return new \WP_REST_Response( array( 'success' => false, 'message' => $message ), $code ); }
}
