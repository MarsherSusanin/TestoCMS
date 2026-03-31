<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Web\Services\LocalizedSiteRouterService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteContentController extends Controller
{
    public function __construct(private readonly LocalizedSiteRouterService $router)
    {
    }

    public function __invoke(Request $request, string $locale, ?string $slug = null): Response
    {
        return $this->router->dispatch($request, $locale, $slug);
    }
}
