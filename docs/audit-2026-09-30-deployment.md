# TestoCMS: аудит установки и эксплуатации — 30 сентября 2026

Проверяемая область: local Artisan, local Docker, production Docker/VPS, shared hosting, wizard/CLI, миграции, начальные данные, конфигурация, storage, публикация модулей, упаковка релизов, обновление и восстановление. Это отчет о существующем коде: исправления в CMS не вносились.

## Метод и ограничения

- Проверены `AGENTS.md` в корне проекта и всех предках рабочего каталога; файлов с применимыми инструкциями не найдено.
- Runtime-проверки выполнены в отдельной копии `/private/tmp/testocms-audit-deployment-20260930`, с отдельными cache/storage и SQLite `:memory:`. Пользовательские `.env`, БД, installed marker, рабочие модули не изменялись.
- PHP CLI: 8.5.3; vendor уже установлен. Основные supported runtime 8.2–8.4 в этой машине не проверены.
- Запущены `SetupWizardTest`, `SetupFinalizationTest`, `CmsSetupCommandTest`, `CoreUpdatesAdminTest`, `FilesystemUpdaterSharedHostingTest`, `CoreBackupRestoreTest`, `ModulesAdminTest`, `BuildModulesCacheCommandTest`: **30 tests, 165 assertions, все прошли** после генерации ключа в временной копии.
- `config:cache`, `route:cache`, `view:cache` в копии завершились успешно.
- Отдельно выполнены запросы через настоящий Laravel HTTP Kernel с production middleware, а также targeted probes методов настройки, health check, media fallback. Browser UI, реальный HTTP-сервер и реальный GitHub release здесь не использовались.
- Первичный доступ к Docker daemon был закрыт sandbox; после разрешенной escalation доступен Docker 28.3.2. Оба compose проходят `config --quiet`. Выполнены изолированные Docker probes read-only env и прав модулей, а также PostgreSQL 16 в отдельном контейнере `--network none` с БД в tmpfs. Использован существующий local CMS image для permission/entrypoint probes, не выполнен rebuild текущего Dockerfile. Все созданные audit-контейнеры и два audit-volume удалены. MySQL не запускался, полный production Docker stack не поднимался.
- В временной копии `npm ci` (137 packages) и `npm run build` проходят: Vite 6.4.1, 53 modules, manifest и CSS/JS созданы. Есть только warning об устаревшем Browserslist data. Первичный install в sandbox завершился внутренней ошибкой npm; повтор с разрешенным network access успешен, поэтому это не классифицировано как дефект CMS.
- Ссылки `file:line` относятся к файлам репозитория. Приоритет: P1 — блокирует основной рабочий сценарий либо нарушает надежность восстановления; P2 — функциональный дефект в определенном профиле/конфигурации; P3 — документация/индикация.

## Итог по этапам

| Этап | Результат | Основание |
|---|---|---|
| Bootstrap установленного приложения | Частично проверен, основной framework загружается | tests/cache commands |
| Fresh web wizard на пустой БД | **Сломан** | HTTP 500 до первого шага, DEP-01 |
| CLI installer | Базовые tests проходят; VPS и redo имеют дефекты | DEP-02, DEP-12 |
| Docker/VPS bootstrap из runbook | **Не может закончить стандартную setup процедуру** | read-only `.env`, DEP-02 |
| Local Docker автоматическая готовность | Не совпадает с инструкцией | нет installed marker, DEP-09 |
| Shared hosting media без symlink | **Новые загрузки не публикуются** | runtime DEP-06 |
| Storage с обычным symlink | Механизм присутствует | tests и код, реальный host не проверен |
| Модули в изолированном Laravel | Базовые операции проходят | ModulesAdminTest |
| Модули в штатном VPS | **Проблема прав воспроизведена на fresh volumes** | Docker probe DEP-07 |
| Упаковка updater и preflight | **Несовместимы** | runtime parser + workflow, DEP-03 |
| Health check обновления | Есть ложный rollback и ложный успех | DEP-04, DEP-05 |
| Rollback SQLite | Проверен | CoreBackupRestoreTest |
| Rollback PostgreSQL | **Snapshot не восстановлен; ложный exit 0** | runtime PostgreSQL 16 DEP-11 |
| Cron/queue | Команды и службы заданы; supervision/живые задания не проверены | routes/console.php, compose |

