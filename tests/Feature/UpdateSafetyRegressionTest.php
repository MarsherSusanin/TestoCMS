<?php

namespace Tests\Feature;

use App\Modules\Extensibility\Services\ModuleManifestParserService;
use App\Modules\Updates\Services\CoreUpdateHealthCheckService;
use App\Modules\Updates\Services\UpdateOperationGate;
use App\Modules\Updates\Services\UpdatePreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class UpdateSafetyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Artisan::call('up');
        parent::tearDown();
    }

    public static function constraints(): array
    {
        return [
            ['8.2.0', '^8.2', true], ['8.5.3', '^8.2', true], ['8.1.0', '^8.2', false],
            ['9.0.0', '^8.2', false], ['1.2.0', '>=1.0.0', true], ['0.9.0', '>=1.0.0', false],
            ['1.2.0', '1.0.0', true], ['1.2.0', '^1.0 || ^2.0', true], ['1.2.0', 'broken constraint', false],
        ];
    }

    #[DataProvider('constraints')]
    public function test_release_and_module_constraints_use_composer_semantics(string $version, string $constraint, bool $expected): void
    {
        $method = new \ReflectionMethod(UpdatePreflightService::class, 'versionSatisfiesConstraint');
        $this->assertSame($expected, $method->invoke(app(UpdatePreflightService::class), $version, $constraint));
    }

    public static function moduleConstraints(): array
    {
        return [
            ['8.2.0', '^8.2', true], ['8.5.3', '^8.2', true], ['8.1.0', '^8.2', false],
            ['9.0.0', '^8.2', false], ['0.2.3', '^0.2.3', true], ['0.2.9', '^0.2.3', true],
            ['0.3.0', '^0.2.3', false], ['1.5.0', '>=1 <2 || ^3', true],
            ['3.1.0', '>=1 <2 || ^3', true], ['2.3.0', '>=1 <2 || ^3', false],
            ['1.2.0', '1.0.0', true],
        ];
    }

    #[DataProvider('moduleConstraints')]
    public function test_module_manifest_constraints_use_composer_semantics(string $version, string $constraint, bool $expected): void
    {
        $method = new \ReflectionMethod(ModuleManifestParserService::class, 'assertVersionCompatibility');
        if (! $expected) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('CMS version '.$version.' does not satisfy '.$constraint);
        }

        $this->assertNull($method->invoke(app(ModuleManifestParserService::class), $constraint, $version, 'CMS'));
    }

    public function test_invalid_module_constraint_reports_a_clear_error(): void
    {
        $method = new \ReflectionMethod(ModuleManifestParserService::class, 'assertVersionCompatibility');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid CMS version constraint: broken constraint');
        $method->invoke(app(ModuleManifestParserService::class), 'broken constraint', '1.0.0', 'CMS');
    }

    public static function failedStatuses(): array
    {
        return [[301], [403], [404], [500], [503]];
    }

    #[DataProvider('failedStatuses')]
    public function test_only_2xx_health_responses_are_successful(int $status): void
    {
        config(['updates.health_check_url' => 'https://health.test/up']);
        Http::fake(['https://health.test/up' => Http::response('unhealthy', $status)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP '.$status);
        app(CoreUpdateHealthCheckService::class)->runHealthCheck();
    }

    public function test_non_strict_outage_is_explicitly_unverified(): void
    {
        config(['updates.health_check_url' => 'https://health.test/up', 'updates.health_check_strict' => false]);
        Http::fake(fn () => throw new ConnectionException('unreachable'));
        $result = app(CoreUpdateHealthCheckService::class)->runHealthCheck();
        $this->assertSame('health_unverified', $result['status']);
        $this->assertNotEmpty($result['warning']);
    }

    public function test_health_alias_is_available_in_maintenance_without_html_cache(): void
    {
        Artisan::call('down');
        $this->get('/up')->assertOk();
        $this->get('/healthz')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/login')->assertStatus(503);
    }

    public function test_exclusive_gate_blocks_another_process_and_releases_after_exception(): void
    {
        $gate = app(UpdateOperationGate::class);
        $script = <<<'PHP_SCRIPT'
$h=fopen($argv[1], 'c+');
$locked=flock($h, LOCK_SH|LOCK_NB);
if ($locked) { flock($h, LOCK_UN); }
fclose($h);
exit($locked ? 0 : 7);
PHP_SCRIPT;
        try {
            $gate->runExclusive(function () use ($script): void {
                $other = new Process([PHP_BINARY, '-r', $script, storage_path('app/private/core-updates.lock')]);
                $other->run();
                $this->assertSame(7, $other->getExitCode());
                throw new RuntimeException('probe');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('probe', $e->getMessage());
        }
        $other = new Process([PHP_BINARY, '-r', $script, storage_path('app/private/core-updates.lock')]);
        $other->run();
        $this->assertSame(0, $other->getExitCode());
    }
}
