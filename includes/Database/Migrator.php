<?php
namespace LinkRiseEnterprise\Database;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Migrator {
	public function register() {
		add_action( 'init', array( $this, 'maybe_migrate' ), 4 );
	}
	public function maybe_migrate() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$ver = (string) get_option( 'linkrise_enterprise_schema', '0' );
		if ( version_compare( $ver, '4.0.0', '>=' ) ) { return; }
		foreach ( Schema::sql() as $sql ) { dbDelta( $sql ); }
		update_option( 'linkrise_enterprise_schema', '4.0.0' );
	}
}
