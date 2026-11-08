#!/bin/sh
set -eu
mkdir -p storage/app/scorm
php artisan optimize --no-ansi
exec "$@"
