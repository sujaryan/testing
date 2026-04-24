<?php
/**
 * Student-facing booking confirmation email.
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
<p><?php printf( esc_html__( 'Hi %s,', 'wc-course-booking' ), esc_html( $booking->customer_name ?: '' ) ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: course name. */
		esc_html__( 'Your booking for %s is confirmed.', 'wc-course-booking' ),
		'<strong>' . esc_html( $product ? $product->get_name() : '' ) . '</strong>'
	);
	?>
</p>
<p><strong><?php esc_html_e( 'When:', 'wc-course-booking' ); ?></strong> <?php echo esc_html( $when ); ?></p>
<?php if ( ! empty( $booking->meeting_url ) ) : ?>
	<p><strong><?php esc_html_e( 'Meeting link:', 'wc-course-booking' ); ?></strong> <a href="<?php echo esc_url( $booking->meeting_url ); ?>"><?php echo esc_html( $booking->meeting_url ); ?></a></p>
<?php endif; ?>
<p><?php esc_html_e( 'See you then!', 'wc-course-booking' ); ?></p>
