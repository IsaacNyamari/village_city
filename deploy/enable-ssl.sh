#!/usr/bin/env bash
# Run AFTER the DNS A record resolves:
#   ssh root@217.20.120.238 'bash -s' < deploy/enable-ssl.sh
set -euo pipefail
DOMAIN="${DOMAIN:-market.humsols.com}"
APP_DIR=/var/www/village_city
EMAIL="${EMAIL:-}"   # optional: EMAIL=you@example.com for expiry notices

IP=$(curl -s https://api.ipify.org)
RES=$(getent hosts "$DOMAIN" | awk '{print $1}' | head -1)
[ "$RES" = "$IP" ] || { echo "ERROR: $DOMAIN resolves to '${RES:-nothing}', server is $IP. Fix DNS first."; exit 1; }

apt-get install -y certbot python3-certbot-nginx
CE=(--register-unsafely-without-email); [ -n "$EMAIL" ] && CE=(-m "$EMAIL")
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos "${CE[@]}" --redirect

sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" $APP_DIR/.env
cd $APP_DIR && php artisan config:cache
systemctl list-timers | grep -i certbot || true
echo "HTTPS enabled: https://$DOMAIN"
