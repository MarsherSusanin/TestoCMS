<?php

namespace App\Modules\Content\Support;

use App\Modules\Core\Services\SiteChromeSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait LocalizedContentHelpers
{
    /**
     * @return array<int, string>
     */
    protected function supportedLocales(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            config('cms.supported_locales', ['en'])
        )));
    }

    protected function defaultLocale(): string
    {
        return strtolower((string) config('cms.default_locale', 'en'));
    }

    /**
     * @param  iterable<int, object>  $translations
     * @return array<string, object>
     */
    protected function translationsByLocale(iterable $translations): array
    {
        $map = [];
        foreach ($translations as $translation) {
            $locale = strtolower((string) ($translation->locale ?? ''));
            if ($locale !== '') {
                $map[$locale] = $translation;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<int, string>  $fields
     */
    protected function requireDefaultLocaleTranslation(array $translations, array $fields = ['title', 'slug']): void
    {
        $default = $this->defaultLocale();
        $item = $translations[$default] ?? null;

        if (! is_array($item)) {
            throw ValidationException::withMessages([
                'translations' => ["Default locale ({$default}) translation is required."],
            ]);
        }

        foreach ($fields as $field) {
            if (trim((string) ($item[$field] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "translations.{$default}.{$field}" => ["Field {$field} is required for locale {$default}."],
                ]);
            }
        }
    }

    /**
     * @return array<int, mixed>|null
     */
    protected function decodeJsonArrayText(?string $json, string $field): ?array
    {
        $json = trim((string) $json);
        if ($json === '') {
            return null;
        }

        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                $field => ['Invalid JSON: '.$e->getMessage()],
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => ['JSON value must be an array.'],
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>|null  $robotsInput
     * @return array<string, bool>|null
     */
    protected function normalizeRobots(?array $robotsInput): ?array
    {
        if (! is_array($robotsInput)) {
            return null;
        }

        $hasAny = collect($robotsInput)->filter(static fn (mixed $value): bool => (string) $value !== '')->isNotEmpty();
        if (! $hasAny) {
            return null;
        }

        return [
            'index' => ! (bool) ($robotsInput['noindex'] ?? false),
            'follow' => ! (bool) ($robotsInput['nofollow'] ?? false),
            'noarchive' => (bool) ($robotsInput['noarchive'] ?? false),
            'nosnippet' => (bool) ($robotsInput['nosnippet'] ?? false),
        ];
    }

    protected function normalizeTextarea(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function generateSlugFromTitle(string $title): string
    {
        $value = trim($title);
        if ($value === '') {
            return '';
        }

        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
            'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
            'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, $map);
        $value = Str::ascii($value);
        $value = preg_replace('/[^a-z0-9\\s_-]+/i', '', $value) ?? '';
        $value = preg_replace('/[\\s_]+/', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    protected function defaultCanonicalUrlForPage(string $locale, string $slug): ?string
    {
        $locale = trim(strtolower($locale), '/');
        $slug = trim($slug, '/');

        if ($locale === '' || $slug === '') {
            return null;
        }

        $path = strtolower($slug) === 'home'
            ? '/'.$locale
            : '/'.$locale.'/'.$slug;

        return url($path);
    }

    protected function defaultCanonicalUrlForPost(string $locale, string $slug): ?string
    {
        $locale = trim(strtolower($locale), '/');
        $slug = trim($slug, '/');
        $prefix = trim((string) config('cms.post_url_prefix', 'blog'), '/');

        if ($locale === '' || $slug === '' || $prefix === '') {
            return null;
        }

        return url('/'.$locale.'/'.$prefix.'/'.$slug);
    }

    protected function assertSlugAllowed(string $slug, string $field, string $locale): void
    {
        $normalized = trim(strtolower($slug), '/');
        if ($normalized === '') {
            return;
        }

        if (str_contains($normalized, '/')) {
            throw ValidationException::withMessages([
                $field => [sprintf('Slug for locale %s must be a single path segment (without "/").', strtoupper($locale))],
            ]);
        }

        $reserved = $this->reservedPublicSlugs();
        if (in_array($normalized, $reserved, true)) {
            throw ValidationException::withMessages([
                $field => [sprintf(
                    'Slug "%s" is reserved for system routes (%s). Choose another slug for locale %s.',
                    $normalized,
                    implode(', ', $reserved),
                    strtoupper($locale)
                )],
            ]);
        }
    }

    protected function boolFromMixed(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, mixed>
     */
    protected function values(iterable $items): array
    {
        return $items instanceof Collection ? $items->values()->all() : array_values(is_array($items) ? $items : iterator_to_array($items));
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    protected function assertUniqueTranslationSlugs(
        array $translations,
        string $translationTable,
        string $ownerForeignKey,
        ?int $ownerId,
        string $entityLabel
    ): void {
        foreach ($translations as $locale => $item) {
            if (! is_array($item)) {
                continue;
            }

            $slug = trim((string) ($item['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $query = DB::table($translationTable)
                ->where('locale', strtolower((string) $locale))
                ->where('slug', trim($slug, '/'));

            if ($ownerId !== null) {
                $query->where($ownerForeignKey, '!=', $ownerId);
            }

            $conflictOwnerId = $query->value($ownerForeignKey);
            if ($conflictOwnerId === null) {
                continue;
            }

            $message = sprintf(
                'Slug "%s" is already used by another %s in locale %s. Choose another slug.',
                trim($slug, '/'),
                $entityLabel,
                strtoupper((string) $locale)
            );

            throw ValidationException::withMessages([
                "translations.{$locale}.slug" => [$message],
                "translations.{$locale}" => [$message],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function reservedPublicSlugs(): array
    {
        $searchSlug = null;
        try {
            $searchSlug = app(SiteChromeSettingsService::class)->searchPathSlug();
        } catch (\Throwable) {
            $searchSlug = 'search';
        }

        $candidates = [
            strtolower(trim((string) $searchSlug, '/')),
            strtolower(trim((string) config('cms.post_url_prefix', 'blog'), '/')),
            strtolower(trim((string) config('cms.category_url_prefix', 'category'), '/')),
            strtolower(trim((string) config('cms.booking_url_prefix', 'book'), '/')),
        ];

        return array_values(array_unique(array_filter($candidates, static fn (string $value): bool => $value !== '')));
    }
}
