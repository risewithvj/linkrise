<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( class_exists( 'LinkRise_Admin' ) ) { return; }

class LinkRise_Admin {

	public static function init() {
		add_action( 'admin_menu',            array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// All AJAX actions
		$actions = array(
			'lr_save_link'          => 'ajax_save_link',
			'lr_delete_link'        => 'ajax_delete_link',
			'lr_toggle_status'      => 'ajax_toggle_status',
			'lr_get_analytics'      => 'ajax_analytics',
			'lr_get_clicks'         => 'ajax_clicks',
			'lr_bulk_delete'        => 'ajax_bulk_delete',
			'lr_bulk_expire'        => 'ajax_bulk_expire',
			'lr_bulk_status'        => 'ajax_bulk_status',
			'lr_wipe_analytics'     => 'ajax_wipe_analytics',
			'lr_dismiss_report'     => 'ajax_dismiss_report',
			'lr_delete_report_link' => 'ajax_delete_report_link',
			'lr_bulk_delete_reports'=> 'ajax_bulk_delete_reports',
			'lr_flush_rules'        => 'ajax_flush_rules',
			'lr_export_csv'         => 'ajax_export_csv',
			'lr_export_json'        => 'ajax_export_json',
			'lr_full_backup'        => 'ajax_full_backup',
			'lr_import'             => 'ajax_import',
			'lr_full_restore'       => 'ajax_full_restore',
		);
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
		}
	}

	// Guard: verifies nonce AND capability
	private static function guard() {
		check_ajax_referer( 'lr_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'Unauthorized.' ), 403 );
		}
	}

