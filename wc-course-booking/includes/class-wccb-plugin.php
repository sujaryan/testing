<?php
/**
 * Main plugin bootstrapper.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

final class WCCB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WCCB_Plugin|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( ! $this->woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );
			return;
		}

		$this->includes();
		$this->init_hooks();
	}

	private function woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	public function wc_missing_notice() {
		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'WC Course Booking requires WooCommerce to be installed and active.', 'wc-course-booking' );
		echo '</p></div>';
	}

	private function includes() {
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-install.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-availability.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-booking.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-product-type.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-cart.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-order.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-ajax.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-shortcodes.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-emails.php';
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-frontend.php';
		require_once WCCB_PLUGIN_DIR . 'includes/integrations/class-wccb-google-oauth.php';
		require_once WCCB_PLUGIN_DIR . 'includes/integrations/class-wccb-google-calendar.php';

		if ( is_admin() ) {
			require_once WCCB_PLUGIN_DIR . 'includes/admin/class-wccb-admin.php';
			require_once WCCB_PLUGIN_DIR . 'includes/admin/class-wccb-admin-bookings.php';
			require_once WCCB_PLUGIN_DIR . 'includes/admin/class-wccb-admin-courses.php';
			require_once WCCB_PLUGIN_DIR . 'includes/admin/class-wccb-admin-settings.php';
		}
	}

	private function init_hooks() {
		WCCB_Product_Type::init();
		WCCB_Cart::init();
		WCCB_Order::init();
		WCCB_Ajax::init();
		WCCB_Shortcodes::init();
		WCCB_Emails::init();
		WCCB_Frontend::init();
		WCCB_Google_OAuth::init();
		WCCB_Google_Calendar::init();

		if ( is_admin() ) {
			WCCB_Admin::init();
			WCCB_Admin_Bookings::init();
			WCCB_Admin_Courses::init();
			WCCB_Admin_Settings::init();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wc-course-booking', false, dirname( WCCB_PLUGIN_BASENAME ) . '/languages' );
	}

	public static function activate() {
		require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-install.php';
		WCCB_Install::install();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
