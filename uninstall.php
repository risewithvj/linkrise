<?php
/**
 * LinkRise — Uninstall Handler
 * Removes all plugin data when the plugin is deleted from WordPress.
 *
 * @package     LinkRise
 * @author      Vijaya Kumar L
 * @developer   Vijaya Kumar L
 * @github      https://github.com/risewithvj
 * @linkedin    https://www.linkedin.com/in/vijayakumarl/
 * @copyright   2024 Vijaya Kumar L
 * @license     GPL-2.0+
 */
// This file is called by WordPress when the plugin is deleted.
// The actual cleanup is performed by linkrise_on_uninstall() in linkrise.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
