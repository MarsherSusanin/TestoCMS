<?php

namespace TestoCms\Booking\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\SeoResolverContract;
use App\Modules\Web\Services\PublicResponseSupportService;
use Illuminate\Http\Response;
use TestoCms\Booking\Services\BookingCatalogService;
use TestoCms\Booking\Services\BookingSettingsService;

class BookingPageController extends Controller
{
    public function __construct(
        private readonly BookingCatalogService $catalog,
        private readonly BookingSettingsService $settings,
        private readonly SeoResolverContract $seoResolver,
        private readonly PublicResponseSupportService $responseSupport,
    ) {}

    public function index(string $locale): Response
    {
        $services = $this->catalog->activeServices($locale);
        $prefix = trim((string) ($this->settings->resolved()['public_prefix'] ?? config('cms.booking_url_prefix', 'book')), '/');
        $canonical = '/'.trim($locale.'/'.$prefix, '/');
        $seo = $this->seoResolver->resolve('booking_index', 0, $locale, [
            'meta_title' => $locale === 'ru' ? 'Бронирование услуг' : 'Service Booking',
            'meta_description' => $locale === 'ru'
                ? 'Выберите услугу и забронируйте удобный слот онлайн.'
                : 'Choose a service and reserve a convenient slot online.',
            'canonical_url' => $canonical,
        ]);

        $response = response()->view('booking-module::public.index', [
            'locale' => $locale,
            'services' => $services,
            'seo' => $seo,
            'structuredData' => null,
            'customHeadHtml' => null,
            'hreflangs' => [
                'ru' => url('/ru/'.$prefix),
                'en' => url('/en/'.$prefix),
            ],
            'isPreview' => false,
            'bookingSettings' => $this->settings->resolved(),
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'] ?? null, false);

        return $response;
    }

    public function showService(string $locale, string $slug): Response
    {
        $service = $this->catalog->findActiveServiceBySlug($locale, $slug);
        abort_unless($service, 404);
        $translation = $service->getRelation('current_translation');
        $prefix = trim((string) ($this->settings->resolved()['public_prefix'] ?? config('cms.booking_url_prefix', 'book')), '/');
        $canonical = '/'.trim($locale.'/'.$prefix.'/services/'.$translation->slug, '/');

        $seo = $this->seoResolver->resolve('booking_service', (int) $service->id, $locale, [
            'meta_title' => $translation->meta_title ?: $translation->title,
            'meta_description' => $translation->meta_description ?: $translation->short_description,
            'canonical_url' => $canonical,
        ]);

        $response = response()->view('booking-module::public.service', [
            'locale' => $locale,
            'service' => $service,
            'translation' => $translation,
            'seo' => $seo,
            'structuredData' => null,
            'customHeadHtml' => null,
            'hreflangs' => $this->responseSupport->buildHreflangs(
                $service->translations,
                fn (string $lang, string $serviceSlug): string => '/'.$lang.'/'.$prefix.'/services/'.$serviceSlug
            ),
            'isPreview' => false,
            'bookingSettings' => $this->settings->resolved(),
        ]);

        $this->responseSupport->applyRobotsHeader($response, $seo['robots_directives'] ?? null, false);

        return $response;
    }
}
