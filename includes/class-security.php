<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Security {
	public function init() {}
	public function get_ip() {
		$keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) { continue; }
			$raw = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
			$ip  = trim( $raw[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $ip; }
		}
		return '127.0.0.1';
	}
	public function rate_ok( $scope = 'create' ) {
		$ip = $this->get_ip();
		$key = 'lr_rate_' . md5( $scope . '|' . $ip );
		$cnt = (int) get_transient( $key );
		$lim = (int) get_option( 'linkrise_rate_limit', 60 );
		if ( $lim > 0 && $cnt >= $lim ) { return false; }
		set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );
		return true;
	}
	public function encrypt( $plain ) {
		$k = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv = random_bytes( 12 );
		$tag = '';
		$c = openssl_encrypt( $plain, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag );
		return base64_encode( $iv . $tag . $c );
	}
	public function decrypt( $payload ) {
		$raw = base64_decode( (string) $payload );
		if ( ! $raw || strlen( $raw ) < 29 ) { return ''; }
		$k = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$c = substr( $raw, 28 );
		return (string) openssl_decrypt( $c, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag );
	}
}
