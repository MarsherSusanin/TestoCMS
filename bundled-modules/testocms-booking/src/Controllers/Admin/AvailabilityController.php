<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingAvailabilityException;
use TestoCms\Booking\Models\BookingAvailabilityRule;
use TestoCms\Booking\Models\BookingLocation;
use TestoCms\Booking\Models\BookingResource;
use TestoCms\Booking\Models\BookingService;
use TestoCms\Booking\Models\BookingSlotOccurrence;
use TestoCms\Booking\Services\BookingAvailabilityService;
use TestoCms\Booking\Services\BookingSlotProjectionService;

class AvailabilityController extends Controller
{
    use EnsuresBookingPermissions;

    public function __construct(
        private readonly BookingAvailabilityService $availability,
        private readonly BookingSlotProjectionService $projection,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureBookingRead($request);

        $services = BookingService::query()->with('translations')->orderByDesc('is_active')->orderByDesc('updated_at')->get();
        $selectedService = BookingService::query()->with(['location', 'resources', 'translations', 'rules.location', 'rules.resource', 'exceptions.location', 'exceptions.resource'])
            ->find($request->integer('service_id') ?: $services->first()?->id);
        $previewLocationId = $request->integer('preview_location_id') ?: null;
        $previewResourceId = $request->integer('preview_resource_id') ?: null;

        $serviceLocations = $selectedService
            ? BookingLocation::query()
                ->whereIn('id', collect([$selectedService->location_id])
                    ->merge($selectedService->rules->pluck('location_id'))
                    ->merge($selectedService->exceptions->pluck('location_id'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all())
                ->orderBy('name')
                ->get()
            : collect();
        $serviceResources = $selectedService
            ? BookingResource::query()
                ->whereIn('id', $selectedService->resources->pluck('id')->all())
                ->orderBy('name')
                ->get()
            : collect();

        $previewSlots = $selectedService
            ? $this->buildPreviewSlots($selectedService, $previewLocationId, $previewResourceId)
            : collect();

        return view('booking-module::admin.availability.index', [
            'services' => $services,
            'selectedService' => $selectedService,
            'locations' => BookingLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'resources' => BookingResource::query()->where('is_active', true)->orderBy('name')->get(),
            'previewLocations' => $serviceLocations,
            'previewResources' => $serviceResources,
            'previewFilters' => [
                'location_id' => $previewLocationId,
                'resource_id' => $previewResourceId,
            ],
            'slots' => $selectedService
                ? BookingSlotOccurrence::query()->where('service_id', $selectedService->id)->orderBy('starts_at')->limit(120)->get()
                : collect(),
            'previewSlots' => $previewSlots,
            'availabilityStats' => $this->buildAvailabilityStats($selectedService, $previewSlots),
            'rebuildReportStats' => $this->buildRebuildReportStats($request->session()->get('availability_rebuild_report')),
            'weekdayLabels' => $this->weekdayLabels(),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $payload = $request->validate([
            'service_id' => ['required', 'integer', 'exists:booking_services,id'],
            'location_id' => ['nullable', 'integer', 'exists:booking_locations,id'],
            'resource_id' => ['nullable', 'integer', 'exists:booking_resources,id'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_step_minutes' => ['nullable', 'integer', 'min:5', 'max:720'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $rule = $this->availability->storeRule($payload + ['is_active' => $request->boolean('is_active', true)]);
        $this->audit->log('booking.availability.rule.create.web', $rule, [], $request);

        return redirect()->route('booking.admin.availability.index', ['service_id' => $payload['service_id']])->with('status', 'Правило доступности добавлено.');
    }

    public function updateRule(Request $request, BookingAvailabilityRule $rule): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $payload = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:booking_locations,id'],
            'resource_id' => ['nullable', 'integer', 'exists:booking_resources,id'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_step_minutes' => ['nullable', 'integer', 'min:5', 'max:720'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $rule = $this->availability->updateRule($rule, $payload + ['is_active' => $request->boolean('is_active')]);
        $this->audit->log('booking.availability.rule.update.web', $rule, [], $request);

        return redirect()->route('booking.admin.availability.index', ['service_id' => $rule->service_id])->with('status', 'Правило обновлено.');
    }

    public function destroyRule(Request $request, BookingAvailabilityRule $rule): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $serviceId = $rule->service_id;
        $ruleId = $rule->id;
        $this->availability->destroyRule($rule);
        $this->audit->log('booking.availability.rule.delete.web', null, ['rule_id' => $ruleId], $request);

        return redirect()->route('booking.admin.availability.index', ['service_id' => $serviceId])->with('status', 'Правило удалено.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $payload = $request->validate([
            'service_id' => ['required', 'integer', 'exists:booking_services,id'],
            'location_id' => ['nullable', 'integer', 'exists:booking_locations,id'],
            'resource_id' => ['nullable', 'integer', 'exists:booking_resources,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $exception = $this->availability->storeException($payload + ['is_closed' => $request->boolean('is_closed', true)]);
        $this->audit->log('booking.availability.exception.create.web', $exception, [], $request);

        return redirect()->route('booking.admin.availability.index', ['service_id' => $payload['service_id']])->with('status', 'Исключение доступности добавлено.');
    }

    public function destroyException(Request $request, BookingAvailabilityException $exception): RedirectResponse
    {
        $this->ensureBookingManage($request);
        $serviceId = $exception->service_id;
        $exceptionId = $exception->id;
        $this->availability->destroyException($exception);
        $this->audit->log('booking.availability.exception.delete.web', null, ['exception_id' => $exceptionId], $request);

        return redirect()->route('booking.admin.availability.index', ['service_id' => $serviceId])->with('status', 'Исключение удалено.');
    }

    public function rebuild(Request $request): RedirectResponse
    {
        $this->ensureBookingWrite($request);
        $serviceId = $request->integer('service_id');
        $previewLocationId = $request->integer('preview_location_id') ?: null;
        $previewResourceId = $request->integer('preview_resource_id') ?: null;
        $report = [
            'scope' => $serviceId ? 'service' : 'all',
            'service_id' => $serviceId,
            'created' => 0,
            'updated' => 0,
            'pruned' => 0,
            'projected' => 0,
            'services' => 0,
        ];
        if ($serviceId) {
            $service = BookingService::query()->findOrFail($serviceId);
            $report = array_merge($report, $this->projection->rebuildServiceReport($service));
        } else {
            $report = array_merge($report, $this->projection->rebuildAllReport(), ['scope' => 'all']);
        }

        $this->audit->log('booking.availability.rebuild.web', null, $report, $request);

        return redirect()
            ->route('booking.admin.availability.index', array_filter([
                'service_id' => $serviceId,
                'preview_location_id' => $previewLocationId,
                'preview_resource_id' => $previewResourceId,
            ]))
            ->with('status', $this->formatRebuildStatus($report))
            ->with('availability_rebuild_report', $report);
    }

    /**
     * @param  Collection<int, BookingSlotOccurrence>  $previewSlots
     * @return array<int, array<string, mixed>>
     */
    private function buildAvailabilityStats(?BookingService $selectedService, Collection $previewSlots): array
    {
        if (! $selectedService) {
            return [];
        }

        $translation = $selectedService->translations->firstWhere('locale', app()->getLocale()) ?? $selectedService->translations->first();
        $nextSlot = $previewSlots->first();
        $reserved = $previewSlots->sum('reserved_count');
        $confirmed = $previewSlots->sum('confirmed_count');

        return [
            [
                'label' => 'Услуга',
                'value' => $translation?->title ?? ('Service #'.$selectedService->id),
                'hint' => $selectedService->confirmation_mode === 'instant' ? 'Мгновенное подтверждение' : 'Ручное подтверждение',
            ],
            [
                'label' => 'Ресурсы',
                'value' => $selectedService->resources->count(),
                'hint' => $selectedService->usesResourceChoiceMode() ? 'Клиент выбирает ресурс' : 'Ресурс назначается автоматически',
            ],
            [
                'label' => 'Правила / исключения',
                'value' => $selectedService->rules->count().' / '.$selectedService->exceptions->count(),
                'hint' => 'Активный operational набор для проекции слотов.',
            ],
            [
                'label' => 'Ближайший слот',
                'value' => $nextSlot?->starts_at?->timezone($selectedService->location?->timezone ?: config('app.timezone'))->format('d.m H:i') ?? '—',
                'hint' => 'Видимая ближайшая projected точка.',
            ],
            [
                'label' => 'Reserved / confirmed',
                'value' => $reserved.' / '.$confirmed,
                'hint' => 'По текущему preview-окну слотов.',
            ],
        ];
    }

    /**
     * @return Collection<int, BookingSlotOccurrence>
     */
    private function buildPreviewSlots(BookingService $selectedService, ?int $previewLocationId, ?int $previewResourceId): Collection
    {
        $query = BookingSlotOccurrence::query()
            ->with(['location', 'resource'])
            ->where('service_id', $selectedService->id)
            ->where('starts_at', '>=', now());

        if ($previewLocationId) {
            $query->where('location_id', $previewLocationId);
        }

        if ($previewResourceId) {
            $query->where('resource_id', $previewResourceId);
        }

        return $query->orderBy('starts_at')->limit(24)->get();
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<int, array<string, mixed>>
     */
    private function buildRebuildReportStats(?array $report): array
    {
        if (! is_array($report) || $report === []) {
            return [];
        }

        $stats = [
            [
                'label' => 'Создано',
                'value' => (int) ($report['created'] ?? 0),
                'hint' => 'Новые slot occurrences в rolling window.',
            ],
            [
                'label' => 'Обновлено',
                'value' => (int) ($report['updated'] ?? 0),
                'hint' => 'Существующие слоты, которые были перепроецированы.',
            ],
            [
                'label' => 'Удалено',
                'value' => (int) ($report['pruned'] ?? 0),
                'hint' => 'Пустые слоты, исчезнувшие из актуального ruleset.',
            ],
            [
                'label' => 'Всего обработано',
                'value' => (int) ($report['projected'] ?? 0),
                'hint' => ($report['scope'] ?? 'service') === 'all'
                    ? 'Суммарно по всем активным услугам.'
                    : 'Суммарно по текущей услуге.',
            ],
        ];

        if (($report['scope'] ?? 'service') === 'all') {
            array_unshift($stats, [
                'label' => 'Услуг',
                'value' => (int) ($report['services'] ?? 0),
                'hint' => 'Активные услуги, попавшие в пересборку.',
            ]);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function formatRebuildStatus(array $report): string
    {
        $scopeLabel = ($report['scope'] ?? 'service') === 'all' ? 'Все услуги' : 'Текущая услуга';

        return sprintf(
            '%s: создано %d, обновлено %d, удалено %d, всего обработано %d.',
            $scopeLabel,
            (int) ($report['created'] ?? 0),
            (int) ($report['updated'] ?? 0),
            (int) ($report['pruned'] ?? 0),
            (int) ($report['projected'] ?? 0),
        );
    }

    /**
     * @return array<int, string>
     */
    private function weekdayLabels(): array
    {
        return [
            'Воскресенье',
            'Понедельник',
            'Вторник',
            'Среда',
            'Четверг',
            'Пятница',
            'Суббота',
        ];
    }
}
