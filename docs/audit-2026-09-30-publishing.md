# Аудит TestoCMS: публикация и публичный сайт

Дата: 30 сентября 2026, часовой пояс пользователя Asia/Vladivostok. Область: публикация/снятие с публикации, расписания, публичный HTML, preview, переводы, блоки, блог/рубрики/поиск, content API, техническое SEO и кэш. Развёртывание и интерфейс создания контента рассмотрены другими агентами.

## Методика и достоверность

Изучены исходники и штатные feature tests. Дополнительные HTTP-проверки через Laravel kernel и проверки workflow services выполнены в `/private/tmp/testocms-publishing-audit`, SQLite `:memory:`, CACHE_STORE=array, PHP 8.5.3. Использовалась полная копия vendor и приложения; прикладной bootstrap дополнительного класса тестов явно указывает временный каталог. Пользовательская .env, рабочая БД и прикладной код не изменялись. В окончательный отчёт попали результаты повторного запуска после полной изоляции vendor.

- Выбранные штатные проверки: **89 tests, 585 assertions — PASS** (5,290 сек.).
- Дополнительные проверки наблюдаемого ошибочного поведения: **16 tests, 75 assertions — PASS** (0,783 сек.). PASS здесь означает, что дефект воспроизведён и проверен assertions, а не что функция исправна.
- Дополнительный класс: `/private/tmp/testocms-publishing-audit/tests/Feature/PublishingAuditProbeTest.php`.
- Логи: `/private/tmp/testocms-publishing-audit/publishing-suite.log`, `publishing-probes.log`.
- AGENTS.md в репозитории и непосредственно вышестоящих каталогах не обнаружен.

Штатная выборка: SchedulerCommandTest, ContentWorkflowActionsTest, SeoSitemapLeakTest, SeoEndpointsTest, SeoHeadTest, SeoCacheHardeningTest, ContentApiTest, SiteSearchTest, I18nCorrectnessTest, CmsPublicLayoutTest, LandingBlocksTest, LandingBlockSchemaTest, PostListingBlockTest, ImageBlockCwvTest, VideoEmbedBlockTest, CarouselBlockTest, StatsBlockTest, CustomEmbedBlockTest, PostMarkdownSupportTest, PageStagePreviewTest, PublicAccessibilityTest, CookieConsentTest, AccessibilityModuleTest, BookingModuleTest, ApiAccessModuleTest.

Приоритеты: P1 — нарушение видимости/срока публикации или защиты; P2 — функциональная ошибка, неполное поведение или неверное отображение. P0 в этой области не установлен. Обнаружены **17 подтверждённых дефектов: 9 P1 и 8 P2**. Первый дефект относится к состоянию published с будущим published_at; обычные формы создают published_at=now, поэтому это отдельная защита данных/импорта, а не доказательство обхода штатного UI schedule.

## P1: дефекты публикации, доступа и кэширования

### PUB-01. Будущий published_at не защищает прямой URL страницы/поста и content detail API

**Где:** `app/Modules/Web/Services/PublicPageResolverService.php:21`, `PublicPostResolverService.php:21`; `app/Http/Controllers/Api/Content/PageController.php:61`, `PostController.php:81`. Модельный scope с корректной проверкой даты существует: `app/Models/Page.php:32`, `Post.php:33`, но detail paths его не используют.

**Воспроизведение:** создать page/post со status=published и published_at=now()+1 day, добавить перевод en; запросить `/en/future`, `/en/blog/future-post`, `/api/content/v1/pages/future?locale=en`, `/api/content/v1/posts/future-post?locale=en` с тестовым X-API-Key.

**Ожидание:** до даты публикации 404, как в списках/поиске/sitemap. **Факт:** все четыре detail endpoint возвращают 200 и контент; `/sitemaps/en.xml` такую страницу правильно исключает. Аналогичный пробел есть для status=published с published_at=NULL по точной цепочке кода, отдельно runtime NULL не проверялся.

**Рекомендация:** единый published/visibility predicate для всех публичных detail и list paths; статус, наличие даты и дата <= now проверяются до рендера/DTO. Runtime probe: `test_embargo_bypass`.

