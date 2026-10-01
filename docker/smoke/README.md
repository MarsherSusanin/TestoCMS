# VPS smoke: чистая установка и повторный запуск

Проверка предназначена для отдельной копии репозитория с полным `vendor/`, отдельного Compose project и новых volumes. Рабочий `.env` и рабочие базы в неё не копируются. Override использует текущий код как read-only bind mount, текущий Nginx config, localhost-порт и `volume.nocopy`, чтобы повторно используемый PHP image не переносил свои runtime-данные в новые volumes.

1. Создайте временную копию. `CMS_SMOKE_DIR` должен быть новым каталогом; project `testocms-p1-vps` не должен принадлежать другому стеку.

```sh
CMS_SMOKE_DIR=$(mktemp -d /private/tmp/testocms-p1-vps.XXXXXX)
rsync -a --exclude=.git --exclude='.env*' --exclude=storage --exclude=node_modules --exclude='bootstrap/cache/*.php' ./ "$CMS_SMOKE_DIR/"
cp .env.vps.example "$CMS_SMOKE_DIR/.env"
cd "$CMS_SMOKE_DIR"
```

2. В этой копии задайте валидные `APP_KEY`, `CMS_CONTENT_API_KEY`, `DB_*` и `CMS_ADMIN_*`. Для данной проверки: `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `LARAVEL_PUBLIC_PATH=html_public`, `APP_URL=http://127.0.0.1:18973`, `SESSION_SECURE_COOKIE=false`. Пароль администратора и пароль базы можно задать с literal `$` в single quotes. Smoke читает пароль администратора из конфигурации, не печатает его и сохраняет только fingerprint хеша.

3. Выберите PHP image с PG16 tools. Актуальный `Dockerfile` использует `postgresql16-client` и `postgresql16-dev`; smoke дополнительно проверяет `pg_dump` major. Старый PHP image допустим для проверки entrypoint и исходников, если его клиент PostgreSQL соответствует серверу. Для проверки сохраняемого исходного кода override задаёт entrypoint явно, поэтому также задаёт штатную команду FPM.

```sh
export VPS_SMOKE_APP_IMAGE=testocms-app:local
export VPS_SMOKE_PORT=18973
export VPS_SMOKE_SUBNET=10.253.237.0/24

docker compose -p testocms-p1-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml config --quiet
docker compose -p testocms-p1-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml up -d --no-build
docker compose -p testocms-p1-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml exec -T app php docker/smoke/vps-check.php > first.json
```

Подсеть должна быть свободной. Она указана явно, поскольку default Docker address pools могут быть исчерпаны другими проектами. Скрипт выполняет HTTP-запросы из app к web, проверяет `/up`, `/healthz`, CSRF/login и авторизованную страницу админки, GET/HEAD/Range медиа, немедленную видимость загрузки и удаления. Файлы проверки получают случайный каталог и удаляются в `finally`. Скрипт возвращает nonzero при неуспехе.

4. Сохраните timestamps завершения init и запуска зависимых сервисов. `init` должен завершиться с кодом 0 раньше запуска app, queue и scheduler. У queue/scheduler должны оставаться стабильные running состояния без увеличения `RestartCount`.

```sh
docker inspect testocms-p1-vps-init-1 testocms-p1-vps-app-1 testocms-p1-vps-queue-1 testocms-p1-vps-scheduler-1 --format '{{.Name}} {{.State.Status}} {{.State.ExitCode}} {{.RestartCount}} {{.State.StartedAt}} {{.State.FinishedAt}}'
docker compose -p testocms-p1-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml up -d --no-build
docker compose -p testocms-p1-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml exec -T app php docker/smoke/vps-check.php > second.json
```

В `first.json`/`second.json` должны совпасть `env_sha256`, `admin_count` и `admin_hash_fingerprint`; `provided_password_valid` должен оставаться true. При fresh install с одним администратором `admin_count=1`. Повторный init сообщает, что CMS уже установлена, и не пересоздаёт пользователя. Поскольку исходный каталог смонтирован read-only и отсутствует `html_public/storage`, проверка с `physical_media_link_absent=true` подтверждает PHP fallback.

