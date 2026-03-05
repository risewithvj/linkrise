<?php
namespace LinkRiseEnterprise\Database\Repository;

use LinkRiseEnterprise\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LinkRepository {
	public function find_by_shortcode( $shortcode ) {
		global $wpdb;
		$t = Schema::tables()['links'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE shortcode=%s LIMIT 1", $shortcode ) );
	}

	public function create( $data ) {
		global $wpdb;
		$t = Schema::tables()['links'];
		$wpdb->insert( $t, $data );
		return (int) $wpdb->insert_id;
	}

	public function list_paginated( $page = 1, $per_page = 20 ) {
		global $wpdb;
		$t = Schema::tables()['links'];
		$page = max( 1, (int) $page );
		$per_page = max( 1, min( 1000, (int) $per_page ) );
		$offset = ( $page - 1 ) * $per_page;
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$t} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		return array( 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per_page );
	}
}
