<?php

namespace App\Modules\Caching\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** A database generation changes in the SAME transaction as public content. */
class PublicContentVersionService
{
    public const CACHE_SCHEMA = 'public-v3';

    public function available(): bool
    {
        return Schema::hasTable('public_content_versions');
    }

    public function current(): string
    {
        // The generation and the content it names must use the same primary,
        // even when a deployment configures read replicas.
        DB::connection()->useWriteConnectionWhenReading();

        // Never memoize: long-lived workers and concurrent writers must observe commits.
        return (string) DB::table('public_content_versions')->useWritePdo()->where('id', 1)->value('version');
    }

    public function bump(): void
    {
        if (! $this->available()) {
            return; // Setup before the publication schema has been installed.
        }

        // Random generations never reuse the identity of a rolled-back transaction.
        DB::table('public_content_versions')->where('id', 1)->update(['version' => (string) Str::uuid()]);
    }
}
