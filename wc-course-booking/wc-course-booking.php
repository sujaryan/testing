<?php
/**
 * Plugin Name:       WC Course Booking
 * Plugin URI:        https://example.com/wc-course-booking
 * Description:       Calendly-style course booking for WooCommerce. Students pick an available time slot on a course product, and the booking is created on payment.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * Text Domain:       wc-course-booking
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

define( 'WCCB_VERSION', '1.0.0' );
define( 'WCCB_PLUGIN_FILE', __FILE__ );
define( 'WCCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WCCB_PLUGIN_DIR . 'includes/class-wccb-plugin.php';

register_activation_hook( __FILE__, array( 'WCCB_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCCB_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WCCB_Plugin', 'instance' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