## Подтвержденные проблемы

### DEP-01 — P1. Fresh web wizard требует ключ, подключенную БД и уже созданные таблицы

**Ожидается:** открытие сайта после распаковки / `composer install` запускает мастер, в котором создаются `.env`, параметры БД и таблицы.

**Фактически:** стандартная web middleware цепочка обрабатывает installer как уже установленное приложение. `APP_KEY` в шаблонах пуст; encryption middleware вызывает `MissingAppKeyException`. После добавления ключа `SESSION_DRIVER=database` требует таблицу `sessions` еще до миграций. Даже замена session на `file` не помогает: fallback scheduler на `/setup/step/1` обращается к `publish_schedules` в пустой БД. Для default PostgreSQL шаблона ошибка может быть connection refused вместо отсутствующей таблицы.

**Runtime воспроизведение:** отдельные процессы, SQLite `:memory:`, production middleware, `/setup/step/1`: без ключа HTTP 500; с ключом и file sessions HTTP 500; с ключом и database sessions HTTP 500; с ключом, file sessions и выполненным `migrate` HTTP 200. В журнале подтверждены `MissingAppKeyException` и `no such table: publish_schedules`.

**Код:** `.env.example:3`, `.env.example:64`, `.env.hosting.example:3`, `.env.hosting.example:58`; `bootstrap/app.php:50`; `app/Http/Middleware/RunPublishSchedulerFallbackMiddleware.php:15` без проверки installed/setup; `app/Modules/Ops/Services/PublishSchedulerService.php:24`; `app/Http/Controllers/SetupWizardController.php:184` запускает finalization только в конце wizard.

**Рекомендация:** bootstrap wizard должен использовать собственную file/temporary session и ключ до создания постоянной конфигурации; исключить `/setup*` из scheduler/cache/domain middleware; не делать обращений к целевой БД до шага ее настройки. Добавить end-to-end fresh installation test с отсутствующим `.env`, отсутствующим marker и пустой БД, без RefreshDatabase перед первым запросом. Release smoke сейчас проверяет только `artisan about`, что этот дефект не обнаруживает (`.github/workflows/release.yml:148`).

### DEP-02 — P1. Штатный VPS read-only `.env` несовместим с installer

**Ожидается:** runbook предлагает завершить установку через браузер или `docker compose ... exec app php artisan cms:setup`.

**Фактически:** `.env` в app/queue/scheduler монтируется `:ro`. Installer безусловно делает `file_put_contents(base_path('.env'), ...)`. CLI не завершит critical `.env` step даже при корректных ключах и БД. Web wizard дополнительно может блокироваться проверкой writable `.env`. Если ключи в шаблоне оставлены пустыми, entrypoint пытается заменить строки через `sed -i`; контейнер завершится раньше PHP-FPM из-за `set -e` и read-only mount. Документация просит заполнить ключи, но ни заполнение ключей, ни установка через CLI не устраняют основную несовместимость.

**Воспроизведение:** следовать `docs/docker-vps.md:51`, подготовить все secrets и стартовать compose, затем выполнить указанную `cms:setup`. Запись finalizer против read-only mount подтверждается цепочкой кода. Отдельно в реальном ephemeral Docker container выполнен текущий `docker/entrypoint.sh` с `.env.vps.example:ro`, без сети/БД/migrations/seed: контейнер exits 1 с `sed: can't move ... to '.env': Resource busy`. Это подтверждает cold-start отказ при пустых template keys; полный stack с валидными secrets не поднимался.

