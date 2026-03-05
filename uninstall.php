<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

global $wpdb;

$tables = array(
	$wpdb->prefix . 'linkrise_links',
	$wpdb->prefix . 'linkrise_clicks',
	$wpdb->prefix . 'linkrise_campaigns',
	$wpdb->prefix . 'linkrise_teams',
	$wpdb->prefix . 'linkrise_team_members',
	$wpdb->prefix . 'linkrise_pixels',
	$wpdb->prefix . 'linkrise_api_keys',
	$wpdb->prefix . 'linkrise_reports',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

$options = array(
	'linkrise_db_version',
	'linkrise_enterprise_schema',
	'linkrise_rate_limit',
	'linkrise_bulk_max',
	'linkrise_admin_only',
	'linkrise_countdown',
	'linkrise_tos_url',
	'linkrise_generator_url',
	'linkrise_landing_url',
	'linkrise_prefix',
	'linkrise_recaptcha_site',
	'linkrise_turnstile_site',
	'linkrise_captcha_provider',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
