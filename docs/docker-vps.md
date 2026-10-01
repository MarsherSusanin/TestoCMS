# Docker / VPS Deployment

Docker on your own VPS is the secondary production path for TestoCMS. Use it when you control the server and want separate app, web, queue, scheduler, and database services.

## Requirements

- Linux VPS with Docker Engine + Compose v2
- Public domain with TLS termination
- Persistent disk for PostgreSQL and uploaded files

## 1. Prepare the production env file

```bash
cp .env.vps.example .env
```

Update at minimum:

- `APP_URL`
- `APP_KEY`
- `CMS_CONTENT_API_KEY`
- `DB_PASSWORD`
- `CMS_ADMIN_EMAIL`
- `CMS_ADMIN_PASSWORD`
- mail provider credentials
- LLM provider credentials if used

This profile uses:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `CMS_SEED_DEMO_CONTENT=false`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=file`
- `LARAVEL_PUBLIC_PATH=html_public`

The read-only bind mount controls writes; the file must also be readable by the
PHP runtime for fresh `config:cache`, setup identity validation and CLI recovery.
On Linux keep the deployment user as owner and grant read access to the FPM group
(82 in this Alpine image; verify the image's `id www-data` if using another image):

```bash
sudo chgrp 82 .env
chmod 0640 .env
```

The deployment user must retain read access so Compose can read its env file.
Alternatively root-owned `root:www-data` 0640 is valid when the deployment/Compose
caller also has read access and the group matches the container's numeric GID.
A Linux root-owned 0600 bind is not readable by FPM. Root 0600 applies to the
private PostgreSQL credential-volume files, which are read by root `db-env` and
the official PostgreSQL entrypoint. Docker Desktop ownership mapping can hide
host UID/GID issues; verify these permissions on the target Linux host.

## 2. Start the stack

```bash
docker compose -f docker-compose.vps.yml up -d --build
```

Services:

- `db-env` — private credential preparation with the PHP dotenv parser
- `init` — one-shot CMS initialization
- `db` — PostgreSQL
- `app` — PHP-FPM / Laravel app
- `web` — Nginx
- `queue` — Laravel queue worker
- `scheduler` — `schedule:work`

## 3. One-time bootstrap

The one-shot `init` service initializes from the prepared `.env` using
`php artisan cms:setup --from-env --no-interaction`. It waits for the database,
runs migrations, creates roles and the administrator, verifies public storage,
and only then writes `storage/installed`. The application, queue and scheduler
wait for this service to complete successfully. Inspect failures with:

```bash
docker compose -f docker-compose.vps.yml logs init
```

The production `.env` remains read-only. Initialization preserves its contents,
mail/LLM credentials, APP_KEY and Content API key; it never generates production
secrets. Configure a valid APP_KEY (32 random bytes encoded as `base64:...`),
Content API key and an administrator password of at least eight characters before
starting. For example, generate random values with `openssl rand -base64 32` and
`openssl rand -hex 24`, then place them in `.env`.

Repeated initialization is a no-op when the installed marker exists. It does not
reset the administrator's password. A marker is bound to the database installation UUID, database identity, actual
public root and APP_KEY hash. A mismatch stops bootstrap without resetting
accounts. Existing legacy installations remain available to web requests, but
must be explicitly adopted after schema/admin verification:
`php docker/artisan.php cms:setup --adopt-existing --force`.
`cms:setup --redo --from-env --force --no-interaction` can update allowed existing
admin fields, preserving the password and read-only env. Writable interactive
redo and interrupted-operation recovery are documented in
[setup recovery](setup-recovery.md).

For an already started stack, the same supported initialization can be invoked:

```bash
docker compose -f docker-compose.vps.yml run --rm init
```

The interactive browser wizard is intended for writable shared-hosting/local
configuration. Use `--from-env` for read-only VPS/container environments.

## 4. Operations notes

- This compose file does **not** run auto-migrate or auto-seed on boot.
- It does **not** use source bind mounts.
- Nginx serves `/var/www/html/html_public`, and public runtime assets land in `html_public/storage` plus `html_public/modules`.
- Runtime state is persisted via named volumes for:
  - PostgreSQL data
  - `storage/`
  - `bootstrap/cache`
  - installed modules
  - published module assets
  - private PostgreSQL credential files (root 0600; mounted only by `db-env`/`db`)

`db-env` and PHP use the same dotenv parser, including literal dollar signs and
quoted credentials. PostgreSQL uses its official `POSTGRES_*_FILE` interface;
Compose does not interpolate credential bytes. In a fresh stack a failed `db-env` keeps DB/init
and workers stopped. Treat this credential volume as private runtime state.

Initialization grants write access only to storage, bootstrap cache, installed
modules and public module assets for `www-data`. Application source and vendor
need no write permission. ZIP updates stage and back up beside the installed
module so separate named volumes cannot produce cross-device rename errors.
The image includes PDO MySQL/PostgreSQL/SQLite and phpredis for optional Redis
cache/session configurations.

## 5. Update flow

1. Pull the new release tag or unpack a fresh release checkout on the VPS.
2. Rebuild the containers:
   ```bash
   docker compose -f docker-compose.vps.yml up -d --build
   ```
3. Run migrations and refresh caches:
   ```bash
   docker compose -f docker-compose.vps.yml exec app php docker/artisan.php migrate --force
   docker compose -f docker-compose.vps.yml exec app php docker/artisan.php config:cache
   docker compose -f docker-compose.vps.yml exec app php docker/artisan.php route:cache
   docker compose -f docker-compose.vps.yml exec app php docker/artisan.php view:cache
   ```

## 6. Images

Tagged releases publish container images to GHCR:

- `ghcr.io/<owner>/testocms-app:<tag>`
- `ghcr.io/<owner>/testocms-web:<tag>`

Override them in `docker-compose.vps.yml` via:

- `TESTOCMS_APP_IMAGE`
- `TESTOCMS_WEB_IMAGE`
