<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Extensibility\Services\ModuleCacheService;
use App\Modules\Updates\Services\CoreUpdateSettingsService;
use Illuminate\Support\Facades\Route;

class AdminShellViewModelFactory
{
    public function __construct(
        private readonly ModuleCacheService $moduleCache,
        private readonly CoreUpdateSettingsService $coreUpdateSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user = null): array
    {
        $locale = (string) app()->getLocale();
        $isAdminUiEn = str_starts_with($locale, 'en');
        $adminUiMap = $this->resolveAdminUiMap($isAdminUiEn);
        $canManageSystem = $user !== null && ($user->hasRole('superadmin') || $user->can('settings:write'));
        $canManageUsers = $user !== null && ($user->hasRole('superadmin') || $user->can('users:manage'));
        $canReadTemplates = $user !== null && (
            $user->hasRole('superadmin')
            || $user->can('pages:read')
            || $user->can('pages:write')
            || $user->can('posts:read')
            || $user->can('posts:write')
        );

        return [
            'locale' => $locale,
            'is_admin_ui_en' => $isAdminUiEn,
            'nav' => [
                'main' => $this->buildMainItems($canManageSystem, $canManageUsers, $canReadTemplates),
                'extensions' => $this->buildExtensionItems($user, $isAdminUiEn),
                'public' => $this->buildPublicItems($isAdminUiEn),
            ],
            'boot_payload' => [
                'locale' => $locale,
                'isAdminUiEn' => $isAdminUiEn,
                'sidebar' => [
                    'storageKey' => 'testocms_admin_sidebar_collapsed',
                    'collapseLabel' => $isAdminUiEn ? 'Collapse menu' : 'Свернуть меню',
                    'expandLabel' => $isAdminUiEn ? 'Expand menu' : 'Развернуть меню',
                    'collapseTitle' => $isAdminUiEn ? 'Collapse or expand menu' : 'Свернуть/развернуть меню',
                    'expandTitle' => $isAdminUiEn ? 'Expand or collapse menu' : 'Развернуть/свернуть меню',
                ],
                'i18n' => [
                    'enabled' => $isAdminUiEn && $adminUiMap !== [],
                    'map' => $adminUiMap,
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMainItems(bool $canManageSystem, bool $canManageUsers, bool $canReadTemplates): array
    {
        $items = [
            ['route' => 'admin.dashboard', 'label' => 'Панель', 'short_ru' => 'П', 'short_en' => 'D', 'icon' => 'layout-dashboard'],
            ['route' => 'admin.pages.index', 'label' => 'Страницы', 'short_ru' => 'С', 'short_en' => 'Pg', 'icon' => 'files'],
            ['route' => 'admin.posts.index', 'label' => 'Посты', 'short_ru' => 'Пс', 'short_en' => 'Pt', 'icon' => 'newspaper'],
            ['route' => 'admin.categories.index', 'label' => 'Категории', 'short_ru' => 'К', 'short_en' => 'C', 'icon' => 'tags'],
            ['route' => 'admin.assets.index', 'label' => 'Файлы', 'short_ru' => 'Ф', 'short_en' => 'A', 'icon' => 'image'],
            ['route' => 'admin.theme.edit', 'label' => 'Тема', 'short_ru' => 'Т', 'short_en' => 'Th', 'icon' => 'palette'],
            ['route' => 'admin.settings.edit', 'label' => 'Настройки', 'short_ru' => 'Н', 'short_en' => 'S', 'icon' => 'settings'],
            ['route' => 'admin.audit.index', 'label' => 'Аудит', 'short_ru' => 'А', 'short_en' => 'Au', 'icon' => 'shield'],
        ];

        if ($canReadTemplates) {
            array_splice($items, 3, 0, [[
                'route' => 'admin.templates.index',
                'label' => 'Шаблоны',
                'short_ru' => 'Шб',
                'short_en' => 'Tp',
                'icon' => 'layout-template',
            ]]);
        }

        if ($canManageUsers) {
            array_splice($items, 5, 0, [[
                'route' => 'admin.users.index',
                'label' => 'Пользователи',
                'short_ru' => 'Пл',
                'short_en' => 'Us',
                'icon' => 'users',
            ]]);
        }

        $settingsItemIndex = count($items);
        foreach ($items as $index => $item) {
            if (($item['route'] ?? null) === 'admin.settings.edit') {
                $settingsItemIndex = $index;
                break;
            }
        }

        if ($canManageSystem) {
            array_splice($items, $settingsItemIndex, 0, [[
                'route' => 'admin.updates.index',
                'label' => 'Обновления',
                'short_ru' => 'Об',
                'short_en' => 'Up',
                'icon' => 'refresh-cw',
                'badge' => $this->resolveCoreUpdateBadgeVersion(),
            ], [
                'route' => 'admin.api-keys.index',
                'label' => 'API',
                'short_ru' => 'API',
                'short_en' => 'API',
                'icon' => 'braces',
            ], [
                'route' => 'admin.modules.index',
                'label' => 'Модули',
                'short_ru' => 'Мд',
                'short_en' => 'Md',
                'icon' => 'puzzle',
            ]]);
        }

        return array_values(array_filter(array_map(function (array $item): ?array {
            $route = trim((string) ($item['route'] ?? ''));
            if ($route === '' || ! Route::has($route)) {
                return null;
            }

            $isActive = request()->routeIs($route)
                || (str_ends_with($route, '.index') && request()->routeIs(str_replace('.index', '.*', $route)));

            return $item + [
                'href' => route($route),
                'active' => $isActive,
            ];
        }, $items)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildExtensionItems(?User $user, bool $isAdminUiEn): array
    {
        $items = [];

        foreach ($this->moduleCache->loadEnabledModules() as $moduleRow) {
            $metadata = is_array($moduleRow['metadata'] ?? null) ? $moduleRow['metadata'] : [];
            $navItems = is_array($metadata['admin']['nav'] ?? null) ? $metadata['admin']['nav'] : [];

            foreach ($navItems as $item) {
                if (! is_array($item) || ! $this->canSeeExtensionItem($user, $item)) {
                    continue;
                }

                $label = trim((string) ($item['label'] ?? 'Module'));
                $title = trim((string) ($item['title'] ?? $label));
                $routeName = trim((string) ($item['route'] ?? ''));
                $targetUrl = trim((string) ($item['url'] ?? ''));
                $href = null;
                $external = false;

                if ($routeName !== '' && Route::has($routeName)) {
                    $href = route($routeName);
                } elseif ($targetUrl !== '') {
                    $external = $this->isAbsoluteUrl($targetUrl);
                    $href = $external ? $targetUrl : url($targetUrl);
                }

                if (! is_string($href) || $href === '') {
                    continue;
                }

                $items[] = [
                    'label' => $label,
                    'title' => $title,
                    'href' => $href,
                    'icon' => $this->normalizeIcon($item['icon'] ?? null),
                    'short' => $isAdminUiEn
                        ? trim((string) ($item['short_en'] ?? $item['short_ru'] ?? 'Md'))
                        : trim((string) ($item['short_ru'] ?? 'Мд')),
                    'active' => $routeName !== '' && (request()->routeIs($routeName) || request()->routeIs($routeName.'.*')),
                    'external' => $external,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPublicItems(bool $isAdminUiEn): array
    {
        return [
            [
                'label' => 'Открыть главную',
                'title' => 'Открыть главную',
                'href' => url('/'.config('cms.default_locale', 'en')),
                'icon' => 'house',
                'short' => $isAdminUiEn ? 'Hm' : 'Гл',
                'external' => true,
            ],
            [
                'label' => 'OpenAPI',
                'title' => 'OpenAPI',
                'href' => url('/openapi.yaml'),
                'icon' => 'file-code-2',
                'short' => 'API',
                'external' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveAdminUiMap(bool $isAdminUiEn): array
    {
        if (! $isAdminUiEn) {
            return [];
        }

        $maybeMap = trans('admin_ui_map.map');

        return is_array($maybeMap) ? $maybeMap : [];
    }

    private function resolveCoreUpdateBadgeVersion(): string
    {
        try {
            $updateState = $this->coreUpdateSettings->state();
            $available = is_array($updateState['available_release'] ?? null) ? $updateState['available_release'] : null;
            $availableVersion = trim((string) ($available['version'] ?? ''));
            if ($availableVersion !== '' && version_compare($availableVersion, $this->coreUpdateSettings->installedVersion(), '>')) {
                return $availableVersion;
            }
        } catch (\Throwable) {
            // Ignore update badge issues in shell rendering.
        }

        return '';
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    private function normalizeIcon(mixed $value): ?string
    {
        $icon = trim((string) $value);

        return $icon !== '' ? $icon : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function canSeeExtensionItem(?User $user, array $item): bool
    {
        $permissions = is_array($item['permissions_any'] ?? null) ? $item['permissions_any'] : [];
        if ($permissions === []) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return true;
        }

        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);
            if ($permission !== '' && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
