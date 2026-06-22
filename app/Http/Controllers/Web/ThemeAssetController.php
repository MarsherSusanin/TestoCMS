<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\ThemeCssRenderer;
use Illuminate\Http\Response;

class ThemeAssetController extends Controller
{
    public function __construct(private readonly ThemeCssRenderer $themeCssRenderer) {}

    /**
     * Serve the theme-independent base stylesheet as an external, immutable,
     * long-cached file so it is not re-inlined into every (cached) page.
     * Cache-busting is handled by the ?v=<hash> query the link carries.
     */
    public function baseCss(): Response
    {
        return response($this->themeCssRenderer->baseCss(), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.$this->themeCssRenderer->baseCssHash().'"',
        ]);
    }
}
