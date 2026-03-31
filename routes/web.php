<?php

use App\Http\Controllers\Admin\AdminRuntimeController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\AssetCrudController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryCrudController;
use App\Http\Controllers\Admin\ContentTemplateController;
use App\Http\Controllers\Admin\CoreUpdateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PageCrudController;
use App\Http\Controllers\Admin\PageStagePreviewController;
use App\Http\Controllers\Admin\PostCrudController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\Web\FeedController;
use App\Http\Controllers\Web\HomeRedirectController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\SiteContentController;
use App\Http\Controllers\Web\SitePreviewController;
use App\Http\Controllers\Web\SiteSearchController;
use App\Modules\Extensibility\Services\EnabledModulePublicRoutesLoader;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Setup Wizard
|--------------------------------------------------------------------------
*/
Route::prefix('setup')->group(function (): void {
    Route::get('/', fn () => redirect()->route('setup.step1'));
    Route::get('/step/1', [SetupWizardController::class, 'step1'])->name('setup.step1');
    Route::get('/step/2', [SetupWizardController::class, 'step2'])->name('setup.step2');
    Route::post('/step/2', [SetupWizardController::class, 'saveStep2'])->name('setup.step2.save');
    Route::post('/step/2/test-db', [SetupWizardController::class, 'testDatabase'])->name('setup.test-db');
    Route::get('/step/3', [SetupWizardController::class, 'step3'])->name('setup.step3');
    Route::post('/step/3', [SetupWizardController::class, 'saveStep3'])->name('setup.step3.save');
    Route::get('/step/4', [SetupWizardController::class, 'step4'])->name('setup.step4');
    Route::post('/step/4', [SetupWizardController::class, 'saveStep4'])->name('setup.step4.save');
    Route::get('/step/5', [SetupWizardController::class, 'step5'])->name('setup.step5');
});

Route::get('/', HomeRedirectController::class)->name('site.home');
Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');
Route::get('/robots.txt', [SeoController::class, 'robotsTxt'])->name('seo.robots');
Route::get('/openapi.yaml', fn () => response()->file(base_path('openapi/openapi.yaml')));
Route::get('/llms.txt', [SeoController::class, 'llmsTxt'])->name('seo.llms');
Route::get('/sitemap.xml', fn () => redirect('/sitemap-index.xml', 301));
Route::get('/sitemap-index.xml', [SeoController::class, 'sitemapIndex'])->name('seo.sitemap.index');
Route::get('/sitemaps/{locale}.xml', [SeoController::class, 'sitemapLocale'])->name('seo.sitemap.locale');
Route::get('/feed/{locale}.xml', [FeedController::class, 'blogFeed'])->name('feed.blog');
Route::get('/feed/{locale}/category/{slug}.xml', [FeedController::class, 'categoryFeed'])->name('feed.category');
Route::get('/preview/{token}', SitePreviewController::class)->name('preview.show');