### PUB-02. Scheduler меняет БД, но продолжает выдавать старый статус через slug cache

**Где:** `app/Modules/Ops/Services/PublishSchedulerService.php:35`, `:49`, `:69`; `app/Modules/Content/Services/SlugResolverService.php:27`; `app/Modules/Web/Services/PublicPageResolverService.php:21` и аналогичный post resolver.

**Воспроизведение A:** scheduled page открывается до срока, получаем 404; это сохраняет объект page/status=scheduled в SlugResolver cache. Затем выполнить due publish через `runDue()` и снова открыть URL. **Факт:** в БД published, сайт всё ещё 404. После `SlugResolverService::flush('en','launch')` — 200.

**Воспроизведение B:** открыть published page, создать due unpublish, выполнить scheduler. **Факт:** в БД draft, HTML cache уже очищен scheduler, но следующий публичный запрос снова 200 с содержимым из старого объекта slug cache. После очистки обоих кэшей — 404.

**Ожидание:** сразу после исполнения задача меняет фактическую публичную видимость. **Причина:** scheduler чистит HTML/SEO cache, но не slug cache; SlugResolver хранит целые Eloquent objects с прежним status, а renderer доверяет им. При стандартном slug_cache_ttl эффект может сохраняться до 300 секунд; HTML, повторно созданный из старого slug объекта, может продлить неверную выдачу.

**Рекомендация:** использовать общий workflow publish/unpublish из scheduler или явно чистить slug cache всех переводов изменённой сущности. Не кэшировать статус как окончательное решение о доступе. Probes: `test_scheduler_keeps_stale_slug_status`, `test_scheduler_unpublish_leaves_public_html_after_flush`.

### PUB-03. Schedule «unpublish позже» снимает материал с публикации немедленно

**Где:** `app/Modules/Content/Services/PageWorkflowService.php:74`, `:85`; `PostWorkflowService.php:74`, `:85`.

**Воспроизведение:** для уже опубликованной page вызвать schedule(action=unpublish, due_at=tomorrow); открыть URL без прежнего HTML cache.

**Ожидание:** материал остаётся published до due_at. **Факт:** workflow сразу записывает status=scheduled независимо от action, а публичный resolver признаёт только published; URL возвращает 404 уже сейчас. Подтверждено page runtime; post имеет тот же код изменения статуса.

**Рекомендация:** будущая операция снятия не меняет текущий publication status; факт pending schedule хранить отдельно от фактической видимости. Probe: `test_schedule_unpublish_immediately_hides_content_and_keeps_cache`.

### PUB-04. Создание schedule не инвалидирует HTML и SEO cache

**Где:** `app/Modules/Content/Services/PageWorkflowService.php:74–101`, `PostWorkflowService.php:74–101`; обе функции чистят только slug cache. `PageCacheService::flushAll()` вызывается в publish/unpublish/destroy, но отсутствует после schedule.

**Воспроизведение:** открыть опубликованную страницу гостем (кэш MISS), затем создать для неё будущую задачу, после чего открыть тот же URL.

**Ожидание:** изменения текущего состояния, если workflow их делает, согласованно отражаются во всех публичных поверхностях. **Факт:** status в БД scheduled, старый URL продолжает 200/HIT; после очистки Cache становится 404. Таким образом, разные посетители/URL видят разную публичную видимость. Предварительно кэшированные sitemap/llms также не сбрасываются по этой цепочке кода; отдельный runtime для SEO при schedule не проводился.

**Рекомендация:** после исправления PUB-03 инвалидировать HTML/SEO для любых действий, меняющих actual visibility, и не смешивать фактический статус с наличием расписания. Probe тот же, что PUB-03.

### PUB-05. Истёкшая ссылка preview продолжает раскрывать черновик из HTML cache

**Где:** `app/Http/Middleware/FullPageCacheMiddleware.php:20`, `:37`; `/preview/*` не исключён; `app/Modules/Web/Services/PublicPreviewService.php:20` проверяет срок лишь при фактическом запуске controller; `app/Modules/Caching/Services/PageCacheService.php:58` хранит HTML на общий TTL.

