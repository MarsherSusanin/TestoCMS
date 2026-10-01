<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicMediaTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = 'audit-media-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists(storage_path('app/public/'.$this->directory));
        File::put(storage_path('app/public/'.$this->directory.'/movie.txt'), '0123456789');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/'.$this->directory));
        parent::tearDown();
    }

    public function test_media_get_head_ranges_and_new_upload_without_symlink(): void
    {
        config(['security.csp.enabled' => true, 'security.csp.report_only' => false]);
        $url = '/storage/'.$this->directory.'/movie.txt';
        $full = $this->get($url)->assertOk()->assertHeader('Content-Length', '10')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $etag = $full->headers->get('ETag');
        $this->get($url, ['If-None-Match' => $etag])->assertStatus(304);
        $head = $this->head($url)->assertOk()->assertHeader('Content-Length', '10');
        ob_start();
        $head->baseResponse->sendContent();
        $this->assertSame('', ob_get_clean());
        $range = $this->get($url, ['Range' => 'bytes=2-5'])->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10')->assertHeader('Content-Length', '4');
        ob_start();
        $range->baseResponse->sendContent();
        $this->assertSame('2345', ob_get_clean());
        $this->get($url, ['Range' => 'bytes=99-'])->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */10');
        $this->get($url, ['Range' => 'bytes=5-2'])->assertStatus(416);
        $this->get($url, ['Range' => 'bytes=-0'])->assertStatus(416);
        $suffix = $this->get($url, ['Range' => 'bytes=-100'])->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-9/10');
        ob_start();
        $suffix->baseResponse->sendContent();
        $this->assertSame('0123456789', ob_get_clean());
        $this->get($url, ['Range' => 'bytes=2-5', 'If-Range' => '"stale"'])
            ->assertOk()->assertHeader('Content-Length', '10');
        $this->get($url, ['Range' => 'bytes=2-5', 'If-Range' => $etag])
            ->assertStatus(206)->assertHeader('Content-Length', '4');
        File::put(storage_path('app/public/'.$this->directory.'/new.txt'), 'new upload');
        $this->get('/storage/'.$this->directory.'/new.txt')->assertOk();
        File::delete(storage_path('app/public/'.$this->directory.'/movie.txt'));
        $this->get($url)->assertNotFound();
    }

    public function test_private_dotfiles_scripts_and_escaping_symlinks_are_not_served(): void
    {
        $root = storage_path('app/public/'.$this->directory);
        File::put($root.'/.secret', 'private');
        File::put($root.'/shell.php', '<?php echo "unsafe";');
        $this->get('/storage/'.$this->directory.'/.secret')->assertNotFound();
        $this->get('/storage/'.$this->directory.'/shell.php')->assertNotFound();
        $this->get('/storage/'.$this->directory.'/%2e%2e/private/.env')->assertNotFound();
        if (function_exists('symlink')) {
            symlink($root.'/.secret', $root.'/secret-alias.txt');
            $this->get('/storage/'.$this->directory.'/secret-alias.txt')->assertNotFound();
            symlink(base_path('composer.json'), $root.'/outside.json');
            $this->get('/storage/'.$this->directory.'/outside.json')->assertNotFound();
        }
    }
}
