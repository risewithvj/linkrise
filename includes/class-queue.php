<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Queue {
	public function init() {
		add_action( 'linkrise_process_queue', array( $this, 'process' ) );
		if ( ! wp_next_scheduled( 'linkrise_process_queue' ) ) {
			wp_schedule_event( time() + 60, 'minute', 'linkrise_process_queue' );
		}
	}
	public function push( $type, $data ) {
		global $wpdb;
		$db = new Database();
		$wpdb->insert( $db->table( 'queue' ), array(
			'type' => sanitize_text_field( $type ),
			'data' => wp_json_encode( $data ),
			'status' => 'pending',
			'scheduled_at' => current_time( 'mysql' ),
		) );
	}
	public function process() {
		global $wpdb;
		$db = new Database();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$db->table('queue')} WHERE status=%s ORDER BY id ASC LIMIT %d", 'pending', 25 ) );
		foreach ( $rows as $row ) {
			$wpdb->update( $db->table( 'queue' ), array( 'status' => 'done', 'processed_at' => current_time( 'mysql' ) ), array( 'id' => (int) $row->id ) );
		}
	}
}
