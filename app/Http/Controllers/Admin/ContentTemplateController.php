<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentTemplate;
use App\Modules\Content\Services\ContentTemplateService;
use App\Modules\Ops\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentTemplateController extends Controller
{
    public function __construct(
        private readonly ContentTemplateService $templates,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $entityType = strtolower((string) $request->query('entity_type', ContentTemplateService::ENTITY_PAGE));
        if (! $this->templates->isSupportedEntityType($entityType)) {
            $entityType = ContentTemplateService::ENTITY_PAGE;
        }

        if (! $this->canReadEntityType($user, $entityType)) {
            if ($this->canReadEntityType($user, ContentTemplateService::ENTITY_PAGE)) {
                $entityType = ContentTemplateService::ENTITY_PAGE;
            } elseif ($this->canReadEntityType($user, ContentTemplateService::ENTITY_POST)) {
                $entityType = ContentTemplateService::ENTITY_POST;
            } else {
                abort(403);
            }
        }

        $perPage = $this->resolvePerPage($request, 'admin.templates.per_page');

        $templates = ContentTemplate::query()
            ->with(['creator', 'updater'])
            ->where('entity_type', $entityType)
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.templates.index', [
            'templates' => $templates,
            'entityType' => $entityType,
            'perPage' => $perPage,
            'perPageOptions' => [10, 20, 50, 100],
            'canWriteCurrentType' => $this->canWriteEntityType($user, $entityType),
            'canReadPage' => $this->canReadEntityType($user, ContentTemplateService::ENTITY_PAGE),
            'canReadPost' => $this->canReadEntityType($user, ContentTemplateService::ENTITY_POST),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'entity_type' => 'required|string|in:page,post',
            'name' => 'required|string|max:190',
            'description' => 'nullable|string|max:2000',
            'payload_json' => 'required|string',
        ]);

        $entityType = strtolower((string) $validated['entity_type']);
        abort_unless($this->canWriteEntityType($user, $entityType), 403);

        try {
            $payload = json_decode((string) $validated['payload_json'], true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return back()->withErrors([
                'payload_json' => 'Невалидный JSON payload шаблона: '.$e->getMessage(),
            ])->withInput();
        }

        if (! is_array($payload)) {
            return back()->withErrors([
                'payload_json' => 'Payload шаблона должен быть JSON-объектом.',
            ])->withInput();
        }

        $template = $this->templates->create(
            actor: $user,
            entityType: $entityType,
            name: trim((string) $validated['name']),
            description: $validated['description'] !== null ? trim((string) $validated['description']) : null,
            payload: $payload,
        );

        $this->auditLogger->log('content_templates.create.web', $template, [
            'entity_type' => $template->entity_type,
            'name' => $template->name,
        ], $request);

        return redirect()
            ->route('admin.templates.index', ['entity_type' => $entityType])
            ->with('status', 'Шаблон сохранён.');
    }

    public function update(Request $request, ContentTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->canWriteEntityType($user, $template->entity_type), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:190',
            'description' => 'nullable|string|max:2000',
        ]);

        $updated = $this->templates->updateMetadata(
            actor: $user,
            template: $template,
            name: trim((string) $validated['name']),
            description: $validated['description'] !== null ? trim((string) $validated['description']) : null,
        );

        $this->auditLogger->log('content_templates.update.web', $updated, [
            'entity_type' => $updated->entity_type,
            'name' => $updated->name,
        ], $request);

        return back()->with('status', 'Шаблон обновлён.');
    }

    public function duplicate(Request $request, ContentTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->canWriteEntityType($user, $template->entity_type), 403);

        $copy = $this->templates->duplicate($user, $template);

        $this->auditLogger->log('content_templates.duplicate.web', $copy, [
            'source_id' => $template->id,
            'entity_type' => $copy->entity_type,
            'name' => $copy->name,
        ], $request);

        return back()->with('status', 'Шаблон продублирован.');
    }

    public function destroy(Request $request, ContentTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->canWriteEntityType($user, $template->entity_type), 403);

        $entityType = $template->entity_type;
        $templateName = $template->name;
        $templateId = $template->id;
        $template->delete();

        $this->auditLogger->log('content_templates.delete.web', null, [
            'entity_type' => $entityType,
            'template_id' => $templateId,
            'name' => $templateName,
        ], $request);

        return back()->with('status', 'Шаблон удалён.');
    }

    private function canReadEntityType(object $user, string $entityType): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return true;
        }

        return match ($entityType) {
            ContentTemplateService::ENTITY_PAGE => $user->can('pages:read') || $user->can('pages:write'),
            ContentTemplateService::ENTITY_POST => $user->can('posts:read') || $user->can('posts:write'),
            default => false,
        };
    }

    private function canWriteEntityType(object $user, string $entityType): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return true;
        }

        return match ($entityType) {
            ContentTemplateService::ENTITY_PAGE => $user->can('pages:write'),
            ContentTemplateService::ENTITY_POST => $user->can('posts:write'),
            default => false,
        };
    }

    private function resolvePerPage(Request $request, string $sessionKey): int
    {
        $allowed = [10, 20, 50, 100];
        $requested = (int) $request->query('per_page', 0);
        if (in_array($requested, $allowed, true)) {
            $request->session()->put($sessionKey, $requested);

            return $requested;
        }

        $saved = (int) $request->session()->get($sessionKey, 20);

        return in_array($saved, $allowed, true) ? $saved : 20;
    }
}
