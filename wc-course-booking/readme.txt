=== WC Course Booking ===
Contributors: you
Tags: woocommerce, booking, calendar, courses, calendly
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Calendly-style booking experience for WooCommerce. Students pick a time slot on a course product and the booking is created when the order is paid.

== Description ==

* New WooCommerce product type: **Course booking**.
* Per-course weekly availability (Mon–Sun) with custom hours.
* Date overrides for holidays or one-off sessions.
* Duration, slot increment, buffer, minimum notice, booking window, capacity per slot.
* Calendar + time slot picker embedded on each course product page.
* Short-lived slot holds during checkout prevent double-booking.
* Bookings are confirmed automatically when the order moves to processing/completed.
* Cancellations and refunds free up the slot again.
* Admin booking list at **Course bookings**.
* Shortcodes:
    * `[wccb_booking id="123"]` — embed the slot picker anywhere.
    * `[wccb_my_bookings]` — show the logged-in user's bookings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it under Plugins.
3. Create a new WooCommerce product and set its type to **Course booking**. Configure availability in the *Course booking* tab.
4. Publish — the slot picker appears on the product page.

== Changelog ==

= 1.0.0 =
* Initial release.
