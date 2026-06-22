<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · {{ config('seo.site.name', config('app.name')) }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a; color: #e2e8f0; padding: 24px; text-align: center;
        }
        .error { max-width: 30rem; }
        .error__code { font-size: 4rem; font-weight: 700; line-height: 1; margin: 0 0 .5rem; color: #38bdf8; }
        .error__title { font-size: 1.5rem; font-weight: 600; margin: 0 0 .75rem; }
        .error__message { font-size: 1rem; line-height: 1.6; margin: 0 0 1.75rem; color: #94a3b8; }
        .error__home {
            display: inline-block; padding: .65rem 1.4rem; border-radius: .5rem;
            background: #38bdf8; color: #0f172a; text-decoration: none; font-weight: 600;
        }
        .error__home:hover { background: #7dd3fc; }
    </style>
</head>
<body>
    <main class="error">
        <p class="error__code">@yield('code')</p>
        <h1 class="error__title">@yield('title')</h1>
        <p class="error__message">@yield('message')</p>
        <a class="error__home" href="{{ url('/') }}">@yield('home', 'На главную')</a>
    </main>
</body>
</html>
