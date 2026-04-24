<?php
/**
 * Frontend enqueue + injection of the booking form onto the product page.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_on_product' ), 25 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'add_to_cart_text' ), 10, 2 );
	}

	public static function enqueue() {
		wp_register_style(
			'wccb-frontend',
			WCCB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WCCB_VERSION
		);
		wp_register_script(
			'wccb-frontend',
			WCCB_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			WCCB_VERSION,
			true
		);

		wp_localize_script(
			'wccb-frontend',
			'wccbData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wccb_public' ),
				'i18n'    => array(
					'pickDate'      => __( 'Pick a date to see available times', 'wc-course-booking' ),
					'noSlots'       => __( 'No available times on this day.', 'wc-course-booking' ),
					'loading'       => __( 'Loading available times…', 'wc-course-booking' ),
					'holding'       => __( 'Reserving your slot…', 'wc-course-booking' ),
					'heldFor'       => __( 'Slot held for %s minutes. Complete checkout to confirm.', 'wc-course-booking' ),
					'selectSlot'    => __( 'Please select a time slot first.', 'wc-course-booking' ),
					'spotsLeft'     => __( '%d spots left', 'wc-course-booking' ),
					'oneSpotLeft'   => __( '1 spot left', 'wc-course-booking' ),
					'months'        => array(
						__( 'January', 'wc-course-booking' ),
						__( 'February', 'wc-course-booking' ),
						__( 'March', 'wc-course-booking' ),
						__( 'April', 'wc-course-booking' ),
						__( 'May', 'wc-course-booking' ),
						__( 'June', 'wc-course-booking' ),
						__( 'July', 'wc-course-booking' ),
						__( 'August', 'wc-course-booking' ),
						__( 'September', 'wc-course-booking' ),
						__( 'October', 'wc-course-booking' ),
						__( 'November', 'wc-course-booking' ),
						__( 'December', 'wc-course-booking' ),
					),
					'weekdaysShort' => array(
						__( 'Sun', 'wc-course-booking' ),
						__( 'Mon', 'wc-course-booking' ),
						__( 'Tue', 'wc-course-booking' ),
						__( 'Wed', 'wc-course-booking' ),
						__( 'Thu', 'wc-course-booking' ),
						__( 'Fri', 'wc-course-booking' ),
						__( 'Sat', 'wc-course-booking' ),
					),
				),
				'weekStartsOn' => (int) get_option( 'start_of_week', 1 ),
			)
		);
	}

	public static function render_on_product() {
		global $product;
		if ( ! $product || WCCB_Product_Type::TYPE !== $product->get_type() ) {
			return;
		}

		wp_enqueue_style( 'wccb-frontend' );
		wp_enqueue_script( 'wccb-frontend' );

		$settings = WCCB_Availability::get_settings( $product->get_id() );
		include WCCB_PLUGIN_DIR . 'templates/booking-form.php';
	}

	public static function add_to_cart_text( $text, $product ) {
		if ( $product && WCCB_Product_Type::TYPE === $product->get_type() ) {
			return __( 'Book this course', 'wc-course-booking' );
		}
		return $text;
	}
}
