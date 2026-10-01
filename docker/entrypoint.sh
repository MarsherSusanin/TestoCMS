#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p storage/app/private storage/app/public storage/app/purifier \
  storage/framework/cache storage/framework/sessions storage/framework/views \
  bootstrap/cache modules html_public/modules

if [ ! -f .env ]; then
  echo '[entrypoint] Provide an environment file before starting the container.' >&2
  exit 1
fi
if [ ! -f vendor/autoload.php ]; then
  if [ "${ALLOW_RUNTIME_COMPOSER_INSTALL:-0}" != "1" ]; then
    echo '[entrypoint] vendor/ is missing and runtime composer install is disabled.' >&2
    exit 1
  fi
  composer install ${COMPOSER_INSTALL_FLAGS:---no-interaction --prefer-dist --no-progress --optimize-autoloader}
fi

# Runtime volumes are writable by FPM; application source remains owned by root.
chown -R www-data:www-data storage bootstrap/cache modules html_public/modules
chmod -R ug+rwX storage bootstrap/cache modules html_public/modules

exec php docker/runtime.php "$@"
