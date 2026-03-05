<?php
namespace LinkRiseEnterprise\Services;
use LinkRiseEnterprise\Database\Repository\LinkRepository;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LinkService {
	private $repo;
	public function __construct() { $this->repo = new LinkRepository(); }
	public function create_short( $url, $custom = '' ) {
		$url = esc_url_raw( $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) { return new \WP_Error( 'invalid_url', 'Invalid URL' ); }
		$code = $custom ? preg_replace( '/[^a-zA-Z0-9\-_]/', '', sanitize_text_field( $custom ) ) : wp_generate_password( 6, false, false );
		if ( $this->repo->find_by_shortcode( $code ) ) { return new \WP_Error( 'code_taken', 'That code is taken, try another' ); }
		$id = $this->repo->create( array(
			'shortcode' => $code,
			'long_url' => $url,
			'created_at' => current_time( 'mysql' ),
			'custom_prefix' => lr_prefix(),
			'status' => 'active',
		) );
		return array( 'id' => $id, 'shortcode' => $code, 'short_url' => site_url( '/' . lr_prefix() . '/' . $code ) );
	}
}
