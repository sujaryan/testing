# Live demo environment

A one-command local sandbox for clicking through `wc-course-booking`.
Runs WordPress + WooCommerce + the plugin in Docker with two seeded
demo courses.

## Requirements

- Docker & Docker Compose v2 (ships with Docker Desktop)
- Port **8080** free on localhost

## Start

```bash
cd demo
docker compose up -d db wordpress
# Wait ~15s for WP to finish installing its files, then:
docker compose run --rm wpcli bash /seed/setup.sh
```

When setup finishes you should see:

```
============================================================
  Demo ready
  Shop:       http://localhost:8080/shop/
  Admin:      http://localhost:8080/wp-admin (admin / admin)
  Bookings:   http://localhost:8080/wp-admin/admin.php?page=wccb-bookings
  Student:    student / student
============================================================
```

## What to click through

1. **`/shop/`** – there are two products:
   - *Private 1:1 Yoga Coaching* (capacity 1, Mon–Fri 9–17, 60-min slots)
   - *Group Meditation Class* (capacity 5, Sat+Sun 10–16, 45-min slots)
2. Open either product. You should see the Calendly-style calendar +
   slot picker injected into the product page. Future days with
   availability are highlighted blue; past days are greyed out.
3. Pick a date, then a time. The slot turns blue and a short-lived hold
   is placed server-side (table: `wp_wccb_slot_holds`).
4. Click **Book this course** → **Cart** → **Checkout**. Use the
   **Cash on delivery** gateway to skip payment during the demo.
5. On the Thank-you page, WooCommerce flips the order to *Processing*
   and our plugin creates + confirms a row in `wp_wccb_bookings`.
6. As admin, visit **Course bookings** in the WP admin menu to see the
   record; flip statuses; confirm emails were queued (check
   `wp-content/debug.log`).
7. Open the Group Meditation product — when capacity is > 1, each slot
   shows a *"N spots left"* badge.

## Things you might want to tweak while testing

- **Admin UI layout** – `wc-course-booking/templates/admin/product-data-panel.php`
- **Student picker markup** – `wc-course-booking/templates/booking-form.php`
- **Picker styles** – `wc-course-booking/assets/css/frontend.css`
- **Calendar / slot logic** – `wc-course-booking/assets/js/frontend.js`
- **Slot generation** – `wc-course-booking/includes/class-wccb-availability.php`

Edits on the host update inside the container instantly; just refresh
the browser.

## Reset

```bash
docker compose down -v   # drops DB + WP files; next setup.sh rebuilds
```

## Useful one-liners

```bash
# tail PHP errors
docker compose exec wordpress tail -f wp-content/debug.log

# drop into wp-cli
docker compose run --rm wpcli bash

# inspect the bookings table
docker compose exec db mariadb -uwp -pwp wordpress \
  -e "SELECT id, course_id, customer_email, start_datetime, status FROM wp_wccb_bookings ORDER BY id DESC LIMIT 20;"
```
