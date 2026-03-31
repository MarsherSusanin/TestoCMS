<?php

namespace Tests;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.seed_demo_content' => filter_var(env('CMS_SEED_DEMO_CONTENT', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
        ]);

        $this->app->offsetUnset(OutputStyle::class);
        $this->app->make(Kernel::class)->setArtisan(null);

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
