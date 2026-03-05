<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Settings {
	public function init() {
		add_action( 'admin_post_linkrise_save_settings', array( $this, 'save' ) );
	}
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized', 403 ); }
		check_admin_referer( 'linkrise_settings' );
		$keys = array( 'linkrise_redirect_prefix', 'linkrise_landing_url', 'linkrise_rate_limit', 'linkrise_countdown' );
		foreach ( $keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = wp_unslash( $_POST[ $key ] );
				update_option( $key, is_numeric( $value ) ? absint( $value ) : sanitize_text_field( $value ) );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=linkrise&tab=settings&saved=1' ) );
		exit;
	}
}
