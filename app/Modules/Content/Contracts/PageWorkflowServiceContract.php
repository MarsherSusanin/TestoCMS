<?php

namespace App\Modules\Content\Contracts;

use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use Illuminate\Http\Request;

interface PageWorkflowServiceContract
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function destroy(Page $page, Request $request, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function publish(Page $page, Request $request, array $context = []): Page;

    /**
     * @param  array<string, mixed>  $context
     */
    public function unpublish(Page $page, Request $request, array $context = []): Page;

    /**
     * @param  array<string, mixed>  $context
     */
    public function schedule(Page $page, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule;

    /**
     * @param  array<string, mixed>  $context
     * @return array{token: PreviewToken, url: string}
     */
    public function createPreviewToken(Page $page, string $locale, Request $request, array $context = []): array;
}
