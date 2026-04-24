# Docker-free live demo

Runs WordPress + WooCommerce + the plugin on your laptop with nothing but
PHP — no Docker, no MySQL, no separate installer. Uses WordPress's
official [SQLite drop-in](https://wordpress.org/plugins/sqlite-database-integration/)
for storage and `php -S` as the web server.

## Requirements

- **PHP 7.4+** with the usual extensions (pdo_sqlite, mbstring, curl,
  gd, json, zip, xml). Homebrew's `php` includes them all.
- **curl**, **unzip**, **tar** (standard on macOS and Linux).

If PHP is missing on macOS:

```bash
brew install php
```

## Start

```bash
cd demo/no-docker
./bootstrap.sh
```

First run downloads ~25 MB (WordPress core + WooCommerce + SQLite drop-in)
and installs everything into `./site/`. When it's done you'll see:

```
============================================================
  Demo ready at  http://localhost:8080/

  Admin:     http://localhost:8080/wp-admin/  (admin / admin)
  Shop:      http://localhost:8080/?post_type=product
  Bookings:  http://localhost:8080/wp-admin/admin.php?page=wccb-bookings
  Student:   student / student
  Ctrl+C to stop the server. Run './bootstrap.sh reset' to wipe.
============================================================
```

Subsequent runs just start the server — they skip downloads and reuse
the existing `./site/` directory.

## What to click through

1. **`/?post_type=product`** — the shop shows two seeded products:
   - *Private 1:1 Yoga Coaching* — capacity 1, Mon–Fri 9–17, 60-min slots.
   - *Group Meditation Class* — capacity 5, Sat+Sun 10–16, 45-min slots.
     Because capacity > 1, each time slot shows a *"N spots left"* badge.
2. Open a product. The Calendly-style calendar + slot picker is injected
   into the product summary. Days with availability glow blue.
3. Pick a date, then a time. A short-lived server-side hold is placed
   (row in `wp_wccb_slot_holds`) so two tabs can't grab the same slot.
4. Click **Book this course → View cart → Checkout**. Fill billing and
   use **Pay on arrival** (COD) to skip a real gateway.
5. After placing the order, visit **Course bookings** in WP admin. You
   should see a confirmed booking linked to the order.
6. Go back to the product page — the slot you just booked is gone (or
   on the group class, the remaining count has dropped by 1).

## Reset

```bash
./bootstrap.sh reset
```

Wipes `./site/` completely. Next run is a clean install.

## Useful one-liners

```bash
# Tail WP's debug log
tail -f site/wp-content/debug.log

# Poke the bookings table (SQLite)
sqlite3 site/wp-content/database/.ht.sqlite \
  "SELECT id, course_id, customer_email, start_datetime, status FROM wp_wccb_bookings ORDER BY id DESC LIMIT 20;"
```

## Limitations vs. the Docker path

- Single-threaded `php -S` — fine for clicking around, not for load testing.
- SQLite via the drop-in — covers core + WooCommerce, but if you ever
  write raw MySQL-specific SQL it won't translate.
- No background cron — scheduled events run on pageview as usual, so if
  an action looks delayed, just refresh.

## Troubleshooting

**"php is not installed"** — `brew install php` on macOS, then open a
fresh terminal so the new PATH is picked up.

**"Missing PHP extensions: pdo_sqlite …"** — your PHP is a custom build
without those extensions. `brew reinstall php` is the fastest fix.

**Port 8080 already in use** — set a different port:
`PORT=9090 ./bootstrap.sh`.

**Weird "headers already sent" warnings on first install** — harmless;
the installer fires a couple of notices that the built-in server prints
verbatim. The site is still installed correctly.
