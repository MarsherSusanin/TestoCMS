<?php

namespace App\Modules\I18n\Services;

class LocaleResolver
{
    public function normalize(?string $locale): string
    {
        $locale = strtolower((string) ($locale ?: config('cms.default_locale', 'en')));
        $supported = config('cms.supported_locales', ['en']);

        return in_array($locale, $supported, true)
            ? $locale
            : (string) config('cms.default_locale', 'en');
    }
}
