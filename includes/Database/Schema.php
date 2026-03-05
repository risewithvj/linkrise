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
		"CREATE TABLE {$t['links']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,shortcode VARCHAR(120) NOT NULL,long_url TEXT NOT NULL,title VARCHAR(190) NULL,fallback_url TEXT NULL,expiry_date DATETIME NULL,start_date DATETIME NULL,password_hash VARCHAR(255) NULL,created_at DATETIME NOT NULL,updated_at DATETIME NULL,click_count BIGINT UNSIGNED NOT NULL DEFAULT 0,unique_clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,click_limit BIGINT UNSIGNED DEFAULT 0,click_goal BIGINT UNSIGNED DEFAULT 0,last_clicked DATETIME NULL,category VARCHAR(100) NULL,tags VARCHAR(255) NULL,notes TEXT NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',custom_prefix VARCHAR(60) NULL,rotation_urls LONGTEXT NULL,utm_params LONGTEXT NULL,webhook_url TEXT NULL,metadata LONGTEXT NULL,created_by BIGINT UNSIGNED DEFAULT 0,team_id BIGINT UNSIGNED DEFAULT 0,campaign_id BIGINT UNSIGNED DEFAULT 0,is_public TINYINT(1) NOT NULL DEFAULT 0,PRIMARY KEY(id),UNIQUE KEY shortcode (shortcode),KEY status (status),KEY created_by (created_by),KEY campaign_id (campaign_id),KEY team_id (team_id),KEY created_at (created_at)) {$cs}",
		"CREATE TABLE {$t['clicks']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,link_id BIGINT UNSIGNED NOT NULL,shortcode VARCHAR(120) NOT NULL,clicked_at DATETIME NOT NULL,ip_hash CHAR(32) NOT NULL,country VARCHAR(100) NULL,country_code VARCHAR(10) NULL,city VARCHAR(100) NULL,region VARCHAR(100) NULL,device VARCHAR(40) NULL,browser VARCHAR(80) NULL,os VARCHAR(80) NULL,referrer TEXT NULL,referrer_domain VARCHAR(190) NULL,utm_source VARCHAR(120) NULL,utm_medium VARCHAR(120) NULL,utm_campaign VARCHAR(120) NULL,utm_term VARCHAR(120) NULL,utm_content VARCHAR(120) NULL,language VARCHAR(10) NULL,session_id VARCHAR(64) NULL,is_unique TINYINT(1) NOT NULL DEFAULT 0,is_bot TINYINT(1) NOT NULL DEFAULT 0,PRIMARY KEY(id),KEY link_id (link_id),KEY shortcode (shortcode),KEY clicked_at (clicked_at),KEY country_code (country_code),KEY is_bot (is_bot)) {$cs}",
		"CREATE TABLE {$t['campaigns']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(190) NOT NULL,slug VARCHAR(120) NOT NULL,description TEXT NULL,color VARCHAR(20) NULL,goal_clicks BIGINT UNSIGNED DEFAULT 0,start_date DATETIME NULL,end_date DATETIME NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',created_by BIGINT UNSIGNED DEFAULT 0,team_id BIGINT UNSIGNED DEFAULT 0,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY slug (slug),KEY team_id (team_id)) {$cs}",
		"CREATE TABLE {$t['teams']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(190) NOT NULL,slug VARCHAR(120) NOT NULL,plan VARCHAR(40) NOT NULL DEFAULT 'free',owner_id BIGINT UNSIGNED NOT NULL,settings LONGTEXT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY slug (slug)) {$cs}",
		"CREATE TABLE {$t['team_members']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,team_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,role VARCHAR(20) NOT NULL,invited_by BIGINT UNSIGNED DEFAULT 0,joined_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY team_user (team_id,user_id)) {$cs}",
		"CREATE TABLE {$t['pixels']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,name VARCHAR(190) NOT NULL,type VARCHAR(40) NOT NULL,pixel_id VARCHAR(190) NOT NULL,event_name VARCHAR(100) NOT NULL,fire_on VARCHAR(20) NOT NULL DEFAULT 'all',created_by BIGINT UNSIGNED DEFAULT 0,team_id BIGINT UNSIGNED DEFAULT 0,created_at DATETIME NOT NULL,PRIMARY KEY(id),KEY team_id (team_id)) {$cs}",
		"CREATE TABLE {$t['api_keys']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,team_id BIGINT UNSIGNED DEFAULT 0,name VARCHAR(120) NOT NULL,key_hash CHAR(64) NOT NULL,key_prefix VARCHAR(12) NOT NULL,scope VARCHAR(20) NOT NULL,last_used DATETIME NULL,expires_at DATETIME NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY key_hash (key_hash)) {$cs}",
		"CREATE TABLE {$t['reports']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,shortcode VARCHAR(120) NOT NULL,reported_url TEXT NOT NULL,reason VARCHAR(100) NOT NULL,details TEXT NULL,reporter_ip VARCHAR(64) NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',reviewed_by BIGINT UNSIGNED DEFAULT 0,reviewed_at DATETIME NULL,reported_at DATETIME NOT NULL,PRIMARY KEY(id),KEY shortcode (shortcode),KEY status (status)) {$cs}",
		);
	}
}
