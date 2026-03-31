<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PublishSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_publishes_due_posts(): void
    {
        $post = Post::query()->create([
            'status' => 'draft',
        ]);

        PublishSchedule::query()->create([
            'entity_type' => 'post',
            'entity_id' => $post->id,
            'action' => 'publish',
            'due_at' => now()->subMinute(),
        ]);

        $this->artisan('cms:publish-due')
            ->assertSuccessful();

        $this->assertSame('published', $post->fresh()->status);
    }
}
