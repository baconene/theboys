<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script>
gsap.registerPlugin(ScrollTrigger);

/* ─── Run after each Inertia navigation (fires after Vue renders) ───────────── */
function initWelcomeAnimations() {
    if (window.location.pathname !== '/') return;

    // Kill any stale ScrollTriggers from the previous render
    ScrollTrigger.getAll().forEach(t => t.kill());

    /* ── Nav entrance ─────────────────────────────────────────────────────── */
    gsap.from('nav', {
        y: -40, opacity: 0, duration: 0.55, ease: 'power2.out'
    });

    /* ── Hero text stagger ────────────────────────────────────────────────── */
    const hero = document.getElementById('hero');
    if (hero) {
        const targets = hero.querySelectorAll('h1 span, h1, p, .inline-flex, .animate-pulse');
        gsap.from(targets, {
            y: 35, opacity: 0, duration: 0.8, stagger: 0.12,
            ease: 'power3.out', delay: 0.2, clearProps: 'all'
        });
    }

    /* ── CMS section wrappers: fade-up on scroll ──────────────────────────── */
    ['hero','star_product','concept','tagline'].forEach(id => {
        const el = document.getElementById(id);
        if (!el || id === 'hero') return; // hero already animated above
        ScrollTrigger.create({
            trigger: el, start: 'top 82%', once: true,
            onEnter: () => gsap.from(el, {
                y: 45, opacity: 0, duration: 0.75, ease: 'power2.out', clearProps: 'all'
            })
        });
    });

    /* ── Product grid cards: stagger fade-up ─────────────────────────────── */
    const menu = document.getElementById('menu');
    if (menu) {
        const cards = menu.querySelectorAll('.group');
        ScrollTrigger.create({
            trigger: menu, start: 'top 80%', once: true,
            onEnter: () => gsap.from(cards, {
                y: 30, opacity: 0, duration: 0.5, stagger: 0.07,
                ease: 'power2.out', clearProps: 'all'
            })
        });
    }

    /* ── Floating burger animation on first product image ────────────────── */
    const burger = document.getElementById('burger');
    if (burger) {
        // Drop in
        gsap.from(burger, { y: 60, opacity: 0, duration: 1.1, ease: 'power3.out' });
        // Continuous float
        gsap.to(burger, { y: -12, repeat: -1, yoyo: true, duration: 2.2, ease: 'sine.inOut', delay: 1.2 });
    }

    /* ── Ad banner strip entrance ─────────────────────────────────────────── */
    const bannerStrip = document.querySelector('[class*="fixed top-\\[65px\\]"]');
    if (bannerStrip) {
        gsap.from(bannerStrip, { y: -20, opacity: 0, duration: 0.4, ease: 'power2.out' });
    }
}

/* ─── Inertia fires this after Vue finishes rendering each page ─────────────── */
document.addEventListener('inertia:finish', initWelcomeAnimations);

/* ─── Initial hard load: wait one RAF so Vue has mounted ────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => requestAnimationFrame(initWelcomeAnimations));
});
</script>
        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
