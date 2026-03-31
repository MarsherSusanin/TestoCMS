<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Web\Services\PublicPreviewService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SitePreviewController extends Controller
{
    public function __construct(private readonly PublicPreviewService $previewService)
    {
    }

    public function __invoke(Request $request, string $token): Response
    {
        return $this->previewService->render($request, $token);
    }
}
