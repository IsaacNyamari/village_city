#!/usr/bin/env bash
# One-time setup of GitHub auto-deploy. Run ON the server as root, AFTER the first deploy and after
# this code is on the server (git pull):
#   cd /var/www/village_city && git pull && bash deploy/enable-autodeploy.sh
# Prints the URL and secret to paste into GitHub -> Settings -> Webhooks.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/village_city}"
PHP=/usr/bin/php8.4
cd "$APP_DIR"

chmod +x deploy/update.sh

# Secret (kept if already set)
if ! grep -qE '^DEPLOY_WEBHOOK_SECRET=.+' .env; then
  sed -i '/^DEPLOY_WEBHOOK_SECRET=/d' .env
  printf '\nDEPLOY_WEBHOOK_SECRET=%s\n' "$(openssl rand -hex 32)" >> .env
fi

# Let the web user run ONLY the update script as root, without a password
echo "www-data ALL=(root) NOPASSWD: $APP_DIR/deploy/update.sh" > /etc/sudoers.d/village-city-deploy
chmod 440 /etc/sudoers.d/village-city-deploy
visudo -cf /etc/sudoers.d/village-city-deploy

touch storage/logs/deploy.log && chown www-data:www-data storage/logs/deploy.log

$PHP artisan config:cache && $PHP artisan route:cache

URL=$(grep -E '^APP_URL=' .env | cut -d= -f2-)
echo
echo "GitHub -> Settings -> Webhooks -> Add webhook"
echo "  Payload URL : $URL/deploy/webhook"
echo "  Content type: application/json"
echo "  Secret      : $(grep -E '^DEPLOY_WEBHOOK_SECRET=' .env | cut -d= -f2-)"
echo "  Events      : Just the push event"
echo "Deploy log    : $APP_DIR/storage/logs/deploy.log"