**Воспроизведение:** создать draft и preview token с expires_at=now()+1 minute; открыть preview URL дважды, сдвинуть время на 2 минуты, снова открыть тот же URL.

**Ожидание:** после expires_at 404. **Факт:** 200/HIT, содержимое черновика видно. После очистки cache — 404. Тест использует token с сокращённым сроком для воспроизведения границы; штатный token живёт 24 часа и имеет тот же дефект в конце срока.

**Рекомендация:** исключить preview из общего кэширования, возвращать `Cache-Control: private, no-store`; проверять token на каждом запросе. X-Robots-Tag=noindex сам по себе не ограничивает доступ. Probe: `test_preview_cached_beyond_expiration`.

### PUB-06. post_listing сохраняет снятый с публикации материал в HTML страницы навсегда

**Где:** `app/Modules/Content/Services/BlockLeafRendererService.php:525` выбирает актуальные posts при рендере; `PageTranslationNormalizer.php:155` рендерит их при сохранении страницы; `PageTranslationPersisterService.php:27` сохраняет rendered_html; `resources/views/cms/page.blade.php:34`, `:101` публично выводят сохранённый HTML без повторного рендера блока.

**Воспроизведение:** опубликовать post; сохранить опубликованную page с post_listing; открыть её; выполнить PostWorkflowService::unpublish (он очищает HTML cache); открыть page ещё раз.

**Ожидание:** снятый post исчезает из списка. **Факт:** заголовок, excerpt и ссылка остаются в page, хотя прямой URL post уже 404. Истечение HTML cache не помогает: устаревшая выдача хранится в БД. Новые posts, изменения title/excerpt/slug тоже не попадут в этот блок до повторного сохранения page по той же цепочке.

**Рекомендация:** рендерить динамические блоки в public request либо материализовывать их с полноценными зависимостями и обновлять зависимые pages на mutation posts/categories. Probe: `test_post_listing_persists_unpublished_post_even_after_cache_flush`.

### PUB-07. Удалённый перевод страницы продолжает быть доступен из slug cache

**Где:** `app/Modules/Content/Services/PageTranslationPersisterService.php:37`, `PostTranslationPersisterService.php:39` удаляют отсутствующие переводы; `ContentMutationFinalizerService.php:25`, `:55` очищает только переводы, оставшиеся после refresh.

**Воспроизведение:** published page с EN и RU; открыть EN URL; update через PageContentService, передав только RU; проверить отсутствие EN translation в БД; снова открыть EN URL.

**Ожидание:** удалённый перевод сразу перестаёт публиковаться. **Факт:** EN URL возвращает 200 и удалённый текст. После полной очистки cache — 404. Page runtime подтверждён; post использует такую же prune/finalizer цепочку.

**Рекомендация:** захватывать locale/slug всех старых переводов до mutation и очищать как старые, так и новые ключи; удаление перевода должно отдельно инвалидировать slug resolution. Probe: `test_removed_translation_still_served_from_slug_cache`.

### PUB-08. HTML cache HIT убирает CSP и остальные защитные заголовки

**Где:** `bootstrap/app.php:61–62` ставит FullPageCacheMiddleware перед SecurityHeadersMiddleware; `FullPageCacheMiddleware.php:24` возвращает cached response до `$next`; `PageCacheService.php:51–56` сохраняет только Content-Type и X-Robots-Tag; `SecurityHeadersMiddleware.php:15–21` добавляет остальные headers.

**Воспроизведение:** включить CSP (`default-src 'self'`), открыть опубликованную page гостем дважды.

**Ожидание:** MISS и HIT имеют одинаковые nosniff, SAMEORIGIN, Referrer-Policy и CSP. **Факт:** MISS содержит X-Content-Type-Options, X-Frame-Options, CSP; HIT теряет их. Даже при выключенном CSP исчезают остальные заголовки защиты.