**Код:** `docker-compose.vps.yml:38`, `docker-compose.vps.yml:75`, `docker-compose.vps.yml:98`; `app/Modules/Setup/Services/SetupFinalizationService.php:42`; `app/Modules/Setup/Services/EnvWriterService.php:138`; `docker/entrypoint.sh:39`; `docs/docker-vps.md:56`.

**Рекомендация:** добавить поддерживаемый noninteractive bootstrap из готового env (`write_env=false`, миграции, роли, администратор, marker), либо отдельный writable initialization stage до постоянного read-only mount. Не ограничиваться советом убрать `:ro`: права FPM, согласованность внешнего env и процессы queue/scheduler тоже должны быть обеспечены.

### DEP-03 — P1. Updater отвергает собственные официальные release-пакеты из-за `^8.2`

**Ожидается:** updater ZIP из release workflow проходит проверку PHP совместимости на PHP 8.2+.

**Фактически:** workflow переносит composer `require.php` (`^8.2`) в `release.json.compat.php`. Preflight parser допускает caret только с тремя компонентами (`^8.2.0`). Строка `^8.2` не соответствует ни одной ветке и возвращает false. Такой пакет не может примениться даже после настройки public key и подписи.

**Runtime:** вызван настоящий private parser через Reflection на PHP 8.5.3: `^8.2 => false`, `^8.2.0 => true`, `>=8.2 => true`. Значение `^8.2` взято непосредственно из composer и release workflow.

**Код:** `composer.json:9`; `.github/workflows/release.yml:61`, `.github/workflows/release.yml:130`; `app/Modules/Updates/Services/UpdatePreflightService.php:65`, `app/Modules/Updates/Services/UpdatePreflightService.php:125`.

**Рекомендация:** использовать полноценный Composer Semver parser либо нормализовать все генерируемые ограничения и одинаково проверять их в producer/consumer. Smoke test должен загружать подписанный ZIP и вызывать настоящий preflight, а не только читать JSON. Дополнительно `cms_from` генерируется как `>=1.0.0`, а сравнивается `version_compare` как голая версия (`UpdatePreflightService.php:72`); это отдельная некорректная интерпретация формата.

### DEP-04 — P1. Health path из env templates вызывает автоматический rollback здорового обновления

**Ожидается:** после обновления проверяется endpoint приложения, доступный во время maintenance.

**Фактически:** все env templates задают `CMS_UPDATE_HEALTH_PATH=/healthz`, а framework route настроен на `/up`. Updater сначала делает `down`, затем HTTP health check, и только в `finally` делает `up`. Заголовок `X-CMS-Health-Check` ничем не обрабатывается. `/healthz` не входит в Laravel health исключение и в maintenance получает HTTP 503; check бросает исключение, запускающее rollback. Если `.env` создан wizard, он удаляет эту настройку, поэтому используется default `/up` — дефект зависит от пути настройки.

**Runtime:** после `Artisan::call('down')` запрос через production HTTP Kernel к `/healthz` с этим заголовком вернул 503. `/up` в отдельном probe вернул 200, поскольку framework исключает настоящий health endpoint из maintenance.

**Код:** `.env.vps.example:39`, аналогичная строка в остальных шаблонах; `bootstrap/app.php:38`; `app/Modules/Updates/Services/FilesystemUpdateDriver.php:49`, `app/Modules/Updates/Services/FilesystemUpdateDriver.php:58`, `app/Modules/Updates/Services/FilesystemUpdateDriver.php:98`; `app/Modules/Updates/Services/CoreUpdateHealthCheckService.php:38`, `app/Modules/Updates/Services/CoreUpdateHealthCheckService.php:58`.

**Рекомендация:** единый `/up` во всех templates и runbooks либо реализовать `/healthz` и maintenance exclusion. Проверять реальный update по HTTP в maintenance, без Http::fake для happy path.

### DEP-05 — P2. HTTP 404/403 и недоступный health endpoint считаются успешной проверкой

