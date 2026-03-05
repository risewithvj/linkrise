<?php
namespace LinkRiseEnterprise\Core;

use LinkRiseEnterprise\Admin\AdminMenu;
use LinkRiseEnterprise\Admin\AssetLoader;
use LinkRiseEnterprise\Database\Migrator;
use LinkRiseEnterprise\API\PublicController;
use LinkRiseEnterprise\API\LinksController;
use LinkRiseEnterprise\API\AnalyticsController;
use LinkRiseEnterprise\Frontend\Shortcodes;
use LinkRiseEnterprise\Frontend\RedirectEngine;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugin {
	private static $instance;
	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	public function boot() {
		( new Migrator() )->register();
		( new AdminMenu() )->register();
		( new AssetLoader() )->register();
		( new PublicController() )->register();
		( new LinksController() )->register();
		( new AnalyticsController() )->register();
		( new Shortcodes() )->register();
		( new RedirectEngine() )->register();
	}
}
