<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\HtmlString;

class AdminSidebarIconRegistry
{
    /**
     * @var array<string, string>
     */
    private const ICONS = [
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.25"></rect><rect x="14" y="3" width="7" height="5" rx="1.25"></rect><rect x="14" y="12" width="7" height="9" rx="1.25"></rect><rect x="3" y="14" width="7" height="7" rx="1.25"></rect>',
        'files' => '<path d="M7 18V6a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2Z"></path><path d="M14 4v4h4"></path><path d="M5 8H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10"></path>',
        'newspaper' => '<path d="M5 7h14a2 2 0 0 1 2 2v8a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V7Z"></path><path d="M5 7V5a2 2 0 0 1 2-2h10"></path><path d="M9 11h8"></path><path d="M9 15h8"></path><path d="M9 19h5"></path><path d="M6.5 11h.01"></path><path d="M6.5 15h.01"></path>',
        'layout-template' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 10h18"></path><path d="M9 10v10"></path>',
        'tags' => '<path d="m20 10-8-8H4v8l8 8 8-8Z"></path><path d="M7 7h.01"></path><path d="m14 6 4 4"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="3"></circle><path d="M20 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16.5 4.5a3 3 0 0 1 0 5.99"></path>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="8.5" cy="9" r="1.5"></circle><path d="m21 16-5.5-5.5L6 20"></path>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18h1.2a2.8 2.8 0 0 0 0-5.6H12a1.9 1.9 0 0 1 0-3.8h2.5A5.5 5.5 0 0 0 20 6c0-1-.2-1.9-.6-2.7A9 9 0 0 0 12 3Z"></path><path d="M7.5 10.5h.01"></path><path d="M12 7.5h.01"></path><path d="M16 10.5h.01"></path>',
        'refresh-cw' => '<path d="M21 12a9 9 0 1 1-2.64-6.36"></path><path d="M21 3v6h-6"></path>',
        'braces' => '<path d="M8 4c-2 0-3 1-3 3v2c0 1.1-.9 2-2 2 1.1 0 2 .9 2 2v2c0 2 1 3 3 3"></path><path d="M16 4c2 0 3 1 3 3v2c0 1.1.9 2 2 2-1.1 0-2 .9-2 2v2c0 2-1 3-3 3"></path><path d="m14 4-4 16"></path>',
        'puzzle' => '<path d="M12 6.5V5a2 2 0 0 0-2-2H7.5a1.5 1.5 0 1 0 0 3H9a1 1 0 0 1 1 1v2h4v-2a1 1 0 0 1 1-1h1.5a1.5 1.5 0 1 0 0-3H14a2 2 0 0 0-2 2v1.5Z"></path><path d="M10 9H7a2 2 0 0 0-2 2v1.5a1.5 1.5 0 1 0 3 0V11h2"></path><path d="M14 9h3a2 2 0 0 1 2 2v1.5a1.5 1.5 0 1 1-3 0V11h-2"></path><path d="M10 15H8v1.5a1.5 1.5 0 1 1-3 0V18a2 2 0 0 0 2 2h3a2 2 0 0 0 2-2v-3"></path><path d="M14 15h2v1.5a1.5 1.5 0 1 0 3 0V18a2 2 0 0 1-2 2h-3a2 2 0 0 1-2-2v-3"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3"></path><path d="M12 19v3"></path><path d="m4.93 4.93 2.12 2.12"></path><path d="m16.95 16.95 2.12 2.12"></path><path d="M2 12h3"></path><path d="M19 12h3"></path><path d="m4.93 19.07 2.12-2.12"></path><path d="m16.95 7.05 2.12-2.12"></path>',
        'shield' => '<path d="m12 22 7-4V5l-7-3-7 3v13l7 4Z"></path><path d="m9.5 12 1.8 1.8L15 10.1"></path>',
        'house' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 9.5V20h14V9.5"></path><path d="M10 20v-6h4v6"></path>',
        'file-code-2' => '<path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"></path><path d="M14 2v5h5"></path><path d="m10 13-2 2 2 2"></path><path d="m14 13 2 2-2 2"></path>',
        'calendar-days' => '<rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path>',
    ];

    public function has(?string $icon): bool
    {
        return is_string($icon) && isset(self::ICONS[$icon]);
    }

    public function render(?string $icon): ?HtmlString
    {
        if (! $this->has($icon)) {
            return null;
        }

        return new HtmlString(sprintf(
            '<svg data-nav-icon="%1$s" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
            e($icon),
            self::ICONS[$icon]
        ));
    }
}
