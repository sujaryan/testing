#!/usr/bin/env bash
# Runs once after the Codespace is created. Prepares the WordPress demo
# without starting the web server — the user runs bootstrap.sh serve
# manually once they open a terminal.
set -euo pipefail

cd "$(dirname "$0")/.."

# Make sure sqlite is available in PHP (it already is in the devcontainers
# php image, but double-check).
php -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" \
	|| { echo "pdo_sqlite missing"; exit 1; }

cd demo/no-docker
./bootstrap.sh setup

cat <<MSG

============================================================
  Codespace is ready. Open a terminal and run:

      cd demo/no-docker
      ./bootstrap.sh serve

  Codespaces will forward port 8080 and pop a notification to
  open it in a new browser tab. Admin: admin / admin.
============================================================
MSG