**Ожидается:** статус success соответствует проверенному запускаемому приложению.

**Фактически:** throw выполняется только для `serverError()`. 404, 403 и прочие неуспешные ответы допускаются. При исключении соединения и выключенном strict режиме вызывается `report`, а метод возвращает обычный success; apply выставляет `status=applied`, без явного `health_unverified` в результате. Это маскирует неверный health URL, ACL и отсутствие реального подтверждения работоспособности.

**Runtime:** `Http::fake` вернул 404, реальный `CoreUpdateHealthCheckService::runHealthCheck()` завершился успешно. Это проверка логики, не доступности внешнего сайта.

**Код:** `app/Modules/Updates/Services/CoreUpdateHealthCheckService.php:41`, `app/Modules/Updates/Services/CoreUpdateHealthCheckService.php:58`; `app/Modules/Updates/Services/FilesystemUpdateDriver.php:60`.

**Рекомендация:** требовать ожидаемый 2xx и корректный payload/release ID. Допускаемый инфраструктурой непроверенный результат отображать отдельно как unverified с предупреждением оператору, по аналогии с rollback.

### DEP-06 — P1. Shared-hosting fallback без symlink не публикует новые uploads

**Ожидается:** если хостинг запрещает symlink, загружаемое после установки медиа доступно по выдаваемому `/storage/...` URL.

**Фактически:** finalizer копирует `storage/app/public` в physical `public/storage` только один раз. Затем все uploads записываются в `storage/app/public`; controller формирует обычный URL, но публичная копия больше не синхронизируется. Директория после fresh setup обычно пуста; все дальнейшие изображения получают 404. Повторный `linkOrCopyPublicStorage` тоже не помогает: наличие physical directory приводит к раннему return.

**Runtime:** запущен PHP с `-d disable_functions=symlink`; вызван настоящий finalizer helper, затем `Storage::disk('public')->put('assets/audit-new.txt', ...)`. `storage/app/public/assets/audit-new.txt` существует, `public/storage/assets/audit-new.txt` отсутствует.

**Код:** `app/Modules/Setup/Services/SetupFinalizationService.php:218`, `app/Modules/Setup/Services/SetupFinalizationService.php:224`, `app/Modules/Setup/Services/SetupFinalizationService.php:243`; `app/Http/Controllers/Admin/AssetCrudController.php:56`; аналогично `app/Http/Controllers/Api/Admin/AssetController.php:63`.

**Рекомендация:** для данного профиля использовать реальный public storage root, контролируемую выдачу файлов через application route или синхронизацию каждого upload/update/delete. Однократная копия не является заменой symlink. Нельзя объявлять storage step успешно завершенным без post-install upload smoke.

### DEP-07 — P2. VPS directories модулей и публичных assets не выдаются PHP-FPM на запись

**Ожидается:** после штатного Docker/VPS deploy администратор устанавливает bundled/ZIP module из UI.

**Фактически:** Dockerfile копирует код от root и chown делает лишь для storage и bootstrap/cache. EntryPoint создает `html_public/modules` от root и снова chown делает только для storage/cache. Два named volumes (`modules_data`, `module_public_assets`) не получают www-data ownership. Установка через FPM требует создания каталогов в обоих местах и будет падать на Permission denied при стандартных root-owned 0755 directories. CLI root может успешно установить модуль, скрывая UI-дефект.

**Runtime:** создан ephemeral контейнер существующего `testocms-app:local`, без entrypoint и сети, с двумя отдельными fresh named volumes, команда выполнялась от `www-data` (uid 82). Оба mount root — `root:root`, 0755; `mkdir` в `modules` и `html_public/modules` вернул Permission denied. EntryPoint текущего дерева не chown-ит эти пути, поэтому стандартная initialization не исправляет результат. Это не полный production stack/rebuild test, но модель чистых volume и PHP-FPM пользователя проверена в Docker.

