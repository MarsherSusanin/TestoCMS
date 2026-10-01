# Аудит админки и создания контента TestoCMS — 30 сентября 2026

## Результат

Основные сценарии редактирования и штатные проверки проходят, но CMS нельзя считать полностью корректной: подтверждены **13 дефектов**, включая публикацию без разрешения, продолжение работы заблокированного пользователя и потерю переводов при PATCH API. Это аудит текущего кода, не исправление.

Проверки: изолированная копия `/private/tmp/testocms-admin-audit`, SQLite `:memory:`, PHP 8.5.3, PHPUnit 11.5.55. Для копии сгенерирован отдельный APP_KEY. При запуске с пустым ключом 52 теста сначала упали на MissingAppKeyException; после штатного key:generate эти ошибки исчезли, поэтому они не включены в дефекты CMS. Пользовательские `.env` и БД не менялись. Первый штатный запуск ошибочно выполнен из рабочего checkout, но использовал тестовую БД `:memory:`; tracked изменения приложения не обнаружены. Все дополнительные проверки выполнялись в копии.

**85 тестов / 423 assertions PASS:** 66 существующих тестов админки и контента плюс 19 дополнительных воспроизведений. PASS дополнительных тестов означает, что они **подтвердили неправильное поведение**, а не его исправление. Проверки LLM используют HTTP fake; платные запросы и реальные ключи не использовались.

Доказательства: `docs/audit-2026-09-30/AuditAdminFindingsTest.php` и `docs/audit-2026-09-30/admin-phpunit-results.txt`. Для повторения скопировать тест в `tests/Feature/` отдельного checkout, подготовить `.env` с новым ключом и выполнить `php vendor/bin/phpunit tests/Feature/AuditAdminFindingsTest.php`. В рабочий набор тестов файл намеренно не добавлен.

AGENTS.md в репозитории и просмотренных родительских каталогах не найден. Браузерный smoke и публичную публикацию проверяет основной агент; данный отчет содержит серверные runtime проверки и анализ кода/UI, не заявление о прохождении всех интерактивных элементов браузера.

## Приоритеты

| ID | Приоритет | Дефект |
|---|---|---|
| ADM-01 | P1 | Автор и токен только с write публикуют через поле status |
| ADM-02 | P1 | Заблокированная учетная запись продолжает работать через сессию и API |
| ADM-03 | P1 | PATCH одного перевода удаляет остальные переводы страницы/поста |
| ADM-04 | P2 | API редактирования теряет custom_head_html |
| ADM-05 | P2 | Дублирующий slug категории через API возвращает 500 |
| ADM-06 | P2 | Иерархия категорий допускает циклы, API также допускает self-parent |
| ADM-07 | P2 | API категории не позволяет снять parent и cover через null |
| ADM-08 | P2 | Очистка перевода категории в форме оставляет старый перевод |
| ADM-09 | P2 | API удаления медиа оставляет физический файл |
| ADM-10 | P2 | Необязательное description шаблона при отсутствии вызывает 500 |
| ADM-11 | P2 | OpenAI Responses сохраняется как JSON вместо сгенерированного текста |
| ADM-12 | P2 | LLM создает перевод в неподдерживаемой локали |
| ADM-13 | P2 | Контракт content service с context по умолчанию падает на отсутствующем методе |

P1 — существенное нарушение прав или потеря контента; P2 — неправильный поддерживаемый сценарий, требующий исправления. Приоритет отражает подтвержденный эффект в проверенной конфигурации.

## ADM-01. Публикация без права publish

**Воспроизведение.** Создать пользователя штатной роли `author` (у него есть `posts:write` / `pages:write`, нет `*:publish`). В `/admin/posts/create` или `/admin/pages/create` выбрать `published` в поле статуса и сохранить валидный RU-контент. Аналогично отправить `POST /api/admin/v1/posts` с bearer token, содержащим только `posts:write`, и JSON `{"status":"published","translations":[{"locale":"ru","title":"Audit Title","slug":"audit-post","content_html":"<p>Audit body</p>"}]}`. Для pages отправить blocks и `pages:write`.

**Ожидание:** запрет 403 либо сохранение draft с сообщением о недостаточном разрешении; поле published недоступно автору. **Факт:** веб-форма успешно создает published; API возвращает 201 и `data.status=published`. Прямой `/publish` тому же автору возвращает 403. Сохранение также позволяет изменить статус существующей сущности: код update принимает status без отдельной проверки publish.

