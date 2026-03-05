<?php
namespace LinkRiseEnterprise\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AdminMenu {
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		add_menu_page(
			'LinkRise Enterprise',
			'LinkRise Enterprise',
			'manage_options',
			'linkrise-enterprise',
			array( $this, 'render' ),
			'dashicons-chart-area',
			66
		);
	}

	public function render() {
		$tabs = array( 'dashboard', 'links', 'analytics', 'reports', 'settings' );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		if ( ! in_array( $tab, $tabs, true ) ) { $tab = 'dashboard'; }
		echo '<div class="wrap"><div id="linkrise-admin-root" data-tab="' . esc_attr( $tab ) . '"></div></div>';
	}
}
