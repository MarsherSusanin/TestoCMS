#!/usr/bin/env sh
set -e

cd /var/www/html

ENTRYPOINT_ALLOW_DOCKER_ENV_BOOTSTRAP="${ENTRYPOINT_ALLOW_DOCKER_ENV_BOOTSTRAP:-0}"
ALLOW_RUNTIME_COMPOSER_INSTALL="${ALLOW_RUNTIME_COMPOSER_INSTALL:-0}"
COMPOSER_INSTALL_FLAGS="${COMPOSER_INSTALL_FLAGS:---no-interaction --prefer-dist --no-progress --optimize-autoloader}"

mkdir -p \
  storage/app/purifier \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache \
  html_public/modules

if [ "$ENTRYPOINT_ALLOW_DOCKER_ENV_BOOTSTRAP" = "1" ] && [ ! -f .env ] && [ -f .env.docker ]; then
  cp .env.docker .env
fi

if [ ! -f .env ]; then
  echo "[entrypoint] .env is missing. Provide .env or .env.docker before starting the container." >&2
  exit 1
fi

ensure_env_value_if_empty() {
  key="$1"
  value="$2"

  if grep -q "^${key}=" .env; then
    current="$(grep "^${key}=" .env | head -n 1 | cut -d '=' -f 2-)"
    if [ -n "$current" ]; then
      return 0
    fi
  fi

  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

if ! grep -q '^APP_KEY=base64:' .env; then
  ensure_env_value_if_empty "APP_KEY" "$(php -r 'echo "base64:" . base64_encode(random_bytes(32));')"
fi

ensure_env_value_if_empty "CMS_CONTENT_API_KEY" "$(php -r 'echo bin2hex(random_bytes(24));')"

if [ -f .env ]; then
  set -a
  . ./.env
  set +a
fi

if [ ! -f vendor/autoload.php ]; then
  if [ "$ALLOW_RUNTIME_COMPOSER_INSTALL" != "1" ]; then
    echo "[entrypoint] vendor/ not found and runtime composer install is disabled." >&2
    exit 1
  fi

  echo "[entrypoint] vendor/ not found, running composer install..."
  composer install ${COMPOSER_INSTALL_FLAGS}
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

if [ "${WAIT_FOR_DB:-1}" = "1" ]; then
  echo "[entrypoint] waiting for database ${DB_HOST:-db}:${DB_PORT:-5432}"
  export PGPASSWORD="${DB_PASSWORD:-}"
  until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-testocms}" -d "${DB_DATABASE:-testocms}" >/dev/null 2>&1; do
    sleep 2
  done
fi

if [ "${AUTO_STORAGE_LINK:-1}" = "1" ]; then
  php artisan storage:link || true
fi

mkdir -p html_public/modules

if [ "${AUTO_MIGRATE:-1}" = "1" ]; then
  echo "[entrypoint] running migrations"
  php artisan migrate --force
fi

if [ "${AUTO_SEED:-1}" = "1" ]; then
  echo "[entrypoint] running seeders"
  php artisan db:seed --force
fi

exec "$@"
