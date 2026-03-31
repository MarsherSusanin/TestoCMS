<?php

namespace App\Modules\Extensibility\DTO;

class InstalledModuleDto
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $id,
        public readonly string $moduleKey,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $description,
        public readonly ?string $author,
        public readonly string $installPath,
        public readonly string $provider,
        public readonly bool $enabled,
        public readonly string $status,
        public readonly ?string $lastError,
        public readonly array $metadata,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'module_key' => $this->moduleKey,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'install_path' => $this->installPath,
            'provider' => $this->provider,
            'enabled' => $this->enabled,
            'status' => $this->status,
            'last_error' => $this->lastError,
            'metadata' => $this->metadata,
        ];
    }
}
