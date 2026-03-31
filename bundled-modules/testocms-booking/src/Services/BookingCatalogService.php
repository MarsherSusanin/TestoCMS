<?php

namespace TestoCms\Booking\Services;

use Illuminate\Support\Collection;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingServiceTranslation;

class BookingCatalogService
{
    public function activeServices(string $locale): Collection
    {
        return BookingService::query()
            ->with(['translations', 'location', 'resources', 'image'])
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (BookingService $service) use ($locale): BookingService {
                $service->setRelation('current_translation', $this->translationForLocale($service, $locale));

                return $service;
            })
            ->filter(fn (BookingService $service): bool => $service->getRelation('current_translation') instanceof BookingServiceTranslation)
            ->values();
    }

    public function findActiveServiceBySlug(string $locale, string $slug): ?BookingService
    {
        $translation = BookingServiceTranslation::query()
            ->where('locale', strtolower(trim($locale)))
            ->where('slug', trim($slug, '/'))
            ->first();

        if (! $translation) {
            return null;
        }

        $service = BookingService::query()
            ->with(['translations', 'location', 'resources', 'image'])
            ->whereKey($translation->service_id)
            ->where('is_active', true)
            ->first();

        if (! $service) {
            return null;
        }

        $service->setRelation('current_translation', $translation);

        return $service;
    }

    public function translationForLocale(BookingService $service, string $locale): ?BookingServiceTranslation
    {
        $locale = strtolower(trim($locale));
        $translations = $service->relationLoaded('translations')
            ? $service->translations
            : $service->translations()->get();

        $current = $translations->firstWhere('locale', $locale);
        if ($current) {
            return $current;
        }

        $defaultLocale = strtolower((string) config('cms.default_locale', 'ru'));
        $fallback = $translations->firstWhere('locale', $defaultLocale);
        if ($fallback) {
            return $fallback;
        }

        return $translations->first();
    }
}
