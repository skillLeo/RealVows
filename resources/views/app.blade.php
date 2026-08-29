<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#480027">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RealVows">

    {{-- Read by the autosave composable, which posts outside Inertia so a
         save never re-renders the page under the member's cursor. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- The one-click sign-in is open on the review build, so it must not
         be indexed. Removed automatically when it goes live. --}}
    @if (\App\Support\ReviewBuild::active())
        <meta name="robots" content="noindex, nofollow, noarchive">
    @endif

    <title inertia>{{ config('app.name', 'RealVows') }}</title>

    <link rel="manifest" href="/manifest.webmanifest">

    {{-- ⚠️ iOS ignores the web manifest's icons entirely and uses this. Without
         it the home-screen icon is a screenshot of the page, which is the one
         place the seal has to appear and cannot be checked from a desktop. --}}
    <link rel="apple-touch-icon" href="/icons/rv-apple-touch.png">

    {{-- Self-hosted, so there is no third-party DNS lookup, handshake and TLS
         negotiation standing between a phone on 4G and readable text. The two
         faces that appear above the fold are preloaded; the rest arrive with
         the stylesheet. --}}
    <link rel="preload" href="/fonts/fraunces-var-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/inter-400-latin.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
