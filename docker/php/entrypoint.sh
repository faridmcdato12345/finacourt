#!/usr/bin/env sh
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "APP_KEY is required when APP_ENV=production" >&2
        exit 1
    fi

    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
fi

if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi

exec "$@"
