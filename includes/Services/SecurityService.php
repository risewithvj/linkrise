<?php
namespace LinkRiseEnterprise\Services;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SecurityService {
	public function ip() {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
		return filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '127.0.0.1';
	}
	public function ip_hash() { return md5( $this->ip() ); }
	public function rate_ok( $bucket = 'public', $max = 60 ) {
		$key = 'lr_rate_' . md5( $bucket . '|' . $this->ip() );
		$cnt = (int) get_transient( $key );
		if ( $cnt >= $max ) { return false; }
		set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );
		return true;
	}
	public function admin_only_blocked() {
		return '1' === (string) get_option( 'linkrise_admin_only', '0' ) && ! current_user_can( 'manage_options' );
	}
}
