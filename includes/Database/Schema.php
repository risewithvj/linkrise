<?php
namespace LinkRiseEnterprise\Database;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Schema {
	public static function tables() {
		global $wpdb;
		return array(
			'links' => $wpdb->prefix . 'linkrise_links',
			'clicks' => $wpdb->prefix . 'linkrise_clicks',
			'campaigns' => $wpdb->prefix . 'linkrise_campaigns',
			'teams' => $wpdb->prefix . 'linkrise_teams',
			'team_members' => $wpdb->prefix . 'linkrise_team_members',
			'pixels' => $wpdb->prefix . 'linkrise_pixels',
			'api_keys' => $wpdb->prefix . 'linkrise_api_keys',
			'reports' => $wpdb->prefix . 'linkrise_reports',
		);
	}

	public static function sql() {
		$cs = $GLOBALS['wpdb']->get_charset_collate();
		$t = self::tables();
		return array(
		"CREATE TABLE {$t['campaigns']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(190) NOT NULL,slug VARCHAR(120) NOT NULL,description TEXT NULL,color VARCHAR(20) NULL,goal_clicks BIGINT UNSIGNED DEFAULT 0,start_date DATETIME NULL,end_date DATETIME NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',created_by BIGINT UNSIGNED DEFAULT 0,team_id BIGINT UNSIGNED DEFAULT 0,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY slug (slug),KEY team_id (team_id)) {$cs}",
		"CREATE TABLE {$t['teams']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(190) NOT NULL,slug VARCHAR(120) NOT NULL,plan VARCHAR(40) NOT NULL DEFAULT 'free',owner_id BIGINT UNSIGNED NOT NULL,settings LONGTEXT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY slug (slug)) {$cs}",
		"CREATE TABLE {$t['team_members']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,team_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,role VARCHAR(20) NOT NULL,invited_by BIGINT UNSIGNED DEFAULT 0,joined_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY team_user (team_id,user_id)) {$cs}",
		"CREATE TABLE {$t['pixels']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(190) NOT NULL,type VARCHAR(40) NOT NULL,pixel_id VARCHAR(190) NOT NULL,event_name VARCHAR(100) NOT NULL,fire_on VARCHAR(20) NOT NULL DEFAULT 'all',created_by BIGINT UNSIGNED DEFAULT 0,team_id BIGINT UNSIGNED DEFAULT 0,created_at DATETIME NOT NULL,PRIMARY KEY(id),KEY team_id (team_id)) {$cs}",
		"CREATE TABLE {$t['api_keys']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,team_id BIGINT UNSIGNED DEFAULT 0,name VARCHAR(120) NOT NULL,key_hash CHAR(64) NOT NULL,key_prefix VARCHAR(12) NOT NULL,scope VARCHAR(20) NOT NULL,last_used DATETIME NULL,expires_at DATETIME NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY key_hash (key_hash)) {$cs}",
		);
	}
}
