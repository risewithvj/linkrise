<?php
namespace LinkRiseEnterprise\Database\Repository;

use LinkRiseEnterprise\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CampaignRepository {
	public function all() {
		global $wpdb;
		$table = Schema::tables()['campaigns'];
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
	}
	public function create( $data ) {
		global $wpdb;
		$wpdb->insert( Schema::tables()['campaigns'], $data );
		return (int) $wpdb->insert_id;
	}
}
