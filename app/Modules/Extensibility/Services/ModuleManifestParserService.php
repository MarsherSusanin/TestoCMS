<?php

namespace App\Modules\Extensibility\Services;

use App\Modules\Extensibility\DTO\ModuleManifestDto;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleManifestParserService
{
    /**
     * @return array<int, string>
     */
    public function requiredKeys(): array
    {
        return ['id', 'name', 'version', 'provider', 'autoload'];
    }

    public function parseFromDirectory(string $moduleRoot): ModuleManifestDto
    {
        $manifestPath = rtrim($moduleRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'module.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('module.json not found in module root.');
        }

        $raw = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($raw)) {
            throw new RuntimeException('module.json must be a valid JSON object.');
        }

        $allowedKeys = [
            'id',
            'name',
            'version',
            'description',
            'author',
            'provider',
            'autoload',
            'admin',
            'security',
            'requires',
            'capabilities',
            'docs_url',
        ];
        $unknownKeys = array_values(array_diff(array_keys($raw), $allowedKeys));
        if ($unknownKeys !== []) {
            throw new RuntimeException('module.json contains unsupported keys: '.implode(', ', $unknownKeys));
        }

        foreach ($this->requiredKeys() as $requiredKey) {
            if (! array_key_exists($requiredKey, $raw)) {
                throw new RuntimeException(sprintf('module.json missing required key: %s', $requiredKey));
            }
        }

        $id = strtolower(trim((string) ($raw['id'] ?? '')));
        if (! preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $id)) {
            throw new RuntimeException('module.json id must match vendor/module-name pattern.');
        }

        $provider = trim((string) ($raw['provider'] ?? ''));
        if ($provider === '' || ! preg_match('/^[A-Z][A-Za-z0-9_\\\\]+$/', $provider)) {
            throw new RuntimeException('module.json provider must be a valid FQCN.');
        }

        $autoload = Arr::get($raw, 'autoload.psr-4');
        if (! is_array($autoload) || $autoload === []) {
            throw new RuntimeException('module.json autoload.psr-4 must be a non-empty object.');
        }

        $autoloadPsr4 = [];
        foreach ($autoload as $prefix => $path) {
            $prefix = (string) $prefix;
            $path = trim((string) $path);
            if (! Str::endsWith($prefix, '\\')) {
                throw new RuntimeException(sprintf('PSR-4 prefix "%s" must end with \\.', $prefix));
            }
            if ($path === '' || Str::startsWith($path, ['/','\\']) || str_contains($path, '..')) {
                throw new RuntimeException(sprintf('Invalid PSR-4 path "%s" in module.json.', $path));
            }
            $autoloadPsr4[$prefix] = $path;
        }

        $requires = is_array($raw['requires'] ?? null) ? $raw['requires'] : [];
        $this->assertVersionCompatibility((string) ($requires['php'] ?? '*'), PHP_VERSION, 'PHP');
        $this->assertVersionCompatibility((string) ($requires['cms'] ?? '*'), (string) config('modules.cms_version', '1.0.0'), 'CMS');

        $adminNav = is_array(Arr::get($raw, 'admin.nav')) ? Arr::get($raw, 'admin.nav') : [];
        $adminNav = array_values(array_filter(array_map(function (mixed $item) use ($id): ?array {
            if (! is_array($item)) {
                return null;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $route = trim((string) ($item['route'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            if ($label === '' || ($route === '' && $url === '')) {
                return null;
            }

            return [
                'key' => sprintf('module:%s:%s', $id, strtolower((string) ($item['key'] ?? Str::slug($label)))),
                'label' => $label,
                'route' => $route !== '' ? $route : null,
                'url' => $url !== '' ? $url : null,
                'icon' => $this->normalizeNavIcon($item['icon'] ?? null),
                'short_ru' => trim((string) ($item['short_ru'] ?? $this->defaultShortLabel($label))),
                'short_en' => trim((string) ($item['short_en'] ?? $this->defaultShortLabel($label))),
                'title' => trim((string) ($item['title'] ?? $label)),
                'permissions_any' => array_values(array_filter(array_map(
                    static fn (mixed $permission): string => trim((string) $permission),
                    is_array($item['permissions_any'] ?? null) ? $item['permissions_any'] : []
                ))),
            ];
        }, $adminNav)));

        $security = is_array($raw['security'] ?? null) ? $raw['security'] : [];
        $capabilities = is_array($raw['capabilities'] ?? null) ? $raw['capabilities'] : [];

        return new ModuleManifestDto(
            id: $id,
            name: trim((string) $raw['name']),
            version: trim((string) $raw['version']),
            description: ($raw['description'] ?? null) !== null ? trim((string) $raw['description']) : null,
            author: ($raw['author'] ?? null) !== null ? trim((string) $raw['author']) : null,
            provider: $provider,
            autoloadPsr4: $autoloadPsr4,
            adminNav: $adminNav,
            security: $security,
            requires: $requires,
            capabilities: $capabilities,
            docsUrl: ($raw['docs_url'] ?? null) !== null ? trim((string) $raw['docs_url']) : null,
            raw: $raw,
        );
    }

    private function assertVersionCompatibility(string $constraint, string $actualVersion, string $label): void
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return;
        }

        $chunks = preg_split('/\s*,\s*/', $constraint) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^\^(\d+)\.(\d+)\.(\d+)$/', $chunk, $m) === 1) {
                $major = (int) $m[1];
                $base = sprintf('%d.%d.%d', $m[1], $m[2], $m[3]);
                if ((int) explode('.', $actualVersion)[0] !== $major || version_compare($actualVersion, $base, '<')) {
                    throw new RuntimeException(sprintf('%s version %s does not satisfy %s', $label, $actualVersion, $constraint));
                }
                continue;
            }

            if (preg_match('/^(>=|<=|>|<|=)?\s*([0-9]+(?:\.[0-9]+){0,2})$/', $chunk, $m) === 1) {
                $op = $m[1] !== '' ? $m[1] : '>=';
                $target = $m[2];
                if (! version_compare($actualVersion, $target, $op)) {
                    throw new RuntimeException(sprintf('%s version %s does not satisfy %s', $label, $actualVersion, $constraint));
                }
                continue;
            }

            throw new RuntimeException(sprintf('Unsupported %s version constraint: %s', $label, $constraint));
        }
    }

    private function defaultShortLabel(string $label): string
    {
        preg_match_all('/./u', trim($label), $chars);
        $letters = $chars[0] ?? [];

        return implode('', array_slice($letters, 0, 2));
    }

    private function normalizeNavIcon(mixed $value): ?string
    {
        $icon = trim((string) $value);

        return $icon !== '' ? $icon : null;
    }
}
