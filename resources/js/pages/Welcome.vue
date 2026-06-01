<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Menu, X } from 'lucide-vue-next'

interface ProductCard { id: number; name: string; price: number; description: string | null; image: string | null }
interface CategoryGroup { name: string; products: ProductCard[] }
interface PageSection { key: string; label: string; content: string | null; position: string }

const props = withDefaults(defineProps<{
    canRegister?: boolean
    banners?: { id: number; title: string; body: string | null; badge_text: string | null; bg_color: string }[]
    promos?: { id: number; title: string; body: string | null; badge_text: string | null }[]
    categories?: CategoryGroup[]
    beforeSections?: PageSection[]
    afterSections?: PageSection[]
}>(), {
    canRegister: false,
    banners: () => [],
    promos: () => [],
    categories: () => [],
    beforeSections: () => [],
    afterSections: () => [],
})

const page    = usePage()
const logoUrl = computed(() => (page.props as any).logoUrl as string | null)

const mobileMenuOpen = ref(false)

// Build nav links from sections (skip 'hero' — that's the top of the page)
// Always inject a "Menu" link pointing at the product grid
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
    const id  = href.replace('#', '')
    const el  = document.getElementById(id)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const colorMap: Record<string, string> = {
    orange: 'bg-orange-500 text-black',
    red:    'bg-red-600 text-white',
    green:  'bg-green-600 text-white',
    blue:   'bg-blue-600 text-white',
    yellow: 'bg-yellow-400 text-black',
}
</script>

<template>
    <Head title="The Boys — Grilled Burgers in Calamba, Laguna" />

    <div class="min-h-screen bg-[#0a0602] text-white font-sans overflow-x-hidden">

        <!-- ── NAV ─────────────────────────────────────────── -->
        <nav class="fixed top-0 inset-x-0 z-50 bg-black/70 backdrop-blur-md border-b border-orange-900/40">
            <div class="flex items-center justify-between px-5 py-3">

                <!-- Logo + brand -->
                <a href="#" @click.prevent="scrollTo('#hero')" class="flex items-center gap-2.5 shrink-0">
                    <div class="h-9 w-9 rounded-full border-2 border-orange-500 overflow-hidden flex items-center justify-center bg-black/40">
                        <img v-if="logoUrl" :src="logoUrl" alt="The Boys" class="h-full w-full object-cover" />
                        <span v-else class="text-[8px] font-black text-orange-400 leading-tight text-center">THE<br>BOYS</span>
                    </div>
                    <span class="text-base font-black tracking-widest text-white hidden sm:block">
                        THE <span class="text-orange-500">BOYS</span>
                    </span>
                </a>

                <!-- Desktop nav links -->
                <ul v-if="navLinks.length" class="hidden md:flex items-center gap-1">
                    <li v-for="link in navLinks" :key="link.href">
                        <a
                            :href="link.href"
                            @click.prevent="scrollTo(link.href)"
                            class="px-3 py-1.5 rounded-full text-xs font-semibold text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
                        >{{ link.label }}</a>
                    </li>
                </ul>

                <!-- Mobile hamburger -->
                <button
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden rounded-lg p-2 text-gray-300 hover:text-white hover:bg-white/10 transition"
                    :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
                >
                    <X v-if="mobileMenuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <!-- Mobile dropdown -->
            <div v-if="mobileMenuOpen && navLinks.length"
                class="md:hidden border-t border-orange-900/30 bg-black/90 px-5 pb-4 pt-2">
                <ul class="flex flex-col gap-1">
                    <li v-for="link in navLinks" :key="link.href">
                        <a
                            :href="link.href"
                            @click.prevent="scrollTo(link.href)"
                            class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
                        >{{ link.label }}</a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- ── ADVERTISEMENT BANNERS ──────────────────────── -->
        <div v-if="banners.length" class="fixed top-[65px] inset-x-0 z-40 flex flex-col gap-0">
            <div
                v-for="banner in banners" :key="banner.id"
                :class="['flex items-center justify-center gap-3 px-4 py-2.5 text-sm font-semibold text-center', colorMap[banner.bg_color] ?? colorMap.orange]"
            >
                <span v-if="banner.badge_text" class="rounded-full bg-black/20 px-2 py-0.5 text-xs font-black uppercase tracking-wide">{{ banner.badge_text }}</span>
                <span>{{ banner.title }}</span>
                <span v-if="banner.body" class="hidden sm:inline opacity-80 font-normal">— {{ banner.body }}</span>
            </div>
        </div>

        <!-- ── BEFORE-PRODUCTS CMS SECTIONS ─────────────────── -->
        <!-- spacer so content clears fixed nav + any banners -->
        <div :style="{ paddingTop: banners.length ? (65 + banners.length * 44) + 'px' : '65px' }"></div>
        <div v-for="s in beforeSections" :key="s.key" :id="s.key" v-html="s.content ?? ''"></div>

        <!-- ── PROMOTIONS ──────────────────────────────────── -->
        <section v-if="promos.length" class="py-12 px-6 border-t border-orange-900/30">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-8">
                    <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-2">Limited Time</p>
                    <h2 class="text-3xl font-black">Today's Specials</h2>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="promo in promos" :key="promo.id"
                        class="relative rounded-2xl border border-orange-700/60 bg-orange-950/30 p-5 hover:border-orange-500 transition-colors"
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

