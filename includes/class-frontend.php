<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Frontend {
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_shortcode( 'linkrise_generator', array( $this, 'generator' ) );
		add_shortcode( 'linkrise_bulk_generator', array( $this, 'bulk' ) );
		add_shortcode( 'linkrise_landing', array( $this, 'landing' ) );
	}
	public function assets() {
		wp_enqueue_style( 'linkrise-frontend', LINKRISE_URL . 'assets/css/frontend.css', array(), LINKRISE_VERSION );
		wp_enqueue_script( 'linkrise-frontend', LINKRISE_URL . 'assets/js/frontend.js', array(), LINKRISE_VERSION, true );
	}
	public function generator() { return '<div class="lr-card" data-linkrise-generator></div>'; }
	public function bulk() { return '<div class="lr-card" data-linkrise-bulk></div>'; }
	public function landing() { return '<div class="lr-card" data-linkrise-landing></div>'; }
}
