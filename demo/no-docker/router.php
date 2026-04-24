<?php
/**
 * router.php — minimal router for PHP's built-in web server.
 *
 * For requests that map to a real file (CSS, JS, images, wp-admin/*.php,
 * wp-login.php) the built-in server serves them directly. Anything else
 * is funnelled to WordPress's index.php so permalinks + admin-ajax work.
 *
 * Run from the WordPress root:
 *   cd demo/no-docker/site && php -S localhost:8080 ../router.php
 */

$doc_root = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' );
$uri      = urldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$target   = $doc_root . $uri;

// Direct file hits (CSS, JS, PHP files, images) → let PHP -S serve them.
if ( $uri !== '/' && is_file( $target ) ) {
	return false;
}

// Otherwise hand off to WordPress.
$_SERVER['SCRIPT_FILENAME'] = $doc_root . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
require $doc_root . '/index.php';
