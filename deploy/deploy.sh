#!/usr/bin/env bash
# Village City deploy script. RUN IT YOURSELF from Git Bash / WSL in the project root:
#   bash deploy/deploy.sh
# You'll be prompted for the server password by ssh/scp. Use SSH keys when you can.
set -euo pipefail

SERVER="${SERVER:-217.20.120.238}"
USER_="${USER_:-root}"
APP_DIR=/var/www/village_city
DOMAIN="${DOMAIN:-_}"            # set DOMAIN=example.com for a real hostname
TARGET="$USER_@$SERVER"

cd "$(dirname "$0")/.."

echo "==> Building front-end assets"
npm ci
npm run build

echo "==> Packaging"
tar --exclude=node_modules --exclude=vendor --exclude=.env --exclude='.env.*' \
    --exclude=.git --exclude=.claude --exclude=database/database.sqlite \
    --exclude='storage/logs/*' --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' \
    --exclude=.phpunit.result.cache --exclude=deploy/deploy.tar.gz \
    -czf /tmp/village_city.tar.gz .

echo "==> Uploading (password prompt)"
scp /tmp/village_city.tar.gz deploy/server-setup.sh "$TARGET:/tmp/"

echo "==> Running server setup (password prompt)"
ssh "$TARGET" "APP_DIR=$APP_DIR DOMAIN='$DOMAIN' bash /tmp/server-setup.sh"

echo "==> Done. Visit http://$SERVER/"
echo "Create the first admin:  ssh $TARGET 'cd $APP_DIR && php artisan tinker'  (see docs/setup.md)"
