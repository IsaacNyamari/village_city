#!/usr/bin/env bash
# Run YOURSELF from Git Bash / WSL:  bash deploy/deploy.sh
# The server pulls the code from GitHub; ssh will prompt for the server password.
set -euo pipefail

SERVER="${SERVER:-217.20.120.238}"
USER_="${USER_:-root}"
APP_DIR=/var/www/village_city
DOMAIN="${DOMAIN:-_}"
REPO="${REPO:-https://github.com/IsaacNyamari/village_city.git}"
BRANCH="${BRANCH:-main}"
TARGET="$USER_@$SERVER"

cd "$(dirname "$0")/.."

echo "==> Uploading setup script (password prompt)"
scp deploy/server-setup.sh "$TARGET:/tmp/"

echo "==> Running setup on server (password prompt)"
ssh "$TARGET" "APP_DIR=$APP_DIR DOMAIN='$DOMAIN' REPO='$REPO' BRANCH='$BRANCH' bash /tmp/server-setup.sh"

echo "==> Done. Visit http://$SERVER/"
echo "First admin: ssh $TARGET 'cd $APP_DIR && php artisan tinker'  (see docs/setup.md)"
echo "To update later: push to GitHub, then re-run this script."
