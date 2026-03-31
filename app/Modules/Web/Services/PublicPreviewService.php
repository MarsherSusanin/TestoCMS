<?php

namespace App\Modules\Web\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\PreviewToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicPreviewService
{
    public function __construct(
        private readonly PublicPageResolverService $pageResolver,
        private readonly PublicPostResolverService $postResolver,
    ) {
    }

    public function render(Request $request, string $token): Response
    {
        $previewToken = PreviewToken::query()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $locale = strtolower((string) $request->query('locale', config('cms.default_locale', 'en')));
        app()->setLocale($locale);

        $previewToken->used_at = now();
        $previewToken->save();

        if ($previewToken->entity_type === 'post') {
            $post = Post::query()->findOrFail($previewToken->entity_id);
            $translation = $post->translations()->where('locale', $locale)->first()
                ?? $post->translations()->where('locale', config('cms.default_locale'))->firstOrFail();

            return $this->postResolver->render($locale, $post, $translation, true);
        }

        $page = Page::query()->findOrFail($previewToken->entity_id);
        $translation = $page->translations()->where('locale', $locale)->first()
            ?? $page->translations()->where('locale', config('cms.default_locale'))->firstOrFail();

        return $this->pageResolver->render($locale, $page, $translation, true);
    }
}
