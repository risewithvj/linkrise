<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

namespace LinkRise;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( $class ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
		$file     = LINKRISE_DIR . 'includes/class-' . str_replace( '\\', '/class-', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
