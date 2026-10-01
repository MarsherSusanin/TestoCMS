# ADM-04–13: исправления и проверка — 2 октября 2026

Все десять P2-находок админского аудита закрыты кодом и регрессиями. ADM-04 и ADM-13 были исправлены в P1; текущая работа подтверждает их положительные сценарии. Исходный [аудит](../audit-2026-09-30-admin-content.md) описывает состояние до исправлений и остается историческим документом.

Рабочие `.env` и БД не изменялись. Собственные проверки выполнены в `/private/tmp/testocms_p2_adm`, PHP 8.4.19 в отдельном Docker-контейнере, SQLite. LLM использует только `Http::fake` с запретом незаявленных запросов. Для удаленного медиа использован mock-адаптер, а не реальный AWS S3.

## Результаты проверок

| Проверка | Результат | Доказательство |
|---|---|---|
| ADM-регрессии и связанные существующие проверки API/прав/переводов | 83 tests / 417 assertions PASS | [admin-scoped.log](admin-scoped.log) |
| Последняя проверка optional description, включая строку `0`, и content context | 3 tests / 24 assertions PASS | [admin-final-template-pint.log](admin-final-template-pint.log) |
| Pint по 23 принадлежащим агенту файлам | PASS | [admin-final-template-pint.log](admin-final-template-pint.log) |
| Scoped PHPStan без новых подавлений ошибок | exit 0 | [admin-scoped-phpstan.log](admin-scoped-phpstan.log) |
| Настоящий SIGKILL во время удаления, затем CLI recovery новым процессом | exit 137 → restored=1 / failed=0; строка и точные байты восстановлены | [admin-actual-crash.log](admin-actual-crash.log) |
| OpenAPI YAML и локальные `$ref` | YAML разобран; 190 ссылок разрешены, 24 пути | [openapi.yaml](../../openapi/openapi.yaml) |

Отдельные общие проверки основного агента: [PostgreSQL — 116/757 PASS](postgresql-final.log); реальные многопроцессные гонки [SQLite](sqlite-races.log), [PostgreSQL](postgresql-races.log), [MySQL](mysql-races.log) — по 4 tests / 25 assertions PASS. Проверены взаимное переподчинение категорий, удаление медиа против добавления ссылки в обоих порядках и конкурирующий одинаковый slug. Это не замена проверкам каждого API-поля, а подтверждение поведения общего DB mutex между процессами.

В каталог также входят общие результаты PHP 8.2–8.5. Итоговую матрицу и соответствие последнему снимку исходников фиксирует основной отчет: последние локальные изменения description и исправление сравнения JSON в MySQL проверяются отдельно. JSON допускает переупорядочивание ключей объектов; значения и порядок элементов массивов проверяются без ослабления контракта.

## Матрица находок и тестов

Пути ниже относятся к текущему рабочему набору тестов. Положительный результат означает корректное поведение после исправления, а не воспроизведение старого дефекта.

