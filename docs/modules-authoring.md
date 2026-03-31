# TestoCMS Modules Authoring Guide

## 1. Назначение
Модули расширяют TestoCMS без изменения core-кода:
- добавляют admin/web routes;
- подключают свои views/assets/migrations;
- могут регистрировать пункты меню в админке.

V1 использует `trusted PHP modules`: код модуля выполняется в процессе приложения, поэтому устанавливайте только проверенные пакеты.

## 2. Структура модуля
Минимальная структура:

```text
my-module/
  module.json
  src/
    ModuleServiceProvider.php
  routes/
    admin.php
  resources/
    views/
      admin/
        index.blade.php
  database/
    migrations/
  public/
    (optional assets)
```

## 3. module.json (обязателен)
Пример:

```json
{
  "id": "acme/content-tools",
  "name": "Acme Content Tools",
  "version": "1.0.0",
  "description": "Дополнительные инструменты редактора",
  "author": "Acme",
  "provider": "Acme\\ContentTools\\ModuleServiceProvider",
  "autoload": {
    "psr-4": {
      "Acme\\ContentTools\\": "src/"
    }
  },
  "admin": {
    "nav": [
      {
        "key": "content-tools",
        "label": "Content Tools",
        "route": "module.acme_content_tools.index",
        "icon": "puzzle",
        "short_ru": "CT",
        "short_en": "CT"
      }
    ]
  },
  "requires": {
    "cms": ">=1.0.0",
    "php": ">=8.3"
  },
  "capabilities": {
    "routes": true,
    "views": true,
    "migrations": "database/migrations",
    "assets": true,
    "settings_page": true
  }
}
```

## 4. Service Provider
Пример `src/ModuleServiceProvider.php`:

```php
<?php

namespace Acme\ContentTools;

use App\Modules\Extensibility\Registry\AdminNavigationRegistry;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $base = dirname(__DIR__);
        $this->loadRoutesFrom($base.'/routes/admin.php');
        $this->loadViewsFrom($base.'/resources/views', 'module-acme-content-tools');

        app(AdminNavigationRegistry::class)->register([
            'key' => 'module:acme/content-tools:main',
            'label' => 'Content Tools',
            'route' => 'module.acme_content_tools.index',
            'icon' => 'puzzle',
            'short_ru' => 'CT',
            'short_en' => 'CT',
        ]);
    }
}
```

## 5. Routes / Views / Assets
- Routes: храните в `routes/admin.php` и защищайте `auth`.
- Views: регистрируйте namespace через `loadViewsFrom`.
- Assets: файлы из `public/` модуля автоматически публикуются в `public/modules/{vendor--name}` при установке/обновлении.
- `admin.nav[].icon` опционален. Если имя иконки неизвестно sidebar-реестру, интерфейс автоматически вернётся к `short_ru` / `short_en`.
- Для глобальных публичных UI-элементов используйте `App\Modules\Extensibility\Registry\PublicChromeRegistry`. Он поддерживает зоны `head_bootstrap`, `head`, `body_start`, `header_actions`, чтобы модуль добавлял public controls без правки core theme views.

## 6. Lifecycle
Поддерживаемые действия через `/admin/modules`:
- install ZIP;
- install local path (из allowlist roots);
- activate / deactivate;
- update (ZIP с тем же `id`, версия выше);
- uninstall (опционально `preserve_data`).

При activate запускаются миграции модуля, если они существуют.

## 7. Команды для разработчика
Сгенерировать каркас:

```bash
php artisan cms:module:make acme/content-tools
```

Пересобрать runtime cache модулей:

```bash
php artisan cms:modules:cache
```

## 8. Безопасность
- Никогда не устанавливайте непроверенные ZIP.
- `module.json` проходит строгую валидацию.
- ZIP с path traversal (`../`) блокируется.
- Симлинки внутри ZIP/каталога модуля запрещены.
- Local path install разрешён только внутри `CMS_MODULE_LOCAL_ROOTS`.

## 9. Рекомендации
- Держите модуль атомарным: один функциональный контур на модуль.
- Версионируйте semver (`MAJOR.MINOR.PATCH`).
- На update обеспечивайте обратную совместимость БД-миграций.
- Добавляйте README в модуль с changelog и инструкциями.