**Причина:** create/update проверяет write, валидатор допускает published, сервис напрямую сохраняет status и published_at. Поле status выводит все варианты конфигурации без учета разрешения.

**Доказательства:** [database/seeders/RolesAndPermissionsSeeder.php:46](/Users/ivanradaev/Documents/Кодинг/TestoCMS/database/seeders/RolesAndPermissionsSeeder.php:46), [routes/api.php:37](/Users/ivanradaev/Documents/Кодинг/TestoCMS/routes/api.php:37), [routes/api.php:41](/Users/ivanradaev/Documents/Кодинг/TestoCMS/routes/api.php:41), [app/Modules/Content/Services/PostContentService.php:38](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PostContentService.php:38), [app/Modules/Content/Services/PostContentService.php:68](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PostContentService.php:68), [app/Modules/Content/Services/PageContentService.php:41](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PageContentService.php:41), [resources/views/admin/posts/form.blade.php:1199](/Users/ivanradaev/Documents/Кодинг/TestoCMS/resources/views/admin/posts/form.blade.php:1199), [resources/views/admin/pages/form.blade.php:1298](/Users/ivanradaev/Documents/Кодинг/TestoCMS/resources/views/admin/pages/form.blade.php:1298). Тесты `test_author_can_publish_*_via_status_web` и `test_write_only_token_can_publish_*_via_status`.

**Исправление:** единая проверка перехода состояния в application service, которая для published и снятия с публикации проверяет permission и scope токена. Не доверять скрытию селектора в UI. Добавить отрицательные проверки роли author и токена write-only для create и update.

## ADM-02. Блокировка пользователя не закрывает уже выданный доступ

**Воспроизведение.** Войти автором, затем супер администратором изменить его статус на blocked. С прежней аутентификацией открыть `/admin/posts` и создать запись. Отдельно выдать author API token с posts:write, заблокировать учетную запись, затем выполнить POST API с прежним токеном.

**Ожидание:** следующий запрос блокируется, сессии завершаются, токены блокируются/отзываются. **Факт:** уже аутентифицированный blocked author получает 200 на список и успешно создает запись; bearer token также получает 201. Повторный вход через форму действительно запрещен штатным тестом `test_blocked_user_cannot_login`, но это покрывает только новые входы.

**Причина:** status проверяется в AuthController после Auth::attempt. updateStatus/updateUser сохраняют blocked без отзыва сессий/токенов; в web/API pipeline нет проверки active. Password change умеет отзывать только database sessions, и не помогает при блокировке.

**Доказательства:** [app/Http/Controllers/Admin/AuthController.php:46](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/AuthController.php:46), [app/Modules/Auth/Services/UserManagementService.php:131](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Auth/Services/UserManagementService.php:131), [app/Modules/Auth/Services/UserManagementService.php:145](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Auth/Services/UserManagementService.php:145), [bootstrap/app.php:45](/Users/ivanradaev/Documents/Кодинг/TestoCMS/bootstrap/app.php:45), [bootstrap/app.php:55](/Users/ivanradaev/Documents/Кодинг/TestoCMS/bootstrap/app.php:55), [routes/api.php:34](/Users/ivanradaev/Documents/Кодинг/TestoCMS/routes/api.php:34). Тесты `test_blocked_user_keeps_session_access`, `test_blocked_user_keeps_bearer_token_access`.

**Исправление:** проверять active на каждом аутентифицированном запросе web/API; при blocked отзывать tokens и sessions. Реализовать централизованно, чтобы обновление полного профиля тоже применяло отзыв.

## ADM-03. PATCH удаляет несообщенные переводы

**Воспроизведение.** Создать post/page через API с RU и EN. Отправить `PATCH /api/admin/v1/posts/{id}` или pages/{id} с `translations`, содержащим только RU (обновить RU заголовок/контент).

**Ожидание:** частичное обновление RU сохраняет EN. Удаление EN должно быть явной операцией, либо допустимо только в документированной полной замене PUT. **Факт:** 200, EN полностью удален из translation table. Это не просто скрытие локали; запись теряется.

**Причина:** PUT и PATCH ведут в один update; persister после upsert удаляет все локали, не вошедшие в массив. Такую очистку требуется сохранить для намеренного удаления локали из полной веб-формы, но API PATCH не должен трактоваться как полная замена.

