# Docker Local Development

This guide is for local development and evaluation only. It is not a hardened production deployment recipe.

For production Docker on a VPS, use [docs/docker-vps.md](docker-vps.md) and `docker-compose.vps.yml`.

## Requirements

- Docker Desktop 4.0+ (or Docker Engine + Compose v2)
- Free ports: `8080` (web) and `5432` is used inside Docker network only

## 1. Prepare environment

Create local docker env file from template:

```bash
cp .env.docker.example .env.docker
```

Optional: edit `.env.docker` and change:

- `APP_URL`
- `CMS_ADMIN_EMAIL` / `CMS_ADMIN_PASSWORD`
- `CMS_CONTENT_API_KEY`
- LLM provider keys (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`)

If `APP_KEY` or `CMS_CONTENT_API_KEY` are left blank, the container entrypoint generates local values on first boot.

## 2. Build and start CMS stack

```bash
docker compose up --build -d
```

This starts:

- `db` (PostgreSQL 16)
- `app` (PHP 8.4 FPM, Laravel app)
- `web` (Nginx)
- `queue` (Laravel queue worker)
- `scheduler` (Laravel scheduler worker)

On startup, `app` container automatically:

- waits for PostgreSQL
- runs migrations
- runs seeders (roles/permissions/admin user + demo content for local use)
- creates storage symlink (`html_public/storage`)

## 3. Open CMS

- Site: `http://localhost:8080`
- Admin: `http://localhost:8080/admin/login`

Admin credentials come from your `.env.docker` file. The template ships with local-only placeholder values:

- Email: `admin@example.test`
- Password: `ChangeThisLocalPassword123!`

After first start, demo content is created (if `CMS_SEED_DEMO_CONTENT=true`):

- homepage: `/en` and `/ru`
- blog index: `/en/blog` and `/ru/blog`
- demo post/category for smoke testing

## 4. Useful commands

Show service status:

```bash
docker compose ps
```

Follow logs:

```bash
docker compose logs -f app web db
```

Run artisan command:

```bash
docker compose exec app php artisan about
```

Create PAT token:

```bash
docker compose exec app php artisan cms:token:create <admin-email> integration --abilities=posts:write,pages:write,llm:generate
```

Stop stack:

```bash
docker compose down
```

Stop stack and remove DB/cache volumes (full reset):

```bash
docker compose down -v
```

## 5. First-run notes

- First `up --build` can take several minutes (image pulls + composer install).
- If you changed env values, restart containers:

```bash
docker compose down && docker compose up -d
```

## Boundary

- `docker-compose.yml` is local-only.
- It intentionally keeps bind mounts, runtime bootstrap helpers, and demo-friendly defaults.
- Do not expose it directly to the public internet.
