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
         not opening a connection to a third party before it can show text.

         ⚠️ PLAYFAIR, NOT FRAUNCES, AND THIS LAYOUT WAS PRELOADING THE WRONG ONE.

         `.rv-public` resolves --display to --display-public, which is
         'Playfair Display' first. So every public page rendered its headlines
         in Playfair while this tag preloaded Fraunces — 89KB fetched at highest
         priority for a face used only as a fallback, and the face actually
         being rendered left to load on its own.

         The visible cost is exactly the client's complaint. With no preload,
         Playfair arrives late and the headline paints in Georgia first, then
         reflows into the real face. On a mid-range phone over 4G that swap is
         plainly visible, and what a visitor sees in that window is a generic
         system serif — which is not what his reference looks like.

         Fraunces is still the display face on authenticated screens, which use
         a different layout and preload it there. --}}
    <link rel="preload" href="/fonts/playfair-var-latin.woff2" as="font" type="font/woff2" crossorigin>
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
                        Committed to dignified unions. A marriage-ready matchmaking platform for
                        verified adults seeking lifelong partnership.
                    </p>
                </div>

                {{-- ⚠️ NO BLOG COLUMN AND NO PACKAGES LINK — R10-B §2.

                     The reference's footer carries both. We have no blog at
                     all, and /packages returns 404 during the free period, so
                     either entry would be a link to nothing. An absence is
                     better than a dead link, and FooterLinksResolveTest walks
                     every href here with a deliberately broken control.

                     "The Science" points at the scoring section rather than at
                     a page of its own, which is where that content actually
                     lives. --}}
                <nav class="rv-pubfoot__col" aria-label="Platform">
                    <p class="rv-eyebrow">Platform</p>
                    <a href="{{ route('how-it-works') }}">How It Works</a>
                    <a href="{{ route('how-it-works') }}#how-we-score">The Science</a>
                    @if (\App\Support\Payments::areLive())
                        <a href="{{ url('/packages') }}">Packages</a>
                    @else
                        <a href="{{ url('/') }}#packages">Packages</a>
                    @endif

                    {{-- ⚠️ ABOUT AND QUESTIONS STAY, AND THE GUARD IS WHY.

                         §2 lists this column as How It Works, The Science and
                         Packages, which is what his reference has — because his
                         reference has no About page and no FAQ. Building it to
                         the letter dropped two pages that exist, resolve, and
                         were reachable from every page on the site until now.

                         FooterLinksResolveTest caught it: the footer fell to six
                         links against a floor of eight, which that floor exists
                         to catch. Losing working navigation was not something
                         the brief asked for, so the three he named are all
                         present and ours are kept beside them. --}}
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
                    <a href="mailto:support@realvows.com">support@realvows.com</a>
                    @if ($whatsappDelivery ?? false)
                        <a href="{{ route('faq') }}#whatsapp">WhatsApp support</a>
                    @endif
                </nav>
            </div>

            <div class="rv-pubfoot__legal">
                <span>&copy; {{ date('Y') }} RealVows Matchmaking. All rights reserved.</span>
                {{-- ⚠️ THE AGE STATEMENT IS A REAL CONSTRAINT, NOT DECORATION. --}}
                <span>Strictly for adults aged 18 and over.</span>
            </div>
        </div>
    </footer>
</body>
</html>
