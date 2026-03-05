<?php
namespace LinkRiseEnterprise\Database\Repository;
use LinkRiseEnterprise\Database\Schema;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ClickRepository {
	public function insert( $data ) { global $wpdb; return $wpdb->insert( Schema::tables()['clicks'], $data ); }
}
