<?php
/**
 * Admin hooks specific to the course-booking product type (show/hide pricing
 * fields, inline JS to toggle the tab class, etc.).
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Admin_Courses {

	public static function init() {
		add_action( 'admin_footer', array( __CLASS__, 'type_toggle_script' ) );
	}

	/**
	 * Tell WooCommerce's product-data JS that the 'Course booking' type
	 * supports the General (price) tab, and to show our own tab.
	 */
	public static function type_toggle_script() {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		?>
		<script>
		(function ($) {
			$(function () {
				// Treat course_booking like simple/virtual for pricing/inventory visibility.
				$('body').on('woocommerce-product-type-change', function (e, type) {
					if (type === 'course_booking') {
						$('.show_if_simple').show();
						$('.show_if_virtual').show();
						$('.hide_if_course_booking').hide();
						$('.show_if_course_booking').show();
					}
				});
				if ($('#product-type').val() === 'course_booking') {
					$('.show_if_simple').show();
					$('.show_if_virtual').show();
					$('.show_if_course_booking').show();
				}
			});
		})(jQuery);
		</script>
		<?php
	}
}
