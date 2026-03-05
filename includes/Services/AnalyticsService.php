<?php
namespace LinkRiseEnterprise\Services;
use LinkRiseEnterprise\Database\Repository\ClickRepository;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class AnalyticsService {
	public function track_click( $link ) {
		$security = new SecurityService();
		( new ClickRepository() )->insert( array(
			'link_id' => (int) $link->id,
			'shortcode' => $link->shortcode,
			'clicked_at' => current_time( 'mysql' ),
			'ip_hash' => $security->ip_hash(),
			'is_unique' => 0,
		) );
	}
}