**Доказательства:** [routes/api.php:39](/Users/ivanradaev/Documents/Кодинг/TestoCMS/routes/api.php:39), [routes/api.php:48](/Users/ivanradaev/Documents/Кодинг/TestoCMS/routes/api.php:48), [app/Modules/Content/Services/PostTranslationPersisterService.php:39](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PostTranslationPersisterService.php:39), [app/Modules/Content/Services/PageTranslationPersisterService.php:37](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PageTranslationPersisterService.php:37). Тесты `test_patch_post_one_translation_deletes_other_locale`, `test_patch_page_one_translation_deletes_other_locale`.

**Исправление:** разделить merge PATCH и replacement PUT/web; явно передавать режим сохранения. Для удаления переводов отдельный delete или remove_locales с валидацией. Проверить partial update остальных translation fields: normalizer также заменяет отсутствующие поля значениями по умолчанию.

## ADM-04. API update теряет custom_head_html

**Воспроизведение.** Супер администратором создать пост с `custom_head_html=<meta name="audit" content="one">` через web/service. Через API обновить перевод поста, включая `custom_head_html=<meta name="audit" content="two">` либо не передавая это поле.

**Ожидание:** поле сохраняется/меняется по правам advanced role; отсутствие поля PATCH сохраняет старое значение. **Факт:** 200, custom_head_html становится null. Поле выбрасывается API validation, normalizer принимает отсутствующее значение как null, persister записывает null. Для pages отсутствует то же правило в валидаторе; аналогичная цепочка кода.

**Доказательства:** [app/Http/Controllers/Api/Admin/PostController.php:129](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/PostController.php:129), [app/Http/Controllers/Api/Admin/PageController.php:128](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/PageController.php:128), [app/Modules/Content/Services/PostTranslationNormalizer.php:61](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PostTranslationNormalizer.php:61), [app/Modules/Content/Services/PostTranslationPersisterService.php:32](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PostTranslationPersisterService.php:32). Runtime: `test_post_api_update_drops_custom_head_html`; pages — точная аналогичная цепочка кода, отдельный runtime тест не выполнялся.

**Исправление:** принимать поле с действующим gate advanced role; PATCH должен сохранять несообщенные поля. Проверить обратную совместимость integrations при обновлении контента, имеющего служебные meta/script-теги.

## ADM-05. Дублирующий slug категории дает 500

**Воспроизведение.** Два раза создать категорию API с одним `translations[0].locale=ru`, `slug=duplicate-category`.

**Ожидание:** второй запрос 422 с понятной ошибкой slug, как в веб-форме. **Факт:** 500 из-за unique constraint, транзакция откатывается. Клиент получает серверную ошибку для обычного конфликта данных.

**Причина:** API CategoryController не вызывает assertUniqueTranslationSlugs; unique index оставлен на уровне БД.

**Доказательства:** [app/Http/Controllers/Api/Admin/CategoryController.php:116](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/CategoryController.php:116), [app/Http/Controllers/Api/Admin/CategoryController.php:138](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/CategoryController.php:138), [app/Http/Controllers/Admin/CategoryCrudController.php:64](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/CategoryCrudController.php:64), [database/migrations/2026_02_20_233600_create_cms_tables.php:56](/Users/ivanradaev/Documents/Кодинг/TestoCMS/database/migrations/2026_02_20_233600_create_cms_tables.php:56). Тест `test_category_api_duplicate_slug_yields_500`.

**Исправление:** разделяемая нормализация/валидация категорий для web/API, проверка уникальности с исключением текущей категории и обработка race condition unique violation в 422/409.

## ADM-06. Циклическая иерархия категорий

**Воспроизведение.** Создать A и B с parent=A. В форме A выбрать parent=B (B остается в списке допустимых родителей). Либо через API присвоить категории parent_id равным ее id.

**Ожидание:** 422/ошибка формы; родителем нельзя сделать себя или потомка. **Факт:** веб-форма сохраняет A.parent=B и B.parent=A, ошибок нет. API сохраняет self-parent и возвращает 200.

**Причина:** web исключает только текущую категорию из вариантов и проверяет только прямое self-parent. API не проверяет и его. Проверка `exists` устанавливает наличие id, но не корректность дерева.

**Доказательства:** [app/Http/Controllers/Admin/CategoryCrudController.php:95](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/CategoryCrudController.php:95), [app/Http/Controllers/Admin/CategoryCrudController.php:108](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/CategoryCrudController.php:108), [app/Http/Controllers/Api/Admin/CategoryController.php:82](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/CategoryController.php:82), [app/Http/Controllers/Api/Admin/CategoryController.php:116](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/CategoryController.php:116). Тесты `test_category_web_descendant_can_be_parent_creating_cycle`, `test_category_api_self_parent_and_invalid_slug_accepted` (self-parent подтвержден; reserved slug — дополнительное различие валидации).

