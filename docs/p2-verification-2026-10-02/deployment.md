# DEP P2 — реализация и проверки 2026-10-02

Все изменения проверялись на disposable полных копиях `/private/tmp/testocms-p2-deployment`, с собственными env, PostgreSQL 16, Docker projects/networks/volumes и localhost ports. Рабочие `.env`/БД не менялись. Проверенные файлы source и vendor смонтированы read-only; vendor — полная копия, не symlink. Нативный старый image `testocms-app:local` использовался как PHP 8.4.19 runtime с актуальными исходниками/entrypoint. Эти проверки не являются повторной полной сборкой финального canonical Dockerfile; итоговая release/matrix проверяется корневым агентом отдельно.

| ID | Итоговый контракт | Регрессии / runtime evidence |
| --- | --- | --- |
| DEP-05 | Только HTTP 2xx подтверждает health; redirects/4xx/5xx — failure; unreachable без strict — health_unverified, maintenance и actionable UI/journal | Корневой пакет: `UpdateSafetyRegressionTest`, `FilesystemUpdaterSharedHostingTest`, `CoreUpdatesAdminTest`; `CoreUpdateHealthCheckService` / `FilesystemUpdateDriver`. HTTP health 200 также в VPS/local JSON. |
| DEP-07 | Init даёт www-data запись в named volumes modules/assets, source/vendor остаются readonly. ZIP update сохраняет старый каталог до успешного завершения, stage/backup находятся на том же modules volume | `ModuleUpdateFilesystemTest`: успешное обновление и rollback code/assets/DB version при injected publication failure. Реальные nginx/FPM login→ZIP install→update→delete, bundled booking install/assets/delete: `deployment-module-http.json`; источник: `docker/smoke/module-http-check.php`. |
| DEP-08 | Writer заменяет только явно заданные setup поля, сохраняет неизвестные integrations/comments/quoting, APP_KEY/API key и заранее заданный public root | `EnvWriterServiceTest`, `SetupDeploymentSafetyTest`, `SetupFromEnvironmentTest`; `deployment-tests.log`; readonly actual env hashes в VPS/local/special JSON. P1 сохранён. |
| DEP-09 | Local отдельный readonly .env.docker; db-env→DB→init→FPM/workers gating; persistent private generated keys; bound DB UUID marker; повтор без reset | `SetupFromEnvironmentTest`, `SetupDeploymentSafetyTest`; `deployment-local-first.json`, `deployment-local-repeat.json` идентичны после down/up; `deployment-local-reset.json` — новый UUID после down -v; `deployment-local-contracts.json`; неверный marker CLI exit1: `deployment-local-mismatch.txt`; failed db-env gating: `deployment-bad-env-gating.json`. |
| DEP-10 | Explicit local/default detection, html_public для Artisan/local Docker; существующий кастомный public root сохраняется; wizard review показывает абсолютный root; до изменения env проверяются index.php и writable module directories | `SetupDeploymentSafetyTest`, `CmsSetupCommandTest`, `SetupWizardTest`, `SetupFinalizationTest`; public_root/environment/profile в local JSON; `DeploymentProfileService`, `PublicRootValidationService`, `SetupWizardController`. |
| DEP-12 | --redo только текущего экземпляра, fixed DB identity/root/key, settings + тот же admin staged, rollback env/admin/cache/marker/sessions/tokens; private durable journal UUID + exclusive gate + maintenance; recover matching operation; --from-env никогда не меняет пароль | `SetupDeploymentSafetyTest`: отказ до mutation; journal/env/admin/config failures; journal transition failure до и после DB commit; recovery failure сохраняет maintenance+journal; неверный operation отказ; readonly пароль сохранён; source-env change не скрывается cached config. `SetupRedoCrashTest`: настоящий fresh PHP process exit73 после env mutation, новый process восстанавливает DB hash/env/marker, journal 0600, down снят. Actual readonly local redo: `deployment-local-redo.txt`, `deployment-local-redo-state.json`, `deployment-local-contracts.json`. |
| DEP-13 | Dotenv не исполняется как shell; один PHP parser для writer/runtime/DB credential preparation; credentials не проходят через Compose dollar interpolation; official PostgreSQL *_FILE + private root0600 files | `EnvWriterServiceTest`; `deployment-special-credentials.json`: actual fresh PG16 PDO authenticated, admin password valid, env unchanged, shell substitutions не исполнены. Fixture содержит $, ${...}, обе кавычки, backticks, $(), #, пробел и Unicode. `generate-credential-fixture.php` → `database-env.php` → PostgreSQL *_FILE → `special-credentials.php`. |

Focused PHPUnit: **32 tests / 214 assertions PASS**, PHP 8.4.19 (`deployment-tests.log`). Scoped Pint: **28 files PASS**. Новые fault tests проверяют причинно различные состояния, а не только успешный путь. Полные multi-driver/multi-PHP, updater/health/admin UI и frontend сборки входят в отчёт корневого агента.

Дополнительные реальные находки при приёмке, исправлены:

