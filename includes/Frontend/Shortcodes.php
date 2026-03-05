<?php
namespace LinkRiseEnterprise\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Shortcodes {
	public function register() {
		add_shortcode( 'linkrise_generator', array( $this, 'generator' ) );
		add_shortcode( 'linkrise_bulk_generator', array( $this, 'bulk' ) );
		add_shortcode( 'linkrise_landing', array( $this, 'landing' ) );
		add_shortcode( 'linkrise_bio', array( $this, 'bio' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets() {
		wp_enqueue_style( 'linkrise-enterprise-frontend', LINKRISE_URL . 'assets/css/linkrise-frontend.css', array(), LINKRISE_VERSION );
		wp_enqueue_script( 'linkrise-enterprise-frontend', LINKRISE_URL . 'assets/js/frontend.js', array(), LINKRISE_VERSION, true );
	}

	public function generator( $atts ) {
		$atts = shortcode_atts( array( 'title' => 'Free URL Shortener', 'button_text' => 'Shorten URL' ), $atts, 'linkrise_generator' );
		return '<div class="lr-wrap"><div class="lr-card" data-lr-generator data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"><h2>' . esc_html( $atts['title'] ) . '</h2><input class="lr-input" type="url" placeholder="Paste your long URL here..."/><button class="lr-btn">' . esc_html( $atts['button_text'] ) . '</button><div class="lr-result"></div></div></div>';
	}

	public function bulk( $atts ) {
		$atts = shortcode_atts( array( 'title' => 'Bulk URL Shortener', 'max_urls' => '50' ), $atts, 'linkrise_bulk_generator' );
		return '<div class="lr-wrap"><div class="lr-card" data-lr-bulk data-max="' . esc_attr( $atts['max_urls'] ) . '"><h2>' . esc_html( $atts['title'] ) . '</h2><textarea class="lr-input" rows="8" placeholder="Paste one URL per line..."></textarea><button class="lr-btn">Shorten All</button><div class="lr-result"></div></div></div>';
	}

	public function landing( $atts ) {
		$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : 'redirect';
		return '<div class="lr-wrap"><div class="lr-card" data-lr-landing data-state="' . esc_attr( $state ) . '"></div></div>';
	}

	public function bio( $atts ) {
		global $wpdb;
		$atts = shortcode_atts( array( 'user_id' => '0' ), $atts, 'linkrise_bio' );
		$uid = absint( $atts['user_id'] );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT shortcode,long_url FROM ' . $wpdb->prefix . "linkrise_links WHERE created_by=%d AND status='active' ORDER BY id DESC LIMIT 50", $uid ) );
		$out = '<div class="lr-wrap"><div class="lr-card"><h2>My Links</h2>';
		foreach ( (array) $rows as $r ) {
			$out .= '<p><a href="' . esc_url( site_url( '/' . lr_prefix() . '/' . $r->shortcode ) ) . '">' . esc_html( $r->shortcode ) . '</a></p>';
		}
		return $out . '</div></div>';
	}
}
