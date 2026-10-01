# Безопасная публикация и расписания

Изменения устраняют PUB-01…PUB-09 из аудита 30 сентября 2026. Для установки обновления обязательна миграция `2026_10_01_000100_harden_publication_state_and_schedules.php`.

## Перед обновлением

После установки версии с этой командой выполнить `php artisan cms:schedules:check`. В исходной версии команда может отсутствовать: до основного пакета установленный bridge выполняет ту же read-only проверку в preflight. Команда только читает данные и поддерживает прежнюю схему без `cancelled_at`. Ошибка сообщает идентификаторы задач, образующих противоречивое окно, и завершает команду ненулевым кодом. Исправить эти расписания до обновления. Миграция выполняет ту же проверку **до любых изменений схемы или данных**, что важно для MySQL с неоткатываемыми DDL.

Старые дубли задач нормализуются независимо для publish и unpublish: выбирается наиболее поздний `created_at`, затем `id`; остальные сохраняются как отменённые с причиной `legacy-superseded`. Legacy `scheduled` с pending publish переводится в скрытый draft. Legacy `scheduled` только с unpublish автоматически не публикуется: команда и редактор показывают, что нужно вмешательство администратора.

## Поведение расписаний

- Назначение/перенос публикации сохраняет текущий статус: draft остаётся draft до исполнения задачи. Сохранение содержимого draft не отменяет расписание; отдельное ручное снятие отменяет задачи.
- Будущее снятие с публикации сохраняет текущую опубликованную страницу/статью доступной до указанного срока.
- Для скрытого материала unpublish можно назначить только при наличии более раннего pending publish. Всегда требуется `publish < unpublish`.
- Перенос задачи одного типа отменяет прежнюю задачу, сохраняя историю, и создаёт новую. Одновременно разрешено по одной pending publish и pending unpublish; это дополнительно защищено уникальным ограничением БД.
- Ручная публикация отменяет pending publish, сохраняя будущую unpublish. Истёкшая unpublish при этом отменяется.
- Ручное снятие с публикации, перевод в draft/review/archived и удаление отменяют обе pending задачи. Удаление сущности затем очищает связанное содержимое согласно действующему cleanup observer.
- Отмена publish у скрытого материала отменяет зависимую unpublish и возвращает scheduled в draft. Отмена задания доступна в редакторе, включая историю и причину отмены.
- API: `DELETE /api/admin/v1/pages/{page}/schedules/{schedule}` и аналогично posts. Требуется publish permission и PAT ability `pages:publish` / `posts:publish`. Задача чужой сущности даёт 404. В admin API сущность содержит `scheduled_actions`.

Все изменения и scheduler используют одинаковый порядок блокировок: сущность → задание. После получения блокировок scheduler повторно проверяет pending/executed/cancelled/due. Это исключает исполнение отменённой задачи и повторную публикацию двумя worker. Поле `pending_slot` становится NULL для выполненной/отменённой истории и 1 для активного задания. Расписания следует создавать и менять через workflow; query-builder запись в обход него требует соблюдения этого инварианта.

Scheduler получает shared update gate и не выполняется во время maintenance/exclusive core update. HTTP fallback не запускается до installed marker/новой схемы и на setup, storage, health, preview и действиях apply/rollback. Для headless API-only трафика по-прежнему нужен cron `schedule:run`: fallback работает в web pipeline.

Время `datetime-local` в редакторе интерпретируется в `APP_TIMEZONE`. ISO 8601 с offset через API преобразуется в эту зону до сохранения; editor показывает зону рядом с датами. Значения даты должны быть будущими.

## Публичная видимость и кэш

Страница/статья доступна только при свежем статусе published, непустом `published_at` и дате не позже now. Этот предикат применяется к web detail, content API detail и **до выдачи HTML cache HIT**. Удалённый перевод также проверяется по актуальной записи.

Slug cache хранит только type и translation ID; Eloquent objects со статусом и текстом не кэшируются. На каждом разрешении идентификатор сверяется с locale/path, запись и сущность загружаются снова.