**Код:** `Dockerfile:44`, `Dockerfile:48`; `docker/entrypoint.sh:15`, `docker/entrypoint.sh:67`; `docker-compose.vps.yml:41`, `docker-compose.vps.yml:42`; `app/Modules/Extensibility/Services/ModuleInstallerService.php:63`; `app/Modules/Extensibility/Services/ModulePublicAssetsPublisherService.php:42`.

**Рекомендация:** создавать/chown modules и public/modules как часть initialization, тестировать установку от www-data с чистыми named volumes. Не делать весь codebase writable ради устранения этого дефекта.

### DEP-08 — P2. Wizard/CLI стирает заранее подготовленные production integrations и ключи

**Ожидается:** параметры mail, LLM, updater, DB schema/SSL и public path, подготовленные по deployment guide, сохраняются при bootstrap; `--redo` не должен менять encryption key без контролируемого перехода.

**Фактически:** writer полностью строит `.env` заново: mail становится `log`, mail credentials `null`, LLM keys пустые, новые APP_KEY и Content API key генерируются каждый раз. `CMS_UPDATE_*`, `CMS_VERSION`, `DB_SCHEMA`, `DB_SSLMODE` не сохраняются. VPS guide просит установить mail/LLM secrets до `cms:setup`, но installer их удалит при writable варианте env. `--redo` меняет APP_KEY без `APP_PREVIOUS_KEYS`, поэтому старые encrypted cookies/sessions и другие encrypted данные теряют совместимость. В совокупности с DEP-02 документированный VPS flow вообще не проходит.

**Runtime:** сгенерирован `.env` через настоящий `buildEnvContent`: SMTP не присутствует, `DB_SCHEMA` не присутствует, `CMS_UPDATE_PUBLIC_KEY` не присутствует. Генерация нового key видна непосредственно в методе; чужие секреты не использовались.

**Код:** `app/Modules/Setup/Services/EnvWriterService.php:20`, `app/Modules/Setup/Services/EnvWriterService.php:89`, `app/Modules/Setup/Services/EnvWriterService.php:112`, `app/Modules/Setup/Services/EnvWriterService.php:138`; `docs/docker-vps.md:25`; `app/Modules/Setup/Services/SetupFinalizationService.php:42`.

**Рекомендация:** писать только поля, явно изменяемые installer; сохранить неизвестные и integration keys. APP_KEY генерировать только при отсутствии. При явной ротации поддержать предыдущие ключи и предупреждение о затрагиваемых encrypted данных.

### DEP-09 — P2. Local Docker делает migrate/seed, но не становится установленной CMS

**Ожидается:** после `docker compose up --build -d` доступны demo site и admin login с env credentials, как написано в `docs/docker.md`.

**Фактически:** entrypoint запускает migrate и db:seed, но нигде не создает `storage/installed`. На чистом checkout storage marker отсутствует. `RedirectToSetupWizardMiddleware` отправит и `/admin/login`, и frontend на wizard. Пользователь должен повторно установить CMS; при этом default profile shared_hosting меняет public path и secrets (DEP-08/DEP-10).

**Код:** `docker/entrypoint.sh:84`, `docker/entrypoint.sh:89`; `database/seeders/DatabaseSeeder.php:15`; `app/Modules/Setup/Services/EnvWriterService.php:156`; `app/Http/Middleware/RedirectToSetupWizardMiddleware.php:41`; `docs/docker.md:53`.

**Рекомендация:** один последовательный initialization pipeline с ролями, admin, migrations и marker либо прямо запускать wizard и отказаться от обещания автоматической готовности. Marker создается только после всех успешных critical steps.

### DEP-10 — P2. В local Artisan/Docker мастер предлагает default public path вне приложения

**Ожидается:** документированный local setup сохраняет `html_public`, используемый `artisan serve` и локальным Nginx.

