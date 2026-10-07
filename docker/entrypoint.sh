#!/bin/sh
# Container start: prepare writable dirs, wait for MySQL, migrate, cache.
set -e
cd /var/www/html

if [ ! -s .env ]; then
  echo "ERROR: /var/www/html/.env is missing (mount it from the host)." >&2
  exit 1
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache .env

echo "Waiting for database..."
i=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST_CHECK").";port=3306", getenv("DB_USER_CHECK"), getenv("DB_PASS_CHECK")); exit(0);} catch (Throwable $e) { exit(1);}'; do
  i=$((i+1)); [ "$i" -gt 60 ] && { echo "Database not reachable" >&2; exit 1; }
  sleep 2
done

gosu() { su -s /bin/sh www-data -c "$*"; }
gosu php artisan package:discover --ansi
gosu php artisan migrate --force
[ -L public/storage ] || gosu php artisan storage:link
gosu php artisan optimize:clear
gosu php artisan optimize
gosu php artisan view:cache
gosu php artisan event:cache

exec "$@"
