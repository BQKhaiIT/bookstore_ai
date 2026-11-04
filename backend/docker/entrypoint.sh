#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -z "${APP_KEY:-}" ]; then
    echo "backend: missing .env mount and APP_KEY" >&2
    exit 1
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ ! -f bootstrap/cache/packages.php ]; then
    php artisan package:discover --ansi --no-interaction
fi

if [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction
fi

exec "$@"
