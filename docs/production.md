# Production server

The application checkout is `/var/www/scorm-campus` on `68.168.30.49`.
The initial public address is `http://68.168.30.49:8080` (also available on port 80).
Host Nginx forwards to Docker on `127.0.0.1:8081`. MySQL listens only on `127.0.0.1:3306` on the server for SSH-tunneled clients.
The firewall permits SSH, HTTP, HTTPS and the initial port 8080.

Initial verification checked all 1,387 bundled files (6,725,360,199 bytes) against
local SHA-256 checksums, all eleven launch pages and video range requests, registration,
login/logout, compiled assets, upload extraction/delivery, and an encrypted database
backup restored into an isolated database.

OpenAI rejected the existing local API key with `401 invalid_api_key` during the initial
deployment check. Replace `OPENAI_API_KEY` in `.env.prod` with a valid key and run
`make prod-up` to enable successful AI requests. Failed requests do not consume quota.

Production configuration and generated secrets are in `.env.prod`, readable only by root.
Do not replace them on subsequent deployments. The initial database was copied from local
MySQL, preserving users, course IDs and course records; development sessions and cache
entries were cleared. Browser localStorage progress cannot be copied with the database.

Course packages live in `/srv/scorm-campus/courses`, mounted read-only into both
containers. Course texts in `storage/course_texts` are baked into the application image.
Texts for courses 1, 2, 7 and 11 are missing from the source project. Their course
packages remain playable, but their AI assistants need those texts.

## PhpStorm database connection

The production data source is `scorm_campus@PROD`.
In **General**, use host `127.0.0.1`, port `3306`, and database `scorm_campus`.
In **SSH/SSL**, enable the SSH tunnel with host `68.168.30.49`, SSH port `22`,
and the existing SSH credentials. Leave **Local port** empty (`<Dynamic>`).
That field is the temporary port on your computer; setting it to `22` causes
a permission error. The SSH server port remains `22`.

Use database credentials from `.env.prod`: `DB_USERNAME` / `DB_PASSWORD` for
the application user, or `root` / `DB_ROOT_PASSWORD` for administration. These
are separate from the SSH login. The database port is bound only to loopback;
do not open it in the firewall. Local development continues to use port `3307`.

Both environments use `OPENAI_MODEL=gpt-6-luna` and
`OPENAI_REASONING_EFFORT=medium`. The client uses the Responses API with strict
JSON schemas and a 16,000-token output budget that includes reasoning tokens.
After changing API keys, run `make up` locally or `make prod-up` on the server
to recreate containers and refresh cached configuration.

## Deploy an update

Keep the deployment changes in this repository committed before pulling future updates.
From `/var/www/scorm-campus`, back up first, then update and rebuild:

```sh
bash docker/production/backup.sh
git pull --ff-only
make prod-up
```

Do not use `docker compose down -v`: it removes persistent database and upload volumes.
Container logs rotate automatically. Docker and Nginx start on boot; containers have
restart policies. The server also has 2 GB of swap as a memory pressure reserve.

## Switch to zimast.am

1. Point the `zimast.am` DNS A record to `68.168.30.49`. Remove conflicting old A records.
   Only publish an AAAA record if the server has working public IPv6. If using `www`,
   configure its DNS too.
2. The domain's port-80 Nginx virtual host is already configured in
   `/etc/nginx/sites-available/scorm-campus`; proxy settings are in
   `/etc/nginx/snippets/scorm-campus-proxy.conf`.
3. After public DNS resolves correctly, run `certbot --nginx -d zimast.am` and select
   HTTP-to-HTTPS redirection. Add `-d www.zimast.am` only after its DNS also resolves.
4. Set `APP_URL=https://zimast.am` and `SESSION_SECURE_COOKIE=true` in `.env.prod`,
   then run `make prod-up`.
5. Run `certbot renew --dry-run` and verify login, playback and video seeking at
   `https://zimast.am`. Certbot's renewal timer is enabled.
6. Retire the IP-access Nginx virtual host and remove the temporary firewall rule with
   `ufw delete allow 8080/tcp`. Prefer a single HTTPS address for learners.

Progress in browser localStorage belongs to the origin. Progress created at the IP
address will not automatically appear at the domain. Secure-cookie sessions will
require HTTPS after the switch.

## Backups and recovery

`scorm-campus-backup.timer` runs daily at 03:15 UTC with up to ten minutes of jitter.
It keeps approximately fourteen days of encrypted backups in `/var/backups/scorm-campus`.
Each archive contains a consistent MySQL dump, uploaded application storage, production
environment, source texts, deployment scripts/configuration, host proxy/backup units, Git
revision, deployment patch and the course checksum manifest.
The database, environment and uploaded storage are included; the 6.3 GB bundled course
packages are separate and must also be retained off-server.

Encryption uses OpenSSL AES-256-CBC with PBKDF2. The key is
`/etc/scorm-campus/backup.key`; preserve it securely off-server alongside backup copies.
The initial encrypted backup and recovery secrets are also saved on the deployment
computer under `~/.local/share/scorm-campus/production-recovery/` with restricted permissions.
Daily copies currently stay on this VPS; arrange an off-server destination for ongoing
protection against server or disk loss.

Useful commands:

```sh
systemctl list-timers scorm-campus-backup.timer
systemctl start scorm-campus-backup.service
journalctl -u scorm-campus-backup.service
docker compose --env-file .env.prod -f compose.prod.yaml ps
docker compose --env-file .env.prod -f compose.prod.yaml logs --tail=100
```

To decrypt an archive into a private working directory:

```sh
umask 077
mkdir -p /root/scorm-restore
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 \
  -pass file:/etc/scorm-campus/backup.key \
  -in /var/backups/scorm-campus/ARCHIVE.tar.gz.enc \
  -out /root/scorm-restore/backup.tar.gz
tar -xzf /root/scorm-restore/backup.tar.gz -C /root/scorm-restore
```

Restore into an isolated database first and verify table counts before replacing any
live data. Preserve the production application key. Restore uploaded files into the
`scorm-campus-prod_app-storage` volume with ownership `www-data` (UID/GID 82 in the
current Alpine image), and restore bundled course packages to their host directory.
Remove plaintext recovery files after use.

To install the scheduled backup on a replacement server, create the private backup
directory, restore the encryption key, and install the supplied units:

```sh
install -d -m 700 /var/backups/scorm-campus /etc/scorm-campus
install -m 644 docker/production/scorm-campus-backup.service /etc/systemd/system/
install -m 644 docker/production/scorm-campus-backup.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now scorm-campus-backup.timer
```