	// Guard for file-download actions (nonce in GET param)
	private static function guard_get() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized', 403 ); }
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lr_admin_nonce' ) ) { wp_die( 'Security check failed', 403 ); }
	}

	public static function add_menu() {
		add_menu_page( 'LinkRise', 'LinkRise', 'manage_options', 'linkrise', array( __CLASS__, 'page' ), 'dashicons-admin-links', 65 );
	}

	public static function enqueue( $hook ) {
		if ( 'toplevel_page_linkrise' !== $hook ) { return; }
		wp_enqueue_style(  'lr-admin', LINKRISE_URL . 'assets/css/linkrise-admin.css',  array(),           LINKRISE_VERSION );
		wp_enqueue_script( 'lr-admin', LINKRISE_URL . 'assets/js/linkrise-admin.js',    array( 'jquery' ), LINKRISE_VERSION, true );
		wp_localize_script( 'lr-admin', 'LR', array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lr_admin_nonce' ),
			'site'    => trailingslashit( site_url() ),
			'prefix'  => lr_prefix(),
			'confirm' => array(
				'del'     => 'Delete this link and all its click history? This cannot be undone.',
				'bulkDel' => 'Delete all selected links permanently?',
				'bulkReportsDel' => 'Delete all selected reports?',
				'wipe'    => 'Wipe ALL analytics data? This cannot be undone.',
				'restore' => 'Restore from backup? Duplicate shortcodes will be skipped.',
			),
		) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — LINKS
	// ═══════════════════════════════════════════════════════════════════════

	public static function ajax_save_link() {
		self::guard();
		global $wpdb;
		$table      = LinkRise_DB::lt();
		$id         = (int) ( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$long_url   = isset( $_POST['long_url'] )    ? esc_url_raw( wp_unslash( $_POST['long_url'] ) )          : '';
		$shortcode  = isset( $_POST['shortcode'] )   ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '';
		$shortcode  = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $shortcode );
		$password   = isset( $_POST['password'] )    ? sanitize_text_field( wp_unslash( $_POST['password'] ) )   : '';
		$expiry     = isset( $_POST['expiry'] )      ? sanitize_text_field( wp_unslash( $_POST['expiry'] ) )     : '';
		$category   = isset( $_POST['category'] )    ? sanitize_text_field( wp_unslash( $_POST['category'] ) )   : '';
		$notes      = isset( $_POST['notes'] )       ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) )  : '';
		$climit     = isset( $_POST['click_limit'] ) ? absint( $_POST['click_limit'] )                           : 0;
		$fallback   = isset( $_POST['fallback'] )    ? esc_url_raw( wp_unslash( $_POST['fallback'] ) )           : '';

		if ( ! LinkRise_Security::is_safe_url( $long_url ) ) {
			wp_send_json_error( array( 'msg' => 'Invalid destination URL. Must start with http:// or https://' ) );
		}
		if ( empty( $shortcode ) ) {
			wp_send_json_error( array( 'msg' => 'Shortcode is required.' ) );
		}

		$data = array(
			'long_url'     => $long_url,
			'shortcode'    => $shortcode,
			'category'     => $category,
			'notes'        => $notes,
			'click_limit'  => $climit > 0 ? $climit : null,
			'fallback_url' => $fallback ?: null,
			'expiry_date'  => ( $expiry && strtotime( $expiry ) ) ? date( 'Y-m-d H:i:s', strtotime( $expiry ) ) : null,
		);
		if ( $password !== '' ) { $data['password_hash'] = wp_hash_password( $password ); }

		if ( $id > 0 ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE shortcode = %s AND id != %d", $shortcode, $id ) ) ) {
				wp_send_json_error( array( 'msg' => 'Shortcode already used by another link.' ) );
			}
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			wp_cache_delete( 'lr_lnk_' . md5( $shortcode ), 'linkrise' );
			wp_send_json_success( array( 'msg' => 'Link updated.' ) );
		} else {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE shortcode = %s", $shortcode ) ) ) {
				wp_send_json_error( array( 'msg' => 'Shortcode already in use.' ) );
			}
			$data['created_at']    = current_time( 'mysql' );
			$data['custom_prefix'] = lr_prefix();
			$data['status']        = 'active';
			$wpdb->insert( $table, $data );
			wp_send_json_success( array( 'msg' => 'Link created!', 'id' => (int) $wpdb->insert_id ) );
		}
	}

	public static function ajax_delete_link() {
		self::guard();
		global $wpdb;
		$id = (int) ( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$sc = $wpdb->get_var( $wpdb->prepare( 'SELECT shortcode FROM ' . LinkRise_DB::lt() . ' WHERE id = %d', $id ) );
		$wpdb->delete( LinkRise_DB::lt(), array( 'id' => $id ) );
		$wpdb->delete( LinkRise_DB::ct(), array( 'link_id' => $id ) );
		if ( $sc ) { wp_cache_delete( 'lr_lnk_' . md5( $sc ), 'linkrise' ); }
		delete_transient( 'lr_analytics' );
		wp_send_json_success();
	}

	public static function ajax_toggle_status() {
		self::guard();
		global $wpdb;
		$id   = (int) ( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$link = $wpdb->get_row( $wpdb->prepare( 'SELECT id,status,shortcode FROM ' . LinkRise_DB::lt() . ' WHERE id = %d', $id ) );
		if ( ! $link ) { wp_send_json_error( array( 'msg' => 'Not found.' ) ); }
		$new = $link->status === 'active' ? 'paused' : 'active';
		$wpdb->update( LinkRise_DB::lt(), array( 'status' => $new ), array( 'id' => $id ) );
		wp_cache_delete( 'lr_lnk_' . md5( $link->shortcode ), 'linkrise' );
		wp_send_json_success( array( 'status' => $new ) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — ANALYTICS
	// ═══════════════════════════════════════════════════════════════════════

	public static function ajax_analytics() {
		self::guard();
		global $wpdb;
		$cached = get_transient( 'lr_analytics' );
		if ( $cached !== false ) { wp_send_json_success( $cached ); return; }
		$lt = LinkRise_DB::lt(); $ct = LinkRise_DB::ct();
		$data = array(
			'total_clicks'  => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$ct}" ), // phpcs:ignore
			'unique_clicks' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ip_hash) FROM {$ct} WHERE ip_hash!=''" ), // phpcs:ignore
			'today'         => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$ct} WHERE DATE(clicked_at)=CURDATE()" ), // phpcs:ignore
			'week'          => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$ct} WHERE clicked_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)" ), // phpcs:ignore
			'total_links'   => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$lt}" ), // phpcs:ignore
			'active_links'  => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$lt} WHERE status='active'" ), // phpcs:ignore
			'countries'     => $wpdb->get_results( "SELECT country,COUNT(id) AS c FROM {$ct} WHERE country!='' AND country!='Unknown' GROUP BY country ORDER BY c DESC LIMIT 10" ), // phpcs:ignore
			'devices'       => $wpdb->get_results( "SELECT device,COUNT(id) AS c FROM {$ct} WHERE device!='' GROUP BY device ORDER BY c DESC" ), // phpcs:ignore
			'browsers'      => $wpdb->get_results( "SELECT browser,COUNT(id) AS c FROM {$ct} WHERE browser!='' GROUP BY browser ORDER BY c DESC LIMIT 8" ), // phpcs:ignore
			'top_links'     => $wpdb->get_results( "SELECT l.shortcode,l.long_url,l.click_count,l.custom_prefix FROM {$lt} l ORDER BY l.click_count DESC LIMIT 10" ), // phpcs:ignore
			'daily'         => $wpdb->get_results( "SELECT DATE(clicked_at) AS d,COUNT(id) AS c FROM {$ct} WHERE clicked_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(clicked_at) ORDER BY d ASC" ), // phpcs:ignore
		);
		set_transient( 'lr_analytics', $data, 3 * MINUTE_IN_SECONDS );
		wp_send_json_success( $data );
	}

	public static function ajax_clicks() {
		self::guard();
		global $wpdb;
		$lid = (int) ( isset( $_POST['link_id'] ) ? $_POST['link_id'] : 0 );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT clicked_at,country,device,browser,os,referrer,utm_source,utm_medium,utm_campaign FROM ' . LinkRise_DB::ct() . ' WHERE link_id=%d ORDER BY clicked_at DESC LIMIT 100',
			$lid
		) );
		wp_send_json_success( array( 'clicks' => $rows ) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — BULK ACTIONS
	// ═══════════════════════════════════════════════════════════════════════

	public static function ajax_bulk_delete() {
		self::guard();
		global $wpdb;
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		if ( empty( $ids ) ) { wp_send_json_error( array( 'msg' => 'No IDs.' ) ); }
		// $ids has been sanitised via array_map('absint') above — IN clause is safe
		$pl = implode( ',', $ids );
		$wpdb->query( "DELETE FROM " . LinkRise_DB::lt() . " WHERE id IN ({$pl})" );  // phpcs:ignore
		$wpdb->query( "DELETE FROM " . LinkRise_DB::ct() . " WHERE link_id IN ({$pl})" ); // phpcs:ignore
		delete_transient( 'lr_analytics' );
		wp_send_json_success();
	}

	public static function ajax_bulk_expire() {
		self::guard();
		global $wpdb;
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		if ( empty( $ids ) ) { wp_send_json_error( array( 'msg' => 'No IDs.' ) ); }
		// $ids has been sanitised via array_map('absint') above — IN clause is safe
		$pl = implode( ',', $ids );
		$wpdb->query( "UPDATE " . LinkRise_DB::lt() . " SET expiry_date=NOW() WHERE id IN ({$pl})" ); // phpcs:ignore
		wp_send_json_success();
	}

	public static function ajax_bulk_status() {
		self::guard();
		global $wpdb;
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$new = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( empty( $ids ) || ! in_array( $new, array( 'active', 'paused' ), true ) ) {
			wp_send_json_error( array( 'msg' => 'Invalid bulk status request.' ) );
		}
		// $ids has been sanitised via array_map('absint') above — IN clause is safe
		$pl = implode( ',', $ids );
		$wpdb->query( "UPDATE " . LinkRise_DB::lt() . " SET status='" . esc_sql( $new ) . "' WHERE id IN ({$pl})" ); // phpcs:ignore
		wp_send_json_success();
	}

	public static function ajax_wipe_analytics() {
		self::guard();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . LinkRise_DB::ct() ); // phpcs:ignore
		$wpdb->query( 'UPDATE ' . LinkRise_DB::lt() . ' SET click_count=0,last_clicked=NULL' ); // phpcs:ignore
		delete_transient( 'lr_analytics' );
		wp_send_json_success();
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — REPORTS
	// ═══════════════════════════════════════════════════════════════════════

	public static function ajax_dismiss_report() {
		self::guard();
		global $wpdb;
		$wpdb->delete( LinkRise_DB::rt(), array( 'id' => (int) $_POST['id'] ) );
		wp_send_json_success();
	}

	public static function ajax_delete_report_link() {
		self::guard();
		global $wpdb;
		$id = (int) $_POST['id'];
		$sc = isset( $_POST['sc'] ) ? sanitize_text_field( wp_unslash( $_POST['sc'] ) ) : '';
		$lnk = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . LinkRise_DB::lt() . ' WHERE shortcode = %s', $sc ) );
		if ( $lnk ) {
			$wpdb->delete( LinkRise_DB::lt(), array( 'id' => $lnk->id ) );
			$wpdb->delete( LinkRise_DB::ct(), array( 'link_id' => $lnk->id ) );
			wp_cache_delete( 'lr_lnk_' . md5( $sc ), 'linkrise' );
		}
		$wpdb->delete( LinkRise_DB::rt(), array( 'id' => $id ) );
		wp_send_json_success();
	}

	public static function ajax_bulk_delete_reports() {
		self::guard();
		global $wpdb;
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		if ( empty( $ids ) ) { wp_send_json_error( array( 'msg' => 'No report IDs.' ) ); }
		$pl = implode( ',', $ids );
		$wpdb->query( 'DELETE FROM ' . LinkRise_DB::rt() . " WHERE id IN ({$pl})" ); // phpcs:ignore
		wp_send_json_success();
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — FLUSH RULES
	// ═══════════════════════════════════════════════════════════════════════

	public static function ajax_flush_rules() {
		self::guard();
		LinkRise_Frontend::add_rewrite_rules();
		flush_rewrite_rules( false );
		wp_send_json_success( array( 'msg' => 'Rewrite rules flushed. Short links should now work.' ) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// AJAX — EXPORT / IMPORT / BACKUP
	// ═══════════════════════════════════════════════════════════════════════

	public static function ajax_export_csv() {
		self::guard_get();
		global $wpdb;
		$pfx   = lr_prefix();
		$links = $wpdb->get_results( 'SELECT * FROM ' . LinkRise_DB::lt() . ' ORDER BY id DESC' ); // phpcs:ignore
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="linkrise-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Short URL', 'Destination', 'Clicks', 'Status', 'Category', 'Expiry', 'Created' ) );
		foreach ( $links as $l ) {
			fputcsv( $out, array( $l->id, site_url( '/' . ( $l->custom_prefix ?: $pfx ) . '/' . $l->shortcode ), $l->long_url, $l->click_count, $l->status, $l->category, $l->expiry_date, $l->created_at ) );
		}
		fclose( $out );
		exit;
	}

	public static function ajax_export_json() {
		self::guard_get();
		global $wpdb;
		$links = $wpdb->get_results( 'SELECT * FROM ' . LinkRise_DB::lt() . ' ORDER BY id DESC' ); // phpcs:ignore
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="linkrise-links-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'Pragma: no-cache' );
		echo wp_json_encode( array( 'version' => LINKRISE_VERSION, 'exported_at' => current_time( 'mysql' ), 'links' => $links ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function ajax_full_backup() {
		self::guard_get();
		global $wpdb;
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="linkrise-backup-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'Pragma: no-cache' );
		echo wp_json_encode( array(
			'version'     => LINKRISE_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'links'       => $wpdb->get_results( 'SELECT * FROM ' . LinkRise_DB::lt() ),   // phpcs:ignore
			'clicks'      => $wpdb->get_results( 'SELECT * FROM ' . LinkRise_DB::ct() ),   // phpcs:ignore
			'reports'     => $wpdb->get_results( 'SELECT * FROM ' . LinkRise_DB::rt() ),   // phpcs:ignore
		), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function import_links( $links ) {
		global $wpdb;
		$table = LinkRise_DB::lt();
		$ok = 0; $skip = 0;
		foreach ( (array) $links as $l ) {
			$sc = sanitize_text_field( (string) ( $l['shortcode'] ?? '' ) );
			if ( ! $sc || $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE shortcode=%s", $sc ) ) ) { $skip++; continue; }
			$wpdb->insert( $table, array(
				'shortcode'     => $sc,
				'long_url'      => esc_url_raw( (string) ( $l['long_url'] ?? '' ) ),
				'created_at'    => sanitize_text_field( (string) ( $l['created_at'] ?? current_time( 'mysql' ) ) ),
				'click_count'   => (int) ( $l['click_count'] ?? 0 ),
				'status'        => in_array( $l['status'] ?? '', array( 'active', 'paused' ), true ) ? $l['status'] : 'active',
				'category'      => sanitize_text_field( (string) ( $l['category'] ?? '' ) ),
				'custom_prefix' => sanitize_text_field( (string) ( $l['custom_prefix'] ?? lr_prefix() ) ),
				'expiry_date'   => ! empty( $l['expiry_date'] ) ? sanitize_text_field( $l['expiry_date'] ) : null,
			) );
			$ok++;
		}
		delete_transient( 'lr_analytics' );
		return array( 'imported' => $ok, 'skipped' => $skip );
	}

	public static function ajax_import() {
		self::guard();
		if ( empty( $_FILES['file']['tmp_name'] ) ) { wp_send_json_error( array( 'msg' => 'No file uploaded.' ) ); }
		$data = json_decode( file_get_contents( $_FILES['file']['tmp_name'] ), true ); // phpcs:ignore
		if ( empty( $data['links'] ) ) { wp_send_json_error( array( 'msg' => 'Invalid file format.' ) ); }
		wp_send_json_success( self::import_links( $data['links'] ) );
	}

	public static function ajax_full_restore() {
		self::guard();
		if ( empty( $_FILES['file']['tmp_name'] ) ) { wp_send_json_error( array( 'msg' => 'No file.' ) ); }
		$data = json_decode( file_get_contents( $_FILES['file']['tmp_name'] ), true ); // phpcs:ignore
		if ( empty( $data['links'] ) ) { wp_send_json_error( array( 'msg' => 'Invalid backup file.' ) ); }
		wp_send_json_success( self::import_links( $data['links'] ) );
	}

	// ═══════════════════════════════════════════════════════════════════════
	// ADMIN PAGE (pure echo, no heredoc, no PHP-in-HTML quotes)
	// ═══════════════════════════════════════════════════════════════════════

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;
		$lt = LinkRise_DB::lt();
		$rt = LinkRise_DB::rt();

		// Pagination / search (fixed 20 per page)
		$pp       = 20;
		$pg       = max( 1, (int) ( isset( $_GET['pg'] ) ? $_GET['pg'] : 1 ) );
		$off      = ( $pg - 1 ) * $pp;
		$rpg      = max( 1, (int) ( isset( $_GET['rpg'] ) ? $_GET['rpg'] : 1 ) );
		$roff     = ( $rpg - 1 ) * $pp;
		$s        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		if ( $s ) {
			$like  = '%' . $wpdb->esc_like( $s ) . '%';
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$lt} WHERE shortcode LIKE %s OR long_url LIKE %s OR category LIKE %s", $like, $like, $like ) );
			$links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$lt} WHERE shortcode LIKE %s OR long_url LIKE %s OR category LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $like, $pp, $off ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$lt}" ); // phpcs:ignore
			$links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$lt} ORDER BY id DESC LIMIT %d OFFSET %d", $pp, $off ) );
		}

		$pages    = max( 1, (int) ceil( $total / $pp ) );
		$n_rep    = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$rt}" ); // phpcs:ignore
		$rep_pages = max( 1, (int) ceil( $n_rep / $pp ) );
		$reports  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$rt} ORDER BY reported_at DESC LIMIT %d OFFSET %d", $pp, $roff ) );
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'analytics';
		$saved    = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
		$pfx      = lr_prefix();
		$site_url = trailingslashit( site_url() );

		echo '<div class="wrap lr-admin">';

		// ── Header ────────────────────────────────────────────────────────
		echo '<div class="lr-hdr">';
		echo '<div><h1>LinkRise <span class="lr-ver">v' . esc_html( LINKRISE_VERSION ) . '</span></h1>';
		echo '<p class="lr-hdr-sub">Advanced URL Shortener &amp; Analytics Platform</p></div>';
		echo '<div class="lr-hdr-status"><span class="lr-dot"></span> System Online</div>';
		echo '</div>';

		if ( $saved ) {
			echo '<div class="lr-notice-ok">✓ Settings saved successfully.</div>';
		}

		// ── Tab Nav ───────────────────────────────────────────────────────
		$tabs = array(
			'analytics' => 'Analytics',
			'links'     => 'Links <span class="lr-cnt">' . esc_html( $total ) . '</span>',
			'reports'   => 'Reports' . ( $n_rep ? ' <span class="lr-cnt lr-cnt-red">' . esc_html( $n_rep ) . '</span>' : '' ),
			'settings'  => 'Settings',
		);
		echo '<nav class="lr-nav">';
		foreach ( $tabs as $slug => $label ) {
			$cls = ( $slug === $tab ) ? 'lr-nav-btn lr-nav-active' : 'lr-nav-btn';
			echo '<button class="' . esc_attr( $cls ) . '" data-tab="' . esc_attr( $slug ) . '">' . $label . '</button>'; // phpcs:ignore — label contains only safe HTML
		}
		echo '</nav>';

		// ════════════════════════════════════════════════════════════════
		// ANALYTICS TAB
		// ════════════════════════════════════════════════════════════════
		$show = $tab === 'analytics' ? '' : ' style="display:none"';
		echo '<div id="lr-panel-analytics" class="lr-panel"' . $show . '>';

		// Stat cards
		echo '<div class="lr-stats">';
		$ico_eye    = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#0363fc" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
		$ico_users  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#8b5cf6" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>';
		$ico_today  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#f59e0b" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
		$ico_chart  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#0363fc" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
		$ico_link   = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#0363fc" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>';
		$ico_check  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
		$cards = array(
			array( 'id' => 'st-total',  'ico' => $ico_eye,   'label' => 'Total Clicks' ),
			array( 'id' => 'st-unique', 'ico' => $ico_users, 'label' => 'Unique Clicks' ),
			array( 'id' => 'st-today',  'ico' => $ico_today, 'label' => 'Today' ),
			array( 'id' => 'st-week',   'ico' => $ico_chart, 'label' => '7-Day Clicks' ),
			array( 'id' => 'st-links',  'ico' => $ico_link,  'label' => 'Total Links' ),
			array( 'id' => 'st-active', 'ico' => $ico_check, 'label' => 'Active Links' ),
		);
		foreach ( $cards as $c ) {
			echo '<div class="lr-stat">';
			$allowed_svg = array(
				'svg'      => array( 'xmlns'=>true,'width'=>true,'height'=>true,'fill'=>true,'viewBox'=>true,'stroke'=>true,'stroke-width'=>true ),
				'path'     => array( 'stroke-linecap'=>true,'stroke-linejoin'=>true,'d'=>true,'stroke'=>true,'fill'=>true ),
				'polyline' => array( 'points'=>true,'stroke'=>true,'fill'=>true ),
				'line'     => array( 'x1'=>true,'y1'=>true,'x2'=>true,'y2'=>true,'stroke'=>true ),
				'rect'     => array( 'x'=>true,'y'=>true,'width'=>true,'height'=>true,'rx'=>true,'ry'=>true,'stroke'=>true,'fill'=>true ),
				'circle'   => array( 'cx'=>true,'cy'=>true,'r'=>true,'stroke'=>true,'fill'=>true ),
			);
			echo '<span class="lr-stat-ico">' . wp_kses( $c['ico'], $allowed_svg ) . '</span>';
			echo '<div><div class="lr-stat-lbl">' . esc_html( $c['label'] ) . '</div>';
			echo '<div class="lr-stat-val" id="' . esc_attr( $c['id'] ) . '">—</div></div>';
			echo '</div>';
		}
		echo '</div>';

		// Charts row
		echo '<div class="lr-chart-row">';
		echo '<div class="lr-chart-box lr-chart-wide"><h3>Clicks — Last 30 Days</h3><canvas id="lr-daily-chart" height="90"></canvas></div>';
		echo '<div class="lr-chart-box"><h3>Countries</h3><div id="lr-countries" class="lr-bars"><p class="lr-loading">Loading…</p></div></div>';
		echo '<div class="lr-chart-box"><h3>Devices</h3><div id="lr-devices" class="lr-bars"><p class="lr-loading">Loading…</p></div></div>';
		echo '</div>';

		// Top links table
		echo '<div class="lr-chart-box" style="margin-top:16px">';
		echo '<div class="lr-flex-between"><h3>Top Links</h3>';
		echo '<button id="btn-wipe" class="lr-btn-danger-sm">Wipe Analytics</button></div>';
		echo '<div class="lr-tbl-wrap"><table class="lr-tbl" id="lr-top-tbl"><thead><tr><th>Short URL</th><th>Destination</th><th>Clicks</th></tr></thead><tbody><tr><td colspan="3" class="lr-cell-load">Loading…</td></tr></tbody></table></div>';
		echo '</div>';
		echo '</div>'; // #lr-panel-analytics

		// ════════════════════════════════════════════════════════════════
		// LINKS TAB
		// ════════════════════════════════════════════════════════════════
		$show = $tab === 'links' ? '' : ' style="display:none"';
		echo '<div id="lr-panel-links" class="lr-panel"' . $show . '>';

		// Toolbar
		echo '<div class="lr-toolbar">';
		echo '<button id="btn-add" class="lr-btn-primary">Add New Link</button>';

		// Search form
		echo '<form method="get" class="lr-search-form">';
		echo '<input type="hidden" name="page" value="linkrise">';
		echo '<input type="hidden" name="tab" value="links">';
		echo '<input class="lr-search-inp" type="text" name="s" value="' . esc_attr( $s ) . '" placeholder="Search links, URLs…">';
		echo '<button type="submit" class="lr-btn-secondary">Search</button>';
		if ( $s ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=linkrise&tab=links' ) ) . '" class="lr-btn-outline">Clear</a>';
		}
		echo '</form>';

		// Bulk bar
		echo '<div class="lr-bulk-bar" id="lr-bulk-bar" style="display:none">';
		echo '<span id="lr-bulk-cnt">0 selected</span> ';
		echo '<button id="btn-bulk-del" class="lr-btn-danger-sm">Bulk Delete</button> ';
		echo '<button id="btn-bulk-exp" class="lr-btn-secondary-sm">Set Expired</button>';
		echo '<button id="btn-bulk-pause" class="lr-btn-secondary-sm">Set Paused</button>';
		echo '<button id="btn-bulk-activate" class="lr-btn-secondary-sm">Set Active</button>';
		echo '</div>';
		echo '</div>'; // .lr-toolbar

		// Links table
		echo '<div class="lr-tbl-wrap">';
		echo '<table class="lr-tbl lr-links-tbl"><thead><tr>';
		echo '<th><input type="checkbox" id="lr-chk-all"></th>';
		echo '<th>Short URL</th><th>Destination</th><th>Clicks</th><th>Status</th><th>Category</th><th>Expiry</th><th>Actions</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $links ) ) {
			echo '<tr><td colspan="8" class="lr-cell-empty">No links yet. Click <strong>Add New Link</strong> to get started.</td></tr>';
		} else {
			foreach ( $links as $l ) {
				$short   = $site_url . ( $l->custom_prefix ?: $pfx ) . '/' . $l->shortcode;
				$expired = $l->expiry_date && strtotime( $l->expiry_date ) < time();
				$status  = $expired ? 'expired' : $l->status;
				$row_cls = $expired ? ' class="lr-row-dim"' : '';

				// Encode all link data as JSON in ONE attribute to avoid mixed-quote nightmares
				$ldata = wp_json_encode( array(
					'id'     => (int) $l->id,
					'code'   => $l->shortcode,
					'url'    => $l->long_url,
					'expiry' => $l->expiry_date ? substr( $l->expiry_date, 0, 16 ) : '',
					'cat'    => (string) $l->category,
					'notes'  => (string) $l->notes,
					'limit'  => (int) $l->click_limit,
					'fb'     => (string) $l->fallback_url,
					'hasPw'  => ! empty( $l->password_hash ),
				) );

				echo '<tr data-id="' . esc_attr( $l->id ) . '"' . $row_cls . '>';
				echo '<td><input type="checkbox" class="lr-row-chk" value="' . esc_attr( $l->id ) . '"></td>';

				// Short URL cell
				echo '<td class="lr-cell-short">';
				echo '<a href="' . esc_url( $short ) . '" target="_blank" class="lr-link">' . esc_html( ( $l->custom_prefix ?: $pfx ) . '/' . $l->shortcode ) . '</a>';
				echo '<div class="lr-mini-acts">';
				echo '<button class="lr-ico-btn lr-copy-btn" data-url="' . esc_attr( $short ) . '" title="Copy">Copy</button>';
				echo '<button class="lr-ico-btn lr-qr-btn" data-url="' . esc_attr( $short ) . '" title="QR Code">QR</button>';
				echo '</div></td>';

				// Destination
				echo '<td class="lr-cell-dest" title="' . esc_attr( $l->long_url ) . '">' . esc_html( mb_strimwidth( $l->long_url, 0, 50, '…' ) ) . '</td>';

				// Clicks
				echo '<td><strong>' . esc_html( number_format( (int) $l->click_count ) ) . '</strong> ';
				echo '<button class="lr-ico-btn lr-hist-btn" data-id="' . esc_attr( $l->id ) . '" title="View Clicks">View</button></td>';

				// Status pill
				echo '<td><span class="lr-pill lr-pill-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span></td>';

				echo '<td>' . ( $l->category ? esc_html( $l->category ) : '<span class="lr-muted">—</span>' ) . '</td>';
				echo '<td>' . ( $l->expiry_date ? '<span' . ( $expired ? ' class="lr-text-red"' : '' ) . '>' . esc_html( gmdate( 'M j, Y', strtotime( $l->expiry_date ) ) ) . '</span>' : '<span class="lr-muted">—</span>' ) . '</td>';

				// Actions — use data-ldata to pass all link data safely as JSON
				echo '<td class="lr-cell-acts">';
				echo '<button class="lr-btn-sm lr-btn-outline lr-edit-btn" data-ldata=\'' . esc_attr( $ldata ) . '\'>Edit</button>';
				echo '<button class="lr-btn-sm lr-btn-secondary lr-toggle-btn" data-id="' . esc_attr( $l->id ) . '" data-status="' . esc_attr( $l->status ) . '">' . ( $l->status === 'active' ? 'Pause' : 'Resume' ) . '</button>';
				echo '<button class="lr-btn-sm lr-btn-danger lr-del-btn" data-id="' . esc_attr( $l->id ) . '">Delete</button>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>'; // .lr-tbl-wrap

		// Pagination
		if ( $pages > 1 ) {
			echo '<div class="lr-pages">';
			for ( $p = 1; $p <= $pages; $p++ ) {
				$url = add_query_arg( array( 'page' => 'linkrise', 'tab' => 'links', 'pg' => $p, 's' => $s ), admin_url( 'admin.php' ) );
				echo '<a href="' . esc_url( $url ) . '" class="lr-pgbtn' . ( $p === $pg ? ' lr-pgbtn-a' : '' ) . '">' . esc_html( $p ) . '</a>';
			}
			echo '</div>';
		}

		// Export row
		echo '<div class="lr-export-row">';
		echo '<span class="lr-tools-label">Tools</span>';
		echo '<a href="#" id="btn-csv" class="lr-btn-outline lr-btn-sm">Export CSV</a>';
		echo '<a href="#" id="btn-json" class="lr-btn-outline lr-btn-sm">Export JSON</a>';
		echo '<label class="lr-btn-outline lr-btn-sm">Import <input type="file" id="import-file" accept=".json" style="display:none"></label>';
		echo '<a href="#" id="btn-backup" class="lr-btn-outline lr-btn-sm">Backup</a>';
		echo '<label class="lr-btn-outline lr-btn-sm">Restore <input type="file" id="restore-file" accept=".json" style="display:none"></label>';
		echo '</div>';

		echo '</div>'; // #lr-panel-links

		// ════════════════════════════════════════════════════════════════
		// REPORTS TAB
		// ════════════════════════════════════════════════════════════════
		$show = $tab === 'reports' ? '' : ' style="display:none"';
		echo '<div id="lr-panel-reports" class="lr-panel"' . $show . '>';
		echo '<div class="lr-bulk-bar" id="lr-rpt-bulk-bar" style="display:none"><span id="lr-rpt-bulk-cnt">0 selected</span> <button id="btn-rpt-bulk-del" class="lr-btn-danger-sm">Bulk Delete</button></div>';
		if ( empty( $reports ) ) {
			echo '<div class="lr-empty-state">No abuse reports.</div>';
		} else {
			echo '<div class="lr-tbl-wrap"><table class="lr-tbl"><thead><tr>';
			echo '<th><input type="checkbox" id="lr-rpt-chk-all"></th><th>Date</th><th>Code</th><th>Reason</th><th>Details</th><th>Reporter IP</th><th>Actions</th>';
			echo '</tr></thead><tbody>';
			foreach ( $reports as $r ) {
				echo '<tr id="rpt-' . esc_attr( $r->id ) . '">';
				echo '<td><input type="checkbox" class="lr-rpt-row-chk" value="' . esc_attr( $r->id ) . '"></td>';
				echo '<td>' . esc_html( gmdate( 'M j, Y H:i', strtotime( $r->reported_at ) ) ) . '</td>';
				echo '<td><a href="' . esc_url( $site_url . $pfx . '/' . $r->shortcode ) . '" target="_blank">' . esc_html( $r->shortcode ) . '</a></td>';
				echo '<td>' . esc_html( $r->reason ) . '</td>';
				echo '<td>' . esc_html( mb_strimwidth( (string) $r->details, 0, 80, '…' ) ) . '</td>';
				echo '<td>' . esc_html( $r->reporter_ip ?: '—' ) . '</td>';
				echo '<td>';
				echo '<button class="lr-btn-sm lr-btn-outline lr-dismiss-btn" data-id="' . esc_attr( $r->id ) . '">Dismiss</button> ';
				echo '<button class="lr-btn-sm lr-btn-danger lr-del-report-btn" data-id="' . esc_attr( $r->id ) . '" data-sc="' . esc_attr( $r->shortcode ) . '">Delete Link</button>';
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		if ( $rep_pages > 1 ) {
			echo '<div class="lr-pages">';
			for ( $rp = 1; $rp <= $rep_pages; $rp++ ) {
				$url = add_query_arg( array( 'page' => 'linkrise', 'tab' => 'reports', 'rpg' => $rp ), admin_url( 'admin.php' ) );
				echo '<a href="' . esc_url( $url ) . '" class="lr-pgbtn' . ( $rp === $rpg ? ' lr-pgbtn-a' : '' ) . '">' . esc_html( $rp ) . '</a>';
			}
			echo '</div>';
		}
		echo '</div>'; // #lr-panel-reports

		// ════════════════════════════════════════════════════════════════
		// SETTINGS TAB
		// ════════════════════════════════════════════════════════════════
		$show = $tab === 'settings' ? '' : ' style="display:none"';
		echo '<div id="lr-panel-settings" class="lr-panel"' . $show . '>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="lr-settings">';
		echo '<input type="hidden" name="action" value="linkrise_save_settings">';
		wp_nonce_field( 'linkrise_settings_nonce' );

		self::settings_section( 'Core', array(
			array( 'text',     'linkrise_redirect_prefix', 'Redirect Prefix',     'go → yoursite.com/go/abc' ),
			array( 'url',      'linkrise_landing_url',     'Landing Page URL',    'URL of page with [linkrise_landing] shortcode' ),
			array( 'url',      'linkrise_fallback_url',    'Fallback URL',        'Where to redirect invalid/expired links' ),
			array( 'number',   'linkrise_rate_limit',      'Rate Limit',          'Max links per IP/hour (0 = unlimited)' ),
			array( 'number',   'linkrise_bulk_max',        'Bulk Max',            'Max URLs per bulk request' ),
			array( 'number',   'linkrise_countdown',       'Countdown Seconds',   'Landing page redirect delay' ),
			array( 'url',      'linkrise_tos_url',         'Terms of Service URL','Enables TOS checkbox on generator' ),
			array( 'checkbox', 'linkrise_admin_only',      'Admin Only Mode',     'Only admins can create links' ),
		) );

		self::settings_section( 'CAPTCHA', array(
			array( 'select', 'linkrise_captcha_provider', 'Provider', '', array( 'disabled' => 'Disabled', 'recaptcha' => 'Google reCAPTCHA v3', 'turnstile' => 'Cloudflare Turnstile' ) ),
			array( 'text',   'linkrise_recaptcha_site',   'reCAPTCHA Site Key',    '' ),
			array( 'pw',     'linkrise_recaptcha_secret', 'reCAPTCHA Secret Key',  '' ),
			array( 'text',   'linkrise_turnstile_site',   'Turnstile Site Key',    '' ),
			array( 'pw',     'linkrise_turnstile_secret', 'Turnstile Secret Key',  '' ),
		) );

		self::settings_section( 'Security', array(
			array( 'pw',   'linkrise_safe_browsing_key', 'Google Safe Browsing API Key', 'Blocks malware/phishing URLs' ),
			array( 'text', 'linkrise_trusted_proxies',   'Trusted Proxy IPs',            'Comma-separated (for CDN / load balancer)' ),
		) );

		self::settings_section( 'Analytics', array(
			array( 'text', 'linkrise_ga4_id',     'GA4 Measurement ID', 'e.g. G-XXXXXXXXXX' ),
			array( 'pw',   'linkrise_ga4_secret', 'GA4 API Secret',     'From GA4 → Data Streams → Measurement Protocol' ),
			array( 'text', 'linkrise_gtm_id',     'GTM Container ID',   'e.g. GTM-XXXXXXX' ),
		) );

		echo '<div class="lr-settings-actions">';
		echo '<button type="submit" class="lr-btn-primary lr-btn-lg">Save Settings</button>';
		echo '<button type="button" id="btn-flush" class="lr-btn-outline lr-btn-lg">Flush Rewrite Rules</button>';
		echo '</div>';
		echo '</form>';
		echo '</div>'; // #lr-panel-settings

		// ════════════════════════════════════════════════════════════════
		// MODALS
		// ════════════════════════════════════════════════════════════════

		// Add/Edit Link
		echo '<div id="lr-modal-link" class="lr-modal" style="display:none" role="dialog">';
		echo '<div class="lr-modal-box">';
		echo '<div class="lr-modal-hd"><h2 id="lr-modal-ttl">Add New Link</h2><button class="lr-modal-cls" data-modal="lr-modal-link">Close</button></div>';
		echo '<div class="lr-modal-bd">';
		echo '<input type="hidden" id="m-id" value="0">';
		self::mfield( 'text',     'm-url',    'Destination URL *', 'https://example.com/your-long-url' );
		self::mfield( 'text',     'm-code',   'Custom Code', 'Optional — auto-generated if blank' );
		self::mfield( 'pw-tog',   'm-pw',     'Password', 'Leave blank to keep existing password' );
		self::mfield( 'text',     'm-cat',    'Category', 'e.g. social, marketing' );
		self::mfield( 'textarea', 'm-notes',  'Notes', 'Internal notes…' );
		self::mfield( 'datetime-local', 'm-expiry', 'Expiry', '' );
		self::mfield( 'number',   'm-limit',  'Click Limit (0 = unlimited)', '0' );
		self::mfield( 'url',      'm-fb',     'Fallback URL', 'https://yoursite.com/expired' );
		echo '</div>';
		echo '<div class="lr-modal-ft"><button id="btn-modal-save" class="lr-btn-primary">Save Link</button> <button class="lr-btn-outline lr-modal-cls" data-modal="lr-modal-link">Cancel</button></div>';
		echo '</div></div>';

		// Click History
		echo '<div id="lr-modal-clicks" class="lr-modal" style="display:none">';
		echo '<div class="lr-modal-box lr-modal-wide">';
		echo '<div class="lr-modal-hd"><h2>Click History</h2><button class="lr-modal-cls" data-modal="lr-modal-clicks">Close</button></div>';
		echo '<div class="lr-modal-bd" id="lr-clicks-bd"><p class="lr-loading">Loading…</p></div>';
		echo '</div></div>';

		// QR Code
		echo '<div id="lr-modal-qr" class="lr-modal" style="display:none">';
		echo '<div class="lr-modal-box lr-modal-sm" style="text-align:center">';
		echo '<div class="lr-modal-hd"><h2>QR Code</h2><button class="lr-modal-cls" data-modal="lr-modal-qr">Close</button></div>';
		echo '<div class="lr-modal-bd" style="text-align:center">';
		echo '<img id="lr-qr-img" src="" alt="QR Code" style="width:220px;height:220px;border-radius:8px;border:1px solid #e2e8f0;display:block;margin:0 auto">';
		echo '<div style="margin-top:14px">';
		echo '<button id="lr-qr-dl" type="button" class="lr-btn-primary">Download PNG</button>';
		echo '</div></div>';
		echo '</div></div>';

		echo '</div>'; // .wrap.lr-admin
	}

	// ── Settings section renderer ──────────────────────────────────────────

	private static function settings_section( $title, $fields ) {
		echo '<div class="lr-settings-card">';
		echo '<h3 class="lr-settings-ttl">' . esc_html( $title ) . '</h3>';
		foreach ( $fields as $f ) {
			$type = $f[0]; $name = $f[1]; $label = $f[2];
			$hint = isset( $f[3] ) ? $f[3] : '';
			$opts = isset( $f[4] ) ? $f[4] : array();
			$val  = (string) get_option( $name, '' );

			echo '<div class="lr-field-row">';
			echo '<label class="lr-field-lbl">' . esc_html( $label );
			if ( $hint ) { echo '<span class="lr-hint"> — ' . esc_html( $hint ) . '</span>'; }
			echo '</label>';

			if ( 'checkbox' === $type ) {
				echo '<label class="lr-toggle"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $val, '1', false ) . '><span class="lr-toggle-knob"></span></label>';
			} elseif ( 'select' === $type ) {
				echo '<select name="' . esc_attr( $name ) . '" class="lr-input lr-input-sm">';
				foreach ( $opts as $v => $l ) {
					echo '<option value="' . esc_attr( $v ) . '"' . selected( $val, $v, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'pw' === $type ) {
				echo '<input type="password" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="lr-input" autocomplete="new-password">';
			} else {
				$itype = ( $type === 'url' ) ? 'url' : ( $type === 'number' ? 'number' : 'text' );
				echo '<input type="' . esc_attr( $itype ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="lr-input">';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	// ── Modal field renderer ──────────────────────────────────────────────

	private static function mfield( $type, $id, $label, $placeholder ) {
		echo '<div class="lr-mfield">';
		echo '<label class="lr-field-lbl" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" class="lr-input" rows="3" placeholder="' . esc_attr( $placeholder ) . '"></textarea>';
		} elseif ( 'pw-tog' === $type ) {
			echo '<div class="lr-pw-row">';
			echo '<input type="password" id="' . esc_attr( $id ) . '" class="lr-input lr-pw-inp" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="new-password">';
			echo '<button type="button" class="lr-btn-sm lr-btn-outline lr-pw-tog">Show</button>';
			echo '</div>';
		} else {
			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" class="lr-input" placeholder="' . esc_attr( $placeholder ) . '">';
		}
		echo '</div>';
	}
}
