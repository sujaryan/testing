<?php
/**
 * Shortcodes.
 *
 * [wccb_booking id="123"] — render the slot picker for a course product.
 * [wccb_my_bookings]      — list the logged-in user's upcoming bookings.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Shortcodes {

	public static function init() {
		add_shortcode( 'wccb_booking', array( __CLASS__, 'render_booking' ) );
		add_shortcode( 'wccb_my_bookings', array( __CLASS__, 'render_my_bookings' ) );
	}

	public static function render_booking( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'wccb_booking' );
		$product_id = absint( $atts['id'] );
		if ( ! $product_id ) {
			return '';
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || WCCB_Product_Type::TYPE !== $product->get_type() ) {
			return '';
		}

		wp_enqueue_style( 'wccb-frontend' );
		wp_enqueue_script( 'wccb-frontend' );

		$settings = WCCB_Availability::get_settings( $product_id );

		ob_start();
		echo '<form class="cart wccb-shortcode-form" method="post" action="' . esc_url( $product->add_to_cart_url() ) . '">';
		echo '<input type="hidden" name="add-to-cart" value="' . esc_attr( $product_id ) . '" />';
		include WCCB_PLUGIN_DIR . 'templates/booking-form.php';
		echo '<button type="submit" class="button alt wccb-shortcode-submit">' . esc_html__( 'Book this course', 'wc-course-booking' ) . '</button>';
		echo '</form>';
		return ob_get_clean();
	}

	public static function render_my_bookings() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to see your bookings.', 'wc-course-booking' ) . '</p>';
		}
		$user_id  = get_current_user_id();
		$bookings = WCCB_Booking::query( array(
			'per_page' => 100,
		) );
		// Filter to this user.
		$bookings = array_values( array_filter( $bookings, static function ( $b ) use ( $user_id ) {
			return (int) $b->customer_id === $user_id;
		} ) );

		if ( empty( $bookings ) ) {
			return '<p>' . esc_html__( 'You have no bookings yet.', 'wc-course-booking' ) . '</p>';
		}

		ob_start();
		echo '<table class="wccb-my-bookings"><thead><tr>';
		echo '<th>' . esc_html__( 'Course', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'When', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wc-course-booking' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $bookings as $b ) {
			$product = wc_get_product( $b->course_id );
			$name    = $product ? $product->get_name() : '#' . $b->course_id;
			try {
				$tz    = WCCB_Availability::safe_timezone( $b->timezone );
				$local = ( new DateTimeImmutable( $b->start_datetime, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz );
				$when  = $local->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) . ' (' . esc_html( $b->timezone ) . ')';
			} catch ( Exception $e ) {
				$when = $b->start_datetime;
			}
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $when ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $b->status ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		return ob_get_clean();
	}
}
