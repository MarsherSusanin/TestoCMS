<?php

namespace App\Modules\Core\Services;

use App\Models\ThemeSetting;
use App\Modules\Caching\Services\PageCacheService;
use App\Modules\Core\Contracts\SiteChromeEditorServiceContract;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteChromeEditorService implements SiteChromeEditorServiceContract
{
    public function __construct(
        private readonly SiteChromeSettingsService $siteChromeSettings,
        private readonly ChromeLinkTargetCatalogService $linkTargetCatalog,
        private readonly PageCacheService $pageCacheService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function editData(Request $request): array
    {
        $siteChrome = $this->siteChromeSettings->resolvedChrome();
        $supportedLocales = $this->supportedLocales();
        $initialChromeBuilderState = $siteChrome;

        if ($request->old('chrome_payload')) {
            try {
                $decodedChromeOld = json_decode((string) $request->old('chrome_payload'), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decodedChromeOld)) {
                    $initialChromeBuilderState = $decodedChromeOld;
                }
            } catch (\Throwable) {
                // Keep resolved state on invalid old payload.
            }
        }

        $chromeLinkTargets = $this->linkTargetCatalog->build();

        return [
            'siteChrome' => $siteChrome,
            'supportedLocales' => $supportedLocales,
            'chromeLinkTargets' => $chromeLinkTargets,
            'initialChromeBuilderState' => $initialChromeBuilderState,
            'chromeBootPayload' => [
                'savedChrome' => $siteChrome,
                'initialChrome' => $initialChromeBuilderState,
                'supportedLocales' => $supportedLocales,
                'chromeLinkTargets' => $chromeLinkTargets,
            ],
        ];
    }

    public function saveFromJson(string $json, Request $request): ThemeSetting
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                'chrome_payload' => ['Invalid chrome payload JSON: '.$e->getMessage()],
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'chrome_payload' => ['Chrome payload must be a JSON object.'],
            ]);
        }

        $payload = $this->siteChromeSettings->normalizeForSave($decoded);
        $record = $this->siteChromeSettings->save($payload, $request->user()?->id);
        $this->pageCacheService->flushAll();

        $this->auditLogger->log('site_chrome.update.web', $record, [
            'header_variant' => $payload['header']['variant'] ?? null,
            'footer_variant' => $payload['footer']['variant'] ?? null,
            'search_enabled' => $payload['search']['enabled'] ?? null,
            'search_path_slug' => $payload['search']['path_slug'] ?? null,
        ], $request);

        return $record;
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        $locales = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            config('cms.supported_locales', ['ru', 'en'])
        )));

        return $locales !== [] ? $locales : ['ru', 'en'];
    }
}