**Фактически:** local profile отсутствует; default всегда shared_hosting, т.е. `LARAVEL_PUBLIC_PATH=../public_html`, `APP_ENV=production`, `QUEUE_CONNECTION=sync`. Пользователь local Artisan, следующий default wizard, переводит framework public root в соседний каталог, который не является подготовленным web root. Local Docker Nginx продолжает отдавать старый `html_public`, а storage/module publishing происходят в другой directory. Статические assets модулей/медиа расходятся с Nginx, следующий `artisan serve` зависит от наличия и наполнения соседнего public_html.

**Код:** `app/Modules/Setup/Services/DeploymentProfileService.php:17`, `app/Modules/Setup/Services/DeploymentProfileService.php:34`; `app/Modules/Setup/Services/EnvWriterService.php:32`, `app/Modules/Setup/Services/EnvWriterService.php:37`; `app/Modules/Setup/Services/SetupFinalizationService.php:252`; `docker/nginx/default.conf:5`.

**Рекомендация:** explicit local profile или detection по existing path/deployment config. Согласованность actual web root, configured public_path и writable asset directories должна проверяться перед сохранением.

### DEP-11 — P1. PostgreSQL rollback импортирует dump поверх существующей БД, не восстанавливая snapshot

**Ожидается:** rollback возвращает schema и content к сохраненной версии и сообщает неуспех при SQL ошибке.

**Фактически:** backup создается обычным plain `pg_dump` без clean/create. Restore выполняет `psql -f` в той же существующей БД, без ее очистки, без `ON_ERROR_STOP`. CREATE TABLE для уже существующих таблиц и COPY для уже существующих primary keys могут конфликтовать; дополнительные schema/data, созданные новым release, остаются. `runProcess` смотрит только exit code. Стандартный psql продолжает исполнение после SQL ошибок; это позволяет записать rollback success при неполном или отсутствующем DB restore.

