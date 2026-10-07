#!/usr/bin/env bash
# Runs on the server after a release has been extracted into the app directory.
#
#   cd /path/to/app && bash scripts/deploy.sh
#
# The web server's document root must point at <app>/public, never at <app>.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  echo "ERROR: .env is missing. Copy .env.example to .env and fill in production values." >&2
  exit 1
fi

php artisan down --retry=15 || true

mkdir -p storage/framework/{cache/data,sessions,views,testing} storage/logs storage/app/public bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan package:discover --ansi
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan optimize          # config + route cache
php artisan view:cache
php artisan event:cache

php artisan up
echo "Deploy finished."
