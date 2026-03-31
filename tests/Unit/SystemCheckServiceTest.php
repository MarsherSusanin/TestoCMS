<?php

namespace Tests\Unit;

use App\Modules\Setup\Services\SystemCheckService;
use Tests\TestCase;

class SystemCheckServiceTest extends TestCase
{
    public function test_postgresql_driver_satisfies_required_database_check(): void
    {
        $service = $this->makeService([
            'pdo_mysql' => false,
            'pdo_pgsql' => true,
        ]);

        $checks = $service->runAll();

        $this->assertTrue($checks['pdo_database']['passed']);
        $this->assertSame('PDO MySQL or PDO PostgreSQL', $checks['pdo_database']['label']);
        $this->assertSame('pdo_pgsql', $checks['pdo_database']['detail']);
        $this->assertTrue($service->allRequiredPassed());
    }

    public function test_required_checks_fail_when_no_database_driver_is_available(): void
    {
        $service = $this->makeService([
            'pdo_mysql' => false,
            'pdo_pgsql' => false,
        ]);

        $checks = $service->runAll();

        $this->assertFalse($checks['pdo_database']['passed']);
        $this->assertSame('missing', $checks['pdo_database']['detail']);
        $this->assertFalse($service->allRequiredPassed());
    }

    /**
     * @param  array<string, bool>  $extensions
     */
    private function makeService(array $extensions): SystemCheckService
    {
        $defaults = [
            'pdo_mysql' => false,
            'pdo_pgsql' => false,
            'mbstring' => true,
            'intl' => true,
            'gd' => true,
            'bcmath' => true,
            'zip' => true,
            'exif' => true,
            'openssl' => true,
            'curl' => true,
            'fileinfo' => true,
        ];

        $resolvedExtensions = array_merge($defaults, $extensions);

        return new class($resolvedExtensions) extends SystemCheckService
        {
            /**
             * @param  array<string, bool>  $extensions
             */
            public function __construct(private readonly array $extensions) {}

            protected function hasExtension(string $ext): bool
            {
                return $this->extensions[$ext] ?? false;
            }

            protected function extensionDetail(string $ext): string
            {
                return $this->hasExtension($ext) ? 'loaded' : 'missing';
            }

            protected function isWritablePath(string $path): bool
            {
                return true;
            }

            protected function isEnvFileWritable(): bool
            {
                return true;
            }
        };
    }
}