**Исправление:** общий валидатор дерева с обходом предков и защитой от уже существующих циклов. Исключать потомков из селектора UI. Не заявляю подтвержденного бесконечного цикла публичного renderer: в тесте доказано именно повреждение структуры дерева.

## ADM-07. Категории API: null не очищает связи

**Воспроизведение.** Категория имеет parent_id и cover_asset_id. Выполнить PATCH с `parent_id:null`, `cover_asset_id:null` и валидным translations.

**Ожидание:** категория становится корневой, обложка удаляется. **Факт:** 200, оба id остаются прежними.

**Причина:** выражения `$validated['parent_id'] ?? $category->parent_id` и аналогичное cover трактуют явный null как отсутствие поля.

**Доказательство:** [app/Http/Controllers/Api/Admin/CategoryController.php:82](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/CategoryController.php:82). Тест `test_category_api_null_does_not_clear_parent_or_cover`.

**Исправление:** array_key_exists для nullable PATCH полей, как уже сделано для featured_asset_id в PostContentService.

## ADM-08. Очистка EN перевода категории не удаляет его

**Воспроизведение.** Категория имеет RU и EN. В edit форме очистить все поля EN, RU оставить. Сохранить.

**Ожидание:** по той же модели работы, что страницы/посты, EN перестает существовать; либо интерфейс явно сообщает, что очистка не удаляет перевод и дает отдельную кнопку удаления. **Факт:** сохранение без ошибок, старый EN заголовок и slug остаются в БД и возвращаются при следующем открытии формы.

**Причина:** normalizer пропускает полностью пустую локаль, upsertTranslations лишь обновляет/создает и не удаляет пропущенные локали. Результат отличается от страниц и постов.

**Доказательство:** [app/Http/Controllers/Admin/CategoryCrudController.php:240](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/CategoryCrudController.php:240). Тест `test_category_web_blank_secondary_translation_is_not_deleted`.

**Исправление:** определить единый UX удаления перевода для сущностей и применить явное удаление; не переносить destructive prune в API PATCH из ADM-03.

## ADM-09. API delete asset оставляет файл

**Воспроизведение.** Создать asset, которому соответствует физический файл disk=public. Выполнить `DELETE /api/admin/v1/assets/{id}`.

**Ожидание:** запись и принадлежащий ей загруженный файл удаляются единообразно с веб-админкой, либо documented retention политика. **Факт:** 204, запись удалена, `Storage::disk('public')->exists(path)` остается true. Файл теряет связь с медиатекой и при public disk продолжает лежать в публичном storage.

**Причина:** web destroy удаляет storage file, API destroy вызывает только `$asset->delete()`.

**Доказательства:** [app/Http/Controllers/Api/Admin/AssetController.php:124](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/AssetController.php:124), [app/Http/Controllers/Admin/AssetCrudController.php:128](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/AssetCrudController.php:128). Тест `test_asset_api_delete_leaves_storage_file` использует Storage::fake('public'), поэтому пользовательские файлы не удалялись.

**Исправление:** общий asset deletion service и ясная политика для загруженных/внешних asset, shared references и cleanup failures. Проверить реальный публичный URL после удаления файла на целевом hosting.

## ADM-10. Необязательное description шаблона вызывает 500

**Воспроизведение.** Авторизованным пользователем с write отправить `POST /admin/templates` с entity_type, name, валидным payload_json, **без description**.

**Ожидание:** template создается с description=null; поле объявлено nullable. **Факт:** 500, обращение к отсутствующему ключу массива `$validated['description']`.

**Доказательства:** [app/Http/Controllers/Admin/ContentTemplateController.php:67](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/ContentTemplateController.php:67), [app/Http/Controllers/Admin/ContentTemplateController.php:93](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/ContentTemplateController.php:93), [app/Http/Controllers/Admin/ContentTemplateController.php:122](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Admin/ContentTemplateController.php:122). Тест `test_template_optional_description_omitted_yields_500`; update содержит такую же ошибку по точной цепочке.

**Исправление:** использовать `$validated['description'] ?? null`. Штатная HTML-форма обычно передает пустое поле, поэтому этот дефект не доказывает, что каждое создание template через обычную форму сломано.

## ADM-11. OpenAI Responses сохраняется как JSON

