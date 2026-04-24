<?php
/**
 * Database schema & first-run install routine.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Install {

	const DB_VERSION_OPTION = 'wccb_db_version';
	const DB_VERSION        = '1.0.0';

	public static function install() {
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		// Bookings table: the authoritative record of a student-slot reservation.
		$bookings = "CREATE TABLE {$wpdb->prefix}wccb_bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NULL,
			order_item_id BIGINT UNSIGNED NULL,
			customer_id BIGINT UNSIGNED NULL,
			customer_name VARCHAR(190) NULL,
			customer_email VARCHAR(190) NULL,
			start_datetime DATETIME NOT NULL,
			end_datetime DATETIME NOT NULL,
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			meeting_url VARCHAR(500) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY course_id (course_id),
			KEY order_id (order_id),
			KEY start_datetime (start_datetime),
			KEY status (status)
		) $charset;";

		// Held slots: a short-lived hold while a student is in checkout. Prevents double-booking.
		$holds = "CREATE TABLE {$wpdb->prefix}wccb_slot_holds (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL,
			session_token VARCHAR(64) NOT NULL,
			start_datetime DATETIME NOT NULL,
			end_datetime DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY course_id (course_id),
			KEY expires_at (expires_at),
			KEY session_token (session_token)
		) $charset;";

		dbDelta( $bookings );
		dbDelta( $holds );
	}
}

add_action( 'plugins_loaded', array( 'WCCB_Install', 'maybe_upgrade' ), 20 );
