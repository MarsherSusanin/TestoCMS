<?php

namespace App\Modules\Extensibility\Registry;

class ModuleWidgetRegistry
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $definitions = [];

    /**
     * @param  array<string, mixed>  $definition
     */
    public function register(string $moduleKey, string $widgetKey, array $definition): void
    {
        $moduleKey = strtolower(trim($moduleKey));
        $widgetKey = trim($widgetKey);
        if ($moduleKey === '' || $widgetKey === '') {
            return;
        }

        $this->definitions[$moduleKey][$widgetKey] = array_merge($definition, [
            'module' => $moduleKey,
            'widget' => $widgetKey,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     */
    public function registerMany(string $moduleKey, array $definitions): void
    {
        foreach ($definitions as $widgetKey => $definition) {
            if (is_array($definition)) {
                $this->register($moduleKey, (string) $widgetKey, $definition);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $moduleKey, string $widgetKey): ?array
    {
        $moduleKey = strtolower(trim($moduleKey));
        $widgetKey = trim($widgetKey);

        return $this->definitions[$moduleKey][$widgetKey] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        $items = [];

        foreach ($this->definitions as $moduleKey => $widgets) {
            foreach ($widgets as $widgetKey => $definition) {
                $items[] = $this->serializableDefinition($moduleKey, $widgetKey, $definition);
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp(
            strtolower((string) ($a['module_label'] ?? $a['module'] ?? '')),
            strtolower((string) ($b['module_label'] ?? $b['module'] ?? ''))
        ) ?: strcmp(
            strtolower((string) ($a['label'] ?? '')),
            strtolower((string) ($b['label'] ?? ''))
        ));

        return $items;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    public function render(string $moduleKey, string $widgetKey, array $config = [], array $context = []): string
    {
        $definition = $this->find($moduleKey, $widgetKey);
        if (! is_array($definition)) {
            return '';
        }

        $renderer = $definition['renderer'] ?? null;
        if (! is_callable($renderer)) {
            return '';
        }

        return (string) call_user_func($renderer, $config, $context, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function serializableDefinition(string $moduleKey, string $widgetKey, array $definition): array
    {
        $fields = [];
        foreach ((array) ($definition['config_fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $normalizedField = $field;
            if (is_callable($normalizedField['options'] ?? null)) {
                $options = call_user_func($normalizedField['options']);
                $normalizedField['options'] = is_array($options) ? array_values($options) : [];
            }
            unset($normalizedField['renderer']);
            $fields[] = $normalizedField;
        }

        return [
            'module' => $moduleKey,
            'module_label' => (string) ($definition['module_label'] ?? $moduleKey),
            'widget' => $widgetKey,
            'label' => (string) ($definition['label'] ?? $widgetKey),
            'description' => (string) ($definition['description'] ?? ''),
            'config_fields' => $fields,
        ];
    }
}
