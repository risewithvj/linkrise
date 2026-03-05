<?php
/**
 * Plugin Name:  LinkRise
 * Plugin URI:   https://github.com/risewithvj
 * Description:  Advanced URL shortener — analytics, QR codes, click tracking, geolocation, GA4/GTM, password protection, bulk generation and full security.
 * Version:      3.1.0
 * Author:       Vijaya Kumar L
 * Author URI:   https://www.linkedin.com/in/vijayakumarl/
 * Text Domain:  linkrise
 * License:      GPL v2 or later
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Block direct access
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Guard against double-loading (e.g. two copies of plugin active)
if ( defined( 'LINKRISE_VERSION' ) ) { return; }

define( 'LINKRISE_VERSION',    '3.1.0' );
define( 'LINKRISE_DIR',        plugin_dir_path( __FILE__ ) );
define( 'LINKRISE_URL',        plugin_dir_url( __FILE__ ) );
define( 'LINKRISE_PFX',        'go' ); // fallback prefix

// ── Load classes at file-include time so activation hook can use them ────────
require_once LINKRISE_DIR . 'includes/class-db.php';
require_once LINKRISE_DIR . 'includes/class-security.php';
require_once LINKRISE_DIR . 'includes/class-settings.php';
require_once LINKRISE_DIR . 'includes/class-frontend.php';
require_once LINKRISE_DIR . 'includes/class-admin.php';

// ── Activation ───────────────────────────────────────────────────────────────
// NOTE: add_rewrite_rule() CANNOT be called here — WP 'init' hasn't fired.
// We set a flag; on the next real page-load 'init' fires, rules are added,
// then maybe_flush_rules() flushes the cache exactly once.
register_activation_hook( __FILE__, 'linkrise_on_activate' );
function linkrise_on_activate() {
	LinkRise_DB::create_tables();
	update_option( 'linkrise_flush_needed', '1' );
}

register_deactivation_hook( __FILE__, 'linkrise_on_deactivate' );
function linkrise_on_deactivate() {
	flush_rewrite_rules( false );
}

// Uninstall via dedicated function (must be global, not closure)
register_uninstall_hook( __FILE__, 'linkrise_on_uninstall' );
function linkrise_on_uninstall() {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}linkrise_links" );    // phpcs:ignore
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}linkrise_clicks" );   // phpcs:ignore
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}linkrise_reports" );  // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'linkrise\_%'" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lr\_%'" ); // phpcs:ignore
	flush_rewrite_rules( false );
}

// ── Boot all systems on plugins_loaded ──────────────────────────────────────
add_action( 'plugins_loaded', 'linkrise_boot', 5 );
function linkrise_boot() {
	LinkRise_DB::init();
	LinkRise_Settings::init();
	LinkRise_Security::init();
	LinkRise_Frontend::init();
	LinkRise_Admin::init();
}

// ── SEO: block crawlers from indexing shortlinks ─────────────────────────────
add_filter( 'robots_txt', 'linkrise_robots_txt', 10, 2 );
function linkrise_robots_txt( $output, $public ) {
	$prefix = lr_prefix();
	return $output . "\nUser-agent: *\nDisallow: /{$prefix}/\n";
}

// ── Global helper ─────────────────────────────────────────────────────────────
/**
 * Returns the current redirect prefix, sanitised and never empty.
 */
function lr_prefix() {
	$raw = (string) get_option( 'linkrise_redirect_prefix', LINKRISE_PFX );
	$pfx = preg_replace( '/[^a-z0-9\-]/', '', strtolower( trim( $raw ) ) );
	return $pfx !== '' ? $pfx : LINKRISE_PFX;
}
