<?php
/**
 * Turns paid order line items into persistent bookings, and keeps booking
 * status in sync with order status.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Order {

	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'create_bookings_from_order' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'create_bookings_from_order_block' ), 20 );

		// Confirm on payment completion.
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'confirm_bookings' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'confirm_bookings' ), 10, 2 );

		// Cancel bookings if order is cancelled or refunded.
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancel_bookings' ), 10, 2 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'cancel_bookings' ), 10, 2 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'cancel_bookings' ), 10, 2 );
	}

	public static function create_bookings_from_order( $order_id, $posted_data, $order ) {
		self::create_for_order( $order );
	}

	public static function create_bookings_from_order_block( $order ) {
		self::create_for_order( $order );
	}

	private static function create_for_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order ) {
			return;
		}

		// Don't double-create.
		if ( $order->get_meta( '_wccb_bookings_created', true ) ) {
			return;
		}

		$created = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || WCCB_Product_Type::TYPE !== $product->get_type() ) {
				continue;
			}

			$start_utc = $item->get_meta( '_wccb_start_utc', true );
			$end_utc   = $item->get_meta( '_wccb_end_utc', true );
			$tz        = $item->get_meta( '_wccb_timezone', true ) ?: wp_timezone_string();

			if ( ! $start_utc || ! $end_utc ) {
				continue;
			}

			$settings = WCCB_Availability::get_settings( $product->get_id() );

			$booking_id = WCCB_Booking::create(
				array(
					'course_id'      => $product->get_id(),
					'order_id'       => $order->get_id(),
					'order_item_id'  => $item_id,
					'customer_id'    => $order->get_customer_id(),
					'customer_name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'customer_email' => $order->get_billing_email(),
					'start_datetime' => ( new DateTimeImmutable( $start_utc, new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' ),
					'end_datetime'   => ( new DateTimeImmutable( $end_utc, new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' ),
					'timezone'       => $tz,
					'status'         => WCCB_Booking::STATUS_PENDING,
					'meeting_url'    => $settings['meeting_url'],
				)
			);

			if ( ! is_wp_error( $booking_id ) ) {
				$item->add_meta_data( '_wccb_booking_id', $booking_id, true );
				$item->save();
				$created = true;
			}
		}

		if ( $created ) {
			$order->update_meta_data( '_wccb_bookings_created', 1 );
			$order->save();

			// Release the session holds — the booking rows now claim the slot.
			$token = WCCB_Ajax::session_token();
			WCCB_Availability::release_hold_by_token( $token );
		}
	}

	public static function confirm_bookings( $order_id, $order = null ) {
		$bookings = WCCB_Booking::get_by_order( $order_id );
		foreach ( $bookings as $booking ) {
			if ( $booking->status === WCCB_Booking::STATUS_PENDING ) {
				WCCB_Booking::update_status( $booking->id, WCCB_Booking::STATUS_CONFIRMED );
				do_action( 'wccb_booking_confirmed', $booking->id, $booking );
			}
		}
	}

	public static function cancel_bookings( $order_id, $order = null ) {
		$bookings = WCCB_Booking::get_by_order( $order_id );
		foreach ( $bookings as $booking ) {
			if ( in_array( $booking->status, array( WCCB_Booking::STATUS_PENDING, WCCB_Booking::STATUS_CONFIRMED ), true ) ) {
				WCCB_Booking::update_status( $booking->id, WCCB_Booking::STATUS_CANCELLED );
				do_action( 'wccb_booking_cancelled', $booking->id, $booking );
			}
		}
	}
}
