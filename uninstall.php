<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$tables = array( 'links', 'clicks', 'reports', 'analytics', 'queue', 'cache' );
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}linkrise_{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'linkrise_%'" ); // phpcs:ignore
