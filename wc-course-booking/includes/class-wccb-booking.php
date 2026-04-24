<?php
/**
 * Booking data access layer.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Booking {

	const STATUS_PENDING   = 'pending';
	const STATUS_CONFIRMED = 'confirmed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_COMPLETED = 'completed';

	public static function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'course_id'      => 0,
			'order_id'       => null,
			'order_item_id'  => null,
			'customer_id'    => null,
			'customer_name'  => '',
			'customer_email' => '',
			'start_datetime' => '',
			'end_datetime'   => '',
			'timezone'       => 'UTC',
			'status'         => self::STATUS_PENDING,
			'notes'          => '',
			'meeting_url'    => '',
		);

		$row = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'wccb_bookings',
			array(
				'course_id'      => (int) $row['course_id'],
				'order_id'       => $row['order_id'] ? (int) $row['order_id'] : null,
				'order_item_id'  => $row['order_item_id'] ? (int) $row['order_item_id'] : null,
				'customer_id'    => $row['customer_id'] ? (int) $row['customer_id'] : null,
				'customer_name'  => $row['customer_name'],
				'customer_email' => $row['customer_email'],
				'start_datetime' => $row['start_datetime'],
				'end_datetime'   => $row['end_datetime'],
				'timezone'       => $row['timezone'],
				'status'         => $row['status'],
				'notes'          => $row['notes'],
				'meeting_url'    => $row['meeting_url'],
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'wccb_create_failed', __( 'Could not create booking.', 'wc-course-booking' ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function update_status( $booking_id, $status ) {
		global $wpdb;
		return $wpdb->update(
			$wpdb->prefix . 'wccb_bookings',
			array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function get( $booking_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wccb_bookings WHERE id = %d",
				(int) $booking_id
			)
		);
		return $row ?: null;
	}

	public static function get_by_order( $order_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wccb_bookings WHERE order_id = %d ORDER BY start_datetime ASC",
				(int) $order_id
			)
		);
	}

	public static function query( array $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wccb_bookings';

		$defaults = array(
			'status'     => '',
			'course_id'  => 0,
			'from'       => '',
			'to'         => '',
			'per_page'   => 50,
			'page'       => 1,
			'orderby'    => 'start_datetime',
			'order'      => 'ASC',
			'search'     => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['course_id'] ) {
			$where[]  = 'course_id = %d';
			$params[] = (int) $args['course_id'];
		}
		if ( $args['from'] ) {
			$where[]  = 'start_datetime >= %s';
			$params[] = $args['from'];
		}
		if ( $args['to'] ) {
			$where[]  = 'start_datetime <= %s';
			$params[] = $args['to'];
		}
		if ( $args['search'] ) {
			$where[]  = '(customer_name LIKE %s OR customer_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$orderby  = in_array( $args['orderby'], array( 'start_datetime', 'id', 'status', 'created_at' ), true ) ? $args['orderby'] : 'start_datetime';
		$order    = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) ) * $per_page;

		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}
}
