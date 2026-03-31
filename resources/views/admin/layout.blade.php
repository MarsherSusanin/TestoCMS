<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('admin.partials.layout-head')
</head>
<body>
<div class="shell">
    @include('admin.partials.sidebar')

    <main class="content">
        @include('admin.partials.flash')
        @yield('content')
    </main>
</div>
@include('admin.partials.shell-scripts')
</body>
</html>
