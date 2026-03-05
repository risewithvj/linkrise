<?php
namespace LinkRiseEnterprise\Frontend;

use LinkRiseEnterprise\Database\Repository\LinkRepository;
use LinkRiseEnterprise\Services\AnalyticsService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RedirectEngine {
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle' ), 2 );
	}

	public function handle() {
		$code = get_query_var( 'lr_code' );
		if ( empty( $code ) ) { return; }
		$link = ( new LinkRepository() )->find_by_shortcode( sanitize_text_field( $code ) );
		if ( ! $link ) { return; }
		if ( ! empty( $link->password_hash ) ) { return; }
		add_action( 'shutdown', function() use ( $link ) { ( new AnalyticsService() )->track_click( $link ); } );
	}
}
