<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Menu, X } from 'lucide-vue-next'

// GSAP is loaded via CDN in app.blade.php — reference the global instead of npm import
declare const gsap: any

interface ProductCard {
    id: number
    name: string
    price: number
    description: string | null
    image: string | null
}
interface CategoryGroup { name: string; products: ProductCard[] }
interface PageSection   { key: string; label: string; content: string | null; position: string }

const props = withDefaults(defineProps<{
    canRegister?: boolean
    banners?:       { id: number; title: string; body: string | null; badge_text: string | null; bg_color: string }[]
    promos?:        { id: number; title: string; body: string | null; badge_text: string | null }[]
    categories?:    CategoryGroup[]
    beforeSections?: PageSection[]
    afterSections?:  PageSection[]
}>(), {
    canRegister: false,
    banners:        () => [],
    promos:         () => [],
    categories:     () => [],
    beforeSections: () => [],
    afterSections:  () => [],
})

const page    = usePage()
const logoUrl = computed(() => (page.props as any).logoUrl as string | null)

const mobileMenuOpen = ref(false)

// Nav links built from CMS sections (hero skipped — it's hardcoded) + always "Menu"
const navLinks = computed(() => {
    const links: { label: string; href: string }[] = []
    for (const s of props.beforeSections) {
        if (s.key !== 'hero') links.push({ label: s.label, href: `#${s.key}` })
    }
    links.push({ label: 'Menu', href: '#menu' })
    for (const s of props.afterSections) {
        links.push({ label: s.label, href: `#${s.key}` })
    }
    return links
})

function scrollTo(href: string) {
    mobileMenuOpen.value = false
    const el = document.getElementById(href.replace('#', ''))
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const colorMap: Record<string, string> = {
    orange: 'bg-orange-500 text-black',
    red:    'bg-red-600 text-white',
    green:  'bg-green-600 text-white',
    blue:   'bg-blue-600 text-white',
    yellow: 'bg-yellow-400 text-black',
}

/* ── Cinematic GSAP intro ────────────────────────────────────────────────────── */
onMounted(async () => {
    await nextTick()

    // Guard: only run once per hard-load (not on every Inertia back-navigation)
    if ((window as any).__cinematic_v1) return
    ;(window as any).__cinematic_v1 = true

    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })

    // Hero burger image drops in
    tl.from('#hero-burger', { y: 120, opacity: 0, duration: 1.2 })

    // Title spans cascade up
    .from('.hero-title span', { y: 60, opacity: 0, stagger: 0.15, duration: 1 }, '-=0.6')

    // Nav slides down
    .from('nav', { y: -80, opacity: 0, duration: 0.8 }, '-=0.8')

    // Menu cards stagger
    .from('.menu-card', { y: 40, opacity: 0, stagger: 0.05, duration: 0.6 }, '-=0.3')

    // Promo cards pop in
    .from('.promo-card', { scale: 0.95, opacity: 0, stagger: 0.1, duration: 0.6 }, '-=0.4')

    // Continuous burger float
    gsap.to('#hero-burger', { y: -10, repeat: -1, yoyo: true, duration: 2, ease: 'sine.inOut' })

    // Background heat pulse
    gsap.to('.heat-glow', { scale: 1.2, opacity: 0.4, duration: 2.5, repeat: -1, yoyo: true, ease: 'sine.inOut' })
})
</script>

