#!/usr/bin/env bash
# bootstrap.sh — Docker-free local demo for wc-course-booking.
#
# Downloads WordPress, the official SQLite drop-in, and WooCommerce into
# ./site/, symlinks our plugin in, installs WP, activates everything,
# seeds two demo courses, and starts PHP's built-in server on :8080.
#
# Idempotent: re-running skips work that is already done.
# Reset: pass `reset` as the first arg to wipe ./site/ and start fresh.

set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
SITE="$HERE/site"
PLUGIN_SRC="$(cd "$HERE/../../wc-course-booking" && pwd)"
PORT="${PORT:-8080}"
HOST="${HOST:-localhost}"
WP_VERSION="${WP_VERSION:-6.5.3}"
NO_SERVE="${WCCB_NO_SERVE:-0}"

colour_reset=$'\033[0m'
green=$'\033[32m'
yellow=$'\033[33m'
red=$'\033[31m'

say() { printf '%s→ %s%s\n' "$green" "$*" "$colour_reset"; }
warn() { printf '%s! %s%s\n' "$yellow" "$*" "$colour_reset"; }
die() { printf '%s✗ %s%s\n' "$red" "$*" "$colour_reset" >&2; exit 1; }

# --- Argument handling ------------------------------------------------------

case "${1:-}" in
	reset)
		say "Wiping $SITE"
		rm -rf "$SITE"
		say "Done. Run ./bootstrap.sh again to rebuild."
		exit 0
		;;
	setup)
		NO_SERVE=1
		;;
	serve)
		[ -f "$SITE/wp-load.php" ] || die "Site not set up yet. Run ./bootstrap.sh setup first."
		cd "$SITE"
		exec php -S "${HOST}:${PORT}" -t "$SITE" "$HERE/router.php"
		;;
esac

# --- Pre-flight -------------------------------------------------------------

command -v php >/dev/null 2>&1 || die "php is not installed. On macOS: brew install php"
command -v curl >/dev/null 2>&1 || die "curl is required."
command -v unzip >/dev/null 2>&1 || die "unzip is required."
command -v tar >/dev/null 2>&1 || die "tar is required."

php_major="$(php -r 'echo PHP_MAJOR_VERSION;')"
php_minor="$(php -r 'echo PHP_MINOR_VERSION;')"
if (( php_major < 7 )) || (( php_major == 7 && php_minor < 4 )); then
	die "PHP 7.4+ required. You have $(php -r 'echo PHP_VERSION;')"
fi

missing=()
for ext in pdo_sqlite mbstring curl json gd zip xml; do
	php -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null || missing+=("$ext")
