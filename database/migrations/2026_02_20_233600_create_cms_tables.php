<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('disk', 64)->default('public');
            $table->string('storage_path');
            $table->string('public_url')->nullable();
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 128)->nullable()->index();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->string('credits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('cover_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('robots_directives')->nullable();
            $table->json('structured_data')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->unique(['category_id', 'locale']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('featured_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title');
            $table->string('slug');
            $table->longText('content_html')->nullable();
            $table->longText('content_plain')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('robots_directives')->nullable();
            $table->json('structured_data')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->unique(['post_id', 'locale']);
        });

        Schema::create('post_category', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['post_id', 'category_id']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->string('page_type', 32)->default('landing');
            $table->json('custom_code')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title');
            $table->string('slug');
            $table->json('content_blocks')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('robots_directives')->nullable();
            $table->json('structured_data')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->unique(['page_id', 'locale']);
        });

        Schema::create('slug_histories', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('locale', 8);
            $table->string('old_slug');
            $table->string('new_slug');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'locale']);
        });

        Schema::create('redirect_rules', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('http_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seo_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('locale', 8);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('robots_directives')->nullable();
            $table->json('structured_data')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'locale']);
        });

        Schema::create('sitemap_state', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->unique();
            $table->timestamp('last_generated_at')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('locale', 8)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('preview_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('token', 128)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('publish_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('action', 32)->default('publish');
            $table->timestamp('due_at')->index();
            $table->timestamp('executed_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('llm_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->string('model')->nullable();
            $table->string('api_base')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('llm_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 128)->index();
            $table->string('locale', 8)->default('en');
            $table->longText('prompt_text');
            $table->timestamps();

            $table->unique(['template_key', 'locale']);
        });

        Schema::create('llm_generations', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 64)->index();
            $table->string('provider', 64);
            $table->string('model')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('entity_type', 64)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('input_payload');
            $table->json('output_payload')->nullable();
            $table->text('error_text')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 128)->index();
            $table->string('entity_type', 64)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX post_translations_search_idx ON post_translations USING GIN (to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(content_plain, '')))");
            DB::statement("CREATE INDEX page_translations_search_idx ON page_translations USING GIN (to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(rendered_html, '')))");
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE post_translations ADD FULLTEXT post_ft_search (title, content_plain)');
            DB::statement('ALTER TABLE page_translations ADD FULLTEXT page_ft_search (title, rendered_html)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS post_translations_search_idx');
            DB::statement('DROP INDEX IF EXISTS page_translations_search_idx');
        }

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('llm_generations');
        Schema::dropIfExists('llm_prompts');
        Schema::dropIfExists('llm_provider_configs');
        Schema::dropIfExists('publish_schedules');
        Schema::dropIfExists('preview_tokens');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('sitemap_state');
        Schema::dropIfExists('seo_overrides');
        Schema::dropIfExists('redirect_rules');
        Schema::dropIfExists('slug_histories');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('post_category');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('assets');
    }
};
