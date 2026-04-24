# Live demo

Three ways to click through the plugin end-to-end. Pick the one that
matches what's available on your machine.

| Path | Requires | Best when |
|---|---|---|
| [Codespaces](#1-github-codespaces) | A GitHub account + a browser | Your laptop is locked down (no sudo, no Docker, no PHP) |
| [Docker](#2-docker-compose) | Docker Desktop | You have Docker and want parity with a real server |
| [No-Docker](no-docker/README.md) | PHP 7.4+ on your laptop | You have PHP but not Docker |

---

## 1. GitHub Codespaces

Zero install on your machine — everything runs in a Codespace, you
interact through your browser.

1. Visit the repo on GitHub and open this branch.
2. Click the green **<> Code** button → **Codespaces** tab → **Create
   codespace on this branch**.
3. Wait ~1 minute. The post-create script downloads WordPress + the
   SQLite drop-in + WooCommerce, activates the plugin, and seeds two
   demo courses.
4. Once the terminal prompts you, run:

   ```
   cd demo/no-docker
   ./bootstrap.sh serve
   ```

5. Codespaces auto-forwards port 8080 and pops a toast — click **Open
   in Browser**. Admin login is `admin` / `admin`, demo student is
   `student` / `student`.

To re-seed from scratch:

```
./bootstrap.sh reset
./bootstrap.sh setup
./bootstrap.sh serve
```

---

## 2. Docker Compose

Runs WordPress + MariaDB + WooCommerce in containers with the plugin
mounted live.

```
cd demo
docker compose up -d db wordpress
docker compose run --rm wpcli bash /seed/setup.sh
```

Open <http://localhost:8080/shop/>. Details in the root of this
directory.

---

## 3. No-Docker (local PHP)

See [no-docker/README.md](no-docker/README.md). Runs `php -S` with the
official SQLite drop-in — no MySQL, no Docker.

---

## What to click through (any path)

1. **Shop** (`/?post_type=product` or `/shop/`) shows two seeded
   products:
   - *Private 1:1 Yoga Coaching* — capacity 1, Mon–Fri 9–17, 60-minute
     slots.
   - *Group Meditation Class* — capacity 5, Sat+Sun 10–16, 45-minute
     slots. Because capacity > 1, each time shows a *"N spots left"*
     badge.
2. Open a product → calendar + slot picker renders in the product
   summary.
3. Pick a date, then a time. A short-lived server-side hold is placed
   so two tabs can't grab the same slot.
4. **Book → Cart → Checkout**. Use *Pay on arrival* (COD) to skip a
   real gateway.
5. As admin, go to **Course bookings** in the sidebar. The booking
   appears and you can change its status.
6. Go back to the product — the slot you just took is gone (capacity-1
   course) or the remaining count has decremented by 1 (group class).
