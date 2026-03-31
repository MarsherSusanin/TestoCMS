<?php

namespace App\Modules\Web\Services;

use Symfony\Component\HttpFoundation\Response;

class PublicResponseSupportService
{
    /**
     * @param  iterable<int, object>  $translations
     * @return array<string, string>
     */
    public function buildHreflangs(iterable $translations, callable $pathBuilder): array
    {
        $hreflangs = [];

        foreach ($translations as $translation) {
            $locale = (string) ($translation->locale ?? 'en');
            $slug = (string) ($translation->slug ?? '');
            $hreflangs[$locale] = url($pathBuilder($locale, $slug));
        }

        return $hreflangs;
    }

    public function applyRobotsHeader(Response $response, ?array $robots, bool $isPreview): void
    {
        if ($isPreview) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return;
        }

        $robots ??= config('seo.default_robots', []);

        $parts = [
            ($robots['index'] ?? true) ? 'index' : 'noindex',
            ($robots['follow'] ?? true) ? 'follow' : 'nofollow',
        ];

        if (($robots['noarchive'] ?? false) === true) {
            $parts[] = 'noarchive';
        }

        if (($robots['nosnippet'] ?? false) === true) {
            $parts[] = 'nosnippet';
        }

        $response->headers->set('X-Robots-Tag', implode(', ', $parts));
    }
}