**Runtime:** PostgreSQL 16 в отдельном Docker контейнере без сети и портов, data в tmpfs. Созданы таблица `cms_audit_probe` и row `title=before`; выполнен plain `pg_dump -f`. После backup row изменена на `after`, добавлены column и новая таблица. Restore выполнен `psql -f`, как в CMS. Получены `relation already exists`, duplicate primary key и multiple primary keys; **exit code psql=0**, row осталась `after`, column и новая таблица остались. Значит данные/schema не восстановились, а exit-code check считает операцию успешной. Сам application-level CoreBackupService с remote PostgreSQL не вызывался: проверены точные generated CLI semantics в disposable БД. Поведение согласуется с [официальным описанием psql ON_ERROR_STOP](https://www.postgresql.org/docs/current/app-psql.html#APP-PSQL-VARIABLES) и [описанием pg_dump clean](https://www.postgresql.org/docs/current/app-pgdump.html).

**Код:** `app/Modules/Updates/Services/CoreBackupService.php:174`, `app/Modules/Updates/Services/CoreBackupService.php:235`, `app/Modules/Updates/Services/CoreBackupService.php:310`; `app/Modules/Updates/Services/FilesystemUpdateDriver.php:147`. Существующий `CoreBackupRestoreTest` проверяет только SQLite (`tests/Feature/CoreBackupRestoreTest.php:12`).

**Шаги для integration regression:** disposable PostgreSQL; создать backup; выполнить migration, меняющую существующую таблицу, и изменить row; restore; сравнить table definition, значения rows, migrations и отсутствие added tables с baseline. Добавить намеренную SQL ошибку в fixture dump и требовать rollback failure.

**Рекомендация:** заранее определить восстановление в чистую БД/schema либо надежный custom-format restore с clean/create и проверками; включить fail-fast и контроль ошибок. Backup делать при гарантированном отсутствии конкурирующей записи: сейчас createBackup предшествует `down`, а queue и scheduler maintenance режим не останавливает. Не обещать безопасный DB rollback без интеграционных проверок обоих production drivers.

### DEP-12 — P2. `cms:setup --redo` снимает установленный статус до проверок и без восстановления при ошибке

**Ожидается:** неудачная повторная настройка не закрывает существующий рабочий frontend/admin.

**Фактически:** сразу после подтверждения CLI удаляет marker. Затем может вернуть FAILURE из system check, проверки подключения или finalization. Marker не восстанавливается; прежняя работающая CMS перенаправляет посетителей и администратора на открытый setup wizard. Даже простая отмена терминала после подтверждения уже меняет эксплуатационное состояние.

**Код:** `app/Console/Commands/CmsSetupCommand.php:34`, `app/Console/Commands/CmsSetupCommand.php:38`, `app/Console/Commands/CmsSetupCommand.php:51`; `app/Http/Middleware/RedirectToSetupWizardMiddleware.php:41`.

**Рекомендация:** не удалять marker до успешного завершения, выполнять redo как отдельное явно авторизованное admin/CLI действие с сохранением прежней конфигурации и атомарным commit. Для runtime проверки использовать isolated marker и mock failed SystemCheck, затем проверить сохранение installed status.

### DEP-13 — P2. Docker shell sourcing меняет пароли с `$` и выполняет shell syntax из `.env`

**Ожидается:** `.env` используется как dotenv data; строковые credentials сохраняют исходные байты.

**Фактически:** entrypoint выполняет `. ./.env` в shell. Double-quoted value `DB_PASSWORD="audit$UNDEFINED_AUDIT_TOKEN"` превращается в `audit`, хотя dotenv value без `${...}` может содержать буквальный доллар. Shell command substitution также исполняется при `$()` в double quotes. `EnvWriterService::quoteEnvValue` экранирует backslash/quotes/newline, но не shell `$`/backticks, поскольку это dotenv writer. Неэкранированные shell метасимволы в ручном env могут даже завершить entrypoint syntax error.

**Runtime:** безопасный dummy env в temporary directory, source через `sh`: dollar password потерял суффикс; никаких внешних действий или пользовательских credentials не использовалось.

**Код:** `docker/entrypoint.sh:51`, `docker/entrypoint.sh:53`; `app/Modules/Setup/Services/EnvWriterService.php:230`.

**Рекомендация:** не выполнять dotenv как shell script; передавать env через Compose/env_file или извлекать нужные DB поля надежным dotenv parser. Проверить буквальные `$`, кавычки, `#`, `&`, backticks, пробелы в credentials на всем пути writer → entrypoint → PDO.

## Дополнительные эксплуатационные риски и несоответствия

1. **File-only backup на restricted host.** Если `proc_open` отключен, CoreBackupService возвращает `db_dump_path=null` и только записывает warning в application log (`CoreBackupService.php:158`). Update с migrations продолжает работать, rollback возвращает только код. В UI оператору требуется явное предупреждение до Apply, иначе обещание backup/rollback в README шире фактической защиты. Если proc_open включен, но `mysqldump`/`pg_dump` отсутствует, fallback отсутствует и update падает на backup; бинарники не входят в wizard SystemCheck.
2. **Version metadata при fresh release.** `CMS_VERSION=1.0.0` остается фиксированным в templates/config fallback; shared artifact не получает release.json. Workflow не записывает tag version в `config/updates.php` или hosting env. Fresh install нового tag может показывать 1.0.0 и неправильно оценивать downgrade compatibility. Требуется проверить реальный tag ZIP; такой артефакт не скачивался.
3. **README update flow неполный.** Built-in manual upload требует updater artifact, configured Ed25519 public key и detached signature (`CoreUpdateService.php:143`), а общий README update section говорит загрузить production `.zip` и не описывает ключ/подпись. `docs/shared-hosting-deploy.md` различает два ZIP, но тоже не дает полного signature provisioning flow. Подписанный update — правильная защита; документацию нужно привести в соответствие.
4. **Optimization step скрывает exit code.** Finalizer отмечает critical migrations и noncritical cache commands успешными, если нет exception, но не проверяет Artisan return code (`SetupFinalizationService.php:59`, `SetupFinalizationService.php:96`). Storage/Optimization warning не входит в `hasErrors`; итог может быть «успешно установлено» с неработающим storage. В копии сами cache commands прошли; намеренная failure simulation здесь не выполнена.
5. **Persistent bootstrap cache при VPS update.** `bootstrap_cache` — named volume; после rebuild все services стартуют до runbook cache refresh, поэтому могут использовать old route/module/config cache. Документация не требует рестарта long-running queue/scheduler после cache refresh. Нужно отдельное integration test update с измененным route/provider/job, чтобы оценить stale workers и downtime.
6. **Backend connections.** Installer проверяет PDO login, но не проверяет privileges на migration/DDL, указанную schema/search_path, dump/restore permission и согласованность установленной версии DB с Laravel 12. Все штатные tests SQLite; успешные unit tests не доказывают работу shared-hosting MySQL 5.7/PostgreSQL и rollback.
7. **Build assets.** Dockerfile не выполняет npm build. В этой рабочей копии `html_public/build` отсутствует; release workflow перед Docker build собирает его, а чистый direct VPS build требует отдельной проверки. Активные admin/site templates в основном используют свой runtime/inline assets; отсутствие Vite directory само по себе не классифицировано как доказанный поломанный UI.

## Что проверенно работает

- Framework/application container загружается; после keygen тестовые feature scenarios выполняются.
- В тестовых условиях wizard сохраняет шаги, finalizer получает explicit credentials, provisioner заменяет bootstrap admin, login возможен; CLI умеет выбрать production profile.
- Authentication/permission access к update admin закрывает гостевой и unauthorized доступ в соответствующих tests.
- Core update manual signature validation, basic package upload/check/apply flows в existing tests работают на их fixtures; official release compat отдельно несовместима (DEP-03).
- File snapshot/shared public root restore и SQLite DB snapshot restore покрыты passing tests. Пропавший referenced database dump корректно приводит к исключению.
- Module installation/admin operations/cache command проходят на writable temporary directories; публикация modules ограничивает executable file extensions.
- Routes и Blade templates кешируются успешно. Это проверка компиляции, не выполнения всех динамических веток. Frontend dependency install и production Vite build успешны; обе compose схемы синтаксически валидны.
- Cron schedule задает `cms:publish-due` каждую минуту; Docker compose включает отдельные database/queue/scheduler services и persistent storage. Их реальная надежность, retries, restart/update race и долгосрочная нагрузка здесь не проверены.

## Рекомендуемый порядок устранения и приемки

1. Исправить cold-start wizard и VPS initialization; пройти чистую установку без `.env`/marker/таблиц по каждому документированному пути.
2. Исправить producer/consumer compat; проверить настоящий release ZIP + detached signature + public key через UI и preflight.
3. Исправить health path, отчет unverified и production rollback PostgreSQL/MySQL; пройти disposable DB update → failure → auto rollback с schema/content comparison.
4. Проверить shared hosting без symlink и VPS от www-data: upload → URL 200, delete → URL 404, bundled module install → public CSS/JS 200.
5. Сохранить integrations/env secrets при setup/redo, обеспечить атомарный redo и корректный local profile.
6. Проверить обновление Docker с persistent volumes и long-running worker restart, then обновить runbooks и release smoke tests под реальные fresh/user-facing сценарии.

## Артефакты воспроизведения

В `/private/tmp/testocms-audit-deployment-20260930` сохранены `tests-deployment.log`, `junit-deployment.xml`, `audit-runtime.php`, `audit-request.php`, `audit-media.php`, `pg-restore.log`, `npm-install-escalated.log`, `npm-build.log`, логи cache commands. В этих fixtures применялись только случайные временные ключи и dummy credentials. Каталог временный; для постоянного regression suite сценарии нужно перенести в tests после устранения дефектов. Основной repo изменен только этим отчетом.
