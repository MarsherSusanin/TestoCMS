<?php

namespace App\Modules\Core\Contracts;

use App\Models\ThemeSetting;
use Illuminate\Http\Request;

interface SiteChromeEditorServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function editData(Request $request): array;

    public function saveFromJson(string $json, Request $request): ThemeSetting;
}