**Рекомендация:** SecurityHeadersMiddleware должен оборачивать FullPageCacheMiddleware, чтобы применять защиту к любому response; либо сохранять/восстанавливать полный безопасный набор headers. Probe: `test_html_cache_hit_loses_security_headers`.

### PUB-09. Повторное назначение срока оставляет старую задачу, которая публикует раньше нового срока

**Где:** `PageWorkflowService.php:77`, `PostWorkflowService.php:77` всегда create; `PublishSchedulerService.php:22–24` исполняет все pending tasks; publish/unpublish также не отменяют pending schedules. Отмены/редактирования расписания в public/admin routes не обнаружено.

**Воспроизведение:** schedule publish для draft через 1 час; затем повторить schedule publish того же материала через 2 дня; сдвинуть время на 2 часа, выполнить scheduler.

**Ожидание:** если второе назначение меняет срок публикации, материал остаётся скрытым до нового срока; если поддерживается несколько jobs, интерфейс должен явно показать/дать отменить обе задачи. **Факт:** в БД две pending задачи; старая уже делает status=published, новая остаётся ждать. В текущем UX нет действия отмены, поэтому исправить ошибочное раннее расписание через CMS нельзя.

**Рекомендация:** явная модель управления задачами с отменой/списком; при «перенести срок» атомарно заменить прежнюю pending задачу. Определить, что делать с pending schedule после ручной publish/unpublish. Probe: `test_replacement_schedule_retains_old_job`.

## P2: функциональность и соответствие публичного результата

### PUB-10. Кнопки пагинации блога не открывают вторую и следующие страницы

**Где:** `app/Modules/Web/Services/LocalizedSiteRouterService.php:31` вызывает blog render без request page; `PublicBlogResolverService.php:12`, `:15`, `:21` принудительно paginate с forcedPage=1; `resources/views/cms/pagination.blade.php:53` генерирует обычные ссылки `?page=N`.

**Воспроизведение:** per_page=1, два posts; открыть `/en/blog`, нажать `?page=2`. **Ожидание:** второй post/страница 2. **Факт:** снова первый post/страница 1. Прямой `/en/blog/page/2` показывает правильный второй post, но стандартные ссылки идут на query pagination. Более того, canonical у route page/2 указывает на `?page=2`, который отображает другую выборку.

**Рекомендация:** единый способ пагинации; query page учитывать в router/resolver либо генерировать path links и согласовать canonical. Probe: `test_blog_query_pagination_repeats_page_one`.

### PUB-11. Переключатель языка строит 404-ссылки при разных slug или отсутствии перевода

**Где:** `app/Modules/Core/Services/ResolvedChromeViewModelFactory.php:84–108`; header выводит links в `resources/views/cms/partials/chrome-header.blade.php:68`. Hreflang в PublicResponseSupportService построен по реальным translations и может быть корректным одновременно со сломанным UI.

**Воспроизведение:** одна page имеет EN `english-slug`, RU `russian-slug`; открыть EN. **Факт:** RU chip ведёт на `/ru/english-slug` (404), а реальный `/ru/russian-slug` — 200. При единственном RU переводе EN chip тоже ведёт на несуществующий URL.

**Ожидание:** ссылаться на реальный slug перевода; если перевода нет — скрывать кнопку или явно вести на locale home. **Рекомендация:** использовать actual hreflangs/entity translations, а не копировать path tail. Probe: `test_language_switch_uses_same_slug_instead_of_translation`; случай RU-only дополнительно увидел родительский агент в браузере.

### PUB-12. Blog/category считают записи других языков, хотя не могут их показать

**Где:** `PublicBlogResolverService.php:17–21`, `PublicCategoryResolverService.php:24–29` eager-load перевод locale, но не фильтруют posts по наличию этого перевода; `resources/views/cms/blog-index.blade.php:43–49` и `cms/category.blade.php:85` пропускают items без translation.

**Воспроизведение:** один published RU-only post, открыть `/en/blog`. **Факт:** счётчик «1 posts», одновременно «No published posts yet.». При большем количестве чужие переводы занимают slots пагинации и вызывают пустые страницы.

