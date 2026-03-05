<?php
namespace LinkRiseEnterprise\Database\Repository;

use LinkRiseEnterprise\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PixelRepository {
	public function all() {
		global $wpdb;
		$table = Schema::tables()['pixels'];
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
	}
	public function create( $data ) {
		global $wpdb;
		$wpdb->insert( Schema::tables()['pixels'], $data );
		return (int) $wpdb->insert_id;
	}
}
