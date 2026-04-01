# Размещение TestoCMS на shared hosting

Shared hosting — основной production path для TestoCMS v1.

## Требования

- PHP ≥ 8.2
- Расширения: `pdo_mysql`, `mbstring`, `intl`, `gd`, `bcmath`, `zip`, `exif`, `openssl`, `curl`, `fileinfo`
- MySQL 5.7+ / MariaDB 10.3+ или PostgreSQL 12+
- Apache с mod_rewrite
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
6. Сгенерирует `.env`, запустит миграции, создаст начальные данные

### 6. SSL-сертификат

Через cPanel → «SSL/TLS» → Let's Encrypt: выпустить сертификат для домена.

После настройки SSL раскомментировать HSTS в `public_html/.htaccess`:
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 7. Cron (опционально)

Через cPanel → «Задания Cron»:
```
* * * * * cd /home/USERNAME/testocms && php artisan schedule:run >> /dev/null 2>&1
```

> На shared hosting baseline используется `QUEUE_CONNECTION=sync`, поэтому отдельный queue worker не требуется. Scheduler через cron всё равно нужен.

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

Или удалить файл `storage/installed` и открыть сайт в браузере.

## Обновление

1. Сделать бэкап БД
2. Скачать новый shared-hosting архив из GitHub Releases
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
