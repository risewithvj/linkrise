<?php
namespace LinkRiseEnterprise\Database\Repository;

use LinkRiseEnterprise\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ReportRepository {
	public function create( $data ) {
		global $wpdb;
		return $wpdb->insert( Schema::tables()['reports'], $data );
	}
	public function all( $status = '' ) {
		global $wpdb;
		$table = Schema::tables()['reports'];
		if ( '' !== $status ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status=%s ORDER BY reported_at DESC LIMIT 200", $status ) );
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY reported_at DESC LIMIT 200" );
	}
	public function set_status( $id, $status, $reviewed_by ) {
		global $wpdb;
		return $wpdb->update(
			Schema::tables()['reports'],
			array( 'status' => $status, 'reviewed_by' => $reviewed_by, 'reviewed_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}
}
