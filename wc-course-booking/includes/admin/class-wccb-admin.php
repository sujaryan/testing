<?php
/**
 * Admin bootstrap: menus, scripts, shared UI wiring.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Course bookings', 'wc-course-booking' ),
			__( 'Course bookings', 'wc-course-booking' ),
			'manage_woocommerce',
			'wccb-bookings',
			array( 'WCCB_Admin_Bookings', 'render_list' ),
			'dashicons-calendar-alt',
			56
		);
	}

	public static function enqueue( $hook ) {
		wp_register_style(
			'wccb-admin',
			WCCB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WCCB_VERSION
		);
		wp_register_script(
			'wccb-admin',
			WCCB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WCCB_VERSION,
			true
		);

		if ( 'post.php' === $hook || 'post-new.php' === $hook || strpos( $hook, 'wccb' ) !== false ) {
			wp_enqueue_style( 'wccb-admin' );
			wp_enqueue_script( 'wccb-admin' );
		}
	}
}