<!-- star_product is now served from beforeSections above -->

        <!-- ── MENU GRID ────────────────────────────────────── -->
        <section id="menu" class="py-24 px-6 bg-gradient-to-b from-transparent to-black/40">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-14">
                    <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-3">Hot Off The Grill</p>
                    <h2 class="text-4xl font-black">Our Menu</h2>
                </div>

                <!-- ── Dynamic product cards from database ── -->
                <template v-if="categories.length">
                    <div v-for="cat in categories" :key="cat.name" class="mb-12 last:mb-0">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="text-orange-500 text-xs font-bold uppercase tracking-widest">{{ cat.name }}</span>
                            <div class="flex-1 h-px bg-orange-900/40"></div>
                        </div>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                            <div
                                v-for="item in cat.products" :key="item.id"
                                class="group relative flex flex-col rounded-2xl border border-white/5 overflow-hidden hover:border-orange-700/60 transition-all duration-300"
                                style="background: rgba(255,255,255,0.02)"
                            >
                                                <!-- Product image -->
                                <div class="relative h-44 w-full overflow-hidden bg-gradient-to-br from-orange-950 to-red-950 shrink-0"
                                    :id="item === cat.products[0] && cat === categories[0] ? 'burger' : undefined">
                                    <img
                                        v-if="item.image"
                                        :src="item.image"
                                        :alt="item.name"
                                        class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    />
                                    <div v-else class="h-full w-full flex items-center justify-center">
                                        <span class="text-6xl select-none opacity-60">🍔</span>
                                    </div>
                                    <!-- price badge overlay -->
                                    <div class="absolute bottom-3 right-3 bg-orange-500 text-black font-black text-sm px-3 py-1 rounded-xl shadow-lg">
                                        ₱{{ item.price.toFixed(2) }}
                                    </div>
                                </div>
                                <!-- Card body -->
                                <div class="p-4 flex-1">
                                    <h3 class="font-bold text-white text-sm leading-snug mb-1.5">{{ item.name }}</h3>
                                    <p v-if="item.description" class="text-xs text-gray-400 leading-relaxed line-clamp-2">{{ item.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ── Fallback if no products in DB yet ── -->
                <template v-else>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <div v-for="item in [
                            { emoji: '🍔', name: 'The Boys Classic Smash', desc: 'Double smash patty, melted cheese, caramelized onions, signature The Boys sauce.' },
                            { emoji: '🌶️', name: 'Spicy Calamba Burger', desc: 'Flame-grilled patty with jalapeños, sriracha aioli, and pepper jack cheese.' },
                            { emoji: '🐓', name: 'Grilled Chicken Burger', desc: 'Juicy marinated chicken thigh fillet, fresh lettuce, tomato, and garlic mayo.' },
                            { emoji: '🥩', name: 'BBQ Bacon Smash', desc: 'Smash patty topped with crispy bacon strips, BBQ glaze, and cheddar cheese.' },
                        ]" :key="item.name"
                            class="flex gap-5 items-start rounded-2xl border border-white/5 p-5"
                            style="background: rgba(255,255,255,0.02)">
                            <div class="shrink-0 w-16 h-16 rounded-xl bg-gradient-to-br from-orange-900 to-red-950 border border-orange-800/40 flex items-center justify-center text-3xl">
                                {{ item.emoji }}
                            </div>
                            <div>
                                <h3 class="font-bold text-white text-sm mb-1">{{ item.name }}</h3>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ item.desc }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- ── AFTER-PRODUCTS CMS SECTIONS ──────────────────── -->
        <div v-for="s in afterSections" :key="s.key" :id="s.key" v-html="s.content ?? ''"></div>

        <!-- ── FOOTER ─────────────────────────────────────── -->
        <footer class="border-t border-orange-900/30 px-6 py-8 text-center">
            <div class="flex items-center justify-center gap-3 mb-3">
                <div class="h-8 w-8 rounded-full border-2 border-orange-600 flex items-center justify-center">
                    <span class="text-[8px] font-black text-orange-500 leading-tight text-center">THE<br>BOYS</span>
                </div>
                <span class="font-black tracking-widest text-white">THE <span class="text-orange-500">BOYS</span></span>
            </div>
            <p class="text-xs text-gray-600">Grilled Burger Pop-Up · Calamba, Laguna</p>
        </footer>

    </div>
</template>

<style scoped>
.bg-white\/3 { background-color: rgba(255,255,255,0.03); }
</style>

<style>
html { scroll-behavior: smooth; }
/* offset scrollIntoView so the fixed nav doesn't cover the target */
[id] { scroll-margin-top: 72px; }
</style>
