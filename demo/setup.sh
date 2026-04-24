#!/usr/bin/env bash
# setup.sh — runs inside the wp-cli container to bootstrap the demo site.
#
# Idempotent: re-running only reapplies what's missing.
set -euo pipefail

cd /var/www/html

WP_URL="http://localhost:8080"
WP_TITLE="Course Booking Demo"
WP_ADMIN_USER="admin"
WP_ADMIN_PASS="admin"
WP_ADMIN_EMAIL="admin@example.com"

echo "→ Waiting for WordPress core files..."
for i in {1..30}; do
  if [ -f wp-settings.php ]; then break; fi
  sleep 1
done

echo "→ Installing WordPress core (if not already)..."
if ! wp core is-installed 2>/dev/null; then
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "→ Ensuring WooCommerce is installed and active..."
wp plugin is-installed woocommerce || wp plugin install woocommerce --version=9.4.3
wp plugin is-active woocommerce    || wp plugin activate woocommerce

# Skip the WooCommerce setup wizard so the site is usable immediately.
wp option update woocommerce_onboarding_profile '{"completed":true,"skipped":true}' --format=json
wp option update woocommerce_task_list_hidden   'yes'
wp option update woocommerce_default_country    'US:CA'
wp option update woocommerce_currency           'USD'

echo "→ Activating wc-course-booking..."
wp plugin activate wc-course-booking

echo "→ Seeding demo course products..."
wp eval-file /seed/seed.php

echo "→ Creating a demo student account..."
wp user get student >/dev/null 2>&1 || \
  wp user create student student@example.com --role=customer --user_pass=student --first_name=Sam --last_name=Student

echo
echo "============================================================"
echo "  Demo ready"
echo "  Shop:       $WP_URL/shop/"
echo "  Admin:      $WP_URL/wp-admin (admin / admin)"
echo "  Bookings:   $WP_URL/wp-admin/admin.php?page=wccb-bookings"
echo "  Student:    student / student"
echo "============================================================"
