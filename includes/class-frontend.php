<?php
/**
 * LinkRise — Frontend Shortcodes & Redirect Engine
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
if ( class_exists( 'LinkRise_Frontend' ) ) { return; }

class LinkRise_Frontend {

	public static function init() {
		// Rewrite rules + flush
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );

		// Redirect engine fires very early on template_redirect
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ), 1 );

		// SEO meta for landing / redirect pages
		add_action( 'wp_head', array( __CLASS__, 'seo_head' ), 1 );
		add_filter( 'wp_robots', array( __CLASS__, 'wp_robots_filter' ) );

		// Shared CSS for frontend shortcodes (tiny, can be in head)
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_global_css' ) );

		// Shortcodes
		add_shortcode( 'linkrise_generator',      array( __CLASS__, 'sc_generator' ) );
		add_shortcode( 'linkrise_bulk_generator', array( __CLASS__, 'sc_bulk' ) );
		add_shortcode( 'linkrise_landing',        array( __CLASS__, 'sc_landing' ) );

		// AJAX — public (nopriv) + logged-in
		$ajax = array(
			'linkrise_create'          => 'ajax_create',
			'linkrise_bulk_create'     => 'ajax_bulk_create',
			'linkrise_verify_password' => 'ajax_verify_pw',
			'linkrise_report'          => 'ajax_report',
		);
		foreach ( $ajax as $action => $method ) {
			add_action( 'wp_ajax_' . $action,        array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}
	}

	// ── REWRITE ─────────────────────────────────────────────────────────────

	public static function add_rewrite_rules() {
		$pfx = lr_prefix();
		add_rewrite_rule(
			'^' . preg_quote( $pfx, '/' ) . '/([a-zA-Z0-9_\-]+)/?$',
			'index.php?lr_code=$matches[1]',
			'top'
		);
		// Also register any legacy prefixes stored on links so old links still work
		global $wpdb;
		$table = LinkRise_DB::lt();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) { // phpcs:ignore
			$old_pfx = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT custom_prefix FROM {$table} WHERE custom_prefix != '' AND custom_prefix != %s",
				$pfx
			) );
			foreach ( (array) $old_pfx as $op ) {
				$op = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $op ) );
				if ( $op ) {
					add_rewrite_rule(
						'^' . preg_quote( $op, '/' ) . '/([a-zA-Z0-9_\-]+)/?$',
						'index.php?lr_code=$matches[1]',
						'top'
					);
				}
			}
		}
	}

	public static function maybe_flush() {
		if ( get_option( 'linkrise_flush_needed' ) === '1' ) {
			flush_rewrite_rules( false );
			delete_option( 'linkrise_flush_needed' );
		}
	}

	public static function add_query_var( $vars ) {
		$vars[] = 'lr_code';
		return $vars;
	}

	// ── CSS ─────────────────────────────────────────────────────────────────

	public static function enqueue_global_css() {
		wp_enqueue_style( 'linkrise-frontend', LINKRISE_URL . 'assets/css/linkrise-frontend.css', array(), LINKRISE_VERSION );
		// KEY FIX: JS must be in <head> (not footer) so that LR_Generator/LR_Bulk/LR_Landing
		// are defined BEFORE the inline shortcode scripts that call them.
		// We load with NO dependencies — pure vanilla JS, zero wp.element/React needed.
		wp_enqueue_script( 'linkrise-frontend', LINKRISE_URL . 'assets/js/linkrise-frontend.js', array(), LINKRISE_VERSION, false );
		// Enqueue CAPTCHA scripts if needed (can be in footer)
		$prov = (string) get_option( 'linkrise_captcha_provider', 'disabled' );
		if ( 'recaptcha' === $prov ) {
			$sk = (string) get_option( 'linkrise_recaptcha_site', '' );
			if ( $sk ) {
				wp_enqueue_script( 'linkrise-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $sk ), array(), null, true ); // phpcs:ignore
			}
		} elseif ( 'turnstile' === $prov ) {
			wp_enqueue_script( 'linkrise-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore
		}
	}

	// ── SEO ─────────────────────────────────────────────────────────────────

	public static function seo_head() {
		if ( get_query_var( 'lr_code' ) || isset( $_GET['lrsc'] ) ) {
			echo '<meta name="robots" content="noindex, nofollow, noarchive">' . "\n";
			echo '<meta name="referrer" content="no-referrer-when-downgrade">' . "\n";
			$schema = array(
				'@context' => 'https://schema.org',
				'@type'    => 'WebPage',
				'name'     => 'Link Redirect',
				'description' => 'Secure redirection page with verification and countdown.',
			);
			echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
		}
	}

	public static function wp_robots_filter( $robots ) {
		if ( get_query_var( 'lr_code' ) || isset( $_GET['lrsc'] ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	// ── REDIRECT ENGINE ─────────────────────────────────────────────────────

	public static function handle_redirect() {
		$code = get_query_var( 'lr_code' );
		if ( empty( $code ) ) { return; }
		self::do_redirect( sanitize_text_field( $code ) );
	}

	public static function do_redirect( $code ) {
		global $wpdb;

		// Security headers
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );

		$fallback = (string) get_option( 'linkrise_fallback_url', home_url( '/' ) );
		if ( ! LinkRise_Security::is_safe_url( $fallback ) ) { $fallback = home_url( '/' ); }

		// Object-cache lookup
		$ckey = 'lr_lnk_' . md5( $code );
		$link = wp_cache_get( $ckey, 'linkrise' );
		if ( $link === false ) {
			$link = $wpdb->get_row( $wpdb->prepare(
				'SELECT * FROM ' . LinkRise_DB::lt() . ' WHERE shortcode = %s LIMIT 1',
				$code
			) );
			if ( $link ) { wp_cache_set( $ckey, $link, 'linkrise', 5 * MINUTE_IN_SECONDS ); }
		}

		if ( ! $link ) {
			wp_redirect( $fallback, 302 ); exit;
		}

		if ( $link->status !== 'active' ) {
			$dest = ( ! empty( $link->fallback_url ) && LinkRise_Security::is_safe_url( $link->fallback_url ) ) ? $link->fallback_url : $fallback;
			wp_redirect( $dest, 302 ); exit;
		}

		if ( $link->expiry_date && strtotime( $link->expiry_date ) < time() ) {
			$dest = ( ! empty( $link->fallback_url ) && LinkRise_Security::is_safe_url( $link->fallback_url ) ) ? $link->fallback_url : $fallback;
			wp_redirect( $dest, 302 ); exit;
		}

		if ( ! empty( $link->click_limit ) && (int) $link->click_count >= (int) $link->click_limit ) {
			$wpdb->update( LinkRise_DB::lt(), array( 'status' => 'paused' ), array( 'id' => $link->id ) );
			wp_cache_delete( $ckey, 'linkrise' );
			$dest = ( ! empty( $link->fallback_url ) && LinkRise_Security::is_safe_url( $link->fallback_url ) ) ? $link->fallback_url : $fallback;
			wp_redirect( $dest, 302 ); exit;
		}

		$landing = self::landing_url();

		// Password-protected and public links both resolve via the landing screen
		// so countdown/timer settings are always respected.
		if ( ! empty( $link->password_hash ) ) {
			wp_redirect( esc_url_raw( add_query_arg( 'lrsc', rawurlencode( $code ), $landing ) ), 302 );
			exit;
		}

		$destination = (string) $link->long_url;
		if ( ! LinkRise_Security::is_safe_url( $destination ) ) {
			wp_redirect( $fallback, 302 ); exit;
		}

		// UTM passthrough (destination UTMs win over visitor UTMs)
		$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		$utm = array();
		foreach ( $utm_keys as $k ) {
			if ( ! empty( $_GET[ $k ] ) ) { $utm[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); }
		}
		if ( $utm ) {
			$qs = (string) parse_url( $destination, PHP_URL_QUERY );
			$existing = array();
			if ( $qs !== '' ) { parse_str( $qs, $existing ); }
			$merged      = array_merge( $utm, $existing );
			$destination = strtok( $destination, '?' ) . '?' . http_build_query( $merged );
		}

		// Snapshot everything needed for async logging BEFORE redirect
		$snap_link   = $link;
		$snap_server = $_SERVER;
		$snap_get    = $_GET;
		$snap_ip     = LinkRise_Security::get_ip();

		// Send users to the landing page so the configurable countdown always runs.
		wp_redirect( esc_url_raw( add_query_arg( 'lrsc', rawurlencode( $code ), $landing ) ), 302 );

		// Log click async (after browser receives headers)
		add_action( 'shutdown', function() use ( $snap_link, $snap_server, $snap_get, $snap_ip, $ckey ) {
			if ( function_exists( 'fastcgi_finish_request' ) ) { fastcgi_finish_request(); }
			LinkRise_Frontend::log_click( $snap_link, $snap_server, $snap_get, $snap_ip );
			LinkRise_Frontend::send_ga4( $snap_link, $snap_ip, $snap_server );
			wp_cache_delete( $ckey, 'linkrise' );
		} );

		exit;
	}

	private static function landing_url() {
		global $wpdb;
		$landing = (string) get_option( 'linkrise_landing_url', '' );
		if ( empty( $landing ) ) {
			$pid = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_content LIKE '%[linkrise_landing]%' LIMIT 1" ); // phpcs:ignore
			$landing = $pid ? (string) get_permalink( (int) $pid ) : home_url( '/' );
		}
		return $landing;
	}

	// ── CLICK LOGGING ───────────────────────────────────────────────────────

	public static function log_click( $link, $server, $get, $ip ) {
		global $wpdb;
		$ua  = isset( $server['HTTP_USER_AGENT'] ) ? (string) $server['HTTP_USER_AGENT'] : '';
		$geo = self::geo( $ip );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . LinkRise_DB::lt() . ' SET click_count = click_count + 1, last_clicked = %s WHERE id = %d',
			current_time( 'mysql' ), (int) $link->id
		) );

		$wpdb->insert( LinkRise_DB::ct(), array(
			'link_id'      => (int) $link->id,
			'shortcode'    => $link->shortcode,
			'clicked_at'   => current_time( 'mysql' ),
			'ip_hash'      => md5( $ip ),
			'country'      => $geo['country'],
			'country_code' => $geo['code'],
			'device'       => LinkRise_Security::ua_device( $ua ),
			'browser'      => LinkRise_Security::ua_browser( $ua ),
			'os'           => LinkRise_Security::ua_os( $ua ),
			'referrer'     => isset( $server['HTTP_REFERER'] ) ? sanitize_text_field( substr( (string) $server['HTTP_REFERER'], 0, 500 ) ) : '',
			'utm_source'   => isset( $get['utm_source'] )   ? sanitize_text_field( (string) $get['utm_source'] )   : '',
			'utm_medium'   => isset( $get['utm_medium'] )   ? sanitize_text_field( (string) $get['utm_medium'] )   : '',
			'utm_campaign' => isset( $get['utm_campaign'] ) ? sanitize_text_field( (string) $get['utm_campaign'] ) : '',
		) );
		delete_transient( 'lr_analytics' );
	}

	private static function geo( $ip ) {
		$blank = array( 'country' => 'Unknown', 'code' => 'XX' );
		if ( in_array( $ip, array( '127.0.0.1', '::1' ), true ) ) { return array( 'country' => 'Local', 'code' => 'LO' ); }
		$tkey = 'lr_geo_' . md5( $ip );
		$c    = get_transient( $tkey );
		if ( $c !== false ) { return $c; }
		$r = wp_remote_get( 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/', array(
			'timeout'    => 2,
			'user-agent' => 'LinkRise/' . LINKRISE_VERSION,
		) );
		if ( is_wp_error( $r ) ) { set_transient( $tkey, $blank, 30 * MINUTE_IN_SECONDS ); return $blank; }
		if ( (int) wp_remote_retrieve_response_code( $r ) === 429 ) { set_transient( $tkey, $blank, 5 * MINUTE_IN_SECONDS ); return $blank; }
		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $body['country_name'] ) || ! empty( $body['error'] ) ) { set_transient( $tkey, $blank, 30 * MINUTE_IN_SECONDS ); return $blank; }
		$data = array( 'country' => sanitize_text_field( (string) $body['country_name'] ), 'code' => sanitize_text_field( (string) ( $body['country_code'] ?? 'XX' ) ) );
		set_transient( $tkey, $data, DAY_IN_SECONDS );
		return $data;
	}

	public static function send_ga4( $link, $ip, $server ) {
		$mid = (string) get_option( 'linkrise_ga4_id', '' );
		$sec = (string) get_option( 'linkrise_ga4_secret', '' );
		if ( empty( $mid ) || empty( $sec ) ) { return; }
		$ua = isset( $server['HTTP_USER_AGENT'] ) ? (string) $server['HTTP_USER_AGENT'] : '';
		wp_remote_post(
			'https://www.google-analytics.com/mp/collect?measurement_id=' . rawurlencode( $mid ) . '&api_secret=' . rawurlencode( $sec ),
			array(
				'blocking' => false,
				'body'     => wp_json_encode( array(
					'client_id' => md5( $ip . $ua ),
					'events'    => array( array(
						'name'   => 'linkrise_click',
						'params' => array(
							'shortcode'   => $link->shortcode,
							'destination' => $link->long_url,
							'category'    => isset( $link->category ) ? (string) $link->category : '',
						),
					) ),
				) ),
			)
		);
	}

	// ── SHORTCODES ───────────────────────────────────────────────────────────
	// KEY FIX: Scripts/data are output inline in the shortcode HTML.
	// We do NOT call wp_enqueue_script() from inside a shortcode (too late — 
	// wp_head has already fired). Instead we output a self-contained <script>
	// block inline. This works on ALL WordPress versions with NO dependencies.

	public static function sc_generator( $atts ) {
		if ( get_option( 'linkrise_admin_only' ) === '1' && ! current_user_can( 'manage_options' ) ) { return ''; }
		$cfg = self::js_cfg();
		$id  = 'lrg-' . wp_rand( 1000, 9999 );
		ob_start();
		?>
<div id="<?php echo esc_attr( $id ); ?>" class="lr-wrap"></div>
<script>
(function(){
var cfg=<?php echo wp_json_encode( $cfg ); ?>;
var el=document.getElementById(<?php echo wp_json_encode( $id ); ?>);
if(!el)return;
LR_Generator(el,cfg);
})();
</script>
		<?php
		return ob_get_clean();
	}

	public static function sc_bulk( $atts ) {
		if ( get_option( 'linkrise_admin_only' ) === '1' && ! current_user_can( 'manage_options' ) ) { return ''; }
		$cfg = self::js_cfg();
		$id  = 'lrb-' . wp_rand( 1000, 9999 );
		ob_start();
		?>
<div id="<?php echo esc_attr( $id ); ?>" class="lr-wrap"></div>
<script>
(function(){
var cfg=<?php echo wp_json_encode( $cfg ); ?>;
var el=document.getElementById(<?php echo wp_json_encode( $id ); ?>);
if(!el)return;
LR_Bulk(el,cfg);
})();
</script>
		<?php
		return ob_get_clean();
	}

	public static function sc_landing( $atts ) {
		if ( ! headers_sent() ) {
			header( "X-Content-Type-Options: nosniff" );
			header( "X-Frame-Options: SAMEORIGIN" );
			header( "Referrer-Policy: no-referrer-when-downgrade" );
		}

		$sc   = isset( $_GET['lrsc'] ) ? sanitize_text_field( wp_unslash( $_GET['lrsc'] ) ) : '';
		$id   = 'lrl-' . wp_rand( 1000, 9999 );

		if ( empty( $sc ) ) {
			return '<div class="lr-wrap"><div class="lr-card"><p class="lr-error">No link specified.</p></div></div>';
		}

		global $wpdb;
		$link = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . LinkRise_DB::lt() . " WHERE shortcode = %s AND status = 'active' LIMIT 1",
			$sc
		) );

		if ( ! $link ) {
			return '<div class="lr-wrap"><div class="lr-card"><p class="lr-error">Link not found or inactive.</p></div></div>';
		}
		if ( $link->expiry_date && strtotime( $link->expiry_date ) < time() ) {
			return '<div class="lr-wrap"><div class="lr-card"><p class="lr-error">This link has expired.</p></div></div>';
		}

		$cfg = array(
			'sc'        => $sc,
			'hasPw'     => ! empty( $link->password_hash ),
			'target'    => empty( $link->password_hash ) ? esc_url_raw( $link->long_url ) : '',
			'countdown' => max( 0, (int) get_option( 'linkrise_countdown', 5 ) ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'lr_landing_nonce' ),
			'gtmId'     => (string) get_option( 'linkrise_gtm_id', '' ),
		);
		ob_start();
		?>
<div id="<?php echo esc_attr( $id ); ?>" class="lr-wrap"></div>
<script>
(function(){
var cfg=<?php echo wp_json_encode( $cfg ); ?>;
var el=document.getElementById(<?php echo wp_json_encode( $id ); ?>);
if(!el)return;
LR_Landing(el,cfg);
})();
</script>
		<?php
		return ob_get_clean();
	}

	private static function js_cfg() {
		$prov = (string) get_option( 'linkrise_captcha_provider', 'disabled' );
		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'lr_pub_nonce' ),
			'captcha'     => $prov,
			'rcSite'      => ( $prov === 'recaptcha' )  ? (string) get_option( 'linkrise_recaptcha_site', '' )  : '',
			'tsSite'      => ( $prov === 'turnstile' )  ? (string) get_option( 'linkrise_turnstile_site', '' )  : '',
			'tosUrl'      => (string) get_option( 'linkrise_tos_url', '' ),
			'siteUrl'     => trailingslashit( site_url() ),
			'prefix'      => lr_prefix(),
		);
	}

	// ── AJAX HANDLERS ───────────────────────────────────────────────────────

	public static function ajax_create() {
		check_ajax_referer( 'lr_pub_nonce', 'nonce' );

		if ( get_option( 'linkrise_admin_only' ) === '1' && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'Link creation is restricted to administrators.' ) );
		}

		$long_url  = isset( $_POST['longUrl'] )  ? esc_url_raw( wp_unslash( $_POST['longUrl'] ) )            : '';
		$custom    = isset( $_POST['custom'] )    ? sanitize_text_field( wp_unslash( $_POST['custom'] ) )     : '';
		$password  = isset( $_POST['password'] )  ? sanitize_text_field( wp_unslash( $_POST['password'] ) )   : '';
		$expiry    = isset( $_POST['expiry'] )    ? sanitize_text_field( wp_unslash( $_POST['expiry'] ) )     : '';
		$category  = isset( $_POST['category'] )  ? sanitize_text_field( wp_unslash( $_POST['category'] ) )   : '';
		$custom    = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $custom );

		if ( ! LinkRise_Security::is_safe_url( $long_url ) ) {
			wp_send_json_error( array( 'msg' => 'Please enter a valid URL (must start with http:// or https://)' ) );
		}
		$token = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';
		if ( ! LinkRise_Security::verify_captcha( $token ) ) {
			wp_send_json_error( array( 'msg' => 'Security check failed. Please refresh and try again.' ) );
		}
		if ( ! LinkRise_Security::rate_ok() ) {
			wp_send_json_error( array( 'msg' => 'Rate limit reached. Please wait before creating more links.' ) );
		}
		if ( ! LinkRise_Security::is_url_safe( $long_url ) ) {
			wp_send_json_error( array( 'msg' => 'This URL has been flagged as potentially unsafe.' ) );
		}

		global $wpdb;
		$table = LinkRise_DB::lt();

		if ( $custom ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE shortcode = %s", $custom ) ) ) {
				wp_send_json_error( array( 'msg' => 'That custom code is already taken. Try another.' ) );
			}
			$code = $custom;
		} else {
			$code = '';
			for ( $i = 0; $i < 10; $i++ ) {
				$try = wp_generate_password( 6, false, false );
				if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE shortcode = %s", $try ) ) ) {
					$code = $try; break;
				}
			}
			if ( ! $code ) { wp_send_json_error( array( 'msg' => 'Could not generate a unique code. Please try again.' ) ); }
		}

		$pfx = lr_prefix();
		$row = array(
			'shortcode'     => $code,
			'long_url'      => $long_url,
			'created_at'    => current_time( 'mysql' ),
			'custom_prefix' => $pfx,
			'category'      => $category,
			'status'        => 'active',
		);
		if ( $password ) { $row['password_hash'] = wp_hash_password( $password ); }
		if ( $expiry && strtotime( $expiry ) ) { $row['expiry_date'] = date( 'Y-m-d H:i:s', strtotime( $expiry ) ); }

		if ( $wpdb->insert( $table, $row ) === false ) {
			wp_send_json_error( array( 'msg' => 'Database error. Please try again.' ) );
		}

		wp_send_json_success( array(
			'url'  => site_url( '/' . $pfx . '/' . $code ),
			'code' => $code,
		) );
	}

	public static function ajax_bulk_create() {
		check_ajax_referer( 'lr_pub_nonce', 'nonce' );

		if ( get_option( 'linkrise_admin_only' ) === '1' && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'Restricted to administrators.' ) );
		}

		$max      = max( 1, (int) get_option( 'linkrise_bulk_max', 50 ) );
		$raw_json = isset( $_POST['urls'] ) ? stripslashes( (string) $_POST['urls'] ) : '[]';
		$urls     = json_decode( $raw_json, true );
		if ( ! is_array( $urls ) ) { $urls = array(); }
		$urls     = array_slice( $urls, 0, $max );
		$password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';
		$expiry   = isset( $_POST['expiry'] )   ? sanitize_text_field( wp_unslash( $_POST['expiry'] ) )   : '';
		$token    = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';

		if ( ! LinkRise_Security::verify_captcha( $token ) ) {
			wp_send_json_error( array( 'msg' => 'Security check failed.' ) );
		}
		if ( ! LinkRise_Security::rate_ok() ) {
			wp_send_json_error( array( 'msg' => 'Rate limit reached.' ) );
		}

		global $wpdb;
		$table   = LinkRise_DB::lt();
		$pfx     = lr_prefix();
		$results = array();
		$errors  = array();

		foreach ( $urls as $u ) {
			$url = esc_url_raw( trim( (string) $u ) );
			if ( ! LinkRise_Security::is_safe_url( $url ) ) { $errors[] = 'Invalid URL: ' . $u; continue; }
			if ( ! LinkRise_Security::is_url_safe( $url ) ) { $errors[] = 'Blocked (unsafe): ' . $url; continue; }
			$code = '';
			for ( $i = 0; $i < 10; $i++ ) {
				$try = wp_generate_password( 6, false, false );
				if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE shortcode = %s", $try ) ) ) { $code = $try; break; }
			}
			if ( ! $code ) { $errors[] = 'Could not generate code for: ' . $url; continue; }
			$row = array( 'shortcode' => $code, 'long_url' => $url, 'created_at' => current_time( 'mysql' ), 'custom_prefix' => $pfx, 'status' => 'active' );
			if ( $password ) { $row['password_hash'] = wp_hash_password( $password ); }
			if ( $expiry && strtotime( $expiry ) ) { $row['expiry_date'] = date( 'Y-m-d H:i:s', strtotime( $expiry ) ); }
			if ( $wpdb->insert( $table, $row ) ) {
				$results[] = array( 'orig' => $url, 'short' => site_url( '/' . $pfx . '/' . $code ) );
			}
		}
		wp_send_json_success( array( 'results' => $results, 'errors' => $errors ) );
	}

	public static function ajax_verify_pw() {
		check_ajax_referer( 'lr_landing_nonce', 'nonce' );
		global $wpdb;
		$sc  = isset( $_POST['sc'] )  ? sanitize_text_field( wp_unslash( $_POST['sc'] ) )  : '';
		$pwd = isset( $_POST['pwd'] ) ? sanitize_text_field( wp_unslash( $_POST['pwd'] ) ) : '';
		$lnk = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . LinkRise_DB::lt() . " WHERE shortcode = %s AND status='active' LIMIT 1",
			$sc
		) );
		if ( $lnk && wp_check_password( $pwd, $lnk->password_hash ) ) {
			wp_send_json_success( array( 'url' => esc_url_raw( $lnk->long_url ) ) );
		}
		wp_send_json_error( array( 'msg' => 'Incorrect password. Please try again.' ) );
	}

	public static function ajax_report() {
		check_ajax_referer( 'lr_landing_nonce', 'nonce' );
		global $wpdb;
		$wpdb->insert( LinkRise_DB::rt(), array(
			'shortcode'    => isset( $_POST['sc'] )     ? sanitize_text_field( wp_unslash( $_POST['sc'] ) )          : '',
			'reported_url' => isset( $_POST['url'] )    ? esc_url_raw( wp_unslash( $_POST['url'] ) )                 : '',
			'reason'       => isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) )       : '',
			'details'      => isset( $_POST['details'] )? sanitize_textarea_field( wp_unslash( $_POST['details'] ) )  : '',
			'reporter_ip'  => LinkRise_Security::get_ip(),
			'reported_at'  => current_time( 'mysql' ),
		) );
		wp_send_json_success();
	}
}

// FIX: Register and output the frontend JS in wp_head (not wp_enqueue_scripts)
// This ensures the script is available before shortcode inline calls execute.
// We also need to hook enqueue_global_css to also load the frontend JS.
