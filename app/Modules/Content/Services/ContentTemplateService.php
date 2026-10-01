<?php

namespace App\Modules\Content\Services;

use App\Models\ContentTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContentTemplateService
{
    public const ENTITY_PAGE = 'page';

    public const ENTITY_POST = 'post';

    public function __construct(
        private readonly PageTemplatePayloadBuilder $pageTemplatePayloadBuilder,
        private readonly PostTemplatePayloadBuilder $postTemplatePayloadBuilder,
    ) {}

    /**
     * @return array<int, string>
     */
    public function entityTypes(): array
    {
        return [self::ENTITY_PAGE, self::ENTITY_POST];
    }

    public function isSupportedEntityType(string $entityType): bool
    {
        return in_array($entityType, $this->entityTypes(), true);
    }

    public function create(User $actor, string $entityType, string $name, ?string $description, array $payload): ContentTemplate
    {
        return DB::transaction(function () use ($actor, $entityType, $name, $description, $payload): ContentTemplate {
            app(ContentMutationGuard::class)->lockMedia();
            $normalized = $this->normalizePayload($entityType, $payload);
            app(AssetUsageService::class)->assertReferencesAvailable($normalized);

            return ContentTemplate::query()->create([
                'entity_type' => $entityType,
                'name' => $name,
                'description' => $description,
                'payload' => $normalized,
                'is_active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function updateMetadata(User $actor, ContentTemplate $template, string $name, ?string $description): ContentTemplate
    {
        return DB::transaction(function () use ($actor, $template, $name, $description): ContentTemplate {
            app(ContentMutationGuard::class)->lockMedia();
            $template = ContentTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $template->name = $name;
            $template->description = $description;
            $template->updated_by = $actor->id;
            $template->save();

            return $template->fresh(['creator', 'updater']) ?? $template;
        });
    }

    public function duplicate(User $actor, ContentTemplate $template): ContentTemplate
    {
        return DB::transaction(function () use ($actor, $template): ContentTemplate {
            app(ContentMutationGuard::class)->lockMedia();
            $template = ContentTemplate::query()->lockForUpdate()->findOrFail($template->id);
            app(AssetUsageService::class)->assertReferencesAvailable($template->payload);
            $copy = $template->replicate(['created_at', 'updated_at']);
            $copy->name = $template->name.' (copy)';
            $copy->is_active = true;
            $copy->created_by = $actor->id;
            $copy->updated_by = $actor->id;
            $copy->save();

            return $copy;
        });
    }

    public function normalizePayload(string $entityType, array $payload): array
    {
        return $entityType === self::ENTITY_PAGE
            ? $this->pageTemplatePayloadBuilder->normalizePayload($payload)
            : $this->postTemplatePayloadBuilder->normalizePayload($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrefillPayload(ContentTemplate $template, ?SlugUniquenessService $slugUniqueness = null): array
    {
        return $template->entity_type === self::ENTITY_PAGE
            ? $this->pageTemplatePayloadBuilder->buildPrefill($template)
            : $this->postTemplatePayloadBuilder->buildPrefill($template);
    }
}