| ID | Поведение после исправления | Регрессия |
|---|---|---|
| ADM-04 | API принимает `custom_head_html` под advanced gate; PATCH сохраняет отсутствующее поле, writer не получает право изменять raw HTML | `AdminTranslationPatchTest::test_patch_merges_fields_and_preserves_other_locale_and_custom_head`; `test_writer_patch_preserves_stored_head_without_granting_raw_code_permission`; положительный create/update Page/Post в `TemplateAndContentContextSafetyTest` |
| ADM-05 | Общий web/API сервис нормализует локаль и slug. Обычный дубль и настоящий конфликт уникальности после preflight дают 422 с откатом. Несвязанный PRIMARY конфликт остается ошибкой БД | `CategoryMutationSafetyTest::test_duplicate_slug_is_validation_error_and_rolls_back_creation`; `test_real_unique_collision_after_preflight_is_422_and_rolls_back`; `test_unrelated_primary_key_collision_is_not_masked_as_slug_validation`; `test_api_locale_identity_slug_rules_are_shared_with_web` |
| ADM-06 | Запрещены self-parent, родитель-потомок и присоединение к существующему циклу; варианты web исключают потомков; CLI сообщает о старых циклах без изменения данных | `CategoryMutationSafetyTest::test_self_parent_descendant_parent_and_existing_cycle_are_rejected`; общие multiprocess reparent tests |
| ADM-07 | Явный `null` снимает parent/cover и очищает nullable metadata; отсутствующие PATCH-поля сохраняются | `CategoryMutationSafetyTest::test_patch_null_clears_references_and_metadata_without_losing_other_fields` |
| ADM-08 | PATCH сливает поля/локали; PUT заменяет набор переводов. `remove_translations` явно удаляет локаль, но не последнюю и не одновременно обновляемую. Пустая необязательная web-локаль удаляется | `CategoryMutationSafetyTest::test_put_replaces_locales_and_patch_removal_cannot_remove_last_or_conflict`; `test_web_clear_optional_locale_removes_it_and_preserves_hidden_metadata` |
| ADM-09 | In-use удаление возвращает 409 со списком использований. Свободный файл и запись удаляются; общий disk/path жив до последнего владельца; precommit storage failure дает 503 и recovery journal | 13 методов `AssetDeletionSafetyTest`, перечислены далее; actual SIGKILL proof; общие multiprocess media tests |
| ADM-10 | Description отсутствует/null/пустое/пробелы → null; обычный текст обрезается по краям, строка `0` сохраняется | `TemplateAndContentContextSafetyTest::test_template_description_omission_null_and_empty_are_valid_for_create_update` |
| ADM-11 | Raw OpenAI Responses извлекается из `output[].content[]` в отдельный `text`; raw envelope сохраняется. JSON serialization и SDK-only `output_text` не используются как fallback. Anthropic объединяет text-блоки | `LlmContentSafetyTest::test_real_responses_envelope_becomes_escaped_draft_and_full_revision` (Page/Post); `test_anthropic_joins_all_text_blocks_and_ignores_thinking`; `test_failed_outputs_create_no_draft` |
| ADM-12 | Locale нормализуется и проверяется до HTTP; write role/PAT проверяется до платного вызова при создании draft; статус пользователя повторно проверяется после генерации | `LlmContentSafetyTest::test_invalid_locale_and_missing_write_scope_do_not_cost_provider_calls`; `test_generation_only_does_not_require_content_write_and_creates_no_entity`; `test_account_blocked_during_generation_is_rechecked_before_draft_write` |
| ADM-13 | Общие Page/Post services работают без явного context; API может создавать поддерживаемую EN-локаль без обязательного RU; create/update сохраняют head | `TemplateAndContentContextSafetyTest::test_create_update_without_context_support_non_default_api_locale_and_head` (Page/Post) |

### Категории: единый контракт

Реализация: `CategoryContentService`; web/API controllers используют общий валидатор и mutation service. Все структурные изменения сериализованы через `ContentMutationGuard::lockCategories()`, после media lock и до entity lock. Уникальность обеспечена валидатором и DB constraints; обработчик узнает только индексы `(locale, slug)` / `(category_id, locale)`, а не любую ошибку таблицы. Реальный INSERT из creating listener между preflight и ORM INSERT подтверждает откат и 422. Отдельная PRIMARY-коллизия не маскируется даже при совпадении названия известного индекса в значении пользовательского поля.

`cms:categories:check` выводит IDs участников существующих циклов и возвращает ненулевой код. Данные команда не исправляет автоматически. При удалении категории дети становятся корневыми, переводы удаляются; тест `test_delete_keeps_posts_and_promotes_children_to_roots` непосредственно проверяет детей и переводы. Сохранение самих постов следует из FK/schema и кода; отдельный пост в этом конкретном тесте не создан.

### Медиа: покрытия и восстановление

Регрессии `AssetDeletionSafetyTest`:

| Метод | Проверка |
|---|---|
| `test_api_removes_file_and_record_and_retains_private_tombstone` | 204; нет строки и локальных байтов; приватный tombstone блокирует новую ссылку на удаленный URL |
| `test_shared_storage_path_is_preserved_until_last_owner` | Общий физический объект удаляется только с последним владельцем disk/path |
| `test_foreign_keys_block_deletion_with_actionable_usages` | Post featured / category cover → 409 и actionable usages |
| `test_nested_urls_html_markdown_custom_code_theme_and_templates_are_live_usages` | Текущие nested blocks, HTML/Markdown, custom code, chrome/theme и templates блокируют удаление |
| `test_db_failure_restores_local_bytes_and_retains_record` | Исключение БД после quarantine возвращает исходные байты и сохраняет строку |
| `test_quarantine_journal_recovers_interrupted_local_rename` | Prepared journal + реальный rename восстанавливаются CLI |
| `test_remote_cleanup_false_retains_record_and_retry_can_succeed` | False remote delete не удаляет строку; положительный remote-путь проверяется отдельным методом ниже |
| `test_local_symlink_escape_does_not_touch_external_bytes` | Symlink/выход из корня не трогает внешние байты |
| `test_external_urls_similar_filenames_and_historical_revisions_do_not_block` | Чужой host, похожее имя и старые revisions не дают ложный in-use |
| `test_recovery_purges_private_bytes_after_a_committed_interrupted_delete` | После committed DB delete CLI очищает quarantine; повторный recovery идемпотентен |
| `test_remote_success_purges_backup_and_records_tombstone` | Mock remote copy → private backup → source delete → backup purge → tombstone |
| `test_missing_file_is_idempotent_and_reuploaded_tombstone_path_is_allowed` | Уже отсутствующий файл не ломает удаление; повторно загруженный путь разрешается |
| `test_private_local_files_are_not_deleted_as_registered_media` | Регистрация внутреннего private/local пути не позволяет удалить файл CMS |