**Воспроизведение.** HTTP fake возвращает форму raw JSON Responses API: `{"id":"resp_audit","object":"response","output":[{"type":"message","content":[{"type":"output_text","text":"The generated article"}]}]}`. Выполнить `/api/admin/v1/llm/generate-post`.

**Ожидание:** draft title/body содержит текст `The generated article`, аналогично page и SEO suggestions. **Факт:** draft content_plain равен строке всего JSON; title является его первыми 120 символами. У generate-page и generate-seo используется тот же extractText.

**Причина:** OpenAiProvider возвращает `$response->json()` с HTTP `/responses`, а extractText понимает верхнеуровневый output_text либо Anthropic `content[0].text`, затем сериализует объект. Raw Responses API содержит output message/content; output_text описан как convenience property SDK, PHP Http SDK его не добавляет. Формат сверялся с [официальным API reference](https://developers.openai.com/api/reference/cli/resources/responses/methods/create) и [официальным руководством](https://developers.openai.com/api/docs/guides/text).

**Доказательства:** [app/Modules/LLM/Providers/OpenAiProvider.php:22](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/LLM/Providers/OpenAiProvider.php:22), [app/Modules/LLM/Providers/OpenAiProvider.php:31](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/LLM/Providers/OpenAiProvider.php:31), [app/Http/Controllers/Api/Admin/LlmController.php:167](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/LlmController.php:167). Тест `test_llm_responses_output_array_is_saved_as_json_text`.

**Исправление:** provider должен выдавать единый normalized text, извлекая все output message content с type=output_text. Не подменять непонятный формат JSON-статьей; вернуть диагностируемую ошибку. Live provider availability/качество generation не проверялось.

## ADM-12. LLM сохраняет неподдерживаемую локаль

**Воспроизведение.** При supported_locales=['ru','en'] отправить generate-post с `locale=zz`, замокав успешный provider.

**Ожидание:** 422 до запроса провайдеру. **Факт:** 201, draft с translation locale=zz сохранен. Web редактор показывает только supported locales, и публичные локализованные маршруты ограничены supported_locales, поэтому контент нельзя нормально продолжить по стандартному циклу интерфейса/публикации.

**Причина:** locale валидируется только как строка max:8, затем записывается напрямую, обходя общую нормализацию. generate-page содержит тот же путь.

**Доказательства:** [app/Http/Controllers/Api/Admin/LlmController.php:32](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/LlmController.php:32), [app/Http/Controllers/Api/Admin/LlmController.php:55](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Http/Controllers/Api/Admin/LlmController.php:55), [routes/web.php:167](/Users/ivanradaev/Documents/Кодинг/TestoCMS/routes/web.php:167) (группа публичных локализованных маршрутов). Тест `test_llm_accepts_unsupported_locale`.

**Исправление:** Rule::in supported locales для LLM и создание draft через общий content service; это также унифицирует canonical, block normalization и содержимое revision snapshot.

## ADM-13. Публичный контракт content service не работает с context по умолчанию

**Воспроизведение.** В модуле/интеграции вызвать `app(PageContentServiceContract::class)->createFromValidated(validPayload, user)` без третьего необязательного аргумента. Аналогично PostContentService.

**Ожидание:** действует документированный default `array $context=[]`. **Факт:** Error `Call to undefined method ...::shouldRequireDefaultLocale()` до сохранения.

**Причина:** PageContentService/PostContentService используют LocalizedContentHelpers, но метод находится в TranslationInputMappingHelpers; этот trait подключен в normalizers, не в самих content services. В штатных web/API controllers всегда передается require_default_locale, поэтому обычный UI/API обходит ошибочную ветку.

**Доказательства:** [app/Modules/Content/Contracts/PageContentServiceContract.php:15](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Contracts/PageContentServiceContract.php:15), [app/Modules/Content/Services/PageContentService.php:26](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PageContentService.php:26), [app/Modules/Content/Services/PostContentService.php:25](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Services/PostContentService.php:25), [app/Modules/Content/Support/TranslationInputMappingHelpers.php:61](/Users/ivanradaev/Documents/Кодинг/TestoCMS/app/Modules/Content/Support/TranslationInputMappingHelpers.php:61). Тест `test_content_service_default_context_throws_missing_method`.

**Исправление:** убрать вызов отсутствующего helper, делегировать default normalizer либо корректно подключить trait; проверять контракт с аргументами по умолчанию и для create/update.

## Матрица покрытия и работающие сценарии

| Область | Подтверждено работающим | Ограничения / отклонения |
|---|---|---|
| Login/logout/auth | Аутентификация обязательна; valid login/logout; blocked не проходит новый login; throttling маршрута настроен | ADM-02, действующий blocked token/session не отсекается; CSRF выключен в TestCase, реальный браузерный CSRF проверяет основной агент |
| Users/roles | CRUD профиля, назначение ролей, password change, запрет admin управлять superadmin; защита последнего активного superadmin; обновление permission супер администратором | Блокировка не отзывает доступ; file/session драйверы password revocation live не проверены |
| Admin shell/runtime | Штатные AdminShellTest и AdminEditorRuntimeTest проходят; страницы и runtime scripts возвращаются корректно | Это HTML/server assertions, не доказательство всех drag/drop/modal/browser событий |
| Posts | Создание, редактирование, sanitized HTML, Markdown preview/import, featured asset, категории, web/API workflow, duplicate/bulk/template штатные сценарии | ADM-01, ADM-03, ADM-04; реальный WYSIWYG в браузере проверяет основной агент |
| Pages | Нормализация block layout, stage preview, CRUD/workflow/template/bulk, gate custom code, draft preview tokens | ADM-01, ADM-03, ADM-04; весь каталог блоков и runtime appearance покрывается публичным аудитом |
| Categories | CRUD формы, default locale validation, direct self-parent защита web, cover selector | ADM-05–08; API/формы имеют разные правила и семантику |
| Assets/media | Inline upload и asset payload штатные tests; metadata edit; web deletion имеет cleanup | ADM-09; external disk/S3/live public URL не проверены |
| Preview/schedule | Token создается и audit записывается; web/API publish/unpublish/delete side effects consistent; schedule запись создается | Scheduled publication/end-to-end проверяет публичный агент; UI отмены schedule отсутствует в core routes |
| Templates/bulk | Создание/metadata/duplicate/delete, prefill, bulk действия под правами в штатных тестах | ADM-10; payload editor usability требует браузерной проверки |
| Theme/header/footer | ThemeAdminTest, ChromeAutosaveSignalTest проходят: preset/settings persistence и signal behavior | Autosave tests проверяют signal/markup, не все browser restores; внешний font fetch не проверен |
| Modules | ModulesAdminTest проходит: management role gates, installation restrictions и list UI | Deployment/module package cycle проверяется другим агентом; здесь не запускались произвольные сторонние модули |
| Settings | UI language settings работают в штатном i18n наборе; theme/SEO gates просмотрены | Live email/provider настройки и внешний API connection не проверены; settings — UI language, не полный site setup |
| Admin API | Authentication, CRUD superadmin, policy/ability middleware, pagination, normalized content | ADM-01–07, ADM-09; категории заметно расходятся с web |
| LLM | HTTP fake проходит gateway->draft->audit path; provider error приводит к 422 по коду | ADM-11–12; реальные OpenAI/Anthropic вызовы не делались |
| Revisions | Snapshot запись и retention существуют в RevisionService, используются при content save | Нет core route/view для просмотра или восстановления revision; интерфейс «отменить до прошлой версии» не реализован. Это функциональный пробел, не неуспешный вызов существующей кнопки |
| Autosave | В исходниках используется localStorage и clear-after-save; штатные signal tests PASS | Локальный autosave не заменяет сохранение на сервер; ключ привязан к pathname без user id, restore across users/tabs и очистка при logout отдельно не проверены |

## Что необходимо проверить после исправлений

1. Negative RBAC: author / write-only token создают и меняют published, снимают публикацию, удаляют/меняют уже опубликованный материал; ожидаемый policy должен быть зафиксирован тестами.
2. Блокировка действующего session и bearer; второй способ блокировки через full profile update; отсутствие доступа на следующем запросе.
3. PATCH RU без EN, PATCH одного поля без body/SEO, явное удаление EN, PUT полного набора переводов; различие replacement и merge должно быть одинаковым для entities.
4. Категория: conflict slug -> 422, self-parent/descendant -> 422, null связи снимаются, очистка локали приводит к определенному результату без старого контента.
5. Asset delete web/API: одинаковая политика physical cleanup, проверка существующих references и endpoint возвращаемого URL.
6. LLM fixtures raw Responses и Anthropic, несколько текстовых блоков, unsupported locale, empty/refusal/provider error; drafts остаются редактируемыми.
7. Browser полный цикл: WYSIWYG, block add/move/delete, image picker/upload, locale switch, validation errors сохраняют введенное, fullscreen/stage responsive, save/reload/preview/publish.