**Ожидание:** total и pagination отражают показываемые материалы. **Рекомендация:** whereHas(translations.locale) до paginate, либо явный согласованный fallback, если продукт должен показывать другой язык. Probe: `test_locale_blog_is_empty_despite_total` (runtime blog; category та же кодовая цепочка).

### PUB-13. Поиск в SQLite не ищет текст основного содержимого

**Где:** `PublicSearchService.php:169–175`, `:217–223`, `:258–279` — для sqlite LIKE применяется лишь title/excerpt/meta, body намеренно пропускается.

**Воспроизведение:** published post с уникальным словом только в content_plain, не в title/excerpt; запрос `/en/search?q=uniqueauditbodyword`. **Факт:** опубликованный материал не найден. Для страницы body/rendered_html пропущен аналогично.

**Ожидание:** «поиск по опубликованному контенту» на поддерживаемой SQLite установке находит текст статей и страниц. **Рекомендация:** SQLite FTS5/подходящий индекс либо приемлемый fallback для небольших сайтов; если body search не поддерживается — явно обозначить ограничение в setup/поиске. Probe: `test_sqlite_search_ignores_body`. MySQL/PostgreSQL actual SQL/runtime не проверялся.

### PUB-14. Обычный текст excerpt `]]>` ломает весь RSS XML

**Где:** `app/Http/Controllers/Web/FeedController.php:62`, `:67` вставляют excerpt непосредственно в CDATA.

**Воспроизведение:** published post с excerpt=`hello ]]> world`; GET `/feed/en.xml`, разбор SimpleXML. **Факт:** HTTP 200, но simplexml_load_string возвращает false из-за преждевременного закрытия CDATA. RSS reader не может прочитать ленту.

**Ожидание:** корректный XML при любом допустимом текстовом excerpt. **Рекомендация:** XMLWriter/DOM или корректное разделение `]]>` на последовательные CDATA sections. Probe: `test_rss_cdata_breaks_xml_and_inactive_category_feed_visible`.

### PUB-15. RSS отключённой рубрики продолжает работать

**Где:** `FeedController.php:30–43` не проверяет category.is_active; `PublicCategoryResolverService.php:23` и content category detail API корректно запрещают inactive.

**Воспроизведение:** category is_active=false с translation hidden; привязать published post; открыть `/en/category/hidden` и `/feed/en/category/hidden.xml`. **Факт:** страница 404, feed 200 и содержит post.

**Ожидание:** отключение рубрики одинаково действует на её публичные endpoints. **Рекомендация:** category feed использует active scope/guard. Published posts сами по себе не являются секретными; дефект здесь — несогласованное отключение рубрики. Probe: `test_inactive_category_rss_remains_available`.

### PUB-16. Content API возвращает 304 при несовпавшем If-None-Match

**Где:** `app/Http/Controllers/Api/Concerns/BuildsCacheableResponses.php:19–35`: после несовпавшего ETag всё равно проверяется If-Modified-Since.

**Воспроизведение:** первый GET content page, сохранить Last-Modified; повторить GET с заведомо другим If-None-Match и тем же If-Modified-Since. **Факт:** 304.

**Ожидание:** наличие If-None-Match делает его определяющим валидатором; при несовпадении должен прийти новый 200 payload. **Рекомендация:** If-Modified-Since рассматривать только если If-None-Match отсутствует, корректно разбирать ETag list/weak tags. Probe: `test_api_mismatched_etag_still_returns_304`.

### PUB-17. Публичная page добавляет демо-блоки CMS вне контента и отличается от stage preview

**Где:** `resources/views/cms/page.blade.php:6–31`, `:47–86` безусловно выводят «Что уже включено», SSR, i18n, SEO-first SSR, «Готово к публикации» для каждой страницы; home также содержит демо-текст вместо пользовательского description и кнопку в admin. `resources/views/admin/pages/stage-preview.blade.php:8` скрывает hero-shell, `:24` показывает только stageRenderedHtml.

