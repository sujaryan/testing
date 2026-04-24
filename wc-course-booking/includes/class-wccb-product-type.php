<?php
/**
 * Registers the 'course_booking' WooCommerce product type.
 *
 * A course-booking product has a duration, an instructor, a weekly availability
 * schedule, and a set of date overrides. The price on the product is charged
 * per booking.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Product_Type {

	const TYPE = 'course_booking';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_product_type_class' ) );
		add_filter( 'product_type_selector', array( __CLASS__, 'add_selector' ) );
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'map_class' ), 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta_' . self::TYPE, array( __CLASS__, 'save_meta' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( __CLASS__, 'save_meta' ) );

		// Ensure virtual-like behaviour: no shipping for course bookings.
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 10, 2 );
	}

	/**
	 * Register the WC_Product_Course_Booking class once WooCommerce has loaded.
	 */
	public static function register_product_type_class() {
		if ( ! class_exists( 'WC_Product' ) ) {
			return;
		}
		if ( class_exists( 'WC_Product_Course_Booking' ) ) {
			return;
		}
		require_once WCCB_PLUGIN_DIR . 'includes/class-wc-product-course-booking.php';
	}

	public static function add_selector( $types ) {
		$types[ self::TYPE ] = __( 'Course booking', 'wc-course-booking' );
		return $types;
	}

	public static function map_class( $classname, $product_type ) {
		if ( self::TYPE === $product_type ) {
			return 'WC_Product_Course_Booking';
		}
		return $classname;
	}

	public static function product_data_tab( $tabs ) {
		$tabs['wccb_course'] = array(
			'label'    => __( 'Course booking', 'wc-course-booking' ),
			'target'   => 'wccb_course_data',
			'class'    => array( 'show_if_course_booking' ),
			'priority' => 21,
		);
		return $tabs;
	}

	public static function product_data_panel() {
		global $post;
		$product_id = $post ? $post->ID : 0;
		$settings   = WCCB_Availability::get_settings( $product_id );
		include WCCB_PLUGIN_DIR . 'templates/admin/product-data-panel.php';
	}

	public static function save_meta( $product_id ) {
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		// Only persist when our panel was submitted.
		if ( ! isset( $_POST['wccb_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wccb_nonce'] ), 'wccb_save_course_settings' ) ) {
			return;
		}

		$duration        = isset( $_POST['wccb_duration'] ) ? absint( $_POST['wccb_duration'] ) : 60;
		$buffer          = isset( $_POST['wccb_buffer'] ) ? absint( $_POST['wccb_buffer'] ) : 0;
		$min_notice      = isset( $_POST['wccb_min_notice'] ) ? absint( $_POST['wccb_min_notice'] ) : 60;
		$window_days     = isset( $_POST['wccb_window_days'] ) ? absint( $_POST['wccb_window_days'] ) : 30;
		$capacity        = isset( $_POST['wccb_capacity'] ) ? max( 1, absint( $_POST['wccb_capacity'] ) ) : 1;
		$timezone        = isset( $_POST['wccb_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['wccb_timezone'] ) ) : wp_timezone_string();
		$instructor_id   = isset( $_POST['wccb_instructor_id'] ) ? absint( $_POST['wccb_instructor_id'] ) : 0;
		$meeting_url     = isset( $_POST['wccb_meeting_url'] ) ? esc_url_raw( wp_unslash( $_POST['wccb_meeting_url'] ) ) : '';
		$increment       = isset( $_POST['wccb_increment'] ) ? max( 5, absint( $_POST['wccb_increment'] ) ) : 30;

		$schedule_raw = isset( $_POST['wccb_schedule'] ) ? wp_unslash( $_POST['wccb_schedule'] ) : array();
		$schedule     = array();
		foreach ( array( 0, 1, 2, 3, 4, 5, 6 ) as $dow ) {
			$day                = isset( $schedule_raw[ $dow ] ) ? $schedule_raw[ $dow ] : array();
			$enabled            = ! empty( $day['enabled'] );
			$ranges             = array();
			if ( $enabled && ! empty( $day['ranges'] ) && is_array( $day['ranges'] ) ) {
				foreach ( $day['ranges'] as $range ) {
					$start = isset( $range['start'] ) ? self::sanitize_hhmm( $range['start'] ) : '';
					$end   = isset( $range['end'] ) ? self::sanitize_hhmm( $range['end'] ) : '';
					if ( $start && $end && $start < $end ) {
						$ranges[] = array(
							'start' => $start,
							'end'   => $end,
						);
					}
				}
			}
			$schedule[ $dow ] = array(
				'enabled' => $enabled,
				'ranges'  => $ranges,
			);
		}

		$overrides_raw = isset( $_POST['wccb_overrides'] ) ? wp_unslash( $_POST['wccb_overrides'] ) : array();
		$overrides     = array();
		if ( is_array( $overrides_raw ) ) {
			foreach ( $overrides_raw as $row ) {
				$date = isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '';
				if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
					continue;
				}
				$type   = isset( $row['type'] ) && 'custom' === $row['type'] ? 'custom' : 'closed';
				$ranges = array();
				if ( 'custom' === $type && ! empty( $row['ranges'] ) && is_array( $row['ranges'] ) ) {
					foreach ( $row['ranges'] as $range ) {
						$start = self::sanitize_hhmm( $range['start'] ?? '' );
						$end   = self::sanitize_hhmm( $range['end'] ?? '' );
						if ( $start && $end && $start < $end ) {
							$ranges[] = array(
								'start' => $start,
								'end'   => $end,
							);
						}
					}
				}
				$overrides[ $date ] = array(
					'type'   => $type,
					'ranges' => $ranges,
				);
			}
		}

		update_post_meta( $product_id, '_wccb_duration', $duration );
		update_post_meta( $product_id, '_wccb_buffer', $buffer );
		update_post_meta( $product_id, '_wccb_min_notice', $min_notice );
		update_post_meta( $product_id, '_wccb_window_days', $window_days );
		update_post_meta( $product_id, '_wccb_capacity', $capacity );
		update_post_meta( $product_id, '_wccb_timezone', $timezone );
		update_post_meta( $product_id, '_wccb_instructor_id', $instructor_id );
		update_post_meta( $product_id, '_wccb_meeting_url', $meeting_url );
		update_post_meta( $product_id, '_wccb_increment', $increment );
		update_post_meta( $product_id, '_wccb_schedule', $schedule );
		update_post_meta( $product_id, '_wccb_overrides', $overrides );
	}

	private static function sanitize_hhmm( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) {
			return $value;
		}
		return '';
	}

	public static function is_purchasable( $purchasable, $product ) {
		if ( $product && self::TYPE === $product->get_type() ) {
			// Price can be zero for free bookings; require at least the product to be published.
			return $product->exists() && 'publish' === $product->get_status();
		}
		return $purchasable;
	}
}
