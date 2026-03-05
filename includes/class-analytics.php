<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Analytics {
	public function init() {}
	public function track_click( $link, $context = array() ) {
		global $wpdb;
		$db = new Database();
		$security = new Security();
		$ip = $security->get_ip();
		$ip_hash = md5( $ip );
		$is_unique = (int) ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$db->table('clicks')} WHERE shortcode=%s AND ip_hash=%s LIMIT 1", $link->shortcode, $ip_hash ) );
		$wpdb->insert( $db->table( 'clicks' ), array(
			'link_id' => (int) $link->id,
			'shortcode' => $link->shortcode,
			'clicked_at' => current_time( 'mysql' ),
			'ip_hash' => $ip_hash,
			'device' => isset( $context['device'] ) ? sanitize_text_field( $context['device'] ) : '',
			'browser' => isset( $context['browser'] ) ? sanitize_text_field( $context['browser'] ) : '',
			'os' => isset( $context['os'] ) ? sanitize_text_field( $context['os'] ) : '',
			'language' => isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ), 0, 10 ) : '',
			'is_unique' => $is_unique,
			'session_id' => isset( $_COOKIE['PHPSESSID'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['PHPSESSID'] ) ) : '',
		) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$db->table('links')} SET click_count = click_count + 1, last_clicked=%s WHERE id=%d", current_time( 'mysql' ), (int) $link->id ) );
	}
}
