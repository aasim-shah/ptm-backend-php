#!/bin/sh
set -eu
cd /var/www/html
# App migrations run once in the web entrypoint. Background processes wait for
# that migration gate instead of racing to change the same schema.
i=0
until php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(Illuminate\Support\Facades\Schema::hasTable("workspace_runtime")?0:1);' >/dev/null 2>&1; do
    i=$((i+1))
    [ "$i" -lt 90 ] || { echo 'Workspace migration gate unavailable' >&2; exit 1; }
    sleep 2
done
exec su -s /bin/sh www-data -c "$*"
