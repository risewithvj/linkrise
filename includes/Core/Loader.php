<?php
namespace LinkRiseEnterprise\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Loader {
	public static function hook( $hook, $callable, $priority = 10, $args = 1 ) {
		add_action( $hook, $callable, $priority, $args );
	}
}
