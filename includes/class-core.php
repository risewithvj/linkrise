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

class Core {
	private static $instance;
	private $components = array();

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		$this->components['settings']  = new Settings();
		$this->components['database']  = new Database();
		$this->components['security']  = new Security();
		$this->components['cache']     = new Cache();
		$this->components['validator'] = new Validator();
		$this->components['queue']     = new Queue();
		$this->components['analytics'] = new Analytics();
		$this->components['redirect']  = new Redirect();
		$this->components['api']       = new Api();
		$this->components['frontend']  = new Frontend();
		$this->components['admin']     = new Admin();

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'init' ) ) {
				$component->init();
			}
		}
	}

	public function activate() {
		$db = new Database();
		$db->create_tables();
		$db->check_upgrade();
		update_option( 'linkrise_flush_needed', '1' );
		flush_rewrite_rules( false );
	}

	public function deactivate() {
		wp_clear_scheduled_hook( 'linkrise_process_queue' );
		flush_rewrite_rules( false );
	}

	public function cron_schedules( $schedules ) {
		$schedules['minute'] = array( 'interval' => 60, 'display' => 'Every Minute' );
		return $schedules;
	}
}
