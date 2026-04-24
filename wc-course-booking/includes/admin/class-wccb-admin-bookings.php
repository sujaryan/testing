<?php
/**
 * Admin screen for listing and updating bookings.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Admin_Bookings {

	public static function init() {
		add_action( 'admin_post_wccb_update_booking', array( __CLASS__, 'handle_update' ) );
	}

	public static function render_list() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wc-course-booking' ) );
		}

		$args = array(
			'status'    => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'course_id' => isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0,
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'per_page'  => 50,
			'page'      => max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ),
			'order'     => 'DESC',
		);

		$bookings = WCCB_Booking::query( $args );

		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Course bookings', 'wc-course-booking' ) . '</h1>';

		echo '<form method="get" style="margin: 1em 0;">';
		echo '<input type="hidden" name="page" value="wccb-bookings" />';
		echo '<select name="status"><option value="">' . esc_html__( 'All statuses', 'wc-course-booking' ) . '</option>';
		foreach ( array( 'pending', 'confirmed', 'cancelled', 'completed' ) as $s ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $s ), selected( $args['status'], $s, false ), esc_html( ucfirst( $s ) ) );
		}
		echo '</select> ';
		echo '<input type="search" name="s" placeholder="' . esc_attr__( 'Search name or email', 'wc-course-booking' ) . '" value="' . esc_attr( $args['search'] ) . '" /> ';
		submit_button( __( 'Filter', 'wc-course-booking' ), '', '', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Course', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Student', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'When', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wc-course-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wc-course-booking' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $bookings ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No bookings found.', 'wc-course-booking' ) . '</td></tr>';
		}

		foreach ( $bookings as $b ) {
			$product = wc_get_product( $b->course_id );
			$course  = $product ? $product->get_name() : '#' . $b->course_id;
			try {
				$tz   = WCCB_Availability::safe_timezone( $b->timezone );
				$when = ( new DateTimeImmutable( $b->start_datetime, new DateTimeZone( 'UTC' ) ) )->setTimezone( $tz )->format( 'Y-m-d H:i' ) . ' ' . esc_html( $b->timezone );
			} catch ( Exception $e ) {
				$when = $b->start_datetime . ' UTC';
			}

			$order_link = $b->order_id ? sprintf( '<a href="%s">#%d</a>', esc_url( admin_url( 'post.php?post=' . (int) $b->order_id . '&action=edit' ) ), (int) $b->order_id ) : '—';

			echo '<tr>';
			echo '<td>' . (int) $b->id . '</td>';
			echo '<td>' . esc_html( $course ) . '</td>';
			echo '<td>' . esc_html( $b->customer_name ?: $b->customer_email ) . '<br><small>' . esc_html( $b->customer_email ) . '</small></td>';
			echo '<td>' . esc_html( $when ) . '</td>';
			echo '<td>' . $order_link . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . esc_html( ucfirst( $b->status ) ) . '</td>';
			echo '<td>';
			self::render_status_form( $b );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private static function render_status_form( $b ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex; gap:4px;">
			<?php wp_nonce_field( 'wccb_update_booking_' . $b->id ); ?>
			<input type="hidden" name="action" value="wccb_update_booking" />
			<input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>" />
			<select name="status">
				<?php foreach ( array( 'pending', 'confirmed', 'cancelled', 'completed' ) as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $b->status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button button-small" type="submit"><?php esc_html_e( 'Update', 'wc-course-booking' ); ?></button>
		</form>
		<?php
	}

	public static function handle_update() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission.', 'wc-course-booking' ) );
		}
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		check_admin_referer( 'wccb_update_booking_' . $booking_id );

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'completed' ), true ) ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wccb-bookings' ) );
			exit;
		}

		WCCB_Booking::update_status( $booking_id, $status );
		do_action( 'wccb_booking_status_changed', $booking_id, $status );

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wccb-bookings' ) );
		exit;
	}
}
