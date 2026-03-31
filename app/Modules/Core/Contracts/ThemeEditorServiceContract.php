<?php

namespace App\Modules\Core\Contracts;

use App\Models\ThemeSetting;
use Illuminate\Http\Request;

interface ThemeEditorServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function editData(Request $request): array;

    /**
     * @param array<string, mixed> $validated
     */
    public function save(array $validated, Request $request): ThemeSetting;

    public function applyPreset(string $presetKey, Request $request): ThemeSetting;
}
