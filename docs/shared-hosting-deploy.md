# Размещение TestoCMS на shared hosting

Shared hosting — основной production path для TestoCMS v1.

## Требования

- PHP ≥ 8.2
- Расширения: `pdo_mysql`, `mbstring`, `intl`, `gd`, `bcmath`, `zip`, `exif`, `openssl`, `curl`, `fileinfo`
- MySQL 5.7+ / MariaDB 10.3+ или PostgreSQL 12+
- Apache с mod_rewrite и mod_env (AllowOverride для rewrite/SetEnv)
- SSH-доступ (рекомендуется)

## Шаги установки

### 1. Скачать production-пакет

Скачайте из GitHub Releases архив вида `testocms-vX.Y.Z-shared-hosting.zip`.

### 2. Загрузка на хостинг

Загрузите и распакуйте архив через SFTP/SSH в каталог **выше** `public_html`.

Release-пакет рассчитан на такое дерево:

```text
~/testocms/     ← весь проект
~/public_html/  ← реальный web root хостинга
```

### 3. Разместить web root в `public_html`

Канонический web root проекта — `html_public/`. На shared hosting основной сценарий такой: код живёт в `~/testocms`, а содержимое `~/testocms/html_public/` попадает в `~/public_html/`.

**Вариант A — Копирование / синхронизация в фиксированный `public_html` (основной сценарий):**

```bash
rsync -a ~/testocms/html_public/ ~/public_html/
```

Если SSH недоступен, сделайте то же самое через файловый менеджер панели: копировать нужно **содержимое** `html_public/`, а не сам каталог целиком.

По умолчанию `public_html/index.php` автоматически ищет приложение в `../testocms`. Если вы распаковали код в другой каталог, откройте `~/public_html/bootstrap_path.php` и верните абсолютный путь к директории приложения.

**Вариант Б — Симлинк, если хостинг его разрешает:**

```bash
rm -rf ~/public_html
ln -s ~/testocms/html_public ~/public_html
```

### 4. Создать базу данных

Через cPanel/ISPmanager:
1. Создать базу данных
2. Создать пользователя
3. Назначить пользователя к базе (все привилегии)

### 5. Запустить мастер настройки

Откройте сайт в браузере — автоматически откроется мастер настройки.

**Или через SSH:**
```bash
cd ~/testocms
php artisan cms:setup
```

Мастер:
1. Проверит системные требования
2. Запросит параметры БД (с проверкой подключения)
3. По умолчанию предложит профиль `shared_hosting`
4. Запросит название сайта, URL, языки
5. Создаст администратора
6. Покажет страницу подтверждения; установка запускается только по защищенному POST
7. Сохранит настройки в `.env`, сохранив существующие ключи и интеграции, запустит миграции и создаст администратора

До настройки БД мастер использует отдельную файловую сессию и стабильный
ключ в `storage/app/private/setup.key`: готовая БД и таблица sessions не требуются.
Директории `storage/` и `bootstrap/cache/` должны быть доступны PHP на запись.
Ключи SMTP, LLM, updater и неизвестные integration settings в существующем `.env`
сохраняются; APP_KEY меняется только если ранее отсутствовал.

Если настройки заранее подготовлены и `.env` должен оставаться read-only:

```bash
php artisan cms:setup --from-env --no-interaction
```

Задайте APP_KEY, CMS_CONTENT_API_KEY и CMS_ADMIN_* заранее. Повторный запуск
после успешной установки не изменяет учетную запись администратора.

### 6. SSL-сертификат

Через cPanel → «SSL/TLS» → Let's Encrypt: выпустить сертификат для домена.

После настройки SSL раскомментировать HSTS в `public_html/.htaccess`:
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 7. Cron (обязательно)

**Обязательный шаг.** Без cron отложенная публикация/снятие с публикации и обслуживающие задачи не выполняются. Есть аварийный fallback при просмотре страниц, но полагаться на него нельзя.

Через cPanel → «Задания Cron»:
```
* * * * * cd /home/USERNAME/testocms && php artisan schedule:run >> /dev/null 2>&1
```

> На shared hosting baseline используется `QUEUE_CONNECTION=sync`, поэтому отдельный queue worker не требуется. Scheduler через cron всё равно обязателен.

### 8. Оптимизация (SSH)

```bash
cd ~/testocms
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Повторная установка

Через SSH:
```bash
cd ~/testocms
php artisan cms:setup --redo
```

Повторная настройка через CLI сохраняет installed marker до успешного завершения.
Не удаляйте его на работающем публичном сайте: это открывает мастер установки.

## Обновление

1. Сделать бэкап БД
2. Выбрать способ обновления:
   - manual deploy: скачать новый `testocms-vX.Y.Z-shared-hosting.zip`
   - admin updater: загрузить `testocms-vX.Y.Z-updater.zip` в `/admin/updates`
3. Загрузить новые файлы поверх `~/testocms`
4. Повторно синхронизировать `~/testocms/html_public/` → `~/public_html/`
5. Через SSH:
```bash
cd ~/testocms
php artisan storage:link
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Если используете admin updater, он сам обновит codebase, selectively синхронизирует core-managed файлы из `html_public` в активный `public_html`, пересоздаст `storage` symlink и republish'ит module assets. Host-managed файлы вроде `.well-known` он не трогает.


## Медиа на хостинге без symlink

Файлы хранятся в `storage/app/public`; endpoint `/storage/{path}` выдает только
файлы этого диска, поддерживает GET, HEAD и byte ranges. `symlink()` ускоряет
выдачу, но не требуется для корректной работы. `.htaccess` направляет `/storage/*`
через front controller, в том числе если от прежней установки осталась физическая
копия public/storage: новые загрузки видны сразу, удаленные больше не выдаются.
Обязательно синхронизируйте обновленный `.htaccess` из `html_public` в public_html.
Не копируйте uploads вручную для имитации symlink: такие копии устаревают.

Apache 2.4.59+ по умолчанию удаляет `Content-Length` от CGI/FastCGI. Для корректного
HEAD медиа `.htaccess` задаёт `ap_trust_cgilike_cl=1` только доверенному `index.php`
через `mod_env`; это разрешение не распространяется на uploads. Хостинг должен
разрешать `SetEnv` в `.htaccess`. [Официальное описание Apache](https://httpd.apache.org/docs/2.4/env.html#special)
требует ограничивать этот флаг доверенными скриптами.
