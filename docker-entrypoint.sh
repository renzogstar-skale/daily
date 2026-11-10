#!/bin/sh
set -e

# El volumen de data/ llega vacío la primera vez
DATA=/var/www/html/data
mkdir -p "$DATA/viewcache" "$DATA/storage" "$DATA/plugins" "$DATA/logs"
[ -f "$DATA/config.php" ] || cp /var/www/html/config-dist.php "$DATA/config.php"
chown -R www-data:www-data "$DATA"

# Railway asigna el puerto en $PORT
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
