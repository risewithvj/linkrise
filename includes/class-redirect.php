<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Redirect {
	public function init() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}
	public function rewrite() {
		$prefix = sanitize_title_with_dashes( (string) get_option( 'linkrise_redirect_prefix', 'go' ) );
		add_rewrite_rule( '^' . preg_quote( $prefix, '/' ) . '/([a-zA-Z0-9_-]+)/?$', 'index.php?lr_code=$matches[1]', 'top' );
		if ( '1' === get_option( 'linkrise_flush_needed', '' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'linkrise_flush_needed' );
		}
	}
	public function query_vars( $vars ) { $vars[] = 'lr_code'; return $vars; }
	public function handle() {
		$code = sanitize_text_field( (string) get_query_var( 'lr_code' ) );
		if ( '' === $code ) { return; }
		global $wpdb;
		$db = new Database();
		$link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$db->table('links')} WHERE shortcode=%s LIMIT 1", $code ) );
		if ( ! $link ) { wp_safe_redirect( home_url( '/' ), 302 ); exit; }
		$landing = (string) get_option( 'linkrise_landing_url', '' );
		if ( $landing ) {
			wp_safe_redirect( add_query_arg( 'lrsc', rawurlencode( $code ), $landing ), 302 );
			exit;
		}
		(new Analytics())->track_click( $link );
		wp_safe_redirect( esc_url_raw( $link->long_url ), 302 );
		exit;
	}
}
