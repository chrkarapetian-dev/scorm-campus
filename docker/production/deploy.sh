#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."
[ -f .env.prod ] || { echo 'Create .env.prod from .env.prod.example first.' >&2; exit 1; }
# A host lock serializes deployments, including the first migration before cache tables exist.
lock=.prod-deploy.lock
if ! mkdir "$lock" 2>/dev/null; then
    echo 'Deployment lock exists. Check for a running deployment before removing .prod-deploy.lock.' >&2
    exit 1
fi
trap 'rmdir "$lock"' EXIT HUP INT TERM
compose() { docker compose --env-file .env.prod -f compose.prod.yaml "$@"; }
compose config -q
if [ "${1:-}" = migrate ]; then
    compose exec -T app php artisan migrate --force --no-ansi
    exit
fi
compose build
compose up -d --wait db
compose run --rm --no-deps --entrypoint /bin/sh app -ec '
    for course in storage/courses/course*; do
        if [ -r "$course/story.html" ] || [ -r "$course/index_lms.html" ]; then exit 0; fi
    done
    echo "No readable bundled course found in COURSES_HOST_PATH; check the mount." >&2
    exit 1
'
# Stop traffic before changing the schema; a failed initialization leaves the site offline.
compose stop web
compose run --rm --no-deps --entrypoint /bin/sh app -ec '
    php artisan migrate --force --no-ansi
    php artisan db:seed --force --no-ansi
    php artisan courses:warm-cache --no-ansi
'
compose up -d --wait app web
if ! compose exec -T web wget -q -O /dev/null http://127.0.0.1/up; then
    echo 'Laravel health check failed; stopping web.' >&2
    compose stop web
    exit 1
fi
