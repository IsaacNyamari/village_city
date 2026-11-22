#!/usr/bin/env bash
# Pulls the latest code and refreshes the app. Runs ON the server as root.
# Triggered by the GitHub webhook (see deploy/enable-autodeploy.sh) or by hand:
#   sudo /var/www/village_city/deploy/update.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/village_city}"
BRANCH="${BRANCH:-main}"
PHP=/usr/bin/php8.4
LOCK=/tmp/village_city_deploy.lock

exec 9>"$LOCK"
flock -n 9 || { echo "$(date -Is) deploy already running, skipping"; exit 0; }

echo "=== $(date -Is) deploy start ==="
cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" || true

OLD=$(git rev-parse HEAD)
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"
NEW=$(git rev-parse HEAD)
echo "$OLD -> $NEW"

$PHP /usr/local/bin/composer install --no-dev -o --no-interaction

# Rebuild front-end assets only when they could have changed
if [ "$OLD" = "$NEW" ] || git diff --name-only "$OLD" "$NEW" | grep -qE '^(resources/|package(-lock)?\.json|vite\.config\.js)'; then
  npm ci && npm run build
fi

$PHP artisan down --retry=15 || true
trap '$PHP artisan up || true' EXIT

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache

systemctl restart village-queue
systemctl reload php8.4-fpm
echo "=== $(date -Is) deploy done ==="
