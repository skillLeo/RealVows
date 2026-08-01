<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4A1220">

    <title>@yield('title', 'RealVows') · RealVows</title>
    <meta name="description" content="@yield('description', 'Find someone ready for marriage. Free to join, every profile verified by a real person, matches delivered to your email and WhatsApp.')">

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="manifest" href="/manifest.webmanifest">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Blade pages load only the stylesheet and the small vanilla motion
         script. No Vue, no Inertia, no hydration cost. --}}
    @vite(['resources/css/app.css', 'resources/js/public.js'])
</head>
<body>
    <a href="#rv-main" class="rv-skip">Skip to content</a>

    @if (config('realvows.is_demo'))
        <div class="rv-demobar" role="status">
            <strong>Review build</strong>
            <span>Everyone here is invented and the sign-in buttons are open — not the live platform.</span>
        </div>
    @endif

    <header class="rv-panel">
        <div class="rv-shell rv-pubhead">
            <a href="{{ route('home') }}" class="rv-logo" aria-label="RealVows home">
                <span class="rv-logo__mark" aria-hidden="true">RV</span>
                <span class="rv-logo__word">RealVows</span>
            </a>

            <nav class="rv-pubhead__nav" aria-label="Account">
                <a href="{{ route('login') }}" class="rv-pubhead__link">Log in</a>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--outline-brass">Create free account</a>
            </nav>
        </div>
    </header>

    <main id="rv-main">
        @yield('content')
    </main>

    <footer class="rv-panel rv-pubfoot">
        <div class="rv-shell rv-pubfoot__inner">
            <span class="rv-logo__word rv-pubfoot__word">RealVows</span>

            <nav class="rv-pubfoot__nav" aria-label="Legal">
                <a href="{{ route('legal.terms') }}">Terms of Service</a>
                <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
                <a href="{{ route('legal.guidelines') }}">Community Guidelines</a>
            </nav>
        </div>
    </footer>
</body>
</html>