<template>
    <Head title="The Boys — Grilled Burgers in Calamba, Laguna" />

    <div class="min-h-screen bg-[#0a0602] text-white overflow-x-hidden">

        <!-- ── NAV ───────────────────────────────────────────────────────────── -->
        <nav class="fixed top-0 inset-x-0 z-50 bg-black/70 backdrop-blur-md border-b border-orange-900/40">
            <div class="flex items-center justify-between px-5 py-3">

                <!-- Logo -->
                <a href="#" @click.prevent="scrollTo('#hero')" class="flex items-center gap-2.5 shrink-0">
                    <div class="h-9 w-9 rounded-full border-2 border-orange-500 overflow-hidden flex items-center justify-center bg-black/40">
                        <img v-if="logoUrl" :src="logoUrl" alt="The Boys" class="h-full w-full object-cover" />
                        <span v-else class="text-[8px] font-black text-orange-500 leading-tight text-center">THE<br>BOYS</span>
                    </div>
                    <span class="font-black tracking-widest hidden sm:block">
                        THE <span class="text-orange-500">BOYS</span>
                    </span>
                </a>

                <!-- Desktop links -->
                <ul v-if="navLinks.length" class="hidden md:flex gap-1">
                    <li v-for="link in navLinks" :key="link.href">
                        <a :href="link.href" @click.prevent="scrollTo(link.href)"
                            class="px-3 py-1.5 rounded-full text-xs font-semibold text-gray-300 hover:text-white hover:bg-white/10 transition-colors">
                            {{ link.label }}
                        </a>
                    </li>
                </ul>

                <!-- Hamburger -->
                <button @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden rounded-lg p-2 text-gray-300 hover:text-white hover:bg-white/10 transition">
                    <X v-if="mobileMenuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <!-- Mobile dropdown -->
            <div v-if="mobileMenuOpen && navLinks.length"
                class="md:hidden border-t border-orange-900/30 bg-black/90 px-5 pb-4 pt-2">
                <ul class="flex flex-col gap-1">
                    <li v-for="link in navLinks" :key="link.href">
                        <a :href="link.href" @click.prevent="scrollTo(link.href)"
                            class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-300 hover:text-white hover:bg-white/10 transition-colors">
                            {{ link.label }}
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- ── AD BANNERS ─────────────────────────────────────────────────────── -->
        <div v-if="banners.length" class="fixed top-[65px] inset-x-0 z-40 flex flex-col">
            <div
                v-for="banner in banners" :key="banner.id"
                :class="['flex items-center justify-center gap-3 px-4 py-2.5 text-sm font-semibold text-center', colorMap[banner.bg_color] ?? colorMap.orange]"
            >
                <span v-if="banner.badge_text" class="rounded-full bg-black/20 px-2 py-0.5 text-xs font-black uppercase tracking-wide">{{ banner.badge_text }}</span>
                <span>{{ banner.title }}</span>
                <span v-if="banner.body" class="hidden sm:inline opacity-80 font-normal">— {{ banner.body }}</span>
            </div>
        </div>

        <!-- ── HERO ───────────────────────────────────────────────────────────── -->
        <section id="hero" class="relative min-h-screen flex items-center justify-center"
            :style="{ paddingTop: banners.length ? (65 + banners.length * 44) + 'px' : '65px' }">

            <!-- Background image -->
            <div class="absolute inset-0 bg-cover bg-center opacity-20"
                style="background-image:url('https://www.theboys.baconologies.com/storage/media/2e0d54bd-a94c-469f-bc55-724e2a1285df.png')"></div>

            <!-- Heat glow -->
            <div class="heat-glow absolute w-[600px] h-[600px] bg-orange-500/20 blur-[120px] rounded-full pointer-events-none"></div>

            <!-- Content -->
            <div class="relative z-10 text-center px-6">
                <img
                    id="hero-burger"
                    src="https://www.theboys.baconologies.com/storage/media/2e0d54bd-a94c-469f-bc55-724e2a1285df.png"
                    alt="The Boys Smash Burger"
                    class="w-[320px] md:w-[480px] mx-auto drop-shadow-[0_40px_80px_rgba(249,115,22,0.5)]"
                />
                <h1 class="hero-title mt-10 text-5xl md:text-7xl font-black uppercase leading-none">
                    <span class="block text-white">THE BOYS</span>
                    <span class="block text-orange-500">SMASH BURGER</span>
                </h1>
            </div>
        </section>

        <!-- ── BEFORE-PRODUCTS CMS SECTIONS (skip 'hero' — hardcoded above) ─── -->
        <template v-for="s in beforeSections" :key="s.key">
            <div v-if="s.key !== 'hero'" :id="s.key" v-html="s.content ?? ''"></div>
        </template>

        <!-- ── PROMOTIONS ─────────────────────────────────────────────────────── -->
        <section v-if="promos.length" class="py-12 px-6 border-t border-orange-900/30">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-8">
                    <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-2">Limited Time</p>
                    <h2 class="text-3xl font-black">Today's Specials</h2>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="promo in promos" :key="promo.id"
                        class="promo-card relative rounded-2xl border border-orange-700/60 bg-orange-950/30 p-5 hover:border-orange-500 transition-colors"
                    >
                        <div v-if="promo.badge_text" class="absolute top-3 right-3 bg-orange-500 text-black text-[10px] font-black px-2 py-0.5 rounded-full uppercase">
                            {{ promo.badge_text }}
                        </div>
                        <p class="text-orange-400 text-xs font-bold uppercase tracking-widest mb-1">Promo</p>
                        <h3 class="font-bold text-white text-base mb-2 pr-12">{{ promo.title }}</h3>
                        <p v-if="promo.body" class="text-sm text-gray-400 leading-relaxed">{{ promo.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── MENU ──────────────────────────────────────────────────────────── -->
        <section id="menu" class="py-24 px-6">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-3">Hot Off The Grill</p>
                    <h2 class="text-4xl font-black">Our Menu</h2>
                </div>

                <div v-for="cat in categories" :key="cat.name" class="mb-12">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="text-orange-500 text-xs font-bold uppercase tracking-widest">{{ cat.name }}</span>
                        <div class="flex-1 h-px bg-orange-900/40"></div>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div
                            v-for="item in cat.products" :key="item.id"
                            class="menu-card group relative flex flex-col rounded-2xl border border-white/10 bg-white/5 overflow-hidden hover:border-orange-700/60 transition-all duration-300"
                        >
                            <div class="h-44 bg-black overflow-hidden">
                                <img v-if="item.image" :src="item.image" :alt="item.name"
                                    class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500" />
                                <div v-else class="h-full w-full flex items-center justify-center">
                                    <span class="text-5xl opacity-40">🍔</span>
                                </div>
                            </div>
                            <div class="p-4 flex-1">
                                <h3 class="font-bold text-white text-sm">{{ item.name }}</h3>
                                <p v-if="item.description" class="text-xs text-gray-400 mt-1 line-clamp-2">{{ item.description }}</p>
                                <div class="text-orange-500 font-black mt-3">₱{{ item.price.toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fallback if no products seeded yet -->
                <div v-if="!categories.length" class="text-center text-gray-500 text-sm py-12">
                    Menu coming soon — check back shortly!
                </div>
            </div>
        </section>

        <!-- ── AFTER-PRODUCTS CMS SECTIONS ───────────────────────────────────── -->
        <div v-for="s in afterSections" :key="s.key" :id="s.key" v-html="s.content ?? ''"></div>

        <!-- ── FOOTER ─────────────────────────────────────────────────────────── -->
        <footer class="border-t border-orange-900/30 px-6 py-8 text-center">
            <div class="flex items-center justify-center gap-3 mb-3">
                <div class="h-8 w-8 rounded-full border-2 border-orange-600 overflow-hidden flex items-center justify-center bg-black/40">
                    <img v-if="logoUrl" :src="logoUrl" alt="The Boys" class="h-full w-full object-cover" />
                    <span v-else class="text-[7px] font-black text-orange-500 leading-tight text-center">THE<br>BOYS</span>
                </div>
                <span class="font-black tracking-widest text-white">THE <span class="text-orange-500">BOYS</span></span>
            </div>
            <p class="text-xs text-gray-600">Grilled Burger Pop-Up · Calamba, Laguna</p>
        </footer>

    </div>
</template>

<style>
html { scroll-behavior: smooth; }
[id] { scroll-margin-top: 72px; }
</style>
