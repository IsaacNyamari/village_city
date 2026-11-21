#!/usr/bin/env bash
# Runs ON the server (Ubuntu/Debian) as root. Idempotent.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
APP_DIR="${APP_DIR:-/var/www/village_city}"
DOMAIN="${DOMAIN:-_}"
DB_NAME=village_city
DB_USER=village_city
PHP_V=8.4   # composer.lock needs PHP >= 8.4.1

apt-get update
apt-get install -y software-properties-common ca-certificates lsb-release curl unzip git nginx
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y php$PHP_V-fpm php$PHP_V-cli php$PHP_V-mysql php$PHP_V-mbstring php$PHP_V-xml \
  php$PHP_V-curl php$PHP_V-zip php$PHP_V-bcmath php$PHP_V-intl
update-alternatives --set php /usr/bin/php$PHP_V

# Never replace an existing database server: only install MariaDB if no MySQL/MariaDB is present.
if ! command -v mysqld >/dev/null && ! command -v mariadbd >/dev/null; then
  apt-get install -y mariadb-server
fi
systemctl enable --now mariadb 2>/dev/null || systemctl enable --now mysql
command -v composer >/dev/null || \
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

REPO="${REPO:-https://github.com/IsaacNyamari/village_city.git}"
BRANCH="${BRANCH:-main}"
if [ -d "$APP_DIR/.git" ]; then
  git config --global --add safe.directory "$APP_DIR"
  git -C "$APP_DIR" fetch origin "$BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
  mkdir -p "$APP_DIR"
  git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
  git config --global --add safe.directory "$APP_DIR"
fi
cd "$APP_DIR"

# --- database + .env (created once, never overwritten) ---
if [ ! -f .env ]; then
  DB_PASS=$(openssl rand -hex 16)
  mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
            ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
            GRANT ALL ON $DB_NAME.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"
  cp .env.example .env
  sed -i \
    -e "s|^APP_ENV=.*|APP_ENV=production|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
    -e "s|^APP_URL=.*|APP_URL=http://$( [ "$DOMAIN" = "_" ] && curl -s ifconfig.me || echo "$DOMAIN" )|" \
    -e "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" \
    -e "s|^# *DB_HOST=.*|DB_HOST=127.0.0.1|" -e "s|^# *DB_PORT=.*|DB_PORT=3306|" \
    -e "s|^# *DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" -e "s|^# *DB_USERNAME=.*|DB_USERNAME=$DB_USER|" \
    -e "s|^# *DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" -e "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USER|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
  composer install --no-dev -o --no-interaction
  php artisan key:generate --force
else
  composer install --no-dev -o --no-interaction
fi

# front-end assets (public/build is not in git)
command -v node >/dev/null || { curl -fsSL https://deb.nodesource.com/setup_20.x | bash - ; apt-get install -y nodejs; }
npm ci && npm run build

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
php artisan migrate --force --seed
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache

cat > /etc/nginx/sites-available/village_city <<NGINX
server {
    listen 80 default_server;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 20M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php$PHP_V-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/village_city /etc/nginx/sites-enabled/village_city
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
systemctl restart "php$PHP_V-fpm"

# Queue worker (QUEUE_CONNECTION=database)
cat > /etc/systemd/system/village-queue.service <<UNIT
[Unit]
Description=Village City queue worker
After=mariadb.service
[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php $APP_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload && systemctl enable --now village-queue
systemctl restart village-queue

echo "Server setup complete."