done
if (( ${#missing[@]} )); then
	die "Missing PHP extensions: ${missing[*]}. On macOS with Homebrew PHP these are included by default; reinstall with: brew reinstall php"
fi

[ -d "$PLUGIN_SRC" ] || die "Cannot find plugin source at $PLUGIN_SRC"

# --- Site scaffolding -------------------------------------------------------

mkdir -p "$SITE"
cd "$SITE"

if [ ! -f wp-load.php ]; then
	say "Downloading WordPress $WP_VERSION"
	curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" -o /tmp/wp.tgz
	tar -xzf /tmp/wp.tgz --strip-components=1
	rm /tmp/wp.tgz
fi

if [ ! -f wp-config.php ]; then
	say "Writing wp-config.php"
	cat > wp-config.php <<'EOF'
<?php
// Demo config — SQLite drop-in ignores DB_* values but requires them.
define( 'DB_NAME',     'wordpress_demo' );
define( 'DB_USER',     'demo' );
define( 'DB_PASSWORD', 'demo' );
define( 'DB_HOST',     'localhost' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

$table_prefix = 'wp_';

define( 'WP_DEBUG',         true );
define( 'WP_DEBUG_LOG',     true );
define( 'WP_DEBUG_DISPLAY', false );

// Trust the forwarded proto when running behind a Codespaces / tunnel proxy.
if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

// Derive URLs from the request so the same install works on localhost
// and on a remote proxied URL (Codespaces, ngrok, etc.).
if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$wccb_scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
	define( 'WP_HOME',    $wccb_scheme . '://' . $_SERVER['HTTP_HOST'] );
	define( 'WP_SITEURL', $wccb_scheme . '://' . $_SERVER['HTTP_HOST'] );
} else {
	define( 'WP_HOME',    'http://localhost:8080' );
	define( 'WP_SITEURL', 'http://localhost:8080' );
}

define( 'AUTH_KEY',         'wccb-demo-auth-key' );
define( 'SECURE_AUTH_KEY',  'wccb-demo-secure-auth-key' );
define( 'LOGGED_IN_KEY',    'wccb-demo-logged-in-key' );
define( 'NONCE_KEY',        'wccb-demo-nonce-key' );
define( 'AUTH_SALT',        'wccb-demo-auth-salt' );
define( 'SECURE_AUTH_SALT', 'wccb-demo-secure-auth-salt' );
define( 'LOGGED_IN_SALT',   'wccb-demo-logged-in-salt' );
define( 'NONCE_SALT',       'wccb-demo-nonce-salt' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
EOF
fi

# SQLite drop-in ------------------------------------------------------------

if [ ! -d wp-content/plugins/sqlite-database-integration ]; then
	say "Downloading sqlite-database-integration"
	curl -fsSL 'https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip' -o /tmp/sqlite.zip
	unzip -qo /tmp/sqlite.zip -d wp-content/plugins/
	rm /tmp/sqlite.zip
fi

if [ ! -f wp-content/db.php ]; then
	say "Installing SQLite drop-in (wp-content/db.php)"
	cp wp-content/plugins/sqlite-database-integration/db.copy wp-content/db.php
	# Replace the template placeholders; perl is cross-platform (macOS sed -i is quirky).
	perl -pi -e "s|\\{SQLITE_IMPLEMENTATION_FOLDER_PATH\\}|$SITE/wp-content/plugins/sqlite-database-integration|g" wp-content/db.php
	perl -pi -e "s|\\{SQLITE_PLUGIN\\}|sqlite-database-integration/load.php|g" wp-content/db.php
fi

# WooCommerce ---------------------------------------------------------------

if [ ! -d wp-content/plugins/woocommerce ]; then
	say "Downloading WooCommerce (this is the biggest download, ~15MB)"
	curl -fsSL 'https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip' -o /tmp/wc.zip
	unzip -qo /tmp/wc.zip -d wp-content/plugins/
	rm /tmp/wc.zip
fi

# Our plugin ----------------------------------------------------------------

if [ ! -e wp-content/plugins/wc-course-booking ]; then
	say "Linking wc-course-booking → $PLUGIN_SRC"
	ln -s "$PLUGIN_SRC" wp-content/plugins/wc-course-booking
fi

# Uploads folder writable ---------------------------------------------------

mkdir -p wp-content/uploads

# Install & seed ------------------------------------------------------------

say "Running WordPress installer + seeding demo"
php "$HERE/install.php"

# --- Serve -----------------------------------------------------------------

if [ "$NO_SERVE" = "1" ]; then
	say "Setup complete (skipping web server). Run './bootstrap.sh serve' to start."
	exit 0
fi

cat <<BANNER

============================================================
  Demo ready at  ${yellow}http://${HOST}:${PORT}/${colour_reset}

  Admin:     http://${HOST}:${PORT}/wp-admin/  (admin / admin)
  Shop:      http://${HOST}:${PORT}/?post_type=product
  Bookings:  http://${HOST}:${PORT}/wp-admin/admin.php?page=wccb-bookings
  Student:   student / student

  Ctrl+C to stop the server. Run './bootstrap.sh reset' to wipe.
============================================================
BANNER

cd "$SITE"
exec php -S "${HOST}:${PORT}" -t "$SITE" "$HERE/router.php"
