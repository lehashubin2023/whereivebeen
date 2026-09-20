<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @php($meta = $page['props']['meta'] ?? [])

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <style>
            html {
                background-color: hsl(220 14% 6%);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <meta name="description" content="{{ $meta['description'] ?? '' }}">

        @if ($meta['noindex'] ?? false)
            <meta name="robots" content="noindex, nofollow">
        @else
            <link rel="canonical" href="{{ $meta['canonical'] ?? url()->current() }}">
        @endif

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $meta['title'] ?? config('app.name') }}">
        <meta property="og:description" content="{{ $meta['description'] ?? '' }}">
        <meta property="og:url" content="{{ $meta['canonical'] ?? url()->current() }}">
        <meta property="og:image" content="{{ $meta['image'] ?? '' }}">
        <meta property="og:locale" content="{{ $ogLocale }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $meta['title'] ?? config('app.name') }}">
        <meta name="twitter:description" content="{{ $meta['description'] ?? '' }}">
        <meta name="twitter:image" content="{{ $meta['image'] ?? '' }}">

        @if (! empty($meta['schema']))
            <script type="application/ld+json">{!! json_encode($meta['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif

        @fonts

        <script>
            window.__locale = @json($locale);
            window.__translations = @json((object) $translations);
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $meta['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
