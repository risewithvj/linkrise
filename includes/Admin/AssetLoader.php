<?php
namespace LinkRiseEnterprise\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AssetLoader {
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ) {
		if ( 'toplevel_page_linkrise-enterprise' !== $hook ) { return; }
		wp_enqueue_style( 'linkrise-enterprise-admin', LINKRISE_URL . 'assets/css/linkrise-admin.css', array(), LINKRISE_VERSION );
		wp_enqueue_script( 'linkrise-enterprise-admin', LINKRISE_URL . 'assets/js/admin.js', array(), LINKRISE_VERSION, true );
		wp_localize_script( 'linkrise-enterprise-admin', 'LR_DATA', array(
			'rest_url' => esc_url_raw( rest_url( 'linkrise/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'settings' => array(
				'rate_limit' => (int) get_option( 'linkrise_rate_limit', 60 ),
				'bulk_max'   => (int) get_option( 'linkrise_bulk_max', 50 ),
			),
		) );
	}
}
