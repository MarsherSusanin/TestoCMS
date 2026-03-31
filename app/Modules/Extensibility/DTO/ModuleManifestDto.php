<?php

namespace App\Modules\Extensibility\DTO;

class ModuleManifestDto
{
    /**
     * @param  array<string, string>  $autoloadPsr4
     * @param  array<int, array<string, mixed>>  $adminNav
     * @param  array<string, mixed>  $security
     * @param  array<string, mixed>  $requires
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $description,
        public readonly ?string $author,
        public readonly string $provider,
        public readonly array $autoloadPsr4,
        public readonly array $adminNav,
        public readonly array $security,
        public readonly array $requires,
        public readonly array $capabilities,
        public readonly ?string $docsUrl,
        public readonly array $raw,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetadataArray(): array
    {
        return [
            'manifest' => $this->raw,
            'autoload' => [
                'psr-4' => $this->autoloadPsr4,
            ],
            'admin' => [
                'nav' => $this->adminNav,
            ],
            'security' => $this->security,
            'requires' => $this->requires,
            'capabilities' => $this->capabilities,
            'docs_url' => $this->docsUrl,
        ];
    }
}
