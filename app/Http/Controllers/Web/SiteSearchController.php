<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Web\Services\PublicSearchService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiteSearchController extends Controller
{
    public function __construct(private readonly PublicSearchService $searchService)
    {
    }

    public function __invoke(Request $request, string $locale): Response
    {
        return $this->searchService->render($request, $locale);
    }
}
