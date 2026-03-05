<?php
namespace LinkRiseEnterprise\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class AdminMenu {
	public function register() { add_action( 'admin_menu', array( $this, 'menu' ) ); }
	public function menu() {}
}
