<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'LinkRise_Security' ) ) { return; }

class LinkRise_Security {

	public static function init() {}

	/** Only allow http/https destinations — blocks javascript:, data:, etc. */
	public static function is_safe_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) { return false; }
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) { return false; }
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true );
	}

	/** Real IP — only trust forwarded headers from configured proxy IPs */
	public static function get_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		$proxies = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'linkrise_trusted_proxies', '' ) ) ) );
		if ( ! empty( $proxies ) && in_array( $remote, $proxies, true ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$fwd = trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
			if ( filter_var( $fwd, FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( $fwd );
			}
		}
		return sanitize_text_field( $remote );
	}

	/** Per-IP rate limit. Returns true = OK, false = limit hit */
	public static function rate_ok() {
		$limit = (int) get_option( 'linkrise_rate_limit', 10 );
		if ( $limit <= 0 ) { return true; }
		$key   = 'lr_rate_' . md5( self::get_ip() );
		$count = (int) get_transient( $key );
		if ( $count === 0 ) { set_transient( $key, 1, HOUR_IN_SECONDS ); return true; }
		if ( $count >= $limit ) { return false; }
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/** Server-side CAPTCHA verification (reCAPTCHA v3 or Cloudflare Turnstile) */
	public static function verify_captcha( $token ) {
		$prov = (string) get_option( 'linkrise_captcha_provider', 'disabled' );
		if ( 'disabled' === $prov ) { return true; }
		if ( empty( $token ) ) { return false; }

		if ( 'recaptcha' === $prov ) {
			$secret = (string) get_option( 'linkrise_recaptcha_secret', '' );
			if ( empty( $secret ) ) { return true; }
			$r = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
				'timeout' => 8,
				'body'    => array( 'secret' => $secret, 'response' => $token, 'remoteip' => self::get_ip() ),
			) );
			if ( is_wp_error( $r ) ) { return false; }
			$b = json_decode( wp_remote_retrieve_body( $r ), true );
			return ! empty( $b['success'] ) && ( ! isset( $b['score'] ) || (float) $b['score'] >= 0.5 );
		}

		if ( 'turnstile' === $prov ) {
			$secret = (string) get_option( 'linkrise_turnstile_secret', '' );
			if ( empty( $secret ) ) { return true; }
			$r = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
				'timeout' => 8,
				'body'    => array( 'secret' => $secret, 'response' => $token, 'remoteip' => self::get_ip() ),
			) );
			if ( is_wp_error( $r ) ) { return false; }
			$b = json_decode( wp_remote_retrieve_body( $r ), true );
			return ! empty( $b['success'] );
		}

		return false;
	}

	/** Google Safe Browsing — cached per domain for 24 h */
	public static function is_url_safe( $url ) {
		$api = (string) get_option( 'linkrise_safe_browsing_key', '' );
		if ( empty( $api ) ) { return true; }
		$domain = (string) parse_url( $url, PHP_URL_HOST );
		$tkey   = 'lr_sb_' . md5( $domain );
		$cached = get_transient( $tkey );
		if ( $cached !== false ) { return $cached === 'safe'; }
		$resp = wp_remote_post(
			'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . rawurlencode( $api ),
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'client'     => array( 'clientId' => 'linkrise', 'clientVersion' => LINKRISE_VERSION ),
					'threatInfo' => array(
						'threatTypes'      => array( 'MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE' ),
						'platformTypes'    => array( 'ANY_PLATFORM' ),
						'threatEntryTypes' => array( 'URL' ),
						'threatEntries'    => array( array( 'url' => $url ) ),
					),
				) ),
			)
		);
		if ( is_wp_error( $resp ) ) { set_transient( $tkey, 'safe', 5 * MINUTE_IN_SECONDS ); return true; }
		$result = json_decode( wp_remote_retrieve_body( $resp ), true );
		$safe   = empty( $result['matches'] );
		set_transient( $tkey, $safe ? 'safe' : 'unsafe', DAY_IN_SECONDS );
		return $safe;
	}

	/** User-agent → device type */
	public static function ua_device( $ua ) {
		if ( preg_match( '/tablet|ipad|playbook|silk/i', $ua ) )        { return 'Tablet'; }
		if ( preg_match( '/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $ua ) ) { return 'Mobile'; }
		return 'Desktop';
	}

	/** User-agent → browser name */
	public static function ua_browser( $ua ) {
		if ( strpos( $ua, 'Edg/' ) !== false || strpos( $ua, 'Edge' ) !== false ) { return 'Edge'; }
		if ( strpos( $ua, 'OPR/' ) !== false || strpos( $ua, 'Opera' ) !== false ) { return 'Opera'; }
		if ( strpos( $ua, 'SamsungBrowser' ) !== false ) { return 'Samsung'; }
		if ( strpos( $ua, 'Firefox' ) !== false ) { return 'Firefox'; }
		if ( strpos( $ua, 'Chrome' ) !== false )  { return 'Chrome'; }
		if ( strpos( $ua, 'Safari' ) !== false )  { return 'Safari'; }
		return 'Other';
	}

	/** User-agent → OS name */
	public static function ua_os( $ua ) {
		if ( strpos( $ua, 'Windows' ) !== false ) { return 'Windows'; }
		if ( strpos( $ua, 'Android' ) !== false ) { return 'Android'; }
		if ( strpos( $ua, 'iPhone' ) !== false || strpos( $ua, 'iPad' ) !== false )  { return 'iOS'; }
		if ( strpos( $ua, 'Mac' ) !== false )     { return 'macOS'; }
		if ( strpos( $ua, 'Linux' ) !== false )   { return 'Linux'; }
		return 'Other';
	}
}
