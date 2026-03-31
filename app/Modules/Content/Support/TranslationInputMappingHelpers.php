<?php

namespace App\Modules\Content\Support;

trait TranslationInputMappingHelpers
{
    /**
     * @param array<int|string, mixed> $translationsInput
     * @return array<string, array<string, mixed>>
     */
    protected function translationsInputByLocale(array $translationsInput): array
    {
        $map = [];

        if (array_is_list($translationsInput)) {
            foreach ($translationsInput as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $locale = strtolower(trim((string) ($item['locale'] ?? '')));
                if ($locale === '') {
                    continue;
                }
                $map[$locale] = $item;
            }

            return $map;
        }

        foreach ($translationsInput as $locale => $item) {
            if (! is_array($item)) {
                continue;
            }
            $normalizedLocale = strtolower(trim((string) $locale));
            if ($normalizedLocale === '') {
                continue;
            }
            $map[$normalizedLocale] = $item;
        }

        return $map;
    }

    /**
     * @param array<int|string, mixed> $translationsInput
     * @param array<string, array<string, mixed>> $inputByLocale
     * @return array<int, string>
     */
    protected function resolveLocalesForInput(array $translationsInput, array $inputByLocale): array
    {
        if (array_is_list($translationsInput)) {
            return array_keys($inputByLocale);
        }

        return $this->supportedLocales();
    }

    /**
     * @param array<int|string, mixed> $translationsInput
     */
    protected function shouldRequireDefaultLocale(array $translationsInput): bool
    {
        return ! array_is_list($translationsInput);
    }
}
