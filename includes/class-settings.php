<?php
/**
 * LinkRise — Settings Manager
 *
 * @package     LinkRise
 * @author      Vijaya Kumar L
 * @developer   Vijaya Kumar L
 * @github      https://github.com/risewithvj
 * @linkedin    https://www.linkedin.com/in/vijayakumarl/
 * @copyright   2024 Vijaya Kumar L
 * @license     GPL-2.0+
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'LinkRise_Settings' ) ) { return; }

class LinkRise_Settings {

	public static function init() {
		add_action( 'admin_post_linkrise_save_settings', array( __CLASS__, 'save' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized', 403 ); }
		check_admin_referer( 'linkrise_settings_nonce' );

		// Text / key fields
		$text = array(
			'linkrise_captcha_provider', 'linkrise_recaptcha_site', 'linkrise_recaptcha_secret',
			'linkrise_turnstile_site', 'linkrise_turnstile_secret', 'linkrise_safe_browsing_key',
			'linkrise_ga4_id', 'linkrise_ga4_secret', 'linkrise_gtm_id',
			'linkrise_trusted_proxies', 'linkrise_redirect_prefix',
		);
		foreach ( $text as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				update_option( $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
			}
		}

		// URL fields
		foreach ( array( 'linkrise_landing_url', 'linkrise_tos_url', 'linkrise_fallback_url' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				update_option( $k, esc_url_raw( wp_unslash( $_POST[ $k ] ) ) );
			}
		}

		// Integer fields
		foreach ( array( 'linkrise_rate_limit', 'linkrise_bulk_max', 'linkrise_countdown' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				update_option( $k, absint( wp_unslash( $_POST[ $k ] ) ) );
			}
		}

		// Checkboxes
		update_option( 'linkrise_admin_only', isset( $_POST['linkrise_admin_only'] ) ? '1' : '0' );

		// Sanitise prefix
		$pfx = isset( $_POST['linkrise_redirect_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['linkrise_redirect_prefix'] ) ) : 'go';
		$pfx = preg_replace( '/[^a-z0-9\-]/', '', strtolower( trim( $pfx ) ) );
		update_option( 'linkrise_redirect_prefix', $pfx ?: 'go' );
		update_option( 'linkrise_flush_needed', '1' );

		wp_safe_redirect( add_query_arg( array( 'page' => 'linkrise', 'tab' => 'settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
