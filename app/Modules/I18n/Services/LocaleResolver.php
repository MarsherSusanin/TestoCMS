<?php

namespace App\Modules\I18n\Services;

class LocaleResolver
{
    public function normalize(?string $locale): string
    {
        $candidate = strtolower((string) ($locale ?: ''));

        return in_array($candidate, $this->supported(), true)
            ? $candidate
            : $this->effectiveDefault();
    }

    /**
     * The configured default locale, but only if it is actually one of the
     * supported locales — otherwise the first supported locale. Prevents the
     * site root (and locale fallbacks) from resolving to a non-routable locale
     * when default_locale and supported_locales are misconfigured.
     */
    public function effectiveDefault(): string
    {
        $supported = $this->supported();
        $default = strtolower((string) config('cms.default_locale', 'en'));

        if (in_array($default, $supported, true)) {
            return $default;
        }

        return $supported[0] ?? 'en';
    }

    /**
     * @return list<string>
     */
    private function supported(): array
    {
        $supported = array_values(array_filter(array_map(
            static fn (mixed $locale): string => strtolower(trim((string) $locale)),
            (array) config('cms.supported_locales', ['en'])
        )));

        return $supported !== [] ? $supported : ['en'];
    }
}
