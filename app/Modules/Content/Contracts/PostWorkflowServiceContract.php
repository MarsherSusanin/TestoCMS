<?php

namespace App\Modules\Content\Contracts;

use App\Models\Post;
use App\Models\PreviewToken;
use App\Models\PublishSchedule;
use Illuminate\Http\Request;

interface PostWorkflowServiceContract
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function destroy(Post $post, Request $request, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function publish(Post $post, Request $request, array $context = []): Post;

    /**
     * @param  array<string, mixed>  $context
     */
    public function unpublish(Post $post, Request $request, array $context = []): Post;

    /**
     * @param  array<string, mixed>  $context
     */
    public function schedule(Post $post, string $action, string $dueAt, Request $request, array $context = []): PublishSchedule;

    /**
     * @param  array<string, mixed>  $context
     * @return array{token: PreviewToken, url: string}
     */
    public function createPreviewToken(Post $post, string $locale, Request $request, array $context = []): array;
}