- EXDEV: раньше ZIP update пытался rename module из отдельного modules volume в storage volume. Sibling staging/backup исправил реальный FPM update; injected publish failure восстанавливает старый version/assets.
- Повторный Docker init выполнял config:clear, затем idempotent setup возвращал success без cache rebuild. Fresh CLI терял private local APP_KEY. Runtime теперь всегда config:cache после успешного AUTO_SETUP; restart state идентичен.
- Writer использует \$ для literal доллар phpdotenv, Compose превращал эти байты в двойной доллар PostgreSQL password. db-env и official *_FILE исключают несовместимую интерполяцию; полный special credential PDO smoke проходит.
- Embedded config:cache загружает fresh Laravel Application и меняет глобальные Container/Facade/Model bindings. Installer восстанавливает исходные bindings после команды, сохраняя environment/storage/DB scope следующих этапов; actual redo использует отдельный fresh process.

Private journal содержит secrets и не включён в evidence. Исходные env, resolved Compose environments и credentials не публикуются. Fixture JSON содержит только boolean/fingerprints/nonsecret installation UUID. Runtime credential files дополнительно проверены как root:root 0600; в DB/app не созданы файлы-следы shell execution. Source permissions: `deployment-source-permissions.json` (app/vendor/bootstrap app false).

Повторяемые команды: `docker/smoke/README.md`. Эксплуатационные инструкции: `docs/docker.md`, `docs/docker-vps.md`, `docs/setup-recovery.md`. Legacy adoption — только explicit `cms:setup --adopt-existing --force` после schema/admin/source-env verification; HTTP legacy upgrade не заблокирован. Reset — только явное удаление disposable volumes; готовность/marker mismatch не вызывает reset.

Платформенная граница permissions: Docker Desktop virtiofs отображает bind env
как owner выполняющего процесса (fixture www-data uid82 видел file owner82 и
mode0600). Это не доказательство чтения Linux root-owned0600 файла от FPM.
VPS guide явно требует readable env для runtime group (0640, deploy user owner,
matching numeric FPM GID), сохраняя readonly mount. Root0600 относится только к
private PostgreSQL credential volume. На Linux это требование проверяется при
подготовке env; readonly root0600 bind не является поддерживаемой конфигурацией.

## Canonical no-dev production image — дополнительная финальная приёмка

Полный canonical Dockerfile действительно собран; PHP сервисы используют code и
vendor из image, без source/vendor bind override. Project
`testocms-p2-production-smoke`, собственные новые volumes, PostgreSQL16,
localhost18983. Nginx получил readonly public artifact, включая compiled build,
скопированный из canonical image. Artifact byte-equals isolated frontend build
`/private/tmp/testocms-p2-root/html_public/build/manifest.json` (SHA256
`6abdfc7c044507a2151b258d22099845dcc3178a7130f71937ad05db8021c941`).
Все referenced CSS/JS assets отвечают HTTP200 с точными bytes image.

Canonical cold init успешен: db-env/init exit0; app/queue/scheduler стартуют после
init, RestartCount0, один валидный администратор, UUID identity bound и readonly
env hash сохранён. Повторный up идентичен по полному installation-state JSON.
Реальные FPM ZIP install/update/delete и bundled booking assets/delete проходят
на named volumes; executable assets недоступны. В image нет baked `.env` или
installed marker. PHP8.4.26, no-dev vendor без PHPUnit/Faker, PDO
SQLite/MySQL/PostgreSQL, phpredis, pg_dump16.15; source/vendor не writable www-data.
Hashes ContentTemplateController/LlmTextNormalizer/CategoryContentService и DEP
service файлов сверены с frozen workspace.

Cold/modules: `deployment-production-cold.json`, `-repeat.json`, `-module-http.json`,
`-runtime.json`, `-startup.json`, `-image.json`, `-assets.json`, `-contracts.json`.
Эта проверка выполнена с final production build до последнего ADM09 исправления
ошибки purge; DEP код/зависимости в последнем build идентичны. После ADM09 сервисы
ещё раз force-recreated с последним image: manifest list
`sha256:4d5c2e9c3766a76f183d7605202fb8379b2bb07ddc2efecd904716950be9a720`.
AssetDeletionService image hash совпадает с workspace; установка/пароль/env не
изменились. На последнем image повторён реальный HTTP API media cycle:
upload201 → GET200 точные bytes → HEAD200 → Range206 → DELETE204 → public404,
DB/disk удалены. Временный scoped PAT удалён. Evidence:
`deployment-production-final-contracts.json`, `-final-runtime.json`, `-final-state.json`,
`deployment-production-media-http.json`. Error503 после purge failure проверяется
отдельным root adapter regression; этот HTTP smoke проверяет обычный успешный путь.

Таким образом ограничение старого runtime/source bind из первой проверки снято
этой дополнительной canonical no-dev проверкой. Linux env readability requirement
из предыдущего раздела сохраняется: это тест Docker Desktop, не удалённого Linux.
