<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Validator {
	public function init() {}
	public function shortcode( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_replace( '/[^a-zA-Z0-9\-_]/', '', $value );
	}
	public function url( $value ) {
		$url = esc_url_raw( (string) $value );
		return preg_match( '#^https?://#i', $url ) ? $url : '';
	}
}
