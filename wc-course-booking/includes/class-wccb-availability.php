<?php
/**
 * Availability engine: turns a course's weekly schedule + overrides into a
 * concrete list of bookable slots for a date range, minus anything already
 * booked or currently held.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Availability {

	/**
	 * Default settings for a course.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'duration'      => 60,
			'buffer'        => 10,
			'min_notice'    => 60,
			'window_days'   => 30,
			'capacity'      => 1,
			'timezone'      => wp_timezone_string(),
			'instructor_id' => 0,
			'meeting_url'   => '',
			'increment'     => 30,
			'schedule'      => self::default_schedule(),
			'overrides'     => array(),
		);
	}

	private static function default_schedule() {
		$schedule = array();
		foreach ( array( 0, 1, 2, 3, 4, 5, 6 ) as $dow ) {
			// Mon-Fri 9-5 enabled by default.
			$enabled          = $dow >= 1 && $dow <= 5;
			$schedule[ $dow ] = array(
				'enabled' => $enabled,
				'ranges'  => $enabled ? array( array( 'start' => '09:00', 'end' => '17:00' ) ) : array(),
			);
		}
		return $schedule;
	}

	/**
	 * Merged settings for a product.
	 */
	public static function get_settings( $product_id ) {
		$defaults = self::defaults();

		$settings = array(
			'duration'      => (int) get_post_meta( $product_id, '_wccb_duration', true ) ?: $defaults['duration'],
			'buffer'        => (int) get_post_meta( $product_id, '_wccb_buffer', true ),
			'min_notice'    => (int) get_post_meta( $product_id, '_wccb_min_notice', true ) ?: $defaults['min_notice'],
			'window_days'   => (int) get_post_meta( $product_id, '_wccb_window_days', true ) ?: $defaults['window_days'],
			'capacity'      => max( 1, (int) get_post_meta( $product_id, '_wccb_capacity', true ) ),
			'timezone'      => get_post_meta( $product_id, '_wccb_timezone', true ) ?: $defaults['timezone'],
			'instructor_id' => (int) get_post_meta( $product_id, '_wccb_instructor_id', true ),
			'meeting_url'   => (string) get_post_meta( $product_id, '_wccb_meeting_url', true ),
			'increment'     => (int) get_post_meta( $product_id, '_wccb_increment', true ) ?: $defaults['increment'],
		);

		$schedule = get_post_meta( $product_id, '_wccb_schedule', true );
		if ( ! is_array( $schedule ) || empty( $schedule ) ) {
			$schedule = $defaults['schedule'];
		}
		$settings['schedule'] = $schedule;

		$overrides = get_post_meta( $product_id, '_wccb_overrides', true );
		$settings['overrides'] = is_array( $overrides ) ? $overrides : array();

		return $settings;
	}

	/**
	 * Build the list of bookable slots (UTC datetimes) for a course within
	 * the given local date range [from_date, to_date] inclusive.
	 *
	 * @param int    $product_id Course product ID.
	 * @param string $from_date  Start local date (Y-m-d).
	 * @param string $to_date    End local date (Y-m-d).
	 *
	 * @return array List of slots: [ ['start' => DateTime, 'end' => DateTime, 'remaining' => int], ... ]
	 */
	public static function get_slots( $product_id, $from_date, $to_date ) {
		$settings = self::get_settings( $product_id );
		$tz       = self::safe_timezone( $settings['timezone'] );

		$duration  = max( 5, (int) $settings['duration'] );
		$buffer    = max( 0, (int) $settings['buffer'] );
		$increment = max( 5, (int) $settings['increment'] );

		try {
			$from = new DateTimeImmutable( $from_date . ' 00:00:00', $tz );
			$to   = new DateTimeImmutable( $to_date . ' 23:59:59', $tz );
		} catch ( Exception $e ) {
			return array();
		}

		$now              = new DateTimeImmutable( 'now', $tz );
		$min_notice_limit = $now->modify( '+' . max( 0, (int) $settings['min_notice'] ) . ' minutes' );
		$window_limit     = $now->modify( '+' . max( 1, (int) $settings['window_days'] ) . ' days' );

		if ( $from < $now->setTime( 0, 0 ) ) {
			$from = $now->setTime( 0, 0 );
		}
		if ( $to > $window_limit ) {
			$to = $window_limit;
		}
		if ( $from > $to ) {
			return array();
		}

		$booked_counts = self::count_bookings_in_range( $product_id, $from, $to );
		$slots         = array();

		$day = $from;
		while ( $day <= $to ) {
			$date_key = $day->format( 'Y-m-d' );
			$dow      = (int) $day->format( 'w' );

			$day_ranges = self::resolve_day_ranges( $settings, $date_key, $dow );
			foreach ( $day_ranges as $range ) {
				try {
					$range_start = new DateTimeImmutable( $date_key . ' ' . $range['start'] . ':00', $tz );
					$range_end   = new DateTimeImmutable( $date_key . ' ' . $range['end'] . ':00', $tz );
				} catch ( Exception $e ) {
					continue;
				}

				$cursor = $range_start;
				while ( true ) {
					$slot_end = $cursor->modify( '+' . $duration . ' minutes' );
					if ( $slot_end > $range_end ) {
						break;
					}
					if ( $cursor < $min_notice_limit ) {
						$cursor = $cursor->modify( '+' . $increment . ' minutes' );
						continue;
					}

					$key       = $cursor->format( 'Y-m-d H:i' );
					$booked    = isset( $booked_counts[ $key ] ) ? (int) $booked_counts[ $key ] : 0;
					$remaining = max( 0, $settings['capacity'] - $booked );

					if ( $remaining > 0 ) {
						$slots[] = array(
							'start'     => $cursor,
							'end'       => $slot_end,
							'remaining' => $remaining,
						);
					}

					// Advance by increment + buffer on the next step (buffer only adds when already placed).
					$cursor = $cursor->modify( '+' . ( $increment + ( $remaining > 0 ? $buffer : 0 ) ) . ' minutes' );
				}
			}

			$day = $day->modify( '+1 day' );
		}

		return $slots;
	}

	/**
	 * Resolve the time ranges available on a given date, taking overrides into account.
	 */
	private static function resolve_day_ranges( $settings, $date_key, $dow ) {
		if ( isset( $settings['overrides'][ $date_key ] ) ) {
			$override = $settings['overrides'][ $date_key ];
			if ( 'closed' === ( $override['type'] ?? '' ) ) {
				return array();
			}
			if ( 'custom' === ( $override['type'] ?? '' ) && ! empty( $override['ranges'] ) ) {
				return $override['ranges'];
			}
		}

		$day = $settings['schedule'][ $dow ] ?? null;
		if ( ! $day || empty( $day['enabled'] ) || empty( $day['ranges'] ) ) {
			return array();
		}
		return $day['ranges'];
	}

	/**
	 * Count confirmed/pending bookings per slot start within a range, keyed by
	 * the slot's local start (Y-m-d H:i) in the course's timezone.
	 */
	private static function count_bookings_in_range( $product_id, DateTimeImmutable $from, DateTimeImmutable $to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wccb_bookings';
		$holds = $wpdb->prefix . 'wccb_slot_holds';

		$utc_from = $from->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$utc_to   = $to->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_datetime FROM $table WHERE course_id = %d AND status IN ('pending','confirmed') AND start_datetime BETWEEN %s AND %s",
				$product_id,
				$utc_from,
				$utc_to
			)
		);

		$hold_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_datetime FROM $holds WHERE course_id = %d AND expires_at > UTC_TIMESTAMP() AND start_datetime BETWEEN %s AND %s",
				$product_id,
				$utc_from,
				$utc_to
			)
		);

		$tz     = $from->getTimezone();
		$counts = array();
		$collect = function ( $list ) use ( &$counts, $tz ) {
			foreach ( $list as $row ) {
				try {
					$utc   = new DateTimeImmutable( $row->start_datetime, new DateTimeZone( 'UTC' ) );
					$local = $utc->setTimezone( $tz );
					$key   = $local->format( 'Y-m-d H:i' );
					$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
				} catch ( Exception $e ) {
					continue;
				}
			}
		};

		$collect( $rows );
		$collect( $hold_rows );

		return $counts;
	}

	/**
	 * Check whether a specific start datetime (UTC) is still bookable.
	 */
	public static function is_slot_available( $product_id, DateTimeImmutable $start_utc ) {
		$settings = self::get_settings( $product_id );
		$tz       = self::safe_timezone( $settings['timezone'] );
		$local    = $start_utc->setTimezone( $tz );

		$from = $local->setTime( 0, 0 );
		$to   = $local->setTime( 23, 59, 59 );

		$slots = self::get_slots( $product_id, $from->format( 'Y-m-d' ), $to->format( 'Y-m-d' ) );
		foreach ( $slots as $slot ) {
			if ( $slot['start']->getTimestamp() === $local->getTimestamp() ) {
				return $slot['remaining'] > 0;
			}
		}
		return false;
	}

	/**
	 * Place a short-lived hold on a slot. Returns hold id on success, WP_Error on failure.
	 */
	public static function hold_slot( $product_id, DateTimeImmutable $start_utc, $session_token, $ttl_minutes = 15 ) {
		global $wpdb;

		if ( ! self::is_slot_available( $product_id, $start_utc ) ) {
			return new WP_Error( 'wccb_slot_unavailable', __( 'That time slot is no longer available. Please pick another.', 'wc-course-booking' ) );
		}

		$settings = self::get_settings( $product_id );
		$end_utc  = $start_utc->modify( '+' . max( 5, (int) $settings['duration'] ) . ' minutes' );

		// Clean expired holds opportunistically.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wccb_slot_holds WHERE expires_at < UTC_TIMESTAMP()" );

		$expires = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+' . absint( $ttl_minutes ) . ' minutes' );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'wccb_slot_holds',
			array(
				'course_id'      => $product_id,
				'session_token'  => $session_token,
				'start_datetime' => $start_utc->format( 'Y-m-d H:i:s' ),
				'end_datetime'   => $end_utc->format( 'Y-m-d H:i:s' ),
				'expires_at'     => $expires->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'wccb_hold_failed', __( 'Could not reserve that slot. Please try again.', 'wc-course-booking' ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function release_hold_by_token( $session_token, $product_id = 0 ) {
		global $wpdb;
		if ( $product_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'wccb_slot_holds',
				array( 'session_token' => $session_token, 'course_id' => $product_id ),
				array( '%s', '%d' )
			);
		} else {
			$wpdb->delete( $wpdb->prefix . 'wccb_slot_holds', array( 'session_token' => $session_token ), array( '%s' ) );
		}
	}

	public static function safe_timezone( $tz_string ) {
		try {
			return new DateTimeZone( $tz_string ?: 'UTC' );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}
}
