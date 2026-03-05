<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}
	public function menu() {
		add_menu_page( 'LinkRise', 'LinkRise', 'manage_options', 'linkrise', array( $this, 'render' ), 'dashicons-admin-links', 65 );
	}
	public function assets( $hook ) {
		if ( 'toplevel_page_linkrise' !== $hook ) { return; }
		wp_enqueue_style( 'linkrise-admin', LINKRISE_URL . 'assets/css/admin.css', array(), LINKRISE_VERSION );
		wp_enqueue_script( 'alpine', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js', array(), LINKRISE_VERSION, true ); // phpcs:ignore
		wp_enqueue_script( 'linkrise-admin', LINKRISE_URL . 'assets/js/admin.js', array(), LINKRISE_VERSION, true );
	}
	public function render() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$allowed = array( 'dashboard', 'links', 'analytics', 'reports', 'settings' );
		if ( ! in_array( $tab, $allowed, true ) ) { $tab = 'dashboard'; }
		require LINKRISE_DIR . 'views/admin/' . $tab . '.php';
	}
}