HTML, slug и SEO cache используют UUID generation из singleton `public_content_versions`. Все public content mutations меняют её в той же транзакции, что сущность/переводы/категории. Старая отрисовка пишет только в заранее захваченную generation; если generation уже изменилась, HTML put пропускается. Поэтому поздняя запись старого результата не может заполнить кэш новой публикации. UUID не переиспользуется после rollback; отдельное удаление ключей является лишь дополнительной очисткой. Чтение generation и относящегося к ней контента использует primary DB, если настроены read replicas.

При добавлении новой операции mutation нужно вызвать `PublicContentVersionService::bump()` **внутри её DB transaction**. После commit допускается `PageCacheService::flushAll(false)` для best-effort очистки прежних ключей. Один лишь flush после commit не заменяет транзакционную смену generation.

SecurityHeadersMiddleware оборачивает FullPageCacheMiddleware: CSP, nosniff, SAMEORIGIN и Referrer-Policy одинаково применяются к MISS/HIT. HTTP responses content API требуют revalidation (`public, max-age=0, must-revalidate`), поэтому внешний HTTP cache не обслуживает снятый материал самостоятельно ещё 120 секунд. Публичный HTML сохраняет стандартное no-cache поведение HTTP responses; внутренний серверный HTML cache отдельно проверяет live predicate.

Preview всегда исключён из full-page cache. Успешные и ошибочные preview responses имеют `private, no-store`, no-cache/Expires и `X-Robots-Tag: noindex, nofollow`; срок токена проверяется при каждом запросе.

## Динамические блоки и content API

Если `post_listing` встречается на любом уровне section/columns, page layout рендерится по актуальным published posts при публичном HTTP запросе и формировании content PageDto. Изменение статьи/категории инвалидирует HTML generation. Страницы без таких блоков сохраняют прежний stored rendered_html, включая legacy rich content.

Для content pages используется ETag по реально сформированному JSON, включая dynamic rendered_html. Page.updated_at недостаточен для Last-Modified, поскольку статья в списке может меняться независимо; поэтому page API не использует этот валидатор. Несовпавший If-None-Match не может вернуть 304 по совпавшему If-Modified-Since.

## Проверки

Новые тесты:

- `PublicationSafetyTest`: embargo/null dates, warm HIT live guard, ID-only slug, scheduler transitions page/post, перенос/отмена/временные окна/ручные действия, REST cancellation, expiry preview, CSP cache HIT, nested dynamic listing HTML/API ETag, generation/rollback race, удалённые переводы, ISO timezone.
- `PublicationScheduleMigrationTest`: отдельная legacy SQLite schema, read-only checker, latest-per-action, hidden legacy unpublish-only, отказ противоречивого окна до DDL/data writes.
- `PublicationSchedulerConcurrencyTest`: два отдельных PHP процесса доходят до barrier после чтения одной due задачи, затем запускаются одновременно. Проверяются один executed job, одна audit запись, суммарный processed=1.

Проверки выполняются в изолированной копии и временных SQLite БД. Они не подтверждают production MySQL/PostgreSQL replica lag, Redis или настоящее совместное хранилище нескольких Docker host. Эти среды следует проверять перед соответствующим deployment.

## Интеграция P2 — 2 октября 2026

HTML, slug и SEO используют общий namespace `public-v3` вместе с DB generation. Старые сериализованные модели, страницы с автоматическими demo-блоками и прежние sitemap не разрешаются новым кодом. Preview остаётся вне этого кеша.

Изменение ссылок на медиа и удаление файла сериализуются DB singleton `media`; категории затем получают singleton `categories`, после него блокировки сущностей. Для расписаний сохраняется порядок сущность → задача; удаление сущности сначала получает media guard. SQLite получает writer lock до чтения ссылок. Эти guards создаются миграцией `2026_10_02_000100_create_content_mutation_guards.php`.

Поисковая проекция заполняется порциями по 100 в `2026_10_02_000200_add_authored_search_projections.php`, не изменяя авторское содержимое. Тексты динамических post_listing не сохраняются как авторский searchable snapshot. Public/stage показывают общий partial целиком, включая первый H1. Результаты приёмки: [P2 closure](P2-CLOSURE-2026-10-02.md).
