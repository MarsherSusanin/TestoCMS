<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminRuntimeController extends Controller
{
    public function show(Request $request, string $runtime): Response
    {
        abort_unless($request->user(), 403);

        $path = $this->resolveRuntimePath($runtime);
        abort_unless($path !== null && is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveRuntimePath(string $runtime): ?string
    {
        $map = [
            'admin-shell.js' => resource_path('js/admin/shell/admin-shell.js'),
            'admin-i18n.js' => resource_path('js/admin/shell/admin-i18n.js'),
            'admin-ui.js' => resource_path('js/admin/shared/admin-ui.js'),
            'media-picker.js' => resource_path('js/admin/shared/media-picker.js'),
            'asset-selector.js' => resource_path('js/admin/shared/asset-selector.js'),
            'api-keys.js' => resource_path('js/admin/api-keys/api-keys.js'),
            'editor-shared.js' => resource_path('js/admin/editors/shared/editor-shared.js'),
            'page-form.js' => resource_path('js/admin/editors/pages/page-form.js'),
            'page-fullscreen.js' => resource_path('js/admin/editors/pages/page-fullscreen.js'),
            'post-form.js' => resource_path('js/admin/editors/posts/post-form.js'),
            'theme-builder.js' => resource_path('js/admin/theme/theme-builder.js'),
            'chrome-builder.js' => resource_path('js/admin/theme/chrome-builder.js'),
        ];

        return $map[$runtime] ?? null;
    }
}
