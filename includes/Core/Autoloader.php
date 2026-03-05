<?php
namespace LinkRiseEnterprise\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Autoloader {
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class ) {
		$prefix = 'LinkRiseEnterprise\\';
		if ( strpos( $class, $prefix ) !== 0 ) { return; }
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file = LINKRISE_DIR . 'includes/' . $relative . '.php';
		if ( file_exists( $file ) ) { require_once $file; }
	}
}
