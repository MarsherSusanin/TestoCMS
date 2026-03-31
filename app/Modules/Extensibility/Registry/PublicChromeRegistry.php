<?php

namespace App\Modules\Extensibility\Registry;

use Illuminate\Contracts\Support\Htmlable;

class PublicChromeRegistry
{
    /**
     * @var array<int, string>
     */
    public const ZONES = [
        'head_bootstrap',
        'head',
        'body_start',
        'header_actions',
    ];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $items = [
        'head_bootstrap' => [],
        'head' => [],
        'body_start' => [],
        'header_actions' => [],
    ];

    /**
     * @param  array<string, mixed>  $item
     */
    public function register(string $zone, array $item): void
    {
        if (! in_array($zone, self::ZONES, true)) {
            return;
        }

        $renderer = $item['renderer'] ?? null;
        if (! is_callable($renderer) && ! is_string($renderer) && ! $renderer instanceof Htmlable) {
            return;
        }

        $key = trim((string) ($item['key'] ?? ''));
        if ($key === '') {
            $key = sprintf('%s:%s', $zone, uniqid('public_chrome_', true));
        }

        $this->items[$zone][$key] = [
            'key' => $key,
            'renderer' => $renderer,
        ];
    }

    /**
     * @param  callable(array<string, mixed>): (Htmlable|string|null)|Htmlable|string  $renderer
     */
    public function registerRenderable(string $zone, string $key, callable|Htmlable|string $renderer): void
    {
        $this->register($zone, [
            'key' => $key,
            'renderer' => $renderer,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function render(string $zone, array $context = []): string
    {
        if (! in_array($zone, self::ZONES, true)) {
            return '';
        }

        $html = [];
        foreach ($this->items[$zone] as $item) {
            $rendered = $this->resolveRenderable($item['renderer'] ?? null, $context);
            if ($rendered !== '') {
                $html[] = $rendered;
            }
        }

        return implode("\n", $html);
    }

    /**
     * @param  callable(array<string, mixed>): (Htmlable|string|null)|Htmlable|string|null  $renderer
     * @param  array<string, mixed>  $context
     */
    private function resolveRenderable(callable|Htmlable|string|null $renderer, array $context): string
    {
        if (is_callable($renderer)) {
            $renderer = $renderer($context);
        }

        if ($renderer instanceof Htmlable) {
            return trim($renderer->toHtml());
        }

        return trim((string) $renderer);
    }
}
