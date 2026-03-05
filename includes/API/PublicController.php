<?php
namespace LinkRiseEnterprise\API;

use LinkRiseEnterprise\Services\LinkService;
use LinkRiseEnterprise\Services\SecurityService;
use LinkRiseEnterprise\Database\Repository\LinkRepository;
use LinkRiseEnterprise\Database\Repository\ReportRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PublicController extends RestController {
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}
	public function routes() {
		register_rest_route( $this->namespace, '/public/shorten', array('methods'=>'POST','permission_callback'=>array($this,'public_permission'),'callback'=>array($this,'shorten')) );
		register_rest_route( $this->namespace, '/public/bulk-shorten', array('methods'=>'POST','permission_callback'=>array($this,'public_permission'),'callback'=>array($this,'bulk')) );
		register_rest_route( $this->namespace, '/public/verify-password', array('methods'=>'POST','permission_callback'=>array($this,'public_permission'),'callback'=>array($this,'verify_password')) );
		register_rest_route( $this->namespace, '/public/report', array('methods'=>'POST','permission_callback'=>array($this,'public_permission'),'callback'=>array($this,'report')) );
		register_rest_route( $this->namespace, '/public/stats/(?P<shortcode>[a-zA-Z0-9_-]+)', array('methods'=>'GET','permission_callback'=>array($this,'public_permission'),'callback'=>array($this,'stats')) );
	}
	public function public_permission() { return true; }

	public function shorten( \WP_REST_Request $r ) {
		$security = new SecurityService();
		if ( $security->admin_only_blocked() ) { return $this->error( 'Link creation is restricted to administrators.', 403 ); }
		if ( ! $security->rate_ok( 'public_shorten', (int) get_option( 'linkrise_rate_limit', 60 ) ) ) { return $this->error( 'Rate limit reached. Please try again later.', 429 ); }
		$res = ( new LinkService() )->create_short( (string) $r->get_param( 'url' ), (string) $r->get_param( 'custom_code' ) );
		if ( is_wp_error( $res ) ) { return $this->error( $res->get_error_message(), 400 ); }
		$res['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode( $res['short_url'] );
		return $this->ok( $res );
	}

	public function bulk( \WP_REST_Request $r ) {
		$security = new SecurityService();
		if ( ! $security->rate_ok( 'public_bulk', (int) get_option( 'linkrise_rate_limit', 60 ) ) ) { return $this->error( 'Rate limit reached. Please try again later.', 429 ); }
		$urls = (array) $r->get_param( 'urls' );
		$max = min( 50, (int) get_option( 'linkrise_bulk_max', 50 ) );
		$results = array();
		foreach ( array_slice( $urls, 0, $max ) as $u ) {
			$res = ( new LinkService() )->create_short( (string) $u, '' );
			$results[] = is_wp_error( $res ) ? array('original'=>$u,'status'=>'error','error'=>$res->get_error_message()) : array('original'=>$u,'short_url'=>$res['short_url'],'shortcode'=>$res['shortcode'],'status'=>'success');
		}
		return $this->ok( array( 'results' => $results ) );
	}

	public function verify_password( \WP_REST_Request $r ) {
		global $wpdb;
		$table = $wpdb->prefix . 'linkrise_links';
		$sc = sanitize_text_field( (string) $r->get_param( 'shortcode' ) );
		$pwd = sanitize_text_field( (string) $r->get_param( 'password' ) );
		$link = $wpdb->get_row( $wpdb->prepare( "SELECT long_url,password_hash FROM {$table} WHERE shortcode=%s LIMIT 1", $sc ) );
		if ( ! $link || empty( $link->password_hash ) || ! wp_check_password( $pwd, $link->password_hash ) ) {
			return $this->error( 'Incorrect password.', 400 );
		}
		return $this->ok( array( 'success' => true, 'redirect_url' => esc_url_raw( $link->long_url ) ) );
	}

	public function report( \WP_REST_Request $r ) {
		$shortcode = sanitize_text_field( (string) $r->get_param( 'shortcode' ) );
		$reason = sanitize_text_field( (string) $r->get_param( 'reason' ) );
		if ( '' === $shortcode || '' === $reason ) { return $this->error( 'Shortcode and reason are required.', 400 ); }
		$iphash = md5( ( new SecurityService() )->ip() );
		$key = 'lr_report_' . md5( $shortcode . '|' . $iphash );
		if ( get_transient( $key ) ) { return $this->error( 'You already reported this link recently.', 429 ); }
		( new ReportRepository() )->create( array( 'shortcode'=>$shortcode, 'reported_url'=>esc_url_raw((string)$r->get_param('reported_url')), 'reason'=>$reason, 'details'=>sanitize_textarea_field((string)$r->get_param('details')), 'reporter_ip'=>$iphash, 'reported_at'=>current_time('mysql') ) );
		set_transient( $key, 1, HOUR_IN_SECONDS );
		return $this->ok( array( 'success' => true ) );
	}

	public function stats( \WP_REST_Request $r ) {
		$link = ( new LinkRepository() )->find_by_shortcode( sanitize_text_field( (string) $r['shortcode'] ) );
		if ( ! $link ) { return $this->error( 'Link not found.', 404 ); }
		return $this->ok( array( 'clicks'=>(int)$link->click_count, 'created_at'=>$link->created_at, 'status'=>$link->status ) );
	}
}
