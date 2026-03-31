<?php

use App\Http\Controllers\Api\Admin\AssetController as AdminAssetController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\LlmController;
use App\Http\Controllers\Api\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\Content\AssetController as ContentAssetController;
use App\Http\Controllers\Api\Content\CategoryController as ContentCategoryController;
use App\Http\Controllers\Api\Content\PageController as ContentPageController;
use App\Http\Controllers\Api\Content\PostController as ContentPostController;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::prefix('content/v1')
    ->middleware('content_api_key')
    ->group(function (): void {
        Route::get('/posts', [ContentPostController::class, 'index']);
        Route::get('/posts/{slug}', [ContentPostController::class, 'show']);

        Route::get('/pages', [ContentPageController::class, 'index']);
        Route::get('/pages/{slug}', [ContentPageController::class, 'show']);

        Route::get('/categories', [ContentCategoryController::class, 'index']);
        Route::get('/categories/{slug}', [ContentCategoryController::class, 'show']);

        Route::get('/assets', [ContentAssetController::class, 'index']);
    });

Route::prefix('admin/v1')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/posts', [AdminPostController::class, 'index'])->middleware(['can:viewAny,'.Post::class, 'ability:posts:read']);
        Route::post('/posts', [AdminPostController::class, 'store'])->middleware(['can:create,'.Post::class, 'abilities:posts:write']);
        Route::get('/posts/{post}', [AdminPostController::class, 'show'])->middleware(['can:view,post', 'ability:posts:read']);
        Route::match(['put', 'patch'], '/posts/{post}', [AdminPostController::class, 'update'])->middleware(['can:update,post', 'abilities:posts:write']);
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->middleware(['can:delete,post', 'abilities:posts:write']);
        Route::post('/posts/{post}/publish', [AdminPostController::class, 'publish'])->middleware(['can:publish,post', 'abilities:posts:publish']);
        Route::post('/posts/{post}/unpublish', [AdminPostController::class, 'unpublish'])->middleware(['can:publish,post', 'abilities:posts:publish']);
        Route::post('/posts/{post}/schedule', [AdminPostController::class, 'schedule'])->middleware(['can:publish,post', 'abilities:posts:publish']);

        Route::get('/pages', [AdminPageController::class, 'index'])->middleware(['can:viewAny,'.Page::class, 'ability:pages:read']);
        Route::post('/pages', [AdminPageController::class, 'store'])->middleware(['can:create,'.Page::class, 'abilities:pages:write']);
        Route::get('/pages/{page}', [AdminPageController::class, 'show'])->middleware(['can:view,page', 'ability:pages:read']);
        Route::match(['put', 'patch'], '/pages/{page}', [AdminPageController::class, 'update'])->middleware(['can:update,page', 'abilities:pages:write']);
        Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->middleware(['can:delete,page', 'abilities:pages:write']);
        Route::post('/pages/{page}/publish', [AdminPageController::class, 'publish'])->middleware(['can:publish,page', 'abilities:pages:publish']);
        Route::post('/pages/{page}/unpublish', [AdminPageController::class, 'unpublish'])->middleware(['can:publish,page', 'abilities:pages:publish']);
        Route::post('/pages/{page}/schedule', [AdminPageController::class, 'schedule'])->middleware(['can:publish,page', 'abilities:pages:publish']);

        Route::get('/categories', [AdminCategoryController::class, 'index'])->middleware(['can:viewAny,'.Category::class, 'ability:categories:read']);
        Route::post('/categories', [AdminCategoryController::class, 'store'])->middleware(['can:create,'.Category::class, 'abilities:categories:write']);
        Route::get('/categories/{category}', [AdminCategoryController::class, 'show'])->middleware(['can:view,category', 'ability:categories:read']);
        Route::match(['put', 'patch'], '/categories/{category}', [AdminCategoryController::class, 'update'])->middleware(['can:update,category', 'abilities:categories:write']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->middleware(['can:delete,category', 'abilities:categories:write']);

        Route::get('/assets', [AdminAssetController::class, 'index'])->middleware(['can:viewAny,'.Asset::class, 'ability:assets:read']);
        Route::post('/assets', [AdminAssetController::class, 'store'])->middleware(['can:create,'.Asset::class, 'abilities:assets:write']);
        Route::get('/assets/{asset}', [AdminAssetController::class, 'show'])->middleware(['can:view,asset', 'ability:assets:read']);
        Route::match(['put', 'patch'], '/assets/{asset}', [AdminAssetController::class, 'update'])->middleware(['can:update,asset', 'abilities:assets:write']);
        Route::delete('/assets/{asset}', [AdminAssetController::class, 'destroy'])->middleware(['can:delete,asset', 'abilities:assets:write']);

        Route::post('/llm/generate-post', [LlmController::class, 'generatePost'])->middleware(['permission:llm:generate', 'abilities:llm:generate']);
        Route::post('/llm/generate-page', [LlmController::class, 'generatePage'])->middleware(['permission:llm:generate', 'abilities:llm:generate']);
        Route::post('/llm/generate-seo', [LlmController::class, 'generateSeo'])->middleware(['permission:llm:generate', 'abilities:llm:generate']);
    });