Исторические revisions намеренно не блокируют удаление. Сканирование использований учитывает полные URL и текущие поля/таблицы; оно не исполняет произвольный JavaScript и не вычисляет динамически собранные URL. Незарегистрированные legacy URL допускаются. Исчезнувший зарегистрированный путь остается в приватном tombstone и отвергается при новой записи ссылки, пока файл не загружен повторно.

Локальное удаление использует private journal и quarantine на том же filesystem, с проверкой realpath, родительского пути и symlink до операции. Для disk `local` разрешен только `assets/*`: старые вручную зарегистрированные внутренние private paths требуют оператора. DB transaction сериализует изменение строки и references; filesystem не является частью DB-транзакции, поэтому аварии компенсируются журналом. `cms:assets:recover` восстанавливает bytes при существующем владельце либо удаляет backup после committed delete. Сбой восстановления оставляет журнал и сообщение об операции; 503 не обещает, что физическое восстановление уже выполнено. Финальный root contract-fix: сбой private purge после commit возвращает503 `asset_storage_cleanup_failed` с точным сообщением об уже удалённой публичной записи; журнал остаётся для recovery. `test_post_commit_private_purge_failure_is_reported_and_recoverable` проверяет отказ и успешный повтор очистки.

Настоящая авария проверена отдельно: fixture создала исходный файл/Asset, listener `Asset::deleted` отправил `SIGKILL` своему PHP-процессу до commit. Docker запущен с `--init`, чтобы PHP не являлся namespace PID 1. Exit 137; после смерти процесса SQLite откатил DELETE, публичного файла не было, prepared journal и quarantine были. Новый CLI-процесс вернул `Restored: 1; purged: 0; failed: 0`; еще один процесс и независимая проверка host подтвердили строку и побайтовое совпадение. Это actual process-death proof для локального SQLite/filesystem пути, не доказательство отказоустойчивости реального S3.

### LLM: результат и ошибки

`generate(): array` у provider сохраняет raw response contract; gateway возвращает отдельный нормализованный `text`. OpenAI читает только raw message/content/output_text блоки; reasoning/tools игнорируются. Anthropic читает text blocks. Incomplete/refusal/пустой/malformed response и выход за лимит → 422 без draft; SDK-only верхнеуровневый `output_text` также отвергается. Содержание экранируется перед превращением в HTML, создается draft через общий content service с revision и cache finalizer. Live провайдеры не вызывались.

Тесты transport failure, output limit и отказа аккаунта во время ответа проверяют sanitized error, отсутствие частичной сущности и повторный auth guard. Основной агент дополнительно расширил `test_timeout_and_non_json_provider_response_are_sanitized`: literal повреждённый HTTP JSON body для OpenAI и Anthropic возвращает422 без утечки provider details, одного provider call достаточно, pages/posts не создаются. Malformed JSON-shaped envelope отдельно покрыт data provider.

## Документация и границы приемки

Обновлены [admin-content-api.md](../admin-content-api.md) и опубликованный [OpenAPI](../../openapi/openapi.yaml): category CRUD/PATCH/PUT/removal, asset 409/503/204/usages/recovery, LLM normalized text/raw output/locales/scopes/errors. Проверка YAML и разрешение всех локальных `$ref` выполнены; отдельный формальный OpenAPI validator в собственной среде не установлен.

Браузерный цикл проверяет publishing-агент в отдельной fixture; этот отчет не объявляет собственную приемку всех интерактивных элементов. Собственный новый CUA browser bridge не загрузил policy, native попытка была отменена; агент продолжил через существующую живую tab binding. Серверные действия и filesystem recovery выше проверены независимо от браузера. Скриншот [категорий RU/EN](adm-category-translations.png) принадлежит общей браузерной приемке; окончательный browser outcome фиксируется основным/publishing отчетом.

APP source после объявленного freeze не менялся этим агентом. Последнее изменение тестового сравнения raw JSON для MySQL внес основной агент: порядок ключей JSON object не является контрактом. Production deployment, реальная облачная сеть, платные LLM и UI каждого элемента за пределами описанного браузерного smoke не заявляются как проверенные этим отчетом.
