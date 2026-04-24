/**
 * Admin-side helpers for WC Course Booking.
 *
 * Kept deliberately thin — the heavy logic lives server-side.
 */
(function ($) {
	'use strict';

	$(function () {
		// Hide the 'virtual' checkbox for our product type since we force virtual.
		var $productType = $('#product-type');
		function toggleVirtualCheckbox() {
			if ($productType.val() === 'course_booking') {
				$('.show_if_virtual._virtual_field').hide();
			}
		}
		toggleVirtualCheckbox();
		$productType.on('change', toggleVirtualCheckbox);
	});
})(jQuery);
