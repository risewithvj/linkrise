<?php
namespace LinkRiseEnterprise\Database\Repository;
use LinkRiseEnterprise\Database\Schema;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ReportRepository {
	public function create( $data ) { global $wpdb; return $wpdb->insert( Schema::tables()['reports'], $data ); }
}
