<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_renders_branded_404(): void
    {
        config()->set('app.debug', false);

        $this->get('/en/definitely-not-a-real-page-12345')
            ->assertNotFound()
            ->assertSee('Страница не найдена')
            ->assertSee('error__code', false);
    }
}
