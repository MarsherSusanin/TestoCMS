<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoreBackup;
use App\Modules\Ops\Services\AuditLogger;
use App\Modules\Updates\Services\CoreUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CoreUpdateController extends Controller
{
    public function __construct(
        private readonly CoreUpdateService $updates,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureCanManage($request);

        return view('admin.updates.index', [
            'snapshot' => $this->updates->dashboardSnapshot(),
            'logs' => $this->updates->latestLogs(60),
            'backups' => $this->updates->latestBackups(15),
        ]);
    }

    public function logs(Request $request): View
    {
        $this->ensureCanManage($request);

        return view('admin.updates.logs', [
            'logs' => $this->updates->latestLogs(120),
            'backups' => $this->updates->latestBackups(30),
            'snapshot' => $this->updates->dashboardSnapshot(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'max:32'],
            'mode' => ['required', 'string', Rule::in(['auto', 'filesystem-updater', 'deploy-hook'])],
            'server_url' => ['nullable', 'url', 'max:1024'],
            'public_key' => ['nullable', 'string', 'max:4096'],
            'deploy_hook_url' => ['nullable', 'url', 'max:1024'],
            'deploy_hook_token' => ['nullable', 'string', 'max:1024'],
            'backup_retention' => ['nullable', 'integer', 'min:1', 'max:30'],
            'http_timeout' => ['nullable', 'integer', 'min:3', 'max:120'],
        ]);

        $this->updates->saveSettings($validated, $request->user()?->id);

        $this->auditLogger->log('core_updates.settings.update.web', null, [
            'channel' => (string) ($validated['channel'] ?? ''),
            'mode' => (string) ($validated['mode'] ?? ''),
            'server_url' => ! empty($validated['server_url']) ? 'set' : 'empty',
            'public_key' => ! empty($validated['public_key']) ? 'set' : 'empty',
            'deploy_hook_url' => ! empty($validated['deploy_hook_url']) ? 'set' : 'empty',
            'backup_retention' => (int) ($validated['backup_retention'] ?? 0),
            'http_timeout' => (int) ($validated['http_timeout'] ?? 0),
        ], $request);

        return redirect()->route('admin.updates.index')->with('status', 'Настройки обновлений сохранены.');
    }

    public function check(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $result = $this->updates->checkRemote($request->user()?->id);

            $this->auditLogger->log('core_updates.check.web', null, [
                'current_version' => (string) ($result['current_version'] ?? ''),
                'target_version' => (string) ($result['manifest']['version'] ?? ''),
                'is_newer' => (bool) ($result['is_newer'] ?? false),
            ], $request);

            $message = ! empty($result['is_newer'])
                ? 'Доступно обновление до версии '.(string) ($result['manifest']['version'] ?? '').'.'
                : 'Обновления не найдены.';

            return redirect()->route('admin.updates.index')->with('status', $message);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'update_check' => ['Ошибка проверки обновлений: '.$e->getMessage()],
            ]);
        }
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $maxMb = (int) config('updates.max_zip_size_mb', 120);
        $request->validate([
            'release_zip' => ['required', 'file', 'mimes:zip', 'max:'.($maxMb * 1024)],
        ]);

        try {
            $result = $this->updates->uploadManualPackage($request->file('release_zip'), $request->user()?->id);

            $this->auditLogger->log('core_updates.upload.web', null, [
                'target_version' => (string) ($result['version'] ?? ''),
                'zip_path' => (string) ($result['zip_path'] ?? ''),
            ], $request);

            return redirect()->route('admin.updates.index')->with('status', 'Пакет обновления загружен: v'.(string) ($result['version'] ?? 'unknown'));
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'release_zip' => ['Ошибка загрузки пакета: '.$e->getMessage()],
            ]);
        }
    }

    public function apply(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $result = $this->updates->applyUpdate($request->user()?->id);

            $this->auditLogger->log('core_updates.apply.web', null, [
                'mode' => (string) ($result['mode'] ?? ''),
                'status' => (string) ($result['status'] ?? ''),
                'target_version' => (string) ($result['target_version'] ?? ''),
                'backup_key' => (string) ($result['backup_key'] ?? ''),
            ], $request);

            $message = (string) (($result['mode'] ?? '') === 'deploy-hook'
                ? 'Запрос на обновление отправлен в deploy hook.'
                : 'Обновление успешно применено.');

            return redirect()->route('admin.updates.index')->with('status', $message);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'update_apply' => ['Ошибка применения обновления: '.$e->getMessage()],
            ]);
        }
    }

    public function rollback(Request $request, CoreBackup $backup): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $result = $this->updates->rollbackBackup($backup, $request->user()?->id);

            $this->auditLogger->log('core_updates.rollback.web', $backup, [
                'backup_key' => (string) ($backup->backup_key ?? ''),
                'status' => (string) ($result['status'] ?? ''),
                'restored_version' => (string) ($result['restored_version'] ?? ''),
            ], $request);

            return redirect()->route('admin.updates.index')->with('status', 'Откат выполнен для backup '.$backup->backup_key.'.');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'rollback' => ['Ошибка отката: '.$e->getMessage()],
            ]);
        }
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('superadmin') || $user->can('settings:write')), 403);
    }
}
