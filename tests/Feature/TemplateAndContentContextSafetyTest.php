<?php

namespace Tests\Feature;

use App\Models\ContentTemplate;
use App\Models\User;
use App\Modules\Content\Services\PageContentService;
use App\Modules\Content\Services\PostContentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemplateAndContentContextSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'cms.default_locale' => 'ru', 'cms.supported_locales' => ['ru', 'en']]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actor = User::create(['name' => 'Template admin', 'login' => 'template_admin', 'email' => 'template@audit.local', 'password' => 'password', 'status' => 'active']);
        $this->actor->assignRole('superadmin');
    }

    public function test_template_description_omission_null_and_empty_are_valid_for_create_update(): void
    {
        $this->actingAs($this->actor)->post('/admin/templates', ['entity_type' => 'page', 'name' => 'Optional description', 'payload_json' => '{}'])->assertRedirect();
        $template = ContentTemplate::firstOrFail();
        $this->assertNull($template->description);
        foreach ([[], ['description' => null], ['description' => ''], ['description' => '   ']] as $input) {
            $template->update(['description' => 'Old description']);
            $this->put('/admin/templates/'.$template->id, ['name' => 'Updated'] + $input)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNull($template->fresh()->description);
        }
        $this->put('/admin/templates/'.$template->id, ['name' => 'Updated', 'description' => '  Kept text  '])->assertRedirect();
        $this->assertSame('Kept text', $template->fresh()->description);
        $this->put('/admin/templates/'.$template->id, ['name' => 'Updated', 'description' => '0'])->assertRedirect();
        $this->assertSame('0', $template->fresh()->description);
    }

    public static function contentServices(): array
    {
        return [[PageContentService::class], [PostContentService::class]];
    }

    #[DataProvider('contentServices')]
    public function test_create_update_without_context_support_non_default_api_locale_and_head(string $serviceClass): void
    {
        $service = app($serviceClass);
        $entity = $service->createFromValidated(['translations' => [['locale' => 'en', 'title' => 'Direct creation', 'slug' => 'contextless', 'content_html' => '<p>Body</p>', 'content_blocks' => [], 'custom_head_html' => '<meta name="verification" content="contextless">']]], $this->actor);
        $this->assertSame('en', $entity->translations->first()->locale);
        $this->assertStringContainsString('contextless', $entity->translations->first()->custom_head_html);
        $entity = $service->updateFromValidated($entity, ['translations' => [['locale' => 'en', 'title' => 'Direct update', 'slug' => 'contextless', 'content_html' => '<p>Updated</p>', 'content_blocks' => []]]], $this->actor);
        $this->assertSame('Direct update', $entity->translations->first()->title);
    }
}
