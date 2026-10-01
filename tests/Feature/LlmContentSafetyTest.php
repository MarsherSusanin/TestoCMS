<?php

namespace Tests\Feature;

use App\Models\LlmGeneration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LlmContentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.seed_demo_content' => false, 'cms.supported_locales' => ['ru', 'en'], 'cms.default_locale' => 'ru', 'llm.providers.openai.api_key' => 'test-fake-key', 'llm.providers.anthropic.api_key' => 'test-fake-key']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actor = User::create(['name' => 'LLM admin', 'login' => 'llm_admin', 'email' => 'llm@audit.local', 'password' => 'password', 'status' => 'active']);
        $this->actor->assignRole('superadmin');
        $this->token(['llm:generate', 'pages:write', 'posts:write']);
        Http::preventStrayRequests();
    }

    private function token(array $scopes): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->actor->createToken('llm', $scopes)->plainTextToken);
    }

    private function providerOutput(): array
    {
        return ['status' => 'completed', 'model' => 'fixture-model', 'output' => [
            ['type' => 'reasoning', 'summary' => [['text' => 'Must not persist']]],
            ['type' => 'message', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => 'First <script>alert(1)</script>'], ['type' => 'output_text', 'text' => 'Second paragraph']]],
            ['type' => 'function_call', 'arguments' => 'Must not persist'],
        ]];
    }

    public static function types(): array
    {
        return [['page'], ['post']];
    }

    #[DataProvider('types')]
    public function test_real_responses_envelope_becomes_escaped_draft_and_full_revision(string $type): void
    {
        $output = $this->providerOutput();
        Http::fake(['*' => Http::response($output)]);
        $response = $this->postJson('/api/admin/v1/llm/generate-'.$type, ['prompt' => 'Generate a complete draft', 'locale' => ' EN '])->assertCreated();
        $response->assertJsonPath('data.draft.status', 'draft')->assertJsonPath('data.draft.translations.0.locale', 'en');
        $this->assertSame("First <script>alert(1)</script>\nSecond paragraph", $response->json('data.generation.text'));
        $html = $response->json('data.draft.translations.0.'.($type === 'page' ? 'rendered_html' : 'content_html'));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('Must not persist', $html);
        // MySQL's native JSON storage may reorder object keys; array order and values remain part of the contract.
        $this->assertJsonStringEqualsJsonString(json_encode($output, JSON_THROW_ON_ERROR), json_encode(LlmGeneration::firstOrFail()->output_payload, JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('content_revisions', ['entity_type' => $type, 'entity_id' => $response->json('data.draft.id')]);
    }

    public function test_anthropic_joins_all_text_blocks_and_ignores_thinking(): void
    {
        Http::fake(['*' => Http::response(['stop_reason' => 'end_turn', 'content' => [['type' => 'thinking', 'thinking' => 'Hidden'], ['type' => 'text', 'text' => 'First'], ['type' => 'text', 'text' => 'Second']]])]);
        $this->postJson('/api/admin/v1/llm/generate-seo', ['prompt' => 'Create complete SEO metadata', 'provider' => 'anthropic'])->assertOk()->assertJsonPath('data.generation.text', "First\nSecond");
    }

    public function test_invalid_locale_and_missing_write_scope_do_not_cost_provider_calls(): void
    {
        Http::fake();
        $this->postJson('/api/admin/v1/llm/generate-page', ['prompt' => 'Create an interesting draft', 'locale' => 'zz'])->assertUnprocessable();
        $this->token(['llm:generate']);
        $this->postJson('/api/admin/v1/llm/generate-post', ['prompt' => 'Create an interesting draft'])->assertForbidden();
        Http::assertNothingSent();
        $this->assertDatabaseCount('llm_generations', 0);
    }

    public function test_generation_only_does_not_require_content_write_and_creates_no_entity(): void
    {
        $this->token(['llm:generate']);
        Http::fake(['*' => Http::response($this->providerOutput())]);
        $this->postJson('/api/admin/v1/llm/generate-page', ['prompt' => 'Create an interesting draft', 'save_as_draft' => false])->assertCreated()->assertJsonPath('data.draft', null);
        $this->assertDatabaseCount('pages', 0);
    }

    public static function invalidOutputs(): array
    {
        return [
            [['status' => 'incomplete', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Partial']]]]], 'openai', 'llm_incomplete'],
            [['status' => 'completed', 'output_text' => 'SDK-only field is not raw Responses'], 'openai', 'llm_empty_output'],
            [['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]]], 'openai', 'llm_refused'],
            [['status' => 'completed', 'output' => []], 'openai', 'llm_empty_output'],
            [['arbitrary' => 'Do not save JSON'], 'openai', 'llm_empty_output'],
            [['stop_reason' => 'max_tokens', 'content' => [['type' => 'text', 'text' => 'Truncated']]], 'anthropic', 'llm_incomplete'],
            [['stop_reason' => 'refusal', 'content' => []], 'anthropic', 'llm_refused'],
        ];
    }

    #[DataProvider('invalidOutputs')]
    public function test_failed_outputs_create_no_draft(array $output, string $provider, string $code): void
    {
        Http::fake(['*' => Http::response($output)]);
        $this->postJson('/api/admin/v1/llm/generate-page', ['prompt' => 'Generate complete content please', 'provider' => $provider])->assertUnprocessable()->assertJsonPath('error_code', $code);
        $this->assertDatabaseCount('pages', 0);
        $this->assertSame('failed', LlmGeneration::firstOrFail()->status);
    }

    public function test_transport_failure_is_sanitized_and_output_limit_does_not_truncate(): void
    {
        Http::fake(['*' => Http::sequence()->push(['error' => 'sensitive provider details'], 500)->push($this->providerOutput())]);
        $this->postJson('/api/admin/v1/llm/generate-post', ['prompt' => 'Create an interesting draft'])->assertUnprocessable()->assertJsonPath('error_code', 'llm_provider_failed')->assertDontSee('sensitive');
        config(['llm.max_output_chars' => 5]);
        $this->postJson('/api/admin/v1/llm/generate-post', ['prompt' => 'Create an interesting draft'])->assertUnprocessable()->assertJsonPath('error_code', 'llm_output_too_large');
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_account_blocked_during_generation_is_rechecked_before_draft_write(): void
    {
        Http::fake(function () {
            $this->actor->update(['status' => 'blocked']);

            return Http::response($this->providerOutput());
        });
        $this->postJson('/api/admin/v1/llm/generate-page', ['prompt' => 'Create an interesting draft'])->assertForbidden();
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('page_translations', 0);
    }

    public function test_timeout_and_non_json_provider_response_are_sanitized(): void
    {
        Http::fake(['*' => Http::failedConnection('Sensitive internal endpoint and credential details')]);
        $this->postJson('/api/admin/v1/llm/generate-seo', ['prompt' => 'Create complete SEO metadata'])->assertUnprocessable()->assertJsonPath('error_code', 'llm_provider_failed')->assertDontSee('Sensitive');
        $this->assertSame('failed', LlmGeneration::firstOrFail()->status);
        foreach (['openai', 'anthropic'] as $provider) {
            Http::fake(['*' => Http::response('{invalid JSON with Sensitive provider details', 200, ['Content-Type' => 'application/json'])]);
            $this->postJson('/api/admin/v1/llm/generate-page', ['prompt' => 'Create an interesting draft', 'provider' => $provider])->assertUnprocessable()->assertJsonPath('error_code', 'llm_provider_failed')->assertDontSee('Sensitive');
            Http::assertSentCount(1);
        }
        $this->assertDatabaseCount('llm_generations', 3);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('posts', 0);
    }
}
