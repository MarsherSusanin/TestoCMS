<?php

namespace App\Modules\Extensibility\Registry;

class AdminNavigationRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $items = [];

    /**
     * @param  array<string, mixed>  $item
     */
    public function register(array $item): void
    {
        $key = (string) ($item['key'] ?? $item['route'] ?? $item['url'] ?? uniqid('nav_', true));
        $this->items[$key] = $item;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function registerMany(array $items): void
    {
        foreach ($items as $item) {
            if (is_array($item)) {
                $this->register($item);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->items);
    }
}
