<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const id = page.props.route.params.id || '1';
</script>

<template>
    <Head title="Bypass Grill - Premium Baby Back Ribs" />

    <div class="min-h-screen bg-gradient-to-br from-orange-50 to-red-50 dark:from-slate-900 dark:to-slate-800">
        <!-- Hero Section -->
        <section class="relative overflow-hidden bg-gradient-to-r from-orange-600 to-red-600 text-white">
            <div class="absolute inset-0 bg-black/20"></div>
            <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8">
                <div class="text-center">
                    <div class="mb-8 flex justify-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white text-orange-600 shadow-lg">
                            <span class="text-4xl font-black">BG</span>
                        </div>
                    </div>
                    <h1 class="mb-6 text-5xl font-bold tracking-tight sm:text-6xl">
                        Premium Baby Back Ribs
                    </h1>
                    <p class="mx-auto mb-8 max-w-2xl text-xl text-orange-100">
                        Experience the perfect blend of smoky flavor and tender texture. Our baby back ribs are slow-cooked to perfection, glazed with our signature sauce, and ready to become your next barbecue masterpiece.
                    </p>
                    <div class="flex flex-col gap-4 sm:flex-row sm:justify-center">
                        <button class="rounded-lg bg-white px-8 py-3 font-semibold text-orange-600 shadow-lg transition hover:bg-orange-50">
                            Order Now - $24.99
                        </button>
                        <button class="rounded-lg border-2 border-white px-8 py-3 font-semibold text-white transition hover:bg-white hover:text-orange-600">
                            Learn More
                        </button>
                    </div>
                </div>
            </div>
            <!-- Decorative grill pattern -->
            <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-r from-transparent via-white/10 to-transparent opacity-20"></div>
        </section>

        <!-- Product Features -->
        <section class="py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-4">
                        Why Choose Our Baby Back Ribs?
                    </h2>
                    <p class="text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
                        Hand-selected, expertly prepared, and packed with flavor that will make your taste buds dance.
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-3">
                    <div class="rounded-2xl bg-white p-8 shadow-lg dark:bg-slate-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                            <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-slate-900 dark:text-slate-100">Premium Quality</h3>
                        <p class="text-slate-600 dark:text-slate-300">
                            Sourced from the finest farms, our ribs are tender, juicy, and full of natural flavor. No additives, just pure barbecue perfection.
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white p-8 shadow-lg dark:bg-slate-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-slate-900 dark:text-slate-100">Signature Sauce</h3>
                        <p class="text-slate-600 dark:text-slate-300">
                            Our house-made barbecue sauce combines sweet, smoky, and tangy notes that perfectly complement the rich meat flavor.
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white p-8 shadow-lg dark:bg-slate-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-slate-900 dark:text-slate-100">Ready in Minutes</h3>
                        <p class="text-slate-600 dark:text-slate-300">
                            Pre-seasoned and ready to grill. Just heat up your grill and enjoy restaurant-quality ribs in under 30 minutes.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Image & Details -->
        <section class="bg-white py-16 dark:bg-slate-900">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <h2 class="mb-6 text-3xl font-bold text-slate-900 dark:text-slate-100">
                            Fall-off-the-bone Tender
                        </h2>
                        <p class="mb-6 text-lg text-slate-600 dark:text-slate-300">
                            Our baby back ribs are carefully trimmed and seasoned with a blend of spices that enhance their natural sweetness. Slow-smoked to perfection, they offer that authentic barbecue experience right in your backyard.
                        </p>
                        <ul class="mb-8 space-y-3">
                            <li class="flex items-center text-slate-700 dark:text-slate-300">
                                <svg class="mr-3 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                1.5 lbs of premium baby back ribs
                            </li>
                            <li class="flex items-center text-slate-700 dark:text-slate-300">
                                <svg class="mr-3 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Pre-seasoned with our signature rub
                            </li>
                            <li class="flex items-center text-slate-700 dark:text-slate-300">
                                <svg class="mr-3 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Includes 8oz signature barbecue sauce
                            </li>
                            <li class="flex items-center text-slate-700 dark:text-slate-300">
                                <svg class="mr-3 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Serves 2-3 people
                            </li>
                        </ul>
                        <button class="rounded-lg bg-orange-600 px-8 py-3 font-semibold text-white shadow-lg transition hover:bg-orange-700">
                            Add to Cart - $24.99
                        </button>
                    </div>

                    <div class="relative">
                        <div class="aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-orange-200 to-red-200 dark:from-orange-900 dark:to-red-900">
                            <div class="flex h-full items-center justify-center">
                                <div class="text-center">
                                    <div class="mb-4 text-8xl">🍖</div>
                                    <p class="text-2xl font-bold text-slate-800 dark:text-slate-200">Baby Back Ribs</p>
                                    <p class="text-slate-600 dark:text-slate-400">Premium Cut</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-4 -right-4 rounded-full bg-orange-500 px-4 py-2 text-sm font-bold text-white shadow-lg">
                            $24.99
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-slate-900 py-12 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <div class="mb-4 flex justify-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-600">
                            <span class="text-xl font-black">BG</span>
                        </div>
                    </div>
                    <p class="mb-4 text-slate-300">Bypass Grill - Premium BBQ Products</p>
                    <p class="text-sm text-slate-400">Menu ID: {{ id }} | © 2026 Bypass Grill. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
