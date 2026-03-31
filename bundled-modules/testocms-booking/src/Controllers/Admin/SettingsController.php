<?php

namespace TestoCms\Booking\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TestoCms\Booking\Controllers\Admin\Concerns\EnsuresBookingPermissions;
use TestoCms\Booking\Models\BookingWebhookDelivery;
use TestoCms\Booking\Models\BookingWebhookEndpoint;
use TestoCms\Booking\Services\BookingWebhookEndpointService;
use TestoCms\Booking\Services\BookingSettingsService;
use TestoCms\Booking\Services\BookingWebhookService;

class SettingsController extends Controller
{
    use EnsuresBookingPermissions;

    public function __construct(
        private readonly BookingSettingsService $settings,
        private readonly BookingWebhookService $webhooks,
        private readonly BookingWebhookEndpointService $endpointManager,
        private readonly AuditLogger $audit,
    ) {
    }

    public function edit(Request $request): View
    {
        $this->ensureBookingSettings($request);

        $webhookEndpoints = BookingWebhookEndpoint::query()
            ->withCount(['deliveries as deliveries_total', 'deliveries as deliveries_failed' => fn ($query) => $query->where('status', 'failed')])
            ->latest('id')
            ->get();
        $recentDeliveries = BookingWebhookDelivery::query()
            ->with('endpoint')
            ->latest('attempted_at')
            ->latest('id')
            ->limit(12)
            ->get();

        return view('booking-module::admin.settings.edit', [
            'settings' => $this->settings->resolved(),
            'webhookEndpoints' => $webhookEndpoints,
            'recentDeliveries' => $recentDeliveries,
            'supportedEvents' => $this->webhooks->supportedEvents(),
            'webhookStats' => $this->buildWebhookStats($webhookEndpoints, $recentDeliveries),
            'revealedSecret' => session('booking_webhook_secret'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureBookingSettings($request);

        $validated = $request->validate([
            'hold_ttl_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'projection_horizon_days' => ['required', 'integer', 'min:7', 'max:365'],
            'public_prefix' => ['required', 'string', 'max:60'],
            'webhooks_enabled' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
        ]);

        $payload = $this->settings->save([
            'hold_ttl_minutes' => $validated['hold_ttl_minutes'],
            'projection_horizon_days' => $validated['projection_horizon_days'],
            'public_prefix' => $validated['public_prefix'],
            'webhooks_enabled' => $request->boolean('webhooks_enabled'),
            'email_notifications' => $request->boolean('email_notifications'),
        ]);
        $this->audit->log('booking.settings.update.web', null, array_merge($payload, [
            'webhook_endpoint_count' => BookingWebhookEndpoint::query()->count(),
        ]), $request);

        return redirect()->route('booking.admin.settings.edit')->with('status', 'Настройки бронирования сохранены.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $this->ensureBookingSettings($request);
        $validated = $this->validateWebhookPayload($request, requireSecret: false);
        $endpoint = $this->endpointManager->create([
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? null,
            'subscribed_events' => $validated['events'] ?? [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log('booking.webhook_endpoint.create.web', $endpoint, [
            'events' => $endpoint->subscribed_events,
        ], $request);

        return redirect()
            ->route('booking.admin.settings.edit')
            ->with('status', 'Webhook endpoint создан.')
            ->with('booking_webhook_secret', $endpoint->secret);
    }

    public function updateWebhook(Request $request, BookingWebhookEndpoint $endpoint): RedirectResponse
    {
        $this->ensureBookingSettings($request);
        $validated = $this->validateWebhookPayload($request, requireSecret: false);
        $endpoint = $this->endpointManager->update($endpoint, [
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? null,
            'subscribed_events' => $validated['events'] ?? [],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log('booking.webhook_endpoint.update.web', $endpoint, [
            'events' => $endpoint->subscribed_events,
            'is_active' => $endpoint->is_active,
        ], $request);

        return redirect()->route('booking.admin.settings.edit')->with('status', 'Webhook endpoint обновлён.');
    }

    public function rotateWebhookSecret(Request $request, BookingWebhookEndpoint $endpoint): RedirectResponse
    {
        $this->ensureBookingSettings($request);
        $endpoint = $this->endpointManager->rotateSecret($endpoint);

        $this->audit->log('booking.webhook_endpoint.rotate_secret.web', $endpoint, [], $request);

        return redirect()
            ->route('booking.admin.settings.edit')
            ->with('status', 'Secret для webhook endpoint обновлён.')
            ->with('booking_webhook_secret', $endpoint->secret);
    }

    public function destroyWebhook(Request $request, BookingWebhookEndpoint $endpoint): RedirectResponse
    {
        $this->ensureBookingSettings($request);
        $context = ['endpoint_id' => $endpoint->id, 'url' => $endpoint->url];
        $this->endpointManager->delete($endpoint);
        $this->audit->log('booking.webhook_endpoint.delete.web', null, $context, $request);

        return redirect()->route('booking.admin.settings.edit')->with('status', 'Webhook endpoint удалён.');
    }

    public function retryWebhookDelivery(Request $request, BookingWebhookDelivery $delivery): RedirectResponse
    {
        $this->ensureBookingSettings($request);

        $retried = $this->webhooks->retry($delivery);
        $this->audit->log('booking.webhook_delivery.retry.web', $retried, [
            'original_delivery_id' => $delivery->id,
            'event_name' => $delivery->event_name,
        ], $request);

        return redirect()->route('booking.admin.settings.edit')->with('status', 'Webhook delivery повторно отправлена.');
    }

    /**
     * @return array{url: string, secret?: string|null, events?: array<int, string>}
     */
    private function validateWebhookPayload(Request $request, bool $requireSecret = false): array
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'secret' => [$requireSecret ? 'required' : 'nullable', 'string', 'max:255'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $allowed = $this->webhooks->supportedEvents();
        $events = array_values(array_unique(array_values(array_filter(
            array_map('strval', is_array($validated['events'] ?? null) ? $validated['events'] : []),
            static fn (string $event): bool => in_array($event, $allowed, true)
        ))));

        return [
            'url' => trim((string) $validated['url']),
            'secret' => blank($validated['secret'] ?? null) ? null : trim((string) $validated['secret']),
            'events' => $events,
        ];
    }

    /**
     * @param  Collection<int, BookingWebhookEndpoint>  $endpoints
     * @param  Collection<int, BookingWebhookDelivery>  $deliveries
     * @return array<int, array<string, mixed>>
     */
    private function buildWebhookStats(Collection $endpoints, Collection $deliveries): array
    {
        return [
            [
                'label' => 'Endpoints',
                'value' => $endpoints->count(),
                'hint' => 'Всего настроенных webhook endpoints.',
            ],
            [
                'label' => 'Активные',
                'value' => $endpoints->where('is_active', true)->count(),
                'hint' => 'Только активные endpoints получают события.',
            ],
            [
                'label' => 'Доставлено',
                'value' => $deliveries->where('status', 'delivered')->count(),
                'hint' => 'Статус по последним доставкам.',
            ],
            [
                'label' => 'Ошибки',
                'value' => $deliveries->where('status', 'failed')->count(),
                'hint' => 'Проверьте response body и коды ответа.',
                'value_class' => $deliveries->where('status', 'failed')->count() > 0 ? 'danger' : '',
            ],
        ];
    }
}
