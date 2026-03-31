<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSetting;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function edit(Request $request): View
    {
        $this->ensureCanManage($request);

        $settings = SeoSetting::global();

        return view('admin.settings.seo.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'robots_txt_custom' => ['nullable', 'string', 'max:5000'],
            'llms_txt_intro' => ['nullable', 'string', 'max:5000'],
        ]);

        $settings = SeoSetting::global();
        $settings->update($validated);

        $this->auditLogger->log('admin.settings.seo.update', null, [
            'has_custom_robots' => !empty($validated['robots_txt_custom']),
            'has_custom_llms' => !empty($validated['llms_txt_intro']),
        ], $request);

        return redirect()
            ->route('admin.settings.seo.edit')
            ->with('status', 'Настройки SEO сохранены.');
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('superadmin') || $user->can('settings:write')), 403);
    }
}
