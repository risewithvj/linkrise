<?php
namespace LinkRiseEnterprise\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class AssetLoader {
	public function register() { add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) ); }
	public function enqueue() {}
}
