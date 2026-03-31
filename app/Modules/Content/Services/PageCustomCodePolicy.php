<?php

namespace App\Modules\Content\Services;

use App\Models\User;
use App\Modules\Core\Contracts\SanitizerContract;

class PageCustomCodePolicy
{
    public function __construct(private readonly SanitizerContract $sanitizer) {}

    /**
     * @param  array<string, mixed>|null  $customCode
     * @return array<string, mixed>|null
     */
    public function prepare(?array $customCode, ?User $actor): ?array
    {
        if ($customCode === null) {
            return null;
        }

        $allowedRoles = config('cms.custom_code.advanced_roles', ['superadmin', 'admin']);
        if (! $actor?->hasAnyRole($allowedRoles)) {
            abort(403, 'Custom code is restricted to admin roles.');
        }

        $profile = ((bool) ($customCode['advanced'] ?? false)) ? 'advanced' : 'restricted_embed';

        foreach (['head_html', 'head_css', 'body_html', 'body_js'] as $slot) {
            if (! isset($customCode[$slot]) || ! is_string($customCode[$slot])) {
                continue;
            }

            $customCode[$slot] = $this->sanitizer->sanitizeHtml($customCode[$slot], $profile);
        }

        return $customCode;
    }
}
