<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { dashboard, login } from '@/routes'

interface ProductCard { id: number; name: string; price: number; description: string | null; image: string | null }
interface CategoryGroup { name: string; products: ProductCard[] }

const props = withDefaults(defineProps<{
    canRegister?: boolean
    banners?: { id: number; title: string; body: string | null; badge_text: string | null; bg_color: string }[]
    promos?: { id: number; title: string; body: string | null; badge_text: string | null }[]
    categories?: CategoryGroup[]
}>(), { canRegister: false, banners: () => [], promos: () => [], categories: () => [] })

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
        <nav class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-6 py-4 bg-black/60 backdrop-blur-sm border-b border-orange-900/40">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full border-2 border-orange-500 flex items-center justify-center">
                    <span class="text-[9px] font-black text-orange-400 leading-tight text-center">THE<br>BOYS</span>
                </div>
                <span class="text-lg font-black tracking-widest text-white">THE <span class="text-orange-500">BOYS</span></span>
            </div>
            <div class="flex items-center gap-3">
                <Link v-if="$page.props.auth?.user" :href="dashboard()"
                    class="rounded-full border border-orange-500 px-5 py-1.5 text-sm font-semibold text-orange-400 hover:bg-orange-500 hover:text-black transition-colors">
                    Dashboard
                </Link>
                <Link v-else :href="login()"
                    class="rounded-full bg-orange-500 px-5 py-1.5 text-sm font-bold text-black hover:bg-orange-400 transition-colors">
                    Staff Login
                </Link>
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

        <!-- ── HERO ───────────────────────────────────────── -->
        <section
            :class="['relative min-h-screen flex items-center justify-center pb-16 px-6 overflow-hidden', banners.length ? 'pt-36' : 'pt-20']"
        >
            <!-- fire glow bg -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full opacity-30"
                    style="background: radial-gradient(ellipse, #ff6600 0%, #c2410c 30%, transparent 70%); filter: blur(60px);"></div>
                <div class="absolute top-1/3 left-1/4 w-80 h-80 rounded-full opacity-10"
                    style="background: radial-gradient(circle, #fbbf24 0%, transparent 70%); filter: blur(40px);"></div>
            </div>

            <div class="relative z-10 max-w-5xl mx-auto text-center">
                <!-- badge -->
                <div class="inline-flex items-center gap-2 rounded-full border border-orange-700 bg-orange-950/60 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-orange-400 mb-8">
                    <span class="h-1.5 w-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                    Now Open · Calamba, Laguna
                </div>

                <!-- headline -->
                <h1 class="text-6xl sm:text-7xl md:text-8xl font-black leading-none tracking-tight mb-6">
                    <span class="block text-white">THE</span>
                    <span class="block" style="background: linear-gradient(90deg, #f97316, #fbbf24, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        BOYS
                    </span>
                    <span class="block text-white text-4xl sm:text-5xl font-light tracking-widest mt-2">GRILLED BURGERS</span>
                </h1>

                <p class="max-w-xl mx-auto text-lg text-gray-300 leading-relaxed mb-10">
                    Juicy, flame-grilled burgers made fresh every day.<br>
                    <span class="text-orange-400 font-semibold">Pop-up store in Calamba, Laguna — find us and taste the difference.</span>
                </p>

                <!-- burger visual -->
                <div class="flex justify-center items-end gap-1 mt-2 mb-12 select-none" aria-hidden="true">
                    <div class="w-3 h-16 rounded-full bg-gradient-to-t from-orange-900 to-orange-600 opacity-80"></div>
                    <div class="w-4 h-24 rounded-full bg-gradient-to-t from-orange-800 to-yellow-600 shadow-lg shadow-orange-600/40"></div>
                    <div class="w-5 h-32 rounded-full bg-gradient-to-t from-red-900 to-orange-500 shadow-xl shadow-orange-500/50"></div>
                    <div class="w-7 h-40 rounded-full bg-gradient-to-t from-red-800 to-yellow-500 shadow-2xl shadow-yellow-500/40"></div>
                    <div class="w-8 h-44 rounded-full bg-gradient-to-t from-orange-900 to-orange-400 shadow-2xl shadow-orange-400/60 scale-105"></div>
                    <div class="w-7 h-40 rounded-full bg-gradient-to-t from-red-800 to-yellow-500 shadow-2xl shadow-yellow-500/40"></div>
                    <div class="w-5 h-32 rounded-full bg-gradient-to-t from-red-900 to-orange-500 shadow-xl shadow-orange-500/50"></div>
                    <div class="w-4 h-24 rounded-full bg-gradient-to-t from-orange-800 to-yellow-600 shadow-lg shadow-orange-600/40"></div>
                    <div class="w-3 h-16 rounded-full bg-gradient-to-t from-orange-900 to-orange-600 opacity-80"></div>
                </div>

                <p class="text-xs uppercase tracking-widest text-gray-500">Scroll to discover the full menu</p>
            </div>
        </section>

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

        <!-- ── STAR PRODUCT ────────────────────────────────── -->
        <section class="py-24 px-6 border-t border-orange-900/30">
            <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-16 items-center">
                <!-- visual -->
                <div class="relative flex items-center justify-center">
                    <div class="absolute w-72 h-72 rounded-full opacity-40"
                        style="background: radial-gradient(circle, #f97316, transparent 70%); filter: blur(50px);"></div>
                    <div class="relative z-10 w-56 h-56 rounded-full bg-gradient-to-br from-yellow-800 via-orange-700 to-red-900 border-4 border-orange-500 flex items-center justify-center shadow-2xl shadow-orange-600/50">
                        <span class="text-8xl select-none">🍔</span>
                    </div>
                    <div class="absolute top-4 right-8 bg-orange-500 text-black text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider rotate-12 shadow-lg">
                        Best Seller
                    </div>
                </div>

                <!-- text -->
                <div>
                    <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-3">Signature Burger</p>
                    <h2 class="text-4xl sm:text-5xl font-black mb-5 leading-tight">
                        The Boys<br>
                        <span class="text-orange-400">Classic Smash</span>
                    </h2>
                    <p class="text-gray-300 leading-relaxed mb-6">
                        Double smash patty, melted cheese, caramelized onions, special The Boys sauce — all grilled fresh on the flat-top. Served with <strong class="text-white">crispy fries</strong> or <strong class="text-white">steamed rice</strong> for a complete meal.
                    </p>
                    <div class="flex flex-wrap gap-3 mb-8">
                        <span class="rounded-full bg-orange-950 border border-orange-800 px-4 py-1.5 text-sm text-orange-300">🔥 Flame-Grilled</span>
                        <span class="rounded-full bg-orange-950 border border-orange-800 px-4 py-1.5 text-sm text-orange-300">🧀 Double Cheese</span>
                        <span class="rounded-full bg-orange-950 border border-orange-800 px-4 py-1.5 text-sm text-orange-300">🍟 Fries or Rice</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── MENU GRID ────────────────────────────────────── -->
        <section class="py-24 px-6 bg-gradient-to-b from-transparent to-black/40">
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
                                <div class="relative h-44 w-full overflow-hidden bg-gradient-to-br from-orange-950 to-red-950 shrink-0">
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

        <!-- ── POP-UP STORE CONCEPT ────────────────────────── -->
        <section class="py-24 px-6 border-t border-orange-900/30">
            <div class="max-w-5xl mx-auto text-center mb-16">
                <p class="text-orange-500 text-xs font-bold uppercase tracking-widest mb-3">Our Story</p>
                <h2 class="text-4xl sm:text-5xl font-black mb-6 leading-tight">
                    Born in<br>
                    <span class="text-orange-400">Calamba, Laguna</span>
                </h2>
                <p class="max-w-2xl mx-auto text-gray-300 leading-relaxed text-lg">
                    The Boys is a <strong class="text-white">pop-up burger grill</strong> that started right here in
                    <strong class="text-white">Calamba, Laguna</strong>.
                    No frills, no fuss — just <strong class="text-orange-400">real fire, real beef, real flavor</strong>, served fresh every day by the boys who love to grill.
                </p>
            </div>

            <div class="max-w-5xl mx-auto grid sm:grid-cols-3 gap-6">
                <div v-for="card in [
                    { icon: '📍', title: 'Calamba, Laguna', body: 'Find us at our pop-up spot in Calamba. Follow our page for daily location updates and operating hours.' },
                    { icon: '🔥', title: 'Grilled Fresh Daily', body: 'Every burger is pressed and grilled fresh on order. No reheated patties — only hot, juicy, flame-grilled goodness.' },
                    { icon: '🤙', title: 'Made by The Boys', body: 'A group of friends who turned their love for grilling into a business. Every burger is made with pride.' },
                ]" :key="card.title"
                    class="rounded-2xl border border-white/5 p-6 text-center hover:border-orange-700/50 transition-colors"
                    style="background: rgba(255,255,255,0.025)">
                    <div class="text-5xl mb-5">{{ card.icon }}</div>
                    <h3 class="font-bold text-white mb-3">{{ card.title }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ card.body }}</p>
                </div>
            </div>
        </section>

        <!-- ── TAGLINE BANNER ──────────────────────────────── -->
        <section class="py-20 px-6 text-center relative overflow-hidden">
            <div class="absolute inset-0 pointer-events-none"
                style="background: linear-gradient(180deg, transparent, rgba(249,115,22,0.08), transparent);"></div>
            <div class="relative z-10 max-w-3xl mx-auto">
                <p class="text-3xl sm:text-4xl font-black leading-tight text-white mb-4">
                    "Kain na, bro — <span class="text-orange-400">mas solid habang mainit!</span>"
                </p>
                <p class="text-gray-400 text-sm uppercase tracking-widest">The Boys · Grilled Burgers · Calamba, Laguna</p>
            </div>
        </section>

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
