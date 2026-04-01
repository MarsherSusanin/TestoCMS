# Deployment Notes

TestoCMS supports two production paths:

1. shared hosting (primary recommendation)
2. Docker on your own VPS (secondary option)

The repository also includes a separate local Docker stack for development only.

## PHP

- Recommended: PHP 8.3+
- Enable OPcache in production (`opcache.enable=1`, `opcache.validate_timestamps=0` for immutable deploys)

## Web server caching

### Nginx fastcgi cache (optional)

- Cache only anonymous HTML routes
- Bypass cache for authenticated sessions and `/admin`, `/api`
- Respect app-side cache invalidation on publish/update

### Apache

- Use mod_rewrite for Laravel front controller
- On shared hosting, treat `html_public/` as the canonical web root source and sync its contents into the host-managed `public_html/`
- Add compression (gzip/brotli) and static asset cache headers

## Application cache

- Full-page cache middleware caches anonymous HTML responses
- Publish/update and scheduler events flush page cache

## Scheduler

- Primary: cron executes `php artisan schedule:run` every minute
- Fallback: on web requests, due schedules are processed periodically

## Queue workers

- Shared hosting baseline: `QUEUE_CONNECTION=sync`
- Docker/VPS baseline: `QUEUE_CONNECTION=database`
- For Docker/VPS run at least one supervised queue worker (`php artisan queue:work --tries=3 --timeout=120`)

## Security headers

- CSP is configurable in `config/security.php`
- Additional headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

## Safe production baseline

- Keep `CMS_SEED_DEMO_CONTENT=false`
- Generate unique `APP_KEY` and `CMS_CONTENT_API_KEY`
- Create your own admin credentials during setup and rotate any bootstrap passwords after first login
- Back up both uploaded files and the database before updates
- Shared hosting profile expects `LARAVEL_PUBLIC_PATH=../public_html`
- Treat `docker-compose.yml` as local-only and `docker-compose.vps.yml` as the Docker/VPS production recipe