Route::prefix('admin')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
        Route::get('/', DashboardController::class)->name('admin.dashboard');
        Route::get('/runtime/{runtime}', [AdminRuntimeController::class, 'show'])->name('admin.runtime.show');

        Route::get('/pages', [PageCrudController::class, 'index'])->name('admin.pages.index');
        Route::get('/pages/create', [PageCrudController::class, 'create'])->name('admin.pages.create');
        Route::post('/pages', [PageCrudController::class, 'store'])->name('admin.pages.store');
        Route::get('/pages/{page}/edit', [PageCrudController::class, 'edit'])->name('admin.pages.edit');
        Route::put('/pages/{page}', [PageCrudController::class, 'update'])->name('admin.pages.update');
        Route::delete('/pages/{page}', [PageCrudController::class, 'destroy'])->name('admin.pages.destroy');
        Route::post('/pages/{page}/duplicate', [PageCrudController::class, 'duplicate'])->name('admin.pages.duplicate');
        Route::post('/pages/{page}/publish', [PageCrudController::class, 'publish'])->name('admin.pages.publish');
        Route::post('/pages/{page}/unpublish', [PageCrudController::class, 'unpublish'])->name('admin.pages.unpublish');
        Route::post('/pages/bulk', [PageCrudController::class, 'bulk'])->name('admin.pages.bulk');
        Route::post('/pages/{page}/schedule', [PageCrudController::class, 'schedule'])->name('admin.pages.schedule');
        Route::post('/pages/{page}/preview-token', [PageCrudController::class, 'createPreviewToken'])->name('admin.pages.preview-token');
        Route::post('/pages/fullscreen-stage/render', [PageStagePreviewController::class, 'render'])->name('admin.pages.stage.render');

        Route::get('/posts', [PostCrudController::class, 'index'])->name('admin.posts.index');
        Route::get('/posts/create', [PostCrudController::class, 'create'])->name('admin.posts.create');
        Route::post('/posts', [PostCrudController::class, 'store'])->name('admin.posts.store');
        Route::post('/posts/markdown/preview', [PostCrudController::class, 'previewMarkdown'])->name('admin.posts.markdown.preview');
        Route::post('/posts/markdown/import', [PostCrudController::class, 'importMarkdown'])->name('admin.posts.markdown.import');
        Route::get('/posts/{post}/edit', [PostCrudController::class, 'edit'])->name('admin.posts.edit');
        Route::put('/posts/{post}', [PostCrudController::class, 'update'])->name('admin.posts.update');
        Route::delete('/posts/{post}', [PostCrudController::class, 'destroy'])->name('admin.posts.destroy');
        Route::post('/posts/{post}/duplicate', [PostCrudController::class, 'duplicate'])->name('admin.posts.duplicate');
        Route::post('/posts/{post}/publish', [PostCrudController::class, 'publish'])->name('admin.posts.publish');
        Route::post('/posts/{post}/unpublish', [PostCrudController::class, 'unpublish'])->name('admin.posts.unpublish');
        Route::post('/posts/bulk', [PostCrudController::class, 'bulk'])->name('admin.posts.bulk');
        Route::post('/posts/{post}/schedule', [PostCrudController::class, 'schedule'])->name('admin.posts.schedule');
        Route::post('/posts/{post}/preview-token', [PostCrudController::class, 'createPreviewToken'])->name('admin.posts.preview-token');

        Route::get('/categories', [CategoryCrudController::class, 'index'])->name('admin.categories.index');
        Route::get('/categories/create', [CategoryCrudController::class, 'create'])->name('admin.categories.create');
        Route::post('/categories', [CategoryCrudController::class, 'store'])->name('admin.categories.store');
        Route::get('/categories/{category}/edit', [CategoryCrudController::class, 'edit'])->name('admin.categories.edit');
        Route::put('/categories/{category}', [CategoryCrudController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/{category}', [CategoryCrudController::class, 'destroy'])->name('admin.categories.destroy');

        Route::get('/assets', [AssetCrudController::class, 'index'])->name('admin.assets.index');
        Route::post('/assets', [AssetCrudController::class, 'store'])->name('admin.assets.store');
        Route::get('/assets/{asset}/edit', [AssetCrudController::class, 'edit'])->name('admin.assets.edit');
        Route::put('/assets/{asset}', [AssetCrudController::class, 'update'])->name('admin.assets.update');
        Route::delete('/assets/{asset}', [AssetCrudController::class, 'destroy'])->name('admin.assets.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/roles', [RoleController::class, 'index'])->name('admin.users.roles.index');
        Route::get('/users/roles/{role}/edit', [RoleController::class, 'edit'])->name('admin.users.roles.edit');
        Route::put('/users/roles/{role}', [RoleController::class, 'update'])->name('admin.users.roles.update');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::post('/users/{user}/password', [UserController::class, 'changePassword'])->name('admin.users.password');
        Route::post('/users/{user}/status', [UserController::class, 'changeStatus'])->name('admin.users.status');

        Route::get('/audit', AuditLogController::class)->name('admin.audit.index');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
        Route::get('/settings/seo', [\App\Http\Controllers\Admin\SeoSettingsController::class, 'edit'])->name('admin.settings.seo.edit');
        Route::put('/settings/seo', [\App\Http\Controllers\Admin\SeoSettingsController::class, 'update'])->name('admin.settings.seo.update');

        Route::get('/updates', [CoreUpdateController::class, 'index'])->name('admin.updates.index');
        Route::get('/updates/logs', [CoreUpdateController::class, 'logs'])->name('admin.updates.logs');
        Route::post('/updates/settings', [CoreUpdateController::class, 'saveSettings'])->name('admin.updates.settings');
        Route::post('/updates/check', [CoreUpdateController::class, 'check'])->name('admin.updates.check');
        Route::post('/updates/upload', [CoreUpdateController::class, 'upload'])->name('admin.updates.upload');
        Route::post('/updates/apply', [CoreUpdateController::class, 'apply'])->name('admin.updates.apply');
        Route::post('/updates/rollback/{backup}', [CoreUpdateController::class, 'rollback'])->name('admin.updates.rollback');
        Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('admin.api-keys.index');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('admin.api-keys.store');
        Route::delete('/api-keys/{accessToken}', [ApiKeyController::class, 'destroy'])->name('admin.api-keys.destroy');
        Route::get('/templates', [ContentTemplateController::class, 'index'])->name('admin.templates.index');
        Route::post('/templates', [ContentTemplateController::class, 'store'])->name('admin.templates.store');
        Route::put('/templates/{template}', [ContentTemplateController::class, 'update'])->name('admin.templates.update');
        Route::post('/templates/{template}/duplicate', [ContentTemplateController::class, 'duplicate'])->name('admin.templates.duplicate');
        Route::delete('/templates/{template}', [ContentTemplateController::class, 'destroy'])->name('admin.templates.destroy');
        Route::get('/theme', [ThemeController::class, 'edit'])->name('admin.theme.edit');
        Route::put('/theme', [ThemeController::class, 'update'])->name('admin.theme.update');
        Route::put('/theme/chrome', [ThemeController::class, 'updateChrome'])->name('admin.theme.chrome.update');
        Route::post('/theme/apply-preset', [ThemeController::class, 'applyPreset'])->name('admin.theme.apply-preset');

        Route::get('/modules', [ModuleController::class, 'index'])->name('admin.modules.index');
        Route::get('/modules/docs', [ModuleController::class, 'docs'])->name('admin.modules.docs');
        Route::post('/modules/upload', [ModuleController::class, 'upload'])->name('admin.modules.upload');
        Route::post('/modules/install-local', [ModuleController::class, 'installLocal'])->name('admin.modules.install-local');
        Route::post('/modules/install-bundled/{moduleKey}', [ModuleController::class, 'installBundled'])
            ->where('moduleKey', '[A-Za-z0-9._-]+(?:--[A-Za-z0-9._-]+)?')
            ->name('admin.modules.install-bundled');
        Route::post('/modules/{module}/activate', [ModuleController::class, 'activate'])->name('admin.modules.activate');
        Route::post('/modules/{module}/deactivate', [ModuleController::class, 'deactivate'])->name('admin.modules.deactivate');
        Route::post('/modules/{module}/update', [ModuleController::class, 'update'])->name('admin.modules.update');
        Route::delete('/modules/{module}', [ModuleController::class, 'destroy'])->name('admin.modules.destroy');
    });
});

app(EnabledModulePublicRoutesLoader::class)->load();

Route::prefix('{locale}')
    ->whereIn('locale', config('cms.supported_locales', ['en']))
    ->group(function (): void {
        Route::get('/search', SiteSearchController::class)->name('site.search');
        Route::get('/{slug?}', SiteContentController::class)
            ->where('slug', '.*')
            ->name('site.show');
    });
