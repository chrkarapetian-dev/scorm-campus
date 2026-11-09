# Ուսում · Կայուն զարգացում

Հայերեն ուսումնական հարթակ՝ Laravel 13, Blade, Tailwind CSS 4 և MySQL 8.4 հիմքով, Docker-ով։ Ներառում է Articulate Storyline-ի SCORM 1.2 / 2004 փաթեթների վերբեռնում և ցուցադրում, դասանյութի վրա հիմնված օգնական, բաց պատասխանի գնահատում և դիտարկիչում պահվող առաջընթաց։

## Գործարկել Docker-ով

```sh
make up     # կառուցում և գործարկում է ծառայությունները, կատարում է միգրացիաները
make down   # կանգնեցնում է ծառայությունները (տվյալները պահպանվում են)
```

Առաջին `make up`-ը ստեղծում է `.env`-ը `.env.example`-ից։ Հրապարակային տեղակայման դեպքում փոխեք `DB_PASSWORD` և `DB_ROOT_PASSWORD` արժեքները և սահմանեք `UPLOAD_TOKEN`։

Բացեք [http://localhost:8080](http://localhost:8080)։ Առաջին գործարկման ժամանակ միգրացիաներն ու դասանյութի տվյալները ավտոմատ կիրառվում են, իսկ `APP_KEY`-ը, եթե դատարկ է, ստեղծվում և պահվում է `app-storage` պահոցում։

| Ծառայություն | Դեր |
| --- | --- |
| `web` | Nginx․ սպասարկում է `public/`-ը, մնացածը փոխանցում է `app`-ին |
| `app` | PHP 8.4 FPM + Laravel |
| `db` | MySQL 8.4 (հոսթից՝ `127.0.0.1:3307`) |

Տվյալները պահվում են `mysql-data` (տվյալների բազա) և `app-storage` (SCORM ֆայլեր) պահոցներում և պահպանվում են վերակառուցումների միջև։ `docker compose down -v`-ը ջնջում է դրանք։

## Դասընթաց ավելացնել

Վերբեռնման ձևը բացվում է [http://localhost:8080/?upload=1](http://localhost:8080/?upload=1) հասցեով և պահանջում է `UPLOAD_TOKEN` գաղտնաբառը։ Արտադրական միջավայրում առանց `UPLOAD_TOKEN`-ի վերբեռնումը մերժվում է, քանի որ փաթեթի HTML-ը կատարվում է կայքի հասցեում։ Նույնը՝ հրամանով․

```sh
curl -H "X-Upload-Token: $UPLOAD_TOKEN" -F package=@course.zip -F title="Կայուն զարգացում" http://localhost:8080/api/courses
```

Փաթեթը պետք է լինի մինչև 400 ՄԲ և պարունակի `imsmanifest.xml`։

Օգտակար հղումներ՝ `/?course=1` (ուղիղ դեպի դասընթաց), `/?recover=1` (մաքրում է Storyline-ի պահպանված դիրքը), `/course/{id}` (առանձին էջ)։

## AI հնարավորությունները

`.env`-ում լրացրեք `OPENAI_API_KEY` (`OPENAI_MODEL=gpt-6-luna`, `OPENAI_REASONING_EFFORT=medium`), ապա գործարկեք `make up`։ Օգտագործվում են Responses API-ն և կառուցվածքային JSON պատասխաններ։ Բանալին մնում է սերվերում։ Սովորողի հարցերն ու պատասխաններն այդ դեպքում ուղարկվում են OpenAI API, և օգտագործումը կարող է վճարովի լինել։

Առանց բանալու օգնականը և առաջադրանքների գնահատումը հասանելի չեն։ API-ի սխալի դեպքում հարցը վերադարձվում է սովորողին, իսկ առաջադրանքին գնահատական չի նշանակվում․ հնարավոր է կրկնել փորձը։

Առաջադրանքներն ունեն դասընթացին համապատասխան հարց (`config/scorm.php`)։ Պատասխանը պետք է պարունակի 200–6000 նիշ։ Գնահատումը հիմնվում է համապատասխան `storage/course_texts/courseN.txt` ֆայլի վրա՝ ամբողջ տեքստով կամ, մեծ ծավալի դեպքում, ընտրված հատվածներով։ Միավորները՝ ճշգրտություն՝ 35, ըմբռնում՝ 30, ամբողջականություն՝ 25, հստակություն՝ 10։ Արդյունքներն ու պատմությունը պահվում են `assignment_submissions` աղյուսակում և հասանելի են միայն տվյալ օգտատիրոջը։

Զուգահեռ կատարվում են դասանյութով գնահատում և լեզվական ցուցիչների առանձին կառուցվածքային վերլուծություն՝ տեքստից մեջբերված ապացույցներով և հակափաստարկներով։ Սա մոտավոր գնահատում է, ոչ թե AI-ի կիրառման ապացույց։ `ASSIGNMENT_AI_THRESHOLD=50` և ավելի ցուցանիշի դեպքում համակարգը նշանակում է 0/100՝ անտեսելով արդեն կատարված գնահատման արդյունքը (երկու API կանչերն էլ հաշվվում են)։ Շեմը փոխելուց հետո գործարկեք `make up`։ Հակառակ դեպքում կատարվում է դասանյութով գնահատում՝ 1–100 միավորով։ Նույնական հաջող հարցումների կրկնությունը վերադարձնում է պահպանված արդյունքը, իսկ միաժամանակյա գնահատումները կանխվում են տվյալների բազայում։ Գնահատումն ունի առանձին՝ 5 հարցում/րոպե և 30 հարցում/օր սահմանաչափեր և չի ծախսում օգնականի հարցերի քանակը։

Տեխնիկական մանրամասները և սահմանափակումները՝ [docs/assignments.md](docs/assignments.md)։

## Մշակում

Տվյալների բազան՝ Docker-ում, իսկ PHP-ն և Vite-ը՝ ձեր համակարգչում (PHP 8.3+, Node 22+)․

```sh
docker compose up -d db
composer setup
composer dev
```

Թեստեր՝ `composer test` (SQLite հիշողության մեջ, MySQL պետք չէ)։ PHP-ի ներկառուցված սերվերով մեծ փաթեթներ վերբեռնելու համար բարձրացրեք `upload_max_filesize` և `post_max_size` արժեքները ձեր `php.ini`-ում․ Docker-ում դրանք արդեն կարգավորված են։

## Կառուցվածք

- `resources/views/` — Blade էջեր, `partials/` և `components/`
- `resources/css/app.css` — Tailwind-ի թեման (գույներ, տառատեսակներ, `max-compact:` կետ՝ մինչև 760px)
- `resources/js/app.js`, `storyline.js` — էջի վարքագիծը և Storyline-ի առաջընթացի հետևումը
- `app/Services/` — SCORM ներմուծում, OpenAI, տեղային օգնական
- `database/data/learning-course.json` — դասանյութ, որը սերմանվում է MySQL

## Տվյալներ և սահմանափակումներ

- Սովորողի SCORM առաջընթացը պահվում է նրա դիտարկչի `localStorage`-ում։ Առաջադրանքների արդյունքները պահվում են MySQL-ում։ Վճարումներ չկան։
- Հարթակից և դասընթացի ֆայլերից օգտվելու համար պետք է մուտք գործել (`/login`, `/register`)։ Յուրաքանչյուր օգտատեր կարող է տալ 10 հարց (`AI_QUESTION_LIMIT`)։ Օգտագործված հարցերի քանակը պահվում է `users.ai_questions_used` դաշտում։
- MySQL-ում պահվում են դասընթացների ցանկը, դասանյութը և հարցումների սահմանաչափի քեշը։
- SCORM ընդունողը MVP է և չի ապահովում ամբողջական SCORM համապատասխանություն։
- Հրապարակային տեղակայման դեպքում օգտագործեք HTTPS՝ `TRUSTED_PROXIES` կարգավորելով պրոքսիի համար։

## Local and production operations

Local Docker uses `compose.yaml`, `.env`, and the existing `scorm-campus` project. Copy `.env.example` to `.env` if needed and set local database passwords; `make up` also creates it if absent. The stack is at `http://localhost:8080`. `make down` retains its volumes. Host development remains available through `docker compose up -d db` and `composer dev`.

| Action | Local | Production |
| --- | --- | --- |
| Build, start and migrate | `make up` | `make prod-up` |
| Stop (preserves volumes) | `make down` | `make prod-down` |
| Re-chunk all course texts | `make rebuild-cache` | `make prod-rebuild-cache` |
| Clear Laravel caches | `make clear` | `make prod-clear` |
| Apply pending migrations | `make migrate` | `make prod-migrate` |
| MySQL shell | `make exec-db` | `make prod-exec-db` |
| App shell | `make exec-app` | `make prod-exec-app` |

`optimize:clear` also clears the default database cache, including course chunks and any other entries using that cache. Chunks are rebuilt on demand or on the next `prod-up`. PHP source and course texts are baked into images and require a rebuild. Course packages are mounted separately.

## Production VPS

The deployed server, domain transition and backup procedures are documented in [docs/production.md](docs/production.md).

Use a supported Linux VPS with Docker Engine, the Compose plugin (v2, with `--wait`), Git, Make, host Nginx and Certbot's Nginx plugin. Allow inbound ports 80 and 443 in the VPS firewall and provider firewall; restrict SSH to trusted users. Docker's MySQL port is bound only to `127.0.0.1:3306` for SSH-tunneled database clients. The production web port is bound to loopback only, so host Nginx is the public entry point. Local and production use different Compose project names, networks, images and named volumes. Production uses the same multi-stage Dockerfile with production-only Compose settings and entrypoint.

Initial server steps (run on the VPS):

1. Install Docker Engine and the Compose plugin using Docker's official instructions for the server OS. Install Git, Make, Nginx, Certbot and its Nginx plugin using the OS package manager. Ensure your deploy user can run Docker; Docker access grants root-equivalent privileges.
2. Clone the repository and enter it. Copy `.env.prod.example` to `.env.prod`, restrict it with `chmod 600 .env.prod`, and edit it. Set a persistent, unique `APP_KEY`, `APP_URL=https://your-domain`, independent strong database and root passwords, an independent upload token, and the absolute `COURSES_HOST_PATH`. Set `OPENAI_API_KEY` if the assistant is required. For example, generate a key with `printf 'base64:'; openssl rand -base64 32` and paste the full result into `APP_KEY`. Back up this key securely and do not change it on subsequent deployments. Never commit `.env.prod`.
3. Copy the actual `course1` … `course11` package directories to `COURSES_HOST_PATH` on the VPS, preserving all files. They are not in Git or the images and both containers need read access. Course texts in `storage/course_texts` are in the image; texts for courses 1, 2, 7 and 11 are currently absent, so their assistants are unavailable until those texts are provided. Run `make prod-up` after adding or changing text files.
4. Point your domain's DNS A/AAAA records to the VPS. Only publish an AAAA record if IPv6 works. Copy `docker/production/nginx-host.conf.example` to `/etc/nginx/sites-available/scorm-campus`, replace `example.com` with the real hostname, and enable it under `sites-enabled`. Match its upstream port to `PROD_HTTP_PORT`. Remove any conflicting default site, run `sudo nginx -t`, then reload Nginx.
5. Run `make prod-up`. This builds images, waits for MySQL, stops web traffic, runs pending migrations, re-runs the existing idempotent seeders, warms missing or stale course chunks, starts containers and checks Laravel's `/up` route. The seeders update bundled catalogue and learning content from the repository; do not edit those seeded records manually. A failed initialization leaves the web container stopped; inspect the error, restore from a backup if necessary, and rerun. Deployment is not zero downtime and schema migrations are not automatically rolled back.
6. After DNS resolves and HTTP on port 80 is reachable, run `sudo certbot --nginx -d your-domain` and select HTTPS redirection. Confirm certificate renewal with `sudo certbot renew --dry-run`. The host proxy must overwrite forwarded headers as in the example: Laravel trusts its internal proxy chain, and the Docker web port must remain loopback-only. Secure cookies require HTTPS. Verify login, course playback and upload behavior over the real HTTPS domain.

For an initial launch using `http://SERVER_IP:PORT`, set `APP_URL` to that exact address and `SESSION_SECURE_COOKIE=false` in `.env.prod`. Configure host Nginx to listen on the public port and proxy to the loopback-only `PROD_HTTP_PORT`; preserve the incoming port with `proxy_set_header Host $http_host`. When switching to `https://zimast.am`, set `APP_URL=https://zimast.am` and `SESSION_SECURE_COOKIE=true`, obtain the certificate, and run `make prod-up` again. Learner progress uses browser localStorage and does not automatically move from the IP address to the domain.

For later deployments, from the checkout on the VPS:

```sh
git pull
make prod-up
```

Deployment and `make prod-migrate` use the same host lock to prevent concurrent migration commands on this single VPS. If interrupted by a crash, check that no deployment is running before manually removing `.prod-deploy.lock`. Do not run migrations independently in another container or host. Keep `APP_KEY`, `DB_DATABASE`, `DB_USERNAME` and passwords stable; changing MySQL initialization variables does not alter an existing database or user.

Back up MySQL with a consistent `mysqldump --single-transaction` and separately back up the production `app-storage` volume, course package directory, `.env.prod` and any externally maintained source texts. Encrypt backups, store copies off-server, and periodically test restoration. Database volumes, uploads and course files are not removed by normal commands; never use `down -v` for normal deployments. Browser progress is local to each learner's browser and is not part of server backups.

Troubleshooting: check `docker compose --env-file .env.prod -f compose.prod.yaml ps` and service logs; verify disk space, credentials, the course mount and Docker health. A 502 usually means the app has not passed its health check or the proxy port differs. A 404 for a course can indicate missing files or an unavailable catalogue row; verify the mount then rerun `make prod-up`. After certificate or DNS changes verify renewal and proxy headers. `OPENAI_API_KEY` and working outbound HTTPS are required for the assistant; API failures return an error and failed assignments remain unscored; there is no local grading fallback.
