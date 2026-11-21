#!/usr/bin/env bash
# Run AFTER the DNS A record resolves:
#   ssh root@217.20.120.238 'bash -s' < deploy/enable-ssl.sh
set -euo pipefail
DOMAIN="${DOMAIN:-market.humsols.com}"
APP_DIR=/var/www/village_city
EMAIL="${EMAIL:-}"   # optional: EMAIL=you@example.com for expiry notices

IP=$(curl -s -m 10 https://api.ipify.org || true)
RES=$(nslookup "$DOMAIN" 8.8.8.8 2>/dev/null | awk '/^Address: /{print $2}' | tail -1 || true)
[ "$RES" = "$IP" ] || { echo "ERROR: $DOMAIN resolves to '${RES:-nothing}', server is $IP. Fix DNS first."; exit 1; }

apt-get install -y certbot python3-certbot-nginx
CE=(--register-unsafely-without-email); [ -n "$EMAIL" ] && CE=(-m "$EMAIL")
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos "${CE[@]}" --redirect

sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" $APP_DIR/.env
cd $APP_DIR && php artisan config:cache
systemctl list-timers | grep -i certbot || true
echo "HTTPS enabled: https://$DOMAIN"
