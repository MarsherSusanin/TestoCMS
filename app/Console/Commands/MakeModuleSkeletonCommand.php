<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class MakeModuleSkeletonCommand extends Command
{
    protected $signature = 'cms:module:make
        {module : Module key in vendor/module-name format}
        {--force : Overwrite existing scaffold directory}';

    protected $description = 'Generate a TestoCMS module scaffold in the local modules-dev root';

    public function handle(): int
    {
        $moduleKey = strtolower(trim((string) $this->argument('module')));
        if (! preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $moduleKey)) {
            $this->error('Module key must match vendor/module-name.');

            return self::FAILURE;
        }

        [$vendor, $moduleName] = explode('/', $moduleKey, 2);
        $targetRoot = $this->resolveTargetRoot();
        $targetPath = rtrim($targetRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->moduleDirName($moduleKey);

        if (is_dir($targetPath)) {
            if (! (bool) $this->option('force')) {
                $this->error('Target module directory already exists. Use --force to overwrite: '.$targetPath);

                return self::FAILURE;
            }

            File::deleteDirectory($targetPath);
        }

        $namespace = $this->buildNamespace($vendor, $moduleName);
        $moduleSlug = str_replace('/', '-', $moduleKey);
        $routeKey = str_replace(['/', '-', '.'], '_', $moduleKey);
        $routeName = 'module.'.$routeKey.'.index';
        $viewNamespace = 'module-'.$moduleSlug;
        $adminUri = 'modules/'.str_replace('/', '-', $moduleKey);

        File::ensureDirectoryExists($targetPath.'/src');
        File::ensureDirectoryExists($targetPath.'/routes');
        File::ensureDirectoryExists($targetPath.'/resources/views/admin');
        File::ensureDirectoryExists($targetPath.'/database/migrations');
        File::ensureDirectoryExists($targetPath.'/public');

        $manifest = [
            'id' => $moduleKey,
            'name' => Str::headline(str_replace(['-', '_', '.'], ' ', $moduleName)),
            'version' => '1.0.0',
            'description' => 'Custom module scaffold for TestoCMS.',
            'author' => 'Your Team',
            'provider' => $namespace.'\\ModuleServiceProvider',
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => 'src/',
                ],
            ],
            'admin' => [
                'nav' => [
                    [
                        'key' => 'main',
                        'label' => Str::headline(str_replace(['-', '_', '.'], ' ', $moduleName)),
                        'route' => $routeName,
                        'icon' => 'puzzle',
                        'short_ru' => 'МД',
                        'short_en' => 'MD',
                    ],
                ],
            ],
            'requires' => [
                'cms' => '>='.(string) config('modules.cms_version', '1.0.0'),
                'php' => '>='.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            ],
            'capabilities' => [
                'routes' => true,
                'views' => true,
                'migrations' => 'database/migrations',
                'assets' => true,
                'settings_page' => true,
            ],
            'docs_url' => null,
        ];

        File::put($targetPath.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        File::put($targetPath.'/src/ModuleServiceProvider.php', $this->providerTemplate($namespace, $moduleKey));
        File::put($targetPath.'/routes/admin.php', $this->routesTemplate($adminUri, $routeName, $viewNamespace));
        File::put($targetPath.'/resources/views/admin/index.blade.php', $this->viewTemplate($manifest['name']));
        File::put($targetPath.'/database/migrations/.gitkeep', '');
        File::put($targetPath.'/public/.gitkeep', '');
        File::put($targetPath.'/README.md', $this->readmeTemplate($moduleKey));

        $this->info('Module scaffold created.');
        $this->line('Path: '.$targetPath);
        $this->newLine();
        $this->line('Next steps:');
        $this->line('1) Edit module.json and provider/routes/views.');
        $this->line('2) Install via admin /admin/modules (local path) or zip the module directory.');
        $this->line('3) Activate the module in admin UI.');

        return self::SUCCESS;
    }

    private function resolveTargetRoot(): string
    {
        $roots = (array) config('modules.local_install_roots', [base_path('modules-dev')]);
        $first = trim((string) ($roots[0] ?? ''));
        if ($first === '') {
            throw new RuntimeException('No local module roots configured. Set CMS_MODULE_LOCAL_ROOTS.');
        }

        File::ensureDirectoryExists($first);
        $real = realpath($first);
        if (! is_string($real) || $real === '') {
            throw new RuntimeException('Cannot resolve local module root: '.$first);
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function moduleDirName(string $moduleKey): string
    {
        return str_replace('/', '--', strtolower($moduleKey));
    }

    private function buildNamespace(string $vendor, string $moduleName): string
    {
        $vendorNs = $this->toStudlyNamespaceSegment($vendor);
        $moduleNs = $this->toStudlyNamespaceSegment($moduleName);

        return $vendorNs.'\\'.$moduleNs;
    }

    private function toStudlyNamespaceSegment(string $value): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $value) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        $studly = implode('', array_map(static fn (string $part): string => Str::studly($part), $parts));

        return $studly !== '' ? $studly : 'Module';
    }

    private function providerTemplate(string $namespace, string $moduleKey): string
    {
        $slug = str_replace('/', '-', $moduleKey);
        $routeKey = str_replace(['/', '-', '.'], '_', $moduleKey);
        $routeName = 'module.'.$routeKey.'.index';

        return <<<PHP
<?php

namespace {$namespace};

use App\Modules\Extensibility\Registry\AdminNavigationRegistry;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \$base = dirname(__DIR__);
        \$this->loadRoutesFrom(\$base.'/routes/admin.php');
        \$this->loadViewsFrom(\$base.'/resources/views', 'module-{$slug}');

        app(AdminNavigationRegistry::class)->register([
            'key' => 'module:{$moduleKey}:main',
            'label' => 'Module {$moduleKey}',
            'route' => '{$routeName}',
            'icon' => 'puzzle',
            'short_ru' => 'МД',
            'short_en' => 'MD',
        ]);
    }
}

PHP;
    }

    private function routesTemplate(string $adminUri, string $routeName, string $viewNamespace): string
    {
        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin')->group(function (): void {
    Route::get('{$adminUri}', function () {
        \$user = auth()->user();
        abort_unless(\$user && (\$user->hasRole('superadmin') || \$user->can('settings:write')), 403);

        return view('{$viewNamespace}::admin.index');
    })->name('{$routeName}');
});

PHP;
    }

    private function viewTemplate(string $title): string
    {
        $safeTitle = str_replace(['\\', "'"], ['', "\\'"], $title);

        return <<<BLADE
@extends('admin.layout')

@section('title', '{$safeTitle}')

@section('content')
    <div class="page-header">
        <div>
            <h1>{$safeTitle}</h1>
            <p>Стартовая страница модуля.</p>
        </div>
    </div>

    <section class="panel">
        <p>Модуль подключен успешно. Отредактируйте этот шаблон и добавьте нужный функционал.</p>
    </section>
@endsection

BLADE;
    }

    private function readmeTemplate(string $moduleKey): string
    {
        return <<<MD
# {$moduleKey}

TestoCMS custom module scaffold.

## Develop
1. Update `module.json`.
2. Implement provider logic in `src/ModuleServiceProvider.php`.
3. Add routes in `routes/admin.php`.
4. Build UI in `resources/views`.

## Install
- Via admin: **Modules -> Install local path**.
- Path should point to this module directory.
- Activate after install.

## Notes
- Module is trusted PHP code.
- Keep compatibility with current TestoCMS version.

MD;
    }
}
