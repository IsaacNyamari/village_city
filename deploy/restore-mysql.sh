#!/usr/bin/env bash
# Restores the ORIGINAL MySQL 8 data (moved to /var/lib/mysql-8.0 when MariaDB was installed).
# Run from your machine:   ssh root@217.20.120.238 'bash -s' < deploy/restore-mysql.sh
# It replaces MariaDB with MySQL 8 again. Nothing is deleted: every old directory is kept as a backup.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
APP_DIR=/var/www/village_city

[ -d /var/lib/mysql-8.0/mysql ] || { echo "ERROR: /var/lib/mysql-8.0 has no MySQL data - aborting"; exit 1; }
echo "Old data size: $(du -sh /var/lib/mysql-8.0 | cut -f1); free space: $(df -h /var/lib | tail -1 | awk '{print $4}')"

# Keep the app's DB settings so we can recreate its database afterwards
APP_DB_PASS=""; [ -f $APP_DIR/.env ] && APP_DB_PASS=$(grep -E '^DB_PASSWORD=' $APP_DIR/.env | cut -d= -f2-)

systemctl stop mariadb || true
STAMP=$(date +%s)
mv /var/lib/mysql "/var/lib/mysql-mariadb.bak.$STAMP"          # MariaDB's new, nearly empty data
cp -a /var/lib/mysql-8.0 "/var/lib/mysql-8.0.safety-copy.$STAMP" # untouched copy of the original

apt-get purge -y mariadb-server mariadb-client mariadb-client-core mariadb-server-core \
  'mariadb-plugin-*' galera-4 || true
mkdir -p /var/lib/mysql-purge-guard   # (purge may recreate /var/lib/mysql; clear it below)
rm -rf /var/lib/mysql-purge-guard
[ -d /var/lib/mysql ] && mv /var/lib/mysql "/var/lib/mysql-leftover.$STAMP"

mv /var/lib/mysql-8.0 /var/lib/mysql
chown -R mysql:mysql /var/lib/mysql 2>/dev/null || true   # user may not exist until install

apt-get install -y mysql-server
chown -R mysql:mysql /var/lib/mysql
systemctl enable --now mysql
sleep 5
systemctl is-active mysql

echo "=== Databases now served by MySQL ==="
mysql -e 'SHOW DATABASES;'

# Recreate the Village City database/user if the app was set up
if [ -n "$APP_DB_PASS" ]; then
  mysql -e "CREATE DATABASE IF NOT EXISTS village_city CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER IF NOT EXISTS 'village_city'@'localhost' IDENTIFIED BY '$APP_DB_PASS';
            ALTER USER 'village_city'@'localhost' IDENTIFIED BY '$APP_DB_PASS';
            GRANT ALL ON village_city.* TO 'village_city'@'localhost'; FLUSH PRIVILEGES;"
  echo "village_city database ready; now re-run deploy/deploy.sh"
fi
echo "Backups kept: /var/lib/mysql-mariadb.bak.$STAMP  /var/lib/mysql-8.0.safety-copy.$STAMP"
