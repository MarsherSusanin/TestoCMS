<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\SiteChromeEditorServiceContract;
use App\Modules\Core\Contracts\ThemeEditorServiceContract;
use App\Modules\Core\Services\ThemeSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeEditorServiceContract $themeEditor,
        private readonly SiteChromeEditorServiceContract $siteChromeEditor,
        private readonly ThemeSettingsService $themeSettings,
    ) {}

    public function edit(Request $request): View
    {
        $this->ensureCanManage($request);

        $themeData = $this->themeEditor->editData($request);
        $chromeData = $this->siteChromeEditor->editData($request);

        return view('admin.theme.edit', array_merge($themeData, $chromeData, [
            'adminThemeBootPayload' => array_merge(
                $themeData['themeBootPayload'] ?? [],
                $chromeData['chromeBootPayload'] ?? [],
            ),
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $this->validateTheme($request);
        $this->themeEditor->save($validated, $request);

        return redirect()->route('admin.theme.edit')->with('status', 'Theme saved.');
    }

    public function applyPreset(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'preset_key' => ['required', 'string', Rule::in($this->themeSettings->presetKeys())],
        ]);

        $presetKey = (string) $validated['preset_key'];
        $this->themeEditor->applyPreset($presetKey, $request);

        return redirect()->route('admin.theme.edit')->with('status', 'Preset applied.');
    }

    public function updateChrome(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'chrome_payload' => ['required', 'string'],
        ]);

        $this->siteChromeEditor->saveFromJson((string) $validated['chrome_payload'], $request);

        return redirect()->route('admin.theme.edit')->with('status', 'Header/Footer/Search settings saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTheme(Request $request): array
    {
        $rules = [
            'preset_key' => ['required', 'string', Rule::in($this->themeSettings->presetKeys())],
            'body_font' => ['required', 'string', Rule::in($this->themeSettings->fontKeys())],
            'heading_font' => ['required', 'string', Rule::in($this->themeSettings->fontKeys())],
            'mono_font' => ['required', 'string', Rule::in($this->themeSettings->fontKeys())],
        ];

        foreach ($this->themeSettings->colorKeys() as $colorKey) {
            $rules["colors.$colorKey"] = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        return $request->validate($rules);
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('superadmin') || $user->can('settings:write')), 403);
    }
}
