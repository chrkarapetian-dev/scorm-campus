#!/bin/sh
set -e

# Without a configured APP_KEY, generate one and keep it on the storage volume
# so it stays the same across container rebuilds.
if [ -z "$APP_KEY" ]; then
    key_file=storage/app/.app-key
    [ -s "$key_file" ] || php artisan key:generate --show --no-ansi > "$key_file"
    APP_KEY="$(cat "$key_file")"
    export APP_KEY
fi

mkdir -p "${SCORM_STORAGE_PATH:-storage/app/scorm}"

php artisan migrate --force --no-ansi
php artisan db:seed --force --no-ansi
php artisan optimize --no-ansi

exec "$@"
