<?php
/**
 * Runs when the plugin is deleted via the WP admin. Drops our tables and
 * cleans our option. Post meta on products is left alone so it isn't lost if
 * the user later reinstalls.
 *
 * @package WC_Course_Booking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wccb_bookings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wccb_slot_holds" );
delete_option( 'wccb_db_version' );
