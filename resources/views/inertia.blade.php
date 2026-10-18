<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ \LaraSaaS\Facades\LaraSaaS::textDirection() }}"
      class="{{ config('larasaas.theme.default') === 'dark' ? 'dark' : (config('larasaas.theme.default') === 'light' ? 'light' : '') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <style>
        :root { --larasaas-accent: {{ \LaraSaaS\Facades\LaraSaaS::accentColor() }}; }
    </style>

    {{--
        Root fallback view shipped by LaraSaaS. Hosts that already have an
        Inertia root view (Laravel Breeze & friends) may ignore this file —
        their own app.blade.php wins. To force this one:
        \Inertia\Inertia::setRootView('larasaas::inertia');
    --}}
    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
