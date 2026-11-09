#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/../.."

# The encryption key must also be kept off-server to recover after a disk failure.
backup_dir=${SCORM_BACKUP_DIR:-/var/backups/scorm-campus}
key_file=${SCORM_BACKUP_KEY:-/etc/scorm-campus/backup.key}
[[ -s "$key_file" ]] || { echo "Missing backup encryption key: $key_file" >&2; exit 1; }
install -d -m 700 "$backup_dir"
exec 9>"$backup_dir/.backup.lock"
flock -n 9 || { echo 'Another backup is running.' >&2; exit 1; }
# Use the deployment lock too: do not dump a database while its schema is changing.
if ! mkdir .prod-deploy.lock 2>/dev/null; then
    echo 'Deployment lock exists; retry the backup after deployment finishes.' >&2
    exit 1
fi
work=
archive=
cleanup() {
    [[ -z "$work" ]] || rm -rf "$work"
    [[ -z "$archive" ]] || rm -f "$archive.partial"
    rmdir .prod-deploy.lock
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
work=$(mktemp -d "$backup_dir/.work.XXXXXX")
archive="$backup_dir/scorm-$(date -u +%Y%m%dT%H%M%SZ).tar.gz.enc"
compose() { docker compose --env-file .env.prod -f compose.prod.yaml "$@"; }

compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump -u"$MYSQL_USER" --single-transaction --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 --default-character-set=utf8mb4 "$MYSQL_DATABASE"' > "$work/database.sql"
compose exec -T app tar -C storage/app -czf - . > "$work/app-storage.tar.gz"
cp .env.prod "$work/.env.prod"
cp -a storage/course_texts "$work/course_texts"
cp compose.prod.yaml Dockerfile .dockerignore "$work/"
cp -a docker "$work/docker"
mkdir "$work/server-config"
for file in /etc/nginx/sites-available/scorm-campus /etc/nginx/snippets/scorm-campus-proxy.conf /etc/systemd/system/scorm-campus-backup.{service,timer}; do
    if [[ -r "$file" ]]; then
        cp --parents "$file" "$work/server-config/"
    fi
done
git rev-parse HEAD > "$work/revision.txt"
git diff --binary > "$work/deployment.patch"
if [[ -f "$backup_dir/course-manifest.json" ]]; then
    cp "$backup_dir/course-manifest.json" "$work/course-manifest.json"
fi
tar -C "$work" -czf - . | openssl enc -aes-256-cbc -salt -pbkdf2 -iter 200000 -pass "file:$key_file" -out "$archive.partial"
mv "$archive.partial" "$archive"
find "$backup_dir" -maxdepth 1 -type f -name 'scorm-*.tar.gz.enc' -mtime +14 -delete
echo "Encrypted backup created: $archive"
