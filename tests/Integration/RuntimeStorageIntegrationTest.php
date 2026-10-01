<?php

namespace Tests\Integration;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\PreviewToken;
use App\Models\User;
use App\Modules\Auth\Services\UserManagementService;
use App\Modules\Content\Services\PageWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Opt-in real drivers; Redis must be an isolated test instance. */
class RuntimeStorageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public static function drivers(): array
    {
        return [['file'], ['redis']];
    }

    #[DataProvider('drivers')]
    public function test_public_cache_and_persisted_sessions_observe_revocation(string $driver): void
    {
        if (getenv('CMS_RUN_RUNTIME_STORAGE_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit opt-in and a disposable Redis instance.');
        }
        config([
            'cache.default' => $driver,
            'cache.prefix' => 'p2-runtime-'.bin2hex(random_bytes(6)),
            'session.driver' => $driver,
            'session.cookie' => 'p2_runtime_session',
            'cms.seed_demo_content' => false,
        ]);
        app('cache')->purge();
        app('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => 'Runtime author', 'login' => 'runtime_author', 'email' => 'runtime@author.test', 'password' => 'password', 'status' => 'active']);
        $user->assignRole('author');
        $admin = User::create(['name' => 'Runtime admin', 'login' => 'runtime_admin', 'email' => 'runtime@admin.test', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('superadmin');
        $page = Page::create(['status' => 'published', 'page_type' => 'landing', 'published_at' => now()->subMinute()]);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'slug' => 'runtime-storage', 'title' => 'Runtime', 'rendered_html' => '<h1>Runtime body</h1>']);
        $miss = $this->get('/en/runtime-storage')->assertOk();
        $hit = $this->get('/en/runtime-storage')->assertOk()->assertHeader('X-TestoCMS-Cache', 'HIT');
        $this->assertSame($miss->headers->get('Content-Security-Policy'), $hit->headers->get('Content-Security-Policy'));
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $admin);
        app(PageWorkflowService::class)->unpublish($page, $request);
        $this->get('/en/runtime-storage')->assertNotFound();
        $preview = PreviewToken::create(['entity_type' => 'page', 'entity_id' => $page->id, 'token' => bin2hex(random_bytes(32)), 'expires_at' => now()->addMinute(), 'created_by' => $admin->id]);
        $this->get('/preview/'.$preview->token.'?locale=en')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $preview->update(['expires_at' => now()->subMinute()]);
        $this->get('/preview/'.$preview->token.'?locale=en')->assertNotFound();

        $login = $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $cookie = collect($login->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'p2_runtime_session');
        $this->assertNotNull($cookie);
        $this->assertNotEmpty(app('session')->driver()->getHandler()->read(session()->getId()));
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        $this->forgetRuntimeSession();
        $this->get('/admin/posts')->assertOk();
        app(UserManagementService::class)->updateStatus($admin, $user, 'blocked');
        app(UserManagementService::class)->updateStatus($admin, $user, 'active');
        $this->forgetRuntimeSession();
        $this->get('/admin/posts')->assertRedirect('/admin/login');
        Cache::flush();
    }

    private function forgetRuntimeSession(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
