<?php

return [
    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
        'directives' => [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https:",
            "script-src 'self' 'unsafe-inline' https:",
            "connect-src 'self' https:",
            "font-src 'self' data: https:",
        ],
    ],
    'html' => [
        'allowed_tags' => [
            'p', 'a', 'ul', 'ol', 'li', 'strong', 'em', 'blockquote', 'code',
            'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'table', 'thead',
            'tbody', 'tr', 'th', 'td', 'hr', 'br', 'span', 'div',
        ],
    ],
    'session' => [
        'force_secure_cookie' => (bool) env('SESSION_SECURE_COOKIE', true),
        'same_site' => env('SESSION_SAME_SITE', 'lax'),
    ],
];
