<?php
/**
 * AJAX endpoints for the booking UI.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Ajax {

	public static function init() {
		add_action( 'wp_ajax_wccb_get_slots', array( __CLASS__, 'get_slots' ) );
		add_action( 'wp_ajax_nopriv_wccb_get_slots', array( __CLASS__, 'get_slots' ) );

		add_action( 'wp_ajax_wccb_hold_slot', array( __CLASS__, 'hold_slot' ) );
		add_action( 'wp_ajax_nopriv_wccb_hold_slot', array( __CLASS__, 'hold_slot' ) );
	}

	private static function verify_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wccb_public' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'wc-course-booking' ) ), 403 );
		}
	}

	/**
	 * Return available slots grouped by local date for a product.
	 * Params: product_id, month (YYYY-MM) or from / to.
	 */
	public static function get_slots() {
		self::verify_nonce();

		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing course.', 'wc-course-booking' ) ), 400 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || WCCB_Product_Type::TYPE !== $product->get_type() ) {
			wp_send_json_error( array( 'message' => __( 'Not a bookable course.', 'wc-course-booking' ) ), 400 );
		}

		$settings = WCCB_Availability::get_settings( $product_id );
		$tz       = WCCB_Availability::safe_timezone( $settings['timezone'] );

		if ( ! empty( $_GET['month'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['month'] ) ) {
			$month_str = sanitize_text_field( wp_unslash( $_GET['month'] ) );
			$start     = new DateTimeImmutable( $month_str . '-01 00:00:00', $tz );
			$end       = $start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
		} else {
			$from_str = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-d' );
			$to_str   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : gmdate( 'Y-m-d', strtotime( '+30 days' ) );
			$start    = new DateTimeImmutable( $from_str . ' 00:00:00', $tz );
			$end      = new DateTimeImmutable( $to_str . ' 23:59:59', $tz );
		}

		$slots = WCCB_Availability::get_slots( $product_id, $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );

		$grouped = array();
		foreach ( $slots as $slot ) {
			$date = $slot['start']->format( 'Y-m-d' );
			if ( ! isset( $grouped[ $date ] ) ) {
				$grouped[ $date ] = array();
			}
			$grouped[ $date ][] = array(
				'start_local' => $slot['start']->format( 'H:i' ),
				'label'       => $slot['start']->format( get_option( 'time_format', 'H:i' ) ),
				'start_utc'   => $slot['start']->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'c' ),
				'end_utc'     => $slot['end']->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'c' ),
				'remaining'   => $slot['remaining'],
			);
		}

		wp_send_json_success(
			array(
				'timezone' => $settings['timezone'],
				'duration' => $settings['duration'],
				'slots'    => $grouped,
			)
		);
	}

	/**
	 * Place a temporary hold on a slot so the student can complete checkout.
	 * Params: product_id, start_utc (ISO-8601).
	 */
	public static function hold_slot() {
		self::verify_nonce();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$start_utc  = isset( $_POST['start_utc'] ) ? sanitize_text_field( wp_unslash( $_POST['start_utc'] ) ) : '';

		if ( ! $product_id || ! $start_utc ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'wc-course-booking' ) ), 400 );
		}

		try {
			$start = new DateTimeImmutable( $start_utc, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Invalid start time.', 'wc-course-booking' ) ), 400 );
		}

		$token = self::session_token();

		// Release any prior hold this session has on this course so they can change selection.
		WCCB_Availability::release_hold_by_token( $token, $product_id );

		$hold_id = WCCB_Availability::hold_slot( $product_id, $start, $token, 15 );
		if ( is_wp_error( $hold_id ) ) {
			wp_send_json_error( array( 'message' => $hold_id->get_error_message() ), 409 );
		}

		wp_send_json_success( array( 'hold_id' => $hold_id, 'expires_in' => 15 * MINUTE_IN_SECONDS ) );
	}

	/**
	 * A stable per-visitor token (from the WC session or a cookie) used to
	 * associate slot holds with a browser session.
	 */
	public static function session_token() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			$token = WC()->session->get( 'wccb_session_token' );
			if ( ! $token ) {
				$token = wp_generate_password( 32, false, false );
				WC()->session->set( 'wccb_session_token', $token );
			}
			return $token;
		}

		if ( ! empty( $_COOKIE['wccb_session_token'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['wccb_session_token'] ) );
		}

		$token = wp_generate_password( 32, false, false );
		setcookie( 'wccb_session_token', $token, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['wccb_session_token'] = $token;
		return $token;
	}
}
