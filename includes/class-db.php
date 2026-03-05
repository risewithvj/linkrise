<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Backward-compat placeholder for pre-v4 code paths.
class LinkRise_DB {
	public static function lt() { global $wpdb; return $wpdb->prefix . 'linkrise_links'; }
	public static function ct() { global $wpdb; return $wpdb->prefix . 'linkrise_clicks'; }
	public static function rt() { global $wpdb; return $wpdb->prefix . 'linkrise_reports'; }
}