**Воспроизведение:** создать свою landing page без этих блоков; сравнить stage preview с публичным URL. **Факт:** на публичной странице появляется большой блок рекламы/статусов CMS, которого пользователь не добавлял и не видел в stage. Это подтверждено родительским агентом в браузере и точной цепочкой шаблонов. На blog-index также зашито описание «демо-сайта TestoCMS» (`resources/views/cms/blog-index.blade.php:10`, `:23`).

**Ожидание:** публичная landing соответствует content builder и настройкам сайта; demo presentation относится только к demo template/content. **Рекомендация:** вынести demo hero в редактируемый starter template или configurable layout, stage и публичную страницу строить по одному contract. Отдельного runtime probe для stage здесь не создавалось; штатный PageStagePreviewTest проходит.

## Матрица проверенных элементов

| Элемент | Результат и доказательство | Ограничения |
|---|---|---|
| Ручная publish/unpublish для page/post через web и API | Штатный ContentWorkflowActionsTest проходит: status, audit, очистка HTML/slug cache | Взаимодействие с pending schedule дефектно, PUB-09 |
| Scheduler command | SchedulerCommandTest проходит, due post публикуется в БД | Публичная видимость после исполнения нарушена, PUB-02 |
| HTTP fallback scheduler | В коде запуск не чаще 30 сек., atomic Cache::lock; маршруты web включают middleware | Не проверено конкурентное исполнение; api-only traffic не запускает fallback |
| Черновики по обычному URL | В дополнительных probes scheduled/draft дают 404 при чистом кэше | Старый кэш и preview нарушают границы |
| Preview | Создание токена, rendered page, X-Robots-Tag=noindex проходят штатные тесты | Expiry обходится cache, PUB-05; в HTML meta robots отдельно строится из исходного SEO |
| SSR post HTML/Markdown | PostMarkdownSupportTest, ContentApiTest проходят | Подтверждено сервером, не все внешние embed/resources проверены в браузере |
| Section/columns, heading, rich_text, image, gallery, list, divider, CTA, table | BlockRenderer/LeafRenderer содержат реализации; базовые и schema tests проходят | Наличие реализации не означает ручную визуальную проверку каждого типа |
| Hero/features/testimonial/pricing/FAQ/stats | LandingBlocksTest, LandingBlockSchemaTest, StatsBlockTest проходят | Все размерности/адаптивные комбинации не проверялись |
| Carousel/video | CarouselBlockTest, VideoEmbedBlockTest проходят | Реальные сторонние видео/свайпы/таймеры не тестировались здесь |
| Image CWV | ImageBlockCwvTest проходит, img attributes/server HTML проверены | Реальная сеть/браузерный CLS/LCP не измерялись |
| custom_code_embed/html_embed_restricted | CustomEmbedBlockTest проходит; role/domain checks присутствуют | Полный аудит безопасности пользовательского JS не входил в задачу |
| post_listing | Штатный тест проверяет выбор published posts на момент renderer call | В реально сохранённой page динамика сломана, PUB-06 |
| module_widget, modules public chrome | Registry и renderer integration присутствуют; Booking/Accessibility/ApiAccess tests проходят | Установка/удаление модулей и invalidation ранее сохранённых widget HTML нуждаются в отдельном end-to-end |
| Root/home locale | I18nCorrectnessTest проверяет fallback default locale к supported | Переключатель языка PUB-11; разные варианты home URL/canonical не полностью унифицированы |
| Header/footer/theme | CmsPublicLayoutTest, PublicAccessibilityTest проходят | Наблюдается demo layout PUB-17; функциональность theme editor исследована другим агентом |
| Blog/category | Выбор published работает, route /blog/page/2 работает в probe | UI pagination PUB-10, locale totals PUB-12 |
| Site search | SiteSearchTest проходит для предусмотренных сценариев, published/date/locale фильтры в SQL | Body SQLite PUB-13; hard cap 500/type, performance большого сайта не измерена |
| Content API | Штатный ContentApiTest проходит, ключ/throttling доступны | Detail visibility PUB-01, conditional304 PUB-16; отсутствие static key намеренно открывает API |
| Sitemap, llms.txt | SeoSitemapLeakTest/SeoEndpointsTest проходят: published scope, embargo/noindex исключаются | В sitemap категорий не проверяется per-translation noindex; отдельный runtime не проводился |
| SEO head/canonical/hreflang/structured data | SeoHeadTest проходит; actual translation hreflangs сформированы | UI language switch не использует эти links, PUB-11; категорическая пагинация canonical не проверена отдельно |
| Slug history/301 | Observer создаёт историю, убирает обратный redirect, сокращает цепочки; ContentEntityCleanupTest содержит проверки cleanup (не входил в данную выборку) | Cache removed locale PUB-07; любые ручные изменения БД мимо services отдельно не гарантированы |
| HTML/SEO cache invalidation | SeoCacheHardeningTest проходит, ручной publish/update сбрасывает SEO keys | Scheduler/schedule/preview/security headers PUB-02/04/05/08 |
| RSS | Простые posts рендерятся | Invalid CDATA PUB-14, inactive category PUB-15 |
| Cookie consent/accessibility | CookieConsentTest, AccessibilityModuleTest, PublicAccessibilityTest проходят | Реальная WCAG проверка screen reader/клавиатуры здесь не выполнялась |

