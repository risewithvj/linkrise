<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {
	public function init() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
	}

	public function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'linkrise_' . $name;
	}

	public function maybe_upgrade() {
		$this->check_upgrade();
	}

	public function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = array();
		$sql[] = "CREATE TABLE {$this->table('links')} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			shortcode VARCHAR(120) NOT NULL,
			long_url TEXT NOT NULL,
			fallback_url TEXT NULL,
			password_hash VARCHAR(255) NULL,
			expiry_date DATETIME NULL,
			click_limit BIGINT UNSIGNED NULL,
			click_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			last_clicked DATETIME NULL,
			category VARCHAR(100) NULL,
			notes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			custom_prefix VARCHAR(60) NULL,
			utm_source VARCHAR(120) NULL,
			utm_medium VARCHAR(120) NULL,
			utm_campaign VARCHAR(120) NULL,
			utm_term VARCHAR(120) NULL,
			utm_content VARCHAR(120) NULL,
			metadata LONGTEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			UNIQUE KEY shortcode (shortcode),
			KEY status (status),
			KEY created_by (created_by)
		) $charset";

		$sql[] = "CREATE TABLE {$this->table('clicks')} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			link_id BIGINT UNSIGNED NOT NULL,
			shortcode VARCHAR(120) NOT NULL,
			clicked_at DATETIME NOT NULL,
			ip_hash CHAR(32) NOT NULL,
			country VARCHAR(100) NULL,
			country_code VARCHAR(6) NULL,
			city VARCHAR(120) NULL,
			device VARCHAR(40) NULL,
			browser VARCHAR(80) NULL,
			browser_version VARCHAR(50) NULL,
			os VARCHAR(80) NULL,
			os_version VARCHAR(50) NULL,
			referrer TEXT NULL,
			referrer_domain VARCHAR(190) NULL,
			user_agent TEXT NULL,
			language VARCHAR(10) NULL,
			is_unique TINYINT(1) NOT NULL DEFAULT 0,
			session_id VARCHAR(64) NULL,
			utm_source VARCHAR(120) NULL,
			utm_medium VARCHAR(120) NULL,
			utm_campaign VARCHAR(120) NULL,
			PRIMARY KEY (id),
			KEY link_time (link_id, clicked_at),
			KEY shortcode (shortcode),
			KEY session_id (session_id)
		) $charset";

		$sql[] = "CREATE TABLE {$this->table('reports')} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			shortcode VARCHAR(120) NOT NULL,
			reported_url TEXT NOT NULL,
			reason VARCHAR(100) NOT NULL,
			details TEXT NULL,
			reporter_ip VARCHAR(45) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status_created (status, created_at)
		) $charset";

		$sql[] = "CREATE TABLE {$this->table('analytics')} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			link_id BIGINT UNSIGNED NOT NULL,
			date DATE NOT NULL,
			clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unique_clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
			countries LONGTEXT NULL,
			devices LONGTEXT NULL,
			browsers LONGTEXT NULL,
			referrers LONGTEXT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY link_date (link_id, date)
		) $charset";

		$sql[] = "CREATE TABLE {$this->table('queue')} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(80) NOT NULL,
			data LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
			scheduled_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			error TEXT NULL,
			PRIMARY KEY (id),
			KEY status_schedule (status, scheduled_at)
		) $charset";

		$sql[] = "CREATE TABLE {$this->table('cache')} (
			cache_key VARCHAR(190) NOT NULL,
			cache_value LONGTEXT NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY (cache_key),
			KEY expires_at (expires_at)
		) $charset";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'linkrise_db_version', LINKRISE_DB_VERSION );
	}

	/**
	 * Check if database needs upgrade
	 */
	public function check_upgrade() {
		error_log( 'LinkRise: Checking database upgrade' );
		$current_version = get_option( 'linkrise_db_version', '0' );

		if ( version_compare( $current_version, LINKRISE_PRO_VERSION, '<' ) ) {
			error_log( 'LinkRise: Upgrading from ' . $current_version . ' to ' . LINKRISE_PRO_VERSION );
			$this->upgrade_tables( $current_version );
			update_option( 'linkrise_db_version', LINKRISE_PRO_VERSION );
		}
	}

	/**
	 * Upgrade tables from old version
	 */
	private function upgrade_tables( $from_version ) {
		error_log( 'LinkRise: Upgrading tables from version: ' . $from_version );
		$this->create_tables();
		if ( version_compare( $from_version, '4.0.0', '<' ) ) {
			$this->migrate_to_v4();
		}
	}

	/**
	 * Migrate to version 4.0.0
	 */
	private function migrate_to_v4() {
		error_log( 'LinkRise: Migrating to v4.0.0' );
		global $wpdb;
		$links  = $this->table( 'links' );
		$clicks = $this->table( 'clicks' );

		$columns = array(
			$links => array(
				'metadata'   => "ALTER TABLE {$links} ADD COLUMN metadata LONGTEXT NULL",
				'created_by' => "ALTER TABLE {$links} ADD COLUMN created_by BIGINT UNSIGNED NULL",
				'updated_at' => "ALTER TABLE {$links} ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
			),
			$clicks => array(
				'session_id' => "ALTER TABLE {$clicks} ADD COLUMN session_id VARCHAR(64) NULL",
				'language'   => "ALTER TABLE {$clicks} ADD COLUMN language VARCHAR(10) NULL",
			),
		);

		foreach ( $columns as $table => $ops ) {
			foreach ( $ops as $column => $sql ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
				if ( ! $exists ) {
					$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
		}
		$wpdb->query( "ALTER TABLE {$links} ADD INDEX created_by (created_by)" ); // phpcs:ignore
		$wpdb->query( "ALTER TABLE {$clicks} ADD INDEX session_id (session_id)" ); // phpcs:ignore
		$wpdb->query( "ALTER TABLE {$clicks} ADD INDEX language (language)" ); // phpcs:ignore
	}
}
