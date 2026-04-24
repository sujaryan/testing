<?php
/**
 * seed.php — creates two demonstration course products.
 *
 *   1. "Private 1:1 Yoga Coaching" — 60-min sessions, Mon–Fri 9:00–17:00,
 *      capacity 1 (classic Calendly-style).
 *   2. "Group Meditation Class"   — 45-min sessions, Sat+Sun 10:00–16:00,
 *      capacity 5 (so the "spots left" badge is visible).
 *
 * Run via: wp eval-file /seed/seed.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wccb_seed_course( array $args ) {
	$existing = get_page_by_title( $args['name'], OBJECT, 'product' );
	if ( $existing ) {
		$product_id = $existing->ID;
		WP_CLI::log( "Updating existing course: {$args['name']} (#{$product_id})" );
	} else {
		$product_id = wp_insert_post( array(
			'post_title'   => $args['name'],
			'post_content' => $args['description'],
			'post_status'  => 'publish',
			'post_type'    => 'product',
		) );
		WP_CLI::log( "Created course: {$args['name']} (#{$product_id})" );
	}

	wp_set_object_terms( $product_id, 'course_booking', 'product_type' );

	update_post_meta( $product_id, '_regular_price', $args['price'] );
	update_post_meta( $product_id, '_price', $args['price'] );
	update_post_meta( $product_id, '_virtual', 'yes' );
	update_post_meta( $product_id, '_manage_stock', 'no' );
	update_post_meta( $product_id, '_stock_status', 'instock' );
	update_post_meta( $product_id, '_sold_individually', 'yes' );

	update_post_meta( $product_id, '_wccb_duration',    $args['duration'] );
	update_post_meta( $product_id, '_wccb_buffer',      $args['buffer'] );
	update_post_meta( $product_id, '_wccb_min_notice',  $args['min_notice'] );
	update_post_meta( $product_id, '_wccb_window_days', $args['window_days'] );
	update_post_meta( $product_id, '_wccb_capacity',    $args['capacity'] );
	update_post_meta( $product_id, '_wccb_timezone',    $args['timezone'] );
	update_post_meta( $product_id, '_wccb_increment',   $args['increment'] );
	update_post_meta( $product_id, '_wccb_meeting_url', $args['meeting_url'] );
	update_post_meta( $product_id, '_wccb_schedule',    $args['schedule'] );
	update_post_meta( $product_id, '_wccb_overrides',   array() );

	return $product_id;
}

/**
 * Build a weekly schedule array. $days_ranges maps weekday numbers (0=Sun..6=Sat)
 * to a single [start,end] range; absent days are disabled.
 */
function wccb_build_schedule( array $days_ranges ) {
	$schedule = array();
	for ( $dow = 0; $dow <= 6; $dow++ ) {
		if ( isset( $days_ranges[ $dow ] ) ) {
			$schedule[ $dow ] = array(
				'enabled' => true,
				'ranges'  => array( array(
					'start' => $days_ranges[ $dow ][0],
					'end'   => $days_ranges[ $dow ][1],
				) ),
			);
		} else {
			$schedule[ $dow ] = array( 'enabled' => false, 'ranges' => array() );
		}
	}
	return $schedule;
}

wccb_seed_course( array(
	'name'        => 'Private 1:1 Yoga Coaching',
	'description' => 'A one-on-one yoga session tailored to your level. Pick any available time slot.',
	'price'       => '75',
	'duration'    => 60,
	'buffer'      => 15,
	'min_notice'  => 60,
	'window_days' => 45,
	'capacity'    => 1,
	'timezone'    => 'America/Los_Angeles',
	'increment'   => 30,
	'meeting_url' => 'https://meet.example.com/yoga-1on1',
	'schedule'    => wccb_build_schedule( array(
		1 => array( '09:00', '17:00' ),
		2 => array( '09:00', '17:00' ),
		3 => array( '09:00', '17:00' ),
		4 => array( '09:00', '17:00' ),
		5 => array( '09:00', '17:00' ),
	) ),
) );

wccb_seed_course( array(
	'name'        => 'Group Meditation Class',
	'description' => 'A guided group meditation. Up to five students per session — watch the spots-left count update as people book.',
	'price'       => '25',
	'duration'    => 45,
	'buffer'      => 0,
	'min_notice'  => 60,
	'window_days' => 45,
	'capacity'    => 5,
	'timezone'    => 'America/Los_Angeles',
	'increment'   => 60,
	'meeting_url' => 'https://meet.example.com/group-meditation',
	'schedule'    => wccb_build_schedule( array(
		0 => array( '10:00', '16:00' ), // Sun
		6 => array( '10:00', '16:00' ), // Sat
	) ),
) );

// Force permalinks to be pretty so product pages resolve nicely.
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( false );
