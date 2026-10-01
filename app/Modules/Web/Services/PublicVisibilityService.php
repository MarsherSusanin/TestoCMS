<?php

namespace App\Modules\Web\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Modules\Content\Services\SlugResolverService;
use App\Modules\Core\Services\SiteChromeSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PublicVisibilityService
{
    public function __construct(private readonly SlugResolverService $slugs, private readonly SiteChromeSettingsService $chrome) {}

    public function isLive(Model $entity): bool
    {
        if ($entity instanceof Category) {
            return Category::query()->useWritePdo()->whereKey($entity->id)->where('is_active', true)->exists();
        }
        if ($entity instanceof Page || $entity instanceof Post) {
            return $entity->newQuery()->useWritePdo()->published()->whereKey($entity->id)->exists();
        }

        return false;
    }

    /** Runs before an HTML cache HIT, using fresh translations and live database predicates. */
    public function assertRequestIsLive(Request $request): void
    {
        if (! $request->routeIs('site.show')) {
            return;
        }
        $locale = (string) $request->route('locale');
        $slug = trim((string) ($request->route('slug') ?? 'home'), '/');
        $blog = trim((string) config('cms.post_url_prefix', 'blog'), '/');
        if ($slug === $blog || preg_match('#^'.preg_quote($blog, '#').'/page/\d+$#', $slug)) {
            return;
        }
        if ($slug === $this->chrome->searchPathSlug()) {
            abort_unless(($this->chrome->resolvedChrome()['search']['enabled'] ?? false) === true, 404);

            return;
        }
        $resolved = $this->slugs->resolve($locale, $slug);
        abort_unless($resolved !== null && $this->isLive($resolved['model']), 404);
    }
}
