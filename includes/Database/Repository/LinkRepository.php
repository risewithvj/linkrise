<?php
namespace LinkRiseEnterprise\Database\Repository;
use LinkRiseEnterprise\Database\Schema;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LinkRepository {
	public function find_by_shortcode( $shortcode ) {
		global $wpdb; $t = Schema::tables()['links'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE shortcode=%s LIMIT 1", $shortcode ) );
	}
	public function create( $data ) {
		global $wpdb; $t = Schema::tables()['links'];
		$wpdb->insert( $t, $data );
		return (int) $wpdb->insert_id;
	}
}
