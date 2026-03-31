<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingServiceTranslation;
use TestoCms\Booking\Services\BookingCatalogService;
use TestoCms\Booking\Services\BookingSlotProjectionService;

class ServiceController extends Controller
{
    use EnsuresBookingPermissions;

    public function __construct(
        private readonly BookingCatalogService $catalog,
        private readonly BookingSlotProjectionService $projection,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureBookingRead($request);
        $locale = strtolower((string) app()->getLocale());

        $services = BookingService::query()
            ->with(['translations', 'location', 'resources', 'image'])
            ->latest('id')
            ->paginate(20)
            ->through(function (BookingService $service) use ($locale): BookingService {
                $service->setRelation('current_translation', $this->catalog->translationForLocale($service, $locale));

                return $service;
            });

        return view('booking-module::admin.services.index', [
            'services' => $services,
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureBookingWrite($request);

        return view('booking-module::admin.services.form', $this->formViewData(new BookingService([
            'duration_minutes' => 60,
            'slot_step_minutes' => 30,
            'booking_horizon_days' => 90,
            'lead_time_minutes' => 60,
            'confirmation_mode' => 'manual',
            'resource_selection_mode' => 'auto_assign',
            'price_currency' => 'RUB',
            'is_active' => true,
        ]), false));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        [$entityPayload, $translations, $resourceIds] = $this->validatedPayload($request);

        $service = DB::transaction(function () use ($entityPayload, $translations, $resourceIds): BookingService {
            $service = BookingService::query()->create($entityPayload);
            $service->resources()->sync($resourceIds);
            $this->syncTranslations($service, $translations);
            $this->projection->rebuildService($service->fresh(['rules', 'exceptions']) ?? $service);

            return $service;
        });

        $this->audit->log('booking.service.create.web', $service, [], $request);

        return redirect()->route('booking.admin.services.index')->with('status', 'Услуга создана.');
    }

    public function edit(Request $request, BookingService $service): View
    {
        $this->ensureBookingWrite($request);
        $service->loadMissing(['translations', 'resources']);

        return view('booking-module::admin.services.form', $this->formViewData($service, true));
    }

    public function update(Request $request, BookingService $service): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        [$entityPayload, $translations, $resourceIds] = $this->validatedPayload($request, $service);

        DB::transaction(function () use ($service, $entityPayload, $translations, $resourceIds): void {
            $service->fill($entityPayload);
            $service->save();
            $service->resources()->sync($resourceIds);
            $this->syncTranslations($service, $translations);
            $this->projection->rebuildService($service->fresh(['rules', 'exceptions']) ?? $service);
        });

        $this->audit->log('booking.service.update.web', $service->fresh(), [], $request);

        return redirect()->route('booking.admin.services.index')->with('status', 'Услуга обновлена.');
    }

    public function destroy(Request $request, BookingService $service): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $id = $service->id;
        $service->delete();
        $this->audit->log('booking.service.delete.web', null, ['service_id' => $id], $request);

        return redirect()->route('booking.admin.services.index')->with('status', 'Услуга удалена.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: array<int, int>}
     */
    private function validatedPayload(Request $request, ?BookingService $service = null): array
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:booking_locations,id'],
            'featured_asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'slot_step_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'booking_horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'lead_time_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'confirmation_mode' => ['required', 'in:instant,manual'],
            'resource_selection_mode' => ['required', 'in:auto_assign,choose_resource'],
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'price_currency' => ['required', 'string', 'size:3'],
            'price_label' => ['nullable', 'string', 'max:120'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer', 'exists:booking_resources,id'],
            'translations' => ['required', 'array'],
        ]);

        $translations = [];
        foreach ($this->supportedLocales() as $locale) {
            $raw = is_array($request->input('translations.'.$locale)) ? $request->input('translations.'.$locale) : [];
            $title = trim((string) ($raw['title'] ?? ''));
            $slug = trim((string) ($raw['slug'] ?? ''));
            $short = trim((string) ($raw['short_description'] ?? ''));
            $full = trim((string) ($raw['full_description'] ?? ''));
            $metaTitle = trim((string) ($raw['meta_title'] ?? ''));
            $metaDescription = trim((string) ($raw['meta_description'] ?? ''));

            if ($title === '' && $slug === '' && $short === '' && $full === '' && $metaTitle === '' && $metaDescription === '') {
                continue;
            }

            $title = $title !== '' ? $title : Str::headline($slug !== '' ? $slug : 'service');
            $translations[] = [
                'locale' => $locale,
                'title' => $title,
                'slug' => $this->uniqueSlug($locale, $slug !== '' ? $slug : $title, $service),
                'short_description' => $short !== '' ? $short : null,
                'full_description' => $full !== '' ? $full : null,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            ];
        }

        if ($translations === []) {
            throw ValidationException::withMessages([
                'translations' => ['Заполните хотя бы одну локаль услуги.'],
            ]);
        }

        $resourceIds = array_values(array_map('intval', (array) ($validated['resource_ids'] ?? [])));
        $activeResourcesCount = $resourceIds === []
            ? 0
            : BookingResource::query()->whereIn('id', $resourceIds)->where('is_active', true)->count();
        if ((string) $validated['resource_selection_mode'] === 'choose_resource' && $activeResourcesCount < 1) {
            throw ValidationException::withMessages([
                'resource_ids' => ['Для режима выбора ресурса привяжите хотя бы один активный ресурс.'],
            ]);
        }

        return [[
            'location_id' => $validated['location_id'] ?? null,
            'featured_asset_id' => $validated['featured_asset_id'] ?? null,
            'duration_minutes' => (int) $validated['duration_minutes'],
            'slot_step_minutes' => (int) $validated['slot_step_minutes'],
            'buffer_before_minutes' => (int) ($validated['buffer_before_minutes'] ?? 0),
            'buffer_after_minutes' => (int) ($validated['buffer_after_minutes'] ?? 0),
            'booking_horizon_days' => (int) $validated['booking_horizon_days'],
            'lead_time_minutes' => (int) $validated['lead_time_minutes'],
            'confirmation_mode' => (string) $validated['confirmation_mode'],
            'resource_selection_mode' => (string) $validated['resource_selection_mode'],
            'price_amount' => $validated['price_amount'] ?? null,
            'price_currency' => strtoupper((string) $validated['price_currency']),
            'price_label' => blank($validated['price_label'] ?? null) ? null : (string) $validated['price_label'],
            'cta_label' => blank($validated['cta_label'] ?? null) ? null : (string) $validated['cta_label'],
            'is_active' => $request->boolean('is_active'),
        ], $translations, $resourceIds];
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncTranslations(BookingService $service, array $translations): void
    {
        $existing = $service->translations()->get()->keyBy('locale');
        foreach ($translations as $payload) {
            $translation = $existing->get($payload['locale']) ?? new BookingServiceTranslation(['service_id' => $service->id, 'locale' => $payload['locale']]);
            $translation->fill($payload);
            $translation->service_id = $service->id;
            $translation->save();
        }

        $keptLocales = array_map(static fn (array $payload): string => (string) $payload['locale'], $translations);
        $service->translations()->whereNotIn('locale', $keptLocales)->delete();
    }

    private function uniqueSlug(string $locale, string $source, ?BookingService $service = null): string
    {
        $slug = Str::slug($source, '-', $locale === 'ru' ? 'ru' : 'en');
        $slug = $slug !== '' ? $slug : 'service';
        $base = $slug;
        $suffix = 2;

        while (BookingServiceTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($service, fn ($query) => $query->where('service_id', '!=', $service->id))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(BookingService $service, bool $isEdit): array
    {
        $translations = [];
        $loadedTranslations = $service->relationLoaded('translations') ? $service->translations : $service->translations()->get();
        foreach ($this->supportedLocales() as $locale) {
            $translations[$locale] = $loadedTranslations->firstWhere('locale', $locale);
        }

        return [
            'service' => $service,
            'translations' => $translations,
            'locations' => BookingLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'resources' => BookingResource::query()->where('is_active', true)->orderBy('name')->get(),
            'assets' => Asset::query()->latest('id')->limit(100)->get(),
            'isEdit' => $isEdit,
            'selectedResourceIds' => $service->relationLoaded('resources') ? $service->resources->pluck('id')->all() : $service->resources()->pluck('booking_resources.id')->all(),
            'supportedLocales' => $this->supportedLocales(),
        ];
    }
}
