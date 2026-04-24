<?php
/**
 * Email notifications for bookings.
 *
 * Uses WooCommerce's mailer so the site's branded header/footer apply.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Emails {

	public static function init() {
		add_action( 'wccb_booking_confirmed', array( __CLASS__, 'send_confirmation' ), 10, 2 );
		add_action( 'wccb_booking_cancelled', array( __CLASS__, 'send_cancellation' ), 10, 2 );
	}

	public static function send_confirmation( $booking_id, $booking ) {
		$customer_email = $booking->customer_email;
		if ( ! is_email( $customer_email ) ) {
			return;
		}

		$product = wc_get_product( $booking->course_id );
		$subject = sprintf(
			/* translators: %s: course name */
			__( 'Your booking is confirmed: %s', 'wc-course-booking' ),
			$product ? $product->get_name() : '#' . $booking->course_id
		);

		$body = self::render_email( 'booking-confirmation.php', array(
			'booking' => $booking,
			'product' => $product,
			'heading' => __( 'Your booking is confirmed', 'wc-course-booking' ),
		) );

		self::mail( $customer_email, $subject, $body );

		// Instructor / admin copy.
		$admin_email = self::admin_recipient( $booking->course_id );
		if ( $admin_email && $admin_email !== $customer_email ) {
			self::mail(
				$admin_email,
				sprintf( __( 'New booking: %s', 'wc-course-booking' ), $product ? $product->get_name() : '#' . $booking->course_id ),
				self::render_email( 'booking-confirmation-admin.php', array(
					'booking' => $booking,
					'product' => $product,
					'heading' => __( 'New course booking', 'wc-course-booking' ),
				) )
			);
		}
	}

	public static function send_cancellation( $booking_id, $booking ) {
		if ( ! is_email( $booking->customer_email ) ) {
			return;
		}
		$product = wc_get_product( $booking->course_id );
		self::mail(
			$booking->customer_email,
			sprintf( __( 'Your booking was cancelled: %s', 'wc-course-booking' ), $product ? $product->get_name() : '#' . $booking->course_id ),
			self::render_email( 'booking-confirmation.php', array(
				'booking' => $booking,
				'product' => $product,
				'heading' => __( 'Your booking was cancelled', 'wc-course-booking' ),
			) )
		);
	}

	private static function admin_recipient( $course_id ) {
		$instructor_id = (int) get_post_meta( $course_id, '_wccb_instructor_id', true );
		if ( $instructor_id ) {
			$user = get_user_by( 'id', $instructor_id );
			if ( $user && $user->user_email ) {
				return $user->user_email;
			}
		}
		return get_option( 'admin_email' );
	}

	private static function render_email( $template, $vars ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return '';
		}
		$mailer = WC()->mailer();

		ob_start();
		do_action( 'woocommerce_email_header', $vars['heading'], null );
		extract( $vars, EXTR_SKIP ); // phpcs:ignore
		include WCCB_PLUGIN_DIR . 'templates/emails/' . $template;
		do_action( 'woocommerce_email_footer', null );
		$content = ob_get_clean();

		return $mailer->style_inline( $content );
	}

	private static function mail( $to, $subject, $body ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $body, $headers );
	}
}