## Неподтверждённые риски и пределы проверки

Эти пункты не включены в число 17 подтверждённых дефектов:

1. **Timezone UX:** форма schedule использует `datetime-local` (`resources/views/admin/partials/action-forms/schedule.blade.php:16`) без timezone label/offset; server интерпретирует date в `config/app.php:68` (`APP_TIMEZONE`, default UTC). Нужна проверка browser timezone != APP_TIMEZONE, DST и ISO dates через API; вероятность пользовательской ошибки срока высокая, но runtime mismatch в этой работе не моделировался.
2. **Конкурентность scheduler:** HTTP fallback имеет lock, но `PublishSchedulerService::runDue()` загружает due rows до transaction и не блокирует/claim их (`:22–30`). Два CLI/fallback процесса потенциально исполняют одну задачу дважды. Конкурентный runtime не проводился.
3. **API-only headless installation:** fallback подключён к web middleware, API group его не содержит. Без cron/schedule:run запросы только content API не выполняют due schedules. Это предел заявленного fallback, а не ошибка при корректно настроенном cron.
4. **SEO:** sitemap categories не использует translationIsIndexable; hreflang перечисляет все translations без indexability проверки. Не выполнялась отдельная проверка ожиданий продукта по noindex category/alternate.
5. **Category pagination canonical:** category resolver не учитывает page при default canonical, что может объявить страницы пагинации дублями первой. Нужна отдельная SEO acceptance проверка.
6. **Динамические module widgets:** поскольку page сохраняет rendered_html, результаты widget тоже могут устаревать при изменении module state. Для каждого bundled module это отдельно не воспроизводилось.
7. **Проверка внешних сред:** здесь нет MySQL/PostgreSQL/Redis/production server/CDN, реального cron, browser всех блоков и полноценного нагрузочного/безопасностного аудита. PASS серверных тестов не даёт основания утверждать, что «все элементы работают».

## Рекомендуемая последовательность исправлений

1. Единый live-visibility predicate и единый workflow изменения publication state; scheduler должен использовать ту же инвалидацию, что ручные действия.
2. Восстановить границы preview, переставить security middleware вокруг кэша, инвалидировать старые и удалённые translation keys.
3. Разделить фактическую видимость и pending schedule; добавить отмену/перенос задач с явным UX.
4. Перестать материализовывать post_listing как вечный HTML без зависимостей.
5. Исправить blog pagination и locale filtering/switcher; затем RSS, API conditional validators и SQLite body search.
6. Привести builder preview и public layout к одному результату, демо-материалы вынести в редактируемые шаблоны.

Для регрессии необходимо тестировать полный путь «сохранить page → гостевой запрос → изменение/снятие post/locale/schedule → ещё один гостевой запрос», а не только статус БД или прямой вызов renderer. Именно эти переходы отсутствовали в проходящей штатной выборке и обнаружили основные дефекты.
