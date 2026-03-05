<?php
namespace LinkRiseEnterprise\Core;
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface ServiceProvider {
	public function register();
}
