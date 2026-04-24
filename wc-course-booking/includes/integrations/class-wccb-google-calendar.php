<?php
/**
 * Pushes booking lifecycle events to Google Calendar.
 *
 * On wccb_booking_confirmed we create the event and store its ID.
 * On wccb_booking_cancelled we delete the event.
 * Per-course calendar ID overrides the site default; falls back to 'primary'.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Google_Calendar {

	const API_BASE = 'https://www.googleapis.com/calendar/v3';

	public static function init() {
		add_action( 'wccb_booking_confirmed', array( __CLASS__, 'on_confirmed' ), 20, 2 );
		add_action( 'wccb_booking_cancelled', array( __CLASS__, 'on_cancelled' ), 20, 2 );
	}

	public static function on_confirmed( $booking_id, $booking ) {
		if ( ! WCCB_Google_OAuth::is_connected() ) {
			return;
		}
		// Idempotent: skip if already synced.
		$existing = self::get_event_id( $booking_id );
		if ( $existing ) {
			return;
		}

		$token = WCCB_Google_OAuth::get_access_token();
		if ( is_wp_error( $token ) ) {
			self::log_error( 'auth', $token );
			return;
		}

		$calendar_id = self::calendar_id_for_course( (int) $booking->course_id );
		$product     = wc_get_product( $booking->course_id );

		$body = self::build_event_body( $booking, $product );

		$settings = WCCB_Google_OAuth::get_settings();
		$query    = ! empty( $settings['send_invites'] ) ? '?sendUpdates=all' : '';

		$response = wp_remote_post(
			self::API_BASE . '/calendars/' . rawurlencode( $calendar_id ) . '/events' . $query,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_error( 'create', $response );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $data['id'] ) ) {
			self::log_error( 'create', new WP_Error( 'http_' . $code, wp_remote_retrieve_body( $response ) ) );
			return;
		}

		self::save_event_id( $booking_id, $data['id'], $calendar_id );
	}

	public static function on_cancelled( $booking_id, $booking ) {
		if ( ! WCCB_Google_OAuth::is_connected() ) {
			return;
		}
		$event_id = self::get_event_id( $booking_id );
		if ( ! $event_id ) {
			return;
		}
		$token = WCCB_Google_OAuth::get_access_token();
		if ( is_wp_error( $token ) ) {
			self::log_error( 'auth', $token );
			return;
		}

		$calendar_id = self::get_event_calendar_id( $booking_id ) ?: self::calendar_id_for_course( (int) $booking->course_id );
		$settings    = WCCB_Google_OAuth::get_settings();
		$query       = ! empty( $settings['send_invites'] ) ? '?sendUpdates=all' : '';

		$response = wp_remote_request(
			self::API_BASE . '/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id ) . $query,
			array(
				'timeout' => 15,
				'method'  => 'DELETE',
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::log_error( 'delete', $response );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			self::clear_event_id( $booking_id );
		} elseif ( 404 === $code || 410 === $code ) {
			// Event already gone; drop our reference.
			self::clear_event_id( $booking_id );
		} else {
			self::log_error( 'delete', new WP_Error( 'http_' . $code, wp_remote_retrieve_body( $response ) ) );
		}
	}

	private static function build_event_body( $booking, $product ) {
		$tz = $booking->timezone ?: 'UTC';

		try {
			$start_utc = new DateTimeImmutable( $booking->start_datetime, new DateTimeZone( 'UTC' ) );
			$end_utc   = new DateTimeImmutable( $booking->end_datetime, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return array();
		}

		$summary = $product ? sprintf( /* translators: %s: course name */ __( 'Course booking: %s', 'wc-course-booking' ), $product->get_name() ) : __( 'Course booking', 'wc-course-booking' );

		$description_lines = array();
		if ( $booking->customer_name || $booking->customer_email ) {
			$description_lines[] = sprintf( __( 'Student: %s <%s>', 'wc-course-booking' ), $booking->customer_name, $booking->customer_email );
		}
		if ( $booking->order_id ) {
			$description_lines[] = sprintf( __( 'Order: #%d', 'wc-course-booking' ), $booking->order_id );
		}
		if ( ! empty( $booking->notes ) ) {
			$description_lines[] = $booking->notes;
		}

		$body = array(
			'summary'     => $summary,
			'description' => implode( "\n", $description_lines ),
			'start'       => array(
				'dateTime' => $start_utc->setTimezone( new DateTimeZone( $tz ) )->format( 'c' ),
				'timeZone' => $tz,
			),
			'end'         => array(
				'dateTime' => $end_utc->setTimezone( new DateTimeZone( $tz ) )->format( 'c' ),
				'timeZone' => $tz,
			),
		);

		if ( ! empty( $booking->meeting_url ) ) {
			$body['location'] = $booking->meeting_url;
		}

		if ( is_email( $booking->customer_email ) ) {
			$body['attendees'] = array(
				array(
					'email'       => $booking->customer_email,
					'displayName' => $booking->customer_name ?: '',
				),
			);
		}

		// Instructor as organizer/attendee.
		if ( $product ) {
			$instructor_id = (int) get_post_meta( $product->get_id(), '_wccb_instructor_id', true );
			if ( $instructor_id ) {
				$user = get_user_by( 'id', $instructor_id );
				if ( $user && is_email( $user->user_email ) ) {
					$body['attendees'][] = array(
						'email'       => $user->user_email,
						'displayName' => $user->display_name,
					);
				}
			}
		}

		return $body;
	}

	public static function calendar_id_for_course( $course_id ) {
		$per_course = (string) get_post_meta( $course_id, '_wccb_google_calendar_id', true );
		if ( $per_course ) {
			return $per_course;
		}
		$settings = WCCB_Google_OAuth::get_settings();
		return $settings['calendar_id'] ?: 'primary';
	}

	private static function get_event_id( $booking_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT google_event_id FROM {$wpdb->prefix}wccb_bookings WHERE id = %d", (int) $booking_id ) );
	}

	private static function get_event_calendar_id( $booking_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT google_calendar_id FROM {$wpdb->prefix}wccb_bookings WHERE id = %d", (int) $booking_id ) );
	}

	private static function save_event_id( $booking_id, $event_id, $calendar_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wccb_bookings',
			array( 'google_event_id' => $event_id, 'google_calendar_id' => $calendar_id ),
			array( 'id' => (int) $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private static function clear_event_id( $booking_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wccb_bookings',
			array( 'google_event_id' => null, 'google_calendar_id' => null ),
			array( 'id' => (int) $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private static function log_error( $context, $error ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$msg = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
			error_log( '[WCCB Google ' . $context . '] ' . $msg );
		}
	}
}
