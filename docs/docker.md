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

If `APP_KEY` or `CMS_CONTENT_API_KEY` are left blank, the entrypoint generates
local values in `storage/app/private/docker-bootstrap-keys.json` (0600) in the
persistent storage volume. `.env.docker` is mounted read-only over the container's
`.env`; your host `.env` and `storage/` are not replaced. Preserve the storage,
database and cache volumes together. Missing keys or a marker/database mismatch
stop initialization with recovery guidance; they never trigger a password reset.
Use `DB_*` as the authoritative PostgreSQL settings; legacy `POSTGRES_*` entries
are no longer consumed.

## 2. Build and start CMS stack

```bash
docker compose --env-file .env.docker up --build -d
```

This starts:

- `db-env` (one-shot PHP dotenv credential preparation)
- `init` (one-shot CMS setup before FPM and workers)
- `db` (PostgreSQL 16)
- `app` (PHP 8.4 FPM, Laravel app)
- `web` (Nginx)
- `queue` (Laravel queue worker)
- `scheduler` (Laravel scheduler worker)

The `init` container automatically:

- waits for PostgreSQL
- initializes the CMS from `.env` without rewriting it (migrations, roles, administrator and optional demo content)
- creates the installed marker after successful initialization
- prepares public storage; symlink acceleration is optional because `/storage/*` also has a bounded HTTP fallback

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
docker compose --env-file .env.docker ps
```

Follow logs:

```bash
docker compose --env-file .env.docker logs -f app web db
```

Run artisan command:

```bash
docker compose --env-file .env.docker exec app php docker/artisan.php about
```

Create PAT token:

```bash
docker compose --env-file .env.docker exec app php docker/artisan.php cms:token:create <admin-email> integration --abilities=posts:write,pages:write,llm:generate
```

Stop stack:

```bash
docker compose --env-file .env.docker down
```

Stop stack and remove all database, storage, module, cache and credential volumes (full reset; all local content is deleted):

```bash
docker compose --env-file .env.docker down -v
```

## 5. First-run notes

Initialization uses `cms:setup --from-env --no-interaction`. It is idempotent:
restarting the stack preserves existing administrator credentials. Changing the
bootstrap password in `.env.docker` does not silently reset an existing account.
`cms:setup --redo --from-env --force --no-interaction` updates allowed existing
administrator fields while preserving its password and the read-only env. For an
interactive password/settings change on a writable host, use `cms:setup --redo`.
See [setup recovery](setup-recovery.md). Existing legacy installations require
explicit `cms:setup --adopt-existing --force` after migrations and verification.
Both PostgreSQL credential preparation and the application parse dotenv as data; shell expressions in credentials are never
executed. Use literal quoting/escaping according to dotenv syntax for `$` values.


- First `up --build` can take several minutes (image pulls + composer install).
- If you changed env values, restart containers:

```bash
docker compose --env-file .env.docker down && docker compose --env-file .env.docker up -d
```

## Boundary

- `docker-compose.yml` is local-only.
- It intentionally keeps bind mounts, runtime bootstrap helpers, and demo-friendly defaults.
- Do not expose it directly to the public internet.
