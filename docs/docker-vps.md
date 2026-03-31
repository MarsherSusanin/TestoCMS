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

## 2. Start the stack

```bash
docker compose -f docker-compose.vps.yml up -d --build
```

Services:

- `db` — PostgreSQL
- `app` — PHP-FPM / Laravel app
- `web` — Nginx
- `queue` — Laravel queue worker
- `scheduler` — `schedule:work`

## 3. One-time bootstrap

Complete installation through the setup wizard in the browser, or run the CLI installer:

```bash
docker compose -f docker-compose.vps.yml exec app php artisan cms:setup
docker compose -f docker-compose.vps.yml exec app php artisan storage:link
docker compose -f docker-compose.vps.yml exec app php artisan config:cache
docker compose -f docker-compose.vps.yml exec app php artisan route:cache
docker compose -f docker-compose.vps.yml exec app php artisan view:cache
```

## 4. Operations notes

- This compose file does **not** run auto-migrate or auto-seed on boot.
- It does **not** use source bind mounts.
- Runtime state is persisted via named volumes for:
  - PostgreSQL data
  - `storage/`
  - `bootstrap/cache`
  - installed modules
  - published module assets

## 5. Update flow

1. Pull the new release tag or unpack a fresh release checkout on the VPS.
2. Rebuild the containers:
   ```bash
   docker compose -f docker-compose.vps.yml up -d --build
   ```
3. Run migrations and refresh caches:
   ```bash
   docker compose -f docker-compose.vps.yml exec app php artisan migrate --force
   docker compose -f docker-compose.vps.yml exec app php artisan config:cache
   docker compose -f docker-compose.vps.yml exec app php artisan route:cache
   docker compose -f docker-compose.vps.yml exec app php artisan view:cache
   ```

## 6. Images

Tagged releases publish container images to GHCR:

- `ghcr.io/<owner>/testocms-app:<tag>`
- `ghcr.io/<owner>/testocms-web:<tag>`

Override them in `docker-compose.vps.yml` via:

- `TESTOCMS_APP_IMAGE`
- `TESTOCMS_WEB_IMAGE`
