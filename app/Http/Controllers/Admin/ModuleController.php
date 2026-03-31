<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsModule;
use App\Modules\Extensibility\Services\BundledModuleCatalogService;
use App\Modules\Extensibility\Services\ModuleInstallerService;
use App\Modules\Extensibility\Services\ModuleManagerService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleManagerService $moduleManager,
        private readonly ModuleInstallerService $moduleInstaller,
        private readonly BundledModuleCatalogService $bundledCatalog,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureCanManage($request);

        return view('admin.modules.index', [
            'modules' => $this->moduleManager->listInstalled(),
            'bundledModules' => $this->bundledCatalog->list(),
            'logs' => $this->moduleManager->latestLogs(50),
            'modulesConfig' => [
                'modules_root' => (string) config('modules.modules_root'),
                'bundled_root' => (string) config('modules.bundled_root'),
                'local_install_roots' => (array) config('modules.local_install_roots', []),
                'max_zip_size_mb' => (int) config('modules.max_zip_size_mb', 30),
                'allow_symlink_dev' => (bool) config('modules.allow_symlink_dev', false),
            ],
        ]);
    }

    public function docs(Request $request): View
    {
        $this->ensureCanManage($request);

        $docsPath = base_path('docs/modules-authoring.md');
        $docsMarkdown = is_file($docsPath) ? (string) File::get($docsPath) : '';
        $docsHtml = $docsMarkdown !== ''
            ? new HtmlString((string) Str::markdown($docsMarkdown, [
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
            ]))
            : new HtmlString('');

        return view('admin.modules.docs', [
            'docsPath' => $docsPath,
            'docsMarkdown' => $docsMarkdown,
            'docsHtml' => $docsHtml,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $maxMb = (int) config('modules.max_zip_size_mb', 30);
        $validated = $request->validate([
            'module_zip' => ['required', 'file', 'mimes:zip', 'max:'.($maxMb * 1024)],
            'activate_now' => ['nullable', 'boolean'],
        ]);

        try {
            $module = $this->moduleInstaller->installFromZip($request->file('module_zip'), $request->user()?->id);
            if (! empty($validated['activate_now'])) {
                $module = $this->moduleManager->activate($module, $request->user()?->id);
            }

            $this->auditLogger->log('modules.install_zip.web', $module, [
                'module_key' => $module->module_key,
                'activate_now' => ! empty($validated['activate_now']),
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Модуль установлен.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'module_zip' => ['Ошибка установки модуля: '.$e->getMessage()],
            ]);
        }
    }

    public function installLocal(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'local_path' => ['required', 'string', 'max:1024'],
            'activate_now' => ['nullable', 'boolean'],
            'use_symlink' => ['nullable', 'boolean'],
        ]);

        try {
            $module = $this->moduleInstaller->installFromLocalPath(
                (string) $validated['local_path'],
                $request->user()?->id,
                ! empty($validated['use_symlink'])
            );
            if (! empty($validated['activate_now'])) {
                $module = $this->moduleManager->activate($module, $request->user()?->id);
            }

            $this->auditLogger->log('modules.install_local.web', $module, [
                'module_key' => $module->module_key,
                'local_path' => (string) $validated['local_path'],
                'activate_now' => ! empty($validated['activate_now']),
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Локальный модуль установлен.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'local_path' => ['Ошибка установки модуля: '.$e->getMessage()],
            ]);
        }
    }

    public function installBundled(Request $request, string $moduleKey): RedirectResponse
    {
        $this->ensureCanManage($request);

        $bundled = $this->bundledCatalog->findByRouteKey($moduleKey);
        if (! is_array($bundled)) {
            abort(404);
        }

        try {
            $module = $this->moduleInstaller->installBundled((string) ($bundled['source_path'] ?? ''), $request->user()?->id);
            if ($request->boolean('activate_now')) {
                $module = $this->moduleManager->activate($module, $request->user()?->id);
            }

            $this->auditLogger->log('modules.install_bundled.web', $module, [
                'module_key' => $module->module_key,
                'activate_now' => $request->boolean('activate_now'),
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Bundled модуль установлен.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'bundled' => ['Ошибка установки bundled-модуля: '.$e->getMessage()],
            ]);
        }
    }

    public function activate(Request $request, CmsModule $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $module = $this->moduleManager->activate($module, $request->user()?->id);
            $this->auditLogger->log('modules.activate.web', $module, [
                'module_key' => $module->module_key,
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Модуль активирован.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'module' => ['Ошибка активации: '.$e->getMessage()],
            ]);
        }
    }

    public function deactivate(Request $request, CmsModule $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $module = $this->moduleManager->deactivate($module, $request->user()?->id);
            $this->auditLogger->log('modules.deactivate.web', $module, [
                'module_key' => $module->module_key,
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Модуль деактивирован.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'module' => ['Ошибка деактивации: '.$e->getMessage()],
            ]);
        }
    }

    public function update(Request $request, CmsModule $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        $maxMb = (int) config('modules.max_zip_size_mb', 30);
        $request->validate([
            'module_zip' => ['required', 'file', 'mimes:zip', 'max:'.($maxMb * 1024)],
        ]);

        try {
            $updated = $this->moduleInstaller->updateFromZip($module, $request->file('module_zip'), $request->user()?->id);
            $this->auditLogger->log('modules.update_zip.web', $updated, [
                'module_key' => $updated->module_key,
                'version' => $updated->version,
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Модуль обновлён.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'module_zip' => ['Ошибка обновления модуля: '.$e->getMessage()],
            ]);
        }
    }

    public function destroy(Request $request, CmsModule $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'preserve_data' => ['nullable', 'boolean'],
        ]);

        try {
            $moduleKey = (string) $module->module_key;
            $this->moduleManager->uninstall($module, (bool) ($validated['preserve_data'] ?? false), $request->user()?->id);

            $this->auditLogger->log('modules.uninstall.web', null, [
                'module_key' => $moduleKey,
                'preserve_data' => (bool) ($validated['preserve_data'] ?? false),
            ], $request);

            return redirect()->route('admin.modules.index')->with('status', 'Модуль удалён.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'module' => ['Ошибка удаления модуля: '.$e->getMessage()],
            ]);
        }
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('superadmin') || $user->can('settings:write')), 403);
    }
}
