<?php
/**
 * Booking form rendered on a course product page.
 *
 * @var WC_Product $product
 * @var array      $settings
 */
defined( 'ABSPATH' ) || exit;
?>
<div
	class="wccb-booking"
	data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
	data-timezone="<?php echo esc_attr( $settings['timezone'] ); ?>"
	data-duration="<?php echo esc_attr( $settings['duration'] ); ?>"
	data-window-days="<?php echo esc_attr( $settings['window_days'] ); ?>"
>
	<div class="wccb-booking__header">
		<h3 class="wccb-booking__title"><?php esc_html_e( 'Pick a time for your session', 'wc-course-booking' ); ?></h3>
		<p class="wccb-booking__meta">
			<?php
			printf(
				/* translators: 1: duration minutes, 2: timezone. */
				esc_html__( '%1$d minutes · Times shown in %2$s', 'wc-course-booking' ),
				(int) $settings['duration'],
				esc_html( $settings['timezone'] )
			);
			?>
		</p>
	</div>

	<div class="wccb-booking__body">
		<div class="wccb-calendar" aria-label="<?php esc_attr_e( 'Calendar', 'wc-course-booking' ); ?>">
			<div class="wccb-calendar__nav">
				<button type="button" class="wccb-calendar__prev" aria-label="<?php esc_attr_e( 'Previous month', 'wc-course-booking' ); ?>">&laquo;</button>
				<span class="wccb-calendar__title" aria-live="polite"></span>
				<button type="button" class="wccb-calendar__next" aria-label="<?php esc_attr_e( 'Next month', 'wc-course-booking' ); ?>">&raquo;</button>
			</div>
			<div class="wccb-calendar__weekdays"></div>
			<div class="wccb-calendar__grid" role="grid"></div>
		</div>

		<div class="wccb-slots">
			<div class="wccb-slots__header">
				<span class="wccb-slots__selected-date"><?php esc_html_e( 'Pick a date to see available times', 'wc-course-booking' ); ?></span>
			</div>
			<div class="wccb-slots__list" role="listbox"></div>
			<p class="wccb-slots__status" aria-live="polite"></p>
		</div>
	</div>

	<input type="hidden" name="wccb_slot_start_utc" value="" />
	<input type="hidden" name="wccb_slot_end_utc" value="" />
	<input type="hidden" name="wccb_slot_local" value="" />
	<input type="hidden" name="wccb_slot_tz" value="<?php echo esc_attr( $settings['timezone'] ); ?>" />
</div>
