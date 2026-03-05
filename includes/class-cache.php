<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cache {
	public function init() {}
	public function get( $key ) {
		$group = 'linkrise';
		$v = wp_cache_get( $key, $group );
		if ( false !== $v ) { return $v; }
		return get_transient( 'lr_' . md5( $key ) );
	}
	public function set( $key, $value, $ttl = 300 ) {
		wp_cache_set( $key, $value, 'linkrise', $ttl );
		set_transient( 'lr_' . md5( $key ), $value, $ttl );
	}
	public function delete( $key ) {
		wp_cache_delete( $key, 'linkrise' );
		delete_transient( 'lr_' . md5( $key ) );
	}
}
