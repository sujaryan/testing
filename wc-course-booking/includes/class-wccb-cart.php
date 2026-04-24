<?php
/**
 * Carries the selected slot from the product page through the cart.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Cart {

	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'restore_cart_item' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_slot_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );
	}

	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || WCCB_Product_Type::TYPE !== $product->get_type() ) {
			return $passed;
		}

		$start_utc = isset( $_POST['wccb_slot_start_utc'] ) ? sanitize_text_field( wp_unslash( $_POST['wccb_slot_start_utc'] ) ) : '';
		if ( ! $start_utc ) {
			wc_add_notice( __( 'Please pick an available time slot before booking.', 'wc-course-booking' ), 'error' );
			return false;
		}

		try {
			$start = new DateTimeImmutable( $start_utc, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			wc_add_notice( __( 'The selected time slot is invalid.', 'wc-course-booking' ), 'error' );
			return false;
		}

		if ( ! WCCB_Availability::is_slot_available( $product_id, $start ) ) {
			// Allow if this session holds it.
			$token = WCCB_Ajax::session_token();
			if ( ! self::session_holds_slot( $token, $product_id, $start ) ) {
				wc_add_notice( __( 'That time slot is no longer available. Please pick another.', 'wc-course-booking' ), 'error' );
				return false;
			}
		}

		return $passed;
	}

	private static function session_holds_slot( $token, $product_id, DateTimeImmutable $start ) {
		global $wpdb;
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wccb_slot_holds WHERE session_token = %s AND course_id = %d AND start_datetime = %s AND expires_at > UTC_TIMESTAMP()",
				$token,
				(int) $product_id,
				$start->format( 'Y-m-d H:i:s' )
			)
		);
		return (bool) $row;
	}

	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || WCCB_Product_Type::TYPE !== $product->get_type() ) {
			return $cart_item_data;
		}

		$start_utc = isset( $_POST['wccb_slot_start_utc'] ) ? sanitize_text_field( wp_unslash( $_POST['wccb_slot_start_utc'] ) ) : '';
		$end_utc   = isset( $_POST['wccb_slot_end_utc'] ) ? sanitize_text_field( wp_unslash( $_POST['wccb_slot_end_utc'] ) ) : '';
		$tz        = isset( $_POST['wccb_slot_tz'] ) ? sanitize_text_field( wp_unslash( $_POST['wccb_slot_tz'] ) ) : wp_timezone_string();

		if ( ! $start_utc ) {
			return $cart_item_data;
		}

		$cart_item_data['wccb_booking'] = array(
			'start_utc' => $start_utc,
			'end_utc'   => $end_utc,
			'timezone'  => $tz,
			// Unique key so two different slots on the same product don't merge.
			'unique_key' => md5( $product_id . '|' . $start_utc ),
		);

		return $cart_item_data;
	}

	public static function restore_cart_item( $cart_item, $values ) {
		if ( isset( $values['wccb_booking'] ) ) {
			$cart_item['wccb_booking'] = $values['wccb_booking'];
		}
		return $cart_item;
	}

	public static function display_slot_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['wccb_booking']['start_utc'] ) ) {
			return $item_data;
		}

		try {
			$tz  = WCCB_Availability::safe_timezone( $cart_item['wccb_booking']['timezone'] );
			$utc = new DateTimeImmutable( $cart_item['wccb_booking']['start_utc'], new DateTimeZone( 'UTC' ) );
			$local = $utc->setTimezone( $tz );
			$item_data[] = array(
				'name'    => __( 'Session time', 'wc-course-booking' ),
				'value'   => $local->format( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ) ) . ' (' . $cart_item['wccb_booking']['timezone'] . ')',
				'display' => '',
			);
		} catch ( Exception $e ) {
			return $item_data;
		}

		return $item_data;
	}

	public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wccb_booking']['start_utc'] ) ) {
			return;
		}
		$booking = $values['wccb_booking'];
		$item->add_meta_data( '_wccb_start_utc', $booking['start_utc'], true );
		$item->add_meta_data( '_wccb_end_utc', $booking['end_utc'], true );
		$item->add_meta_data( '_wccb_timezone', $booking['timezone'], true );

		// Also add a human-readable time for the order summary.
		try {
			$tz    = WCCB_Availability::safe_timezone( $booking['timezone'] );
			$local = ( new DateTimeImmutable( $booking['start_utc'], new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
			$item->add_meta_data(
				__( 'Session time', 'wc-course-booking' ),
				$local->format( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ) ) . ' (' . $booking['timezone'] . ')',
				true
			);
		} catch ( Exception $e ) {
			// Skip human-readable meta on parse errors.
		}
	}
}
