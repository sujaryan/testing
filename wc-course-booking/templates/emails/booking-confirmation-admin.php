<?php
/**
 * Instructor/admin booking notification email.
 *
 * @var object          $booking
 * @var WC_Product|null $product
 */
defined( 'ABSPATH' ) || exit;

try {
	$tz    = WCCB_Availability::safe_timezone( $booking->timezone );
	$start = ( new DateTimeImmutable( $booking->start_datetime, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
	$end   = ( new DateTimeImmutable( $booking->end_datetime, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
	$when  = $start->format( 'l, F j, Y' ) . ' · ' . $start->format( 'H:i' ) . '–' . $end->format( 'H:i' ) . ' (' . esc_html( $booking->timezone ) . ')';
} catch ( Exception $e ) {
	$when = $booking->start_datetime . ' UTC';
}
?>
<p><?php esc_html_e( 'A new course booking has been made.', 'wc-course-booking' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Course:', 'wc-course-booking' ); ?></strong> <?php echo esc_html( $product ? $product->get_name() : '#' . $booking->course_id ); ?></li>
	<li><strong><?php esc_html_e( 'Student:', 'wc-course-booking' ); ?></strong> <?php echo esc_html( $booking->customer_name ); ?> &lt;<?php echo esc_html( $booking->customer_email ); ?>&gt;</li>
	<li><strong><?php esc_html_e( 'When:', 'wc-course-booking' ); ?></strong> <?php echo esc_html( $when ); ?></li>
	<?php if ( $booking->order_id ) : ?>
		<li><strong><?php esc_html_e( 'Order:', 'wc-course-booking' ); ?></strong> <a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $booking->order_id . '&action=edit' ) ); ?>">#<?php echo (int) $booking->order_id; ?></a></li>
	<?php endif; ?>
</ul>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wccb-bookings' ) ); ?>"><?php esc_html_e( 'Manage bookings', 'wc-course-booking' ); ?></a></p>
