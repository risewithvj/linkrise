<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'LinkRise_DB' ) ) { return; }

class LinkRise_DB {

	/** Run on every page-load; create/upgrade tables when DB version changes */
	public static function init() {
		if ( (string) get_option( 'linkrise_db_ver', '' ) !== LINKRISE_VERSION ) {
			self::create_tables();
			update_option( 'linkrise_db_ver', LINKRISE_VERSION );
		}
	}

	public static function lt() { global $wpdb; return $wpdb->prefix . 'linkrise_links'; }
	public static function ct() { global $wpdb; return $wpdb->prefix . 'linkrise_clicks'; }
	public static function rt() { global $wpdb; return $wpdb->prefix . 'linkrise_reports'; }

	/**
	 * Create or upgrade all three tables with dbDelta.
	 *
	 * dbDelta is STRICT:
	 *   ✓ Exactly TWO spaces before PRIMARY KEY
	 *   ✓ KEY not INDEX for secondary keys
	 *   ✓ One definition per line
	 *   ✓ Column type in UPPER CASE preferred
	 *   ✓ No trailing comma on last key definition
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cs = $wpdb->get_charset_collate();
		$lt = self::lt();
		$ct = self::ct();
		$rt = self::rt();

		// ── Links ─────────────────────────────────────────────────────
		dbDelta( "CREATE TABLE {$lt} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  shortcode VARCHAR(32) NOT NULL,
  long_url TEXT NOT NULL,
  fallback_url VARCHAR(2048) DEFAULT NULL,
  expiry_date DATETIME DEFAULT NULL,
  password_hash VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  click_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  click_limit BIGINT(20) UNSIGNED DEFAULT NULL,
  last_clicked DATETIME DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'active',
  custom_prefix VARCHAR(20) NOT NULL DEFAULT 'go',
  PRIMARY KEY  (id),
  UNIQUE KEY shortcode (shortcode),
  KEY status (status)
) {$cs};" );

		// ── Clicks ────────────────────────────────────────────────────
		dbDelta( "CREATE TABLE {$ct} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  link_id BIGINT(20) UNSIGNED NOT NULL,
  shortcode VARCHAR(32) NOT NULL,
  clicked_at DATETIME NOT NULL,
  ip_hash VARCHAR(64) DEFAULT NULL,
  country VARCHAR(80) DEFAULT NULL,
  country_code VARCHAR(2) DEFAULT NULL,
  device VARCHAR(20) DEFAULT NULL,
  browser VARCHAR(50) DEFAULT NULL,
  os VARCHAR(50) DEFAULT NULL,
  referrer VARCHAR(500) DEFAULT NULL,
  utm_source VARCHAR(100) DEFAULT NULL,
  utm_medium VARCHAR(100) DEFAULT NULL,
  utm_campaign VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY link_id (link_id),
  KEY shortcode (shortcode),
  KEY clicked_at (clicked_at)
) {$cs};" );

		// ── Reports ───────────────────────────────────────────────────
		dbDelta( "CREATE TABLE {$rt} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  shortcode VARCHAR(32) NOT NULL,
  reported_url TEXT NOT NULL,
  reason VARCHAR(100) NOT NULL,
  details TEXT DEFAULT NULL,
  reporter_ip VARCHAR(45) DEFAULT NULL,
  reported_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY shortcode (shortcode)
) {$cs};" );
	}
}