5. После сохранения результатов удалите только созданный smoke project и его volumes.

```sh
docker compose -p testocms-p1-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml down -v
```

## Результат проверки 2026-10-01

Проверка выполнена с отдельным project `testocms-p1-vps`, PostgreSQL 16.14, localhost-портом 18973, read-only `.env`, новой подсетью и новыми volumes. Первичный HTTP smoke подтвердил health 200, форму входа 200, вход 302 и авторизованные страницы 200; медиа GET/HEAD 200, Range 206, загрузку и удаление без storage symlink. Literal `$` в пароле сохранился; shell-выражение из dotenv не выполнялось; хеш `.env` не изменился.

В ходе проверки исправлены два дополнительно воспроизведённых дефекта: перенос `storage/installed` из image в fresh volumes и создание runtime lock/log файлов CLI-процессами от root при FPM от www-data. Runtime-данные исключены из image; setup, CLI workers и media preparation выполняются от общего пользователя www-data. Мастер FPM запускается штатно от root.

Первоначальная полная сборка production PHP image с `--no-dev` успешно завершилась. Следующая проверка latest source использует этот собранный PHP runtime и отдельный слой PG16 client; это повторное использование базового runtime, а не повторная компиляция финального canonical Dockerfile с pin16. Проверка окончательного canonical image остаётся отдельной release/CI проверкой.

## P2: модули FPM, local restart/reset и сложные credentials

Только в отдельной полной копии с fixture env и новыми volumes:

```sh
docker compose -p testocms-p2-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml exec -T -e CMS_DEPLOYMENT_SMOKE=1 app php docker/smoke/module-http-check.php
docker compose -p testocms-p2-vps --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml exec -T -e CMS_DEPLOYMENT_SMOKE=1 app php docker/smoke/installation-state.php
```

Module checker проходит реальный nginx/FPM login, ZIP install/update/delete,
bundled booking install/assets/delete и проверяет owner www-data/запрет PHP
assets. Он требует отсутствия этих fixture модулей; это не команда для working
stack. Read-only source/vendor не должны быть writable от www-data.

Для local используйте отдельную копию, `.env.docker` из шаблона, новую подсеть и
project; команды аналогичны, но основной файл `docker-compose.yml` и
`--env-file .env.docker`. Сохраните `installation-state.php` до/после обычного
`down`/`up`: UUID, fingerprint admin password, hash env и public root должны
совпасть. Только явный `down -v` удаляет storage/private keys, DB, modules, cache
и credentials; следующий up создаёт новый UUID с одним валидным администратором.
Host `.env` можно заполнить sentinel и сравнить bytes после каждого действия.
Неверный UUID marker должен завершать `cms:setup --from-env` с кодом 1 без reset.

Сложные credentials из штатного writer воспроизводятся так, только внутри
новой disposable копии с подготовленными storage/framework directories:

```sh
docker run --rm --network none -e CMS_DEPLOYMENT_SMOKE=1 --entrypoint php -v "$CMS_SMOKE_DIR:/var/www/html" -w /var/www/html "$VPS_SMOKE_APP_IMAGE" docker/smoke/generate-credential-fixture.php --replace-fixture-env
# Generator intentionally writes this copy's .env and expected.json; port=18976.
export VPS_SMOKE_PORT=18976
export VPS_SMOKE_SUBNET=10.253.233.0/24
docker compose -p testocms-p2-special --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml up -d --no-build
docker compose -p testocms-p2-special --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml exec -T -e CMS_DEPLOYMENT_SMOKE=1 app php docker/smoke/special-credentials.php
docker compose -p testocms-p2-special --env-file .env -f docker-compose.vps.yml -f docker/smoke/vps-compose.override.yml down -v
```

Fixture содержит $, literal ${...}, обе кавычки, backticks, $(), #, пробел и
Unicode. Выводит только boolean/fingerprints, а не secrets. Проверяется parser
→ official PostgreSQL *_FILE → реальное PDO подключение и admin password hash,
неизменность readonly env и отсутствие следов shell command execution. Пароль
базы не проходит через Compose interpolation. Отключение/ошибка db-env должна
оставлять DB/init/app/queue/scheduler созданными, но не запущенными.
