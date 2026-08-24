<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4A1220">

    <title>@yield('title', 'RealVows') · RealVows</title>
    <meta name="description" content="@yield('description', 'Find your true soul mate. Free to join, every member verified, introductions brought to your email and WhatsApp.')">

    {{-- The one-click sign-in is open on the review build, so it must
         not be indexed. Removed automatically when it goes live. --}}
    @if (config('realvows.is_demo'))
        <meta name="robots" content="noindex, nofollow, noarchive">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="manifest" href="/manifest.webmanifest">

    {{-- ⚠️ iOS ignores the web manifest's icons entirely and uses this. Without
         it the home-screen icon is a screenshot of the page, which is the one
         place the seal has to appear and cannot be checked from a desktop. --}}
    <link rel="apple-touch-icon" href="/icons/rv-apple-touch.png">

    {{-- Self-hosted. The home page is the one that has to be fast on a
         mid-range phone over 4G, so it is the page that benefits most from
         not opening a connection to a third party before it can show text. --}}
    <link rel="preload" href="/fonts/fraunces-var-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/inter-400-latin.woff2" as="font" type="font/woff2" crossorigin>

    {{-- Blade pages load only the stylesheet and the small vanilla motion
         script. No Vue, no Inertia, no hydration cost. --}}
    @vite(['resources/css/app.css', 'resources/js/public.js'])
</head>
<body class="rv-public">
    <a href="#rv-main" class="rv-skip">Skip to content</a>

    @if (config('realvows.is_demo'))
        <div class="rv-demobar" role="status">
            {{-- ⚠️ THIS SENTENCE WAS BROKEN, AND IT IS THE FIRST THING ANYONE
                 READS. It ended "the sign-in buttons are open — not the live
                 platform", and "not the live platform" modified nothing: there
                 was no noun for it to attach to. The sentence promised a
                 contrast and never supplied one.

                 The client's whole complaint is that a serious reader will
                 decide the site was rigged up in a few days. The first line
                 they met was ungrammatical. --}}
            <strong>Review build</strong>
            <span>This is not the live platform. Every member shown here is invented, and the sign-in buttons are open to anyone.</span>
        </div>
    @endif

    @include('public.partials.nav')

    <main id="rv-main">
        @yield('content')
    </main>

    {{--
        ⚠️ THE REFERENCE'S FOOTER SHAPE: brand blurb and logo on the left, link
        columns beside it, a thin rule, then a legal bar.

        ⚠️ THREE COLUMNS, NOT FOUR. The reference's fourth is a blog, and there
        is no blog route in this application. A navigation link to a page we do
        not have is worse than no link, and FooterLinksResolveTest fails on any
        route here that does not answer.
    --}}
    <footer class="rv-pubfoot">
        <div class="rv-shell rv-shell--measure">
            <div class="rv-pubfoot__grid">
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-logo__word rv-pubfoot__word">RealVows</span>
                    <p class="rv-small rv-pubfoot__blurb">
                        A small matchmaking platform for people who have decided what they are
                        looking for. Free to join, and everyone here has been checked.
                    </p>
                </div>

                <nav class="rv-pubfoot__col" aria-label="About RealVows">
                    <p class="rv-eyebrow">RealVows</p>
                    <a href="{{ route('how-it-works') }}">How it works</a>
                    <a href="{{ route('about') }}">About</a>
                    <a href="{{ route('faq') }}">Questions</a>
                </nav>

                <nav class="rv-pubfoot__col" aria-label="Legal">
                    <p class="rv-eyebrow">Legal</p>
                    <a href="{{ route('legal.terms') }}">Terms of Service</a>
                    <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
                    <a href="{{ route('legal.guidelines') }}">Community Guidelines</a>
                    <a href="{{ route('legal.refunds') }}">Refunds</a>
                </nav>

                <nav class="rv-pubfoot__col" aria-label="Getting in touch">
                    <p class="rv-eyebrow">Contact</p>
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}">Create an account</a>
                </nav>
            </div>

            <div class="rv-pubfoot__legal">
                <span>&copy; {{ date('Y') }} RealVows. All rights reserved.</span>
                {{-- ⚠️ THE AGE STATEMENT IS A REAL CONSTRAINT, NOT DECORATION. --}}
                <span>Strictly for adults aged 18 and over.</span>
            </div>
        </div>
    </footer>
</body>
</html>
