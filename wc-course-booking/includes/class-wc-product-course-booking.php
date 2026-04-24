<?php
/**
 * WC_Product_Course_Booking — the product class backing our custom type.
 *
 * Kept separate from WCCB_Product_Type because PHP doesn't allow nesting
 * class declarations inside functions.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product' ) ) {
	return;
}

if ( ! class_exists( 'WC_Product_Course_Booking' ) ) {
	class WC_Product_Course_Booking extends WC_Product {
		public function get_type() {
			return WCCB_Product_Type::TYPE;
		}

		public function is_virtual( $context = 'view' ) {
			return true;
		}

		public function needs_shipping() {
			return false;
		}
	}
}
