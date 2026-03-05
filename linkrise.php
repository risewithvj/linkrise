<?php
/**
 * Plugin Name:  LinkRise
 * Plugin URI:   https://github.com/risewithvj
 * Description:  Enterprise-grade URL shortener with analytics, security, caching, and async processing.
 * Version:      4.0.0
 * Author:       Vijaya Kumar L
 * Author URI:   https://www.linkedin.com/in/vijayakumarl/
 * Text Domain:  linkrise
 * License:      GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'LINKRISE_PRO_VERSION' ) ) {
	return;
}

define( 'LINKRISE_PRO_VERSION', '4.0.0' );
define( 'LINKRISE_VERSION', LINKRISE_PRO_VERSION );
define( 'LINKRISE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LINKRISE_URL', plugin_dir_url( __FILE__ ) );
define( 'LINKRISE_DB_VERSION', '4.0.0' );

require_once LINKRISE_DIR . 'includes/class-autoloader.php';
LinkRise\Autoloader::register();

add_action(
	'plugins_loaded',
	function() {
		LinkRise\Core::instance()->boot();
	},
	1
);

register_activation_hook(
	__FILE__,
	function() {
		LinkRise\Core::instance()->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function() {
		LinkRise\Core::instance()->deactivate();
	}
);
