<?php
/**
 * install.php — one-shot headless installer for the no-Docker demo.
 *
 * Boots WordPress directly (bypassing HTTP), installs the site on first
 * run, activates the SQLite drop-in + WooCommerce + our plugin, sets a
 * few WooCommerce options to skip the onboarding wizard, creates a demo
 * student account, and seeds two course products.
 */

// Load WordPress core.
$site = __DIR__ . '/site';
if ( ! file_exists( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "WP core not found at $site\n" );
	exit( 1 );
}

// Silence WP's default "please install" HTML if it shows up.
define( 'WP_INSTALLING', true );
define( 'WP_USE_THEMES', false );

require_once $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function say( $msg ) {
	echo '  ' . $msg . PHP_EOL;
}

if ( ! is_blog_installed() ) {
	say( 'Installing WordPress…' );
	$result = wp_install(
		'Course Booking Demo',
		'admin',
		'admin@example.com',
		true,
		'',
		'admin'
	);
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, 'Install failed: ' . $result->get_error_message() . PHP_EOL );
		exit( 1 );
	}
	say( 'Installed as admin / admin.' );
} else {
	say( 'WordPress already installed — skipping.' );
}

// Plugin activation order matters: WooCommerce first, then our plugin.
$plugins = array(
	'woocommerce/woocommerce.php',
	'wc-course-booking/wc-course-booking.php',
);

foreach ( $plugins as $plugin ) {
	if ( is_plugin_active( $plugin ) ) {
		say( "Already active: $plugin" );
		continue;
	}
	$result = activate_plugin( $plugin );
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, "Could not activate $plugin: " . $result->get_error_message() . PHP_EOL );
		exit( 1 );
	}
	say( "Activated $plugin" );
}

// Skip WooCommerce's onboarding wizard so the site is immediately usable.
update_option( 'woocommerce_onboarding_profile', array( 'completed' => true, 'skipped' => true ) );
update_option( 'woocommerce_task_list_hidden', 'yes' );
update_option( 'woocommerce_default_country', 'US:CA' );
update_option( 'woocommerce_currency', 'USD' );

// Enable Cash on Delivery so the demo checkout completes without a real gateway.
update_option( 'woocommerce_cod_settings', array(
	'enabled'            => 'yes',
	'title'              => 'Pay on arrival',
	'description'        => 'Pay in cash at the session. (Demo only.)',
	'instructions'       => '',
	'enable_for_methods' => array(),
	'enable_for_virtual' => 'yes',
) );

// Demo student account.
if ( ! username_exists( 'student' ) ) {
	$uid = wp_insert_user( array(
		'user_login' => 'student',
		'user_pass'  => 'student',
		'user_email' => 'student@example.com',
		'first_name' => 'Sam',
		'last_name'  => 'Student',
		'role'       => 'customer',
	) );
	if ( is_wp_error( $uid ) ) {
		fwrite( STDERR, 'Could not create student user: ' . $uid->get_error_message() . PHP_EOL );
	} else {
		say( 'Created student user.' );
	}
} else {
	say( 'Student user exists — skipping.' );
}

// Keep permalinks at the PHP-built-in-server-friendly default.
if ( get_option( 'permalink_structure' ) !== '' ) {
	update_option( 'permalink_structure', '' );
	flush_rewrite_rules( false );
}

// Seed demo courses (script is shared with the Docker path).
require __DIR__ . '/../seed.php';

say( 'Demo seeded.' );
