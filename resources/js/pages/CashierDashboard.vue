<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import { useCartStore } from '@/stores/cartStore'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import { ShoppingCart, X, Plus, Minus, Search } from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Point of Sale', href: '/pos' },
        ],
    },
})

interface Modifier { id: number; name: string; price: number }
interface Product {
    id: number; name: string; description: string; price: number; image: string | null
    category_id: number; category: { id: number; name: string } | null; modifiers: Modifier[]
}
interface Category { id: number; name: string }

const props = defineProps<{ categories: Category[]; products: Product[] }>()

const cartStore = useCartStore()
const page = usePage()
const user = computed(() => page.props.auth?.user)

const selectedCategoryId = ref<number | null>(null)
const searchQuery = ref('')
const selectedProduct = ref<Product | null>(null)
const productQty = ref(1)
const selectedModifiers = ref<number[]>([])
const submitting = ref(false)
const lastOrderNumber = ref<number | null>(null)

const filteredProducts = computed(() => {
    let list = props.products
    if (selectedCategoryId.value !== null) {
        list = list.filter((p) => p.category_id === selectedCategoryId.value)
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        list = list.filter((p) => p.name.toLowerCase().includes(q))
    }
    return list
})

const modifierTotal = computed(() =>
    selectedModifiers.value.reduce((sum, id) => {
        const m = selectedProduct.value?.modifiers.find((x) => x.id === id)
        return sum + (m?.price ?? 0)
    }, 0),
)

const openProduct = (product: Product) => {
    selectedProduct.value = product
    productQty.value = 1
    selectedModifiers.value = []
}

const addToCart = () => {
    if (!selectedProduct.value) return
    cartStore.addItem(selectedProduct.value, productQty.value, selectedModifiers.value)
    toast.success(`${selectedProduct.value.name} added to cart`)
    selectedProduct.value = null
}

const submitOrder = async () => {
    if (cartStore.items.length === 0) return
    submitting.value = true
    try {
        const payload = {
            order_type: cartStore.orderType,
            table_number: cartStore.tableNumber,
            discount_amount: cartStore.discount,
            notes: '',
            items: cartStore.items.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                modifiers: item.modifiers ?? [],
            })),
        }
        const res = await api.post('/api/v1/orders', payload)
        const order = res.data.data ?? res.data
        lastOrderNumber.value = order.queue_number ?? order.id
        cartStore.clear()
        toast.success(`Order #${lastOrderNumber.value} placed successfully!`)
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to submit order')
    } finally {
        submitting.value = false
    }
}

const formatPrice = (val: number) => '₱' + val.toFixed(2)
</script>

<template>
    <Head title="Point of Sale" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- LEFT: Product Browser -->
        <div class="lg:col-span-2 space-y-4 lg:overflow-y-auto lg:max-h-[calc(100vh-8rem)] lg:pr-1">
            <!-- Search -->
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search products…"
                    class="w-full rounded-lg border bg-background px-4 py-2 pl-9 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                />
            </div>

            <!-- Category Tabs -->
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    @click="selectedCategoryId = null; searchQuery = ''"
                    :class="[
                        'shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition',
                        selectedCategoryId === null
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80',
                    ]"
                >
                    All
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="selectedCategoryId = cat.id; searchQuery = ''"
                    :class="[
                        'shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition',
                        selectedCategoryId === cat.id
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground hover:bg-muted/80',
                    ]"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                <button
                    v-for="product in filteredProducts"
                    :key="product.id"
                    @click="openProduct(product)"
                    class="flex flex-col items-start rounded-xl border bg-card p-3 text-left shadow-sm transition hover:shadow-md hover:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                >
                    <div class="mb-2 h-20 w-full rounded-lg bg-muted flex items-center justify-center overflow-hidden">
                        <img v-if="product.image" :src="product.image" :alt="product.name" class="h-full w-full object-cover" />
                        <ShoppingCart v-else class="h-8 w-8 text-muted-foreground/40" />
                    </div>
                    <p class="text-xs text-muted-foreground mb-0.5">{{ product.category?.name }}</p>
                    <h3 class="text-sm font-semibold leading-tight line-clamp-2">{{ product.name }}</h3>
                    <p class="mt-1 text-base font-bold text-primary">{{ formatPrice(product.price) }}</p>
                </button>
            </div>

            <p v-if="filteredProducts.length === 0" class="text-center text-muted-foreground py-10 text-sm">
                No products found.
            </p>
        </div>

        <!-- RIGHT: Cart -->
        <div class="flex flex-col rounded-xl border bg-card shadow-sm overflow-hidden sticky top-4 max-h-[calc(100vh-6rem)]">
            <div class="p-4 border-b flex items-center gap-2">
                <ShoppingCart class="h-5 w-5" />
                <h2 class="font-bold text-base">Order Cart</h2>
                <span v-if="cartStore.items.length > 0" class="ml-auto text-xs bg-primary text-primary-foreground rounded-full px-2 py-0.5">
                    {{ cartStore.items.length }}
                </span>
            </div>

            <div class="p-4 border-b space-y-3">
                <!-- Order Type -->
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Order Type</label>
                    <select
                        :value="cartStore.orderType"
                        @change="(e) => cartStore.orderType = (e.target as HTMLSelectElement).value"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <option value="dine_in">Dine In</option>
                        <option value="takeout">Takeout</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
                <div v-if="cartStore.orderType === 'dine_in'">
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Table Number</label>
                    <input
                        :value="cartStore.tableNumber"
                        @input="(e) => cartStore.tableNumber = (e.target as HTMLInputElement).value"
                        type="text"
                        placeholder="e.g. Table 5"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0">
                <div v-if="cartStore.items.length === 0" class="text-center text-muted-foreground text-sm py-10">
                    Cart is empty
                </div>
                <div v-for="item in cartStore.items" :key="item.id" class="flex gap-2 items-start rounded-lg border bg-background p-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ item.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatPrice(item.unit_price) }} each</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            @click="cartStore.updateQuantity(item.id, item.quantity - 1)"
                            class="rounded bg-muted p-0.5 hover:bg-muted/80"
                        >
                            <Minus class="h-3 w-3" />
                        </button>
                        <span class="w-6 text-center text-sm font-bold">{{ item.quantity }}</span>
                        <button
                            @click="cartStore.updateQuantity(item.id, item.quantity + 1)"
                            class="rounded bg-muted p-0.5 hover:bg-muted/80"
                        >
                            <Plus class="h-3 w-3" />
                        </button>
                        <button
                            @click="cartStore.removeItem(item.id)"
                            class="ml-1 rounded bg-destructive/10 p-0.5 text-destructive hover:bg-destructive/20"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Totals + Discount -->
            <div class="p-4 border-t space-y-2 bg-muted/30">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-muted-foreground w-24 shrink-0">Discount (₱)</label>
                    <input
                        :value="cartStore.discount"
                        @input="cartStore.setDiscount(parseFloat(($event.target as HTMLInputElement).value) || 0)"
                        type="number" min="0" step="0.01"
                        class="flex-1 rounded-lg border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
                <div class="space-y-1 text-sm pt-1">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span>{{ formatPrice(cartStore.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-red-600">
                        <span>Discount</span>
                        <span>-{{ formatPrice(cartStore.discount) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-base border-t pt-1 mt-1">
                        <span>TOTAL</span>
                        <span class="text-primary">{{ formatPrice(cartStore.total) }}</span>
                    </div>
                </div>
            </div>

            <div class="p-4 space-y-2">
                <button
                    @click="submitOrder"
                    :disabled="cartStore.items.length === 0 || submitting"
                    class="w-full rounded-lg bg-primary py-3 text-sm font-bold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ submitting ? 'Placing Order…' : `Place Order — ${formatPrice(cartStore.total)}` }}
                </button>
                <button
                    @click="cartStore.clear"
                    class="w-full rounded-lg border bg-background py-2 text-sm font-medium transition hover:bg-muted"
                >
                    Clear Cart
                </button>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="selectedProduct"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="selectedProduct = null"
            >
                <div class="w-full max-w-md rounded-2xl bg-background shadow-2xl">
                    <div class="p-5 border-b flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-bold">{{ selectedProduct.name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ selectedProduct.description }}</p>
                        </div>
                        <button @click="selectedProduct = null" class="ml-2 rounded-full p-1 hover:bg-muted">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Quantity -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Quantity</span>
                            <div class="flex items-center gap-2">
                                <button @click="productQty = Math.max(1, productQty - 1)" class="rounded bg-muted p-1 hover:bg-muted/80">
                                    <Minus class="h-4 w-4" />
                                </button>
                                <span class="w-8 text-center font-bold">{{ productQty }}</span>
                                <button @click="productQty++" class="rounded bg-muted p-1 hover:bg-muted/80">
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Modifiers -->
                        <div v-if="selectedProduct.modifiers?.length > 0">
                            <p class="text-sm font-medium mb-2">Add-ons</p>
                            <div class="space-y-2">
                                <label
                                    v-for="mod in selectedProduct.modifiers"
                                    :key="mod.id"
                                    class="flex items-center gap-3 rounded-lg border bg-muted/30 p-2 cursor-pointer hover:bg-muted/50"
                                >
                                    <input type="checkbox" :value="mod.id" v-model="selectedModifiers" class="rounded" />
                                    <span class="flex-1 text-sm">{{ mod.name }}</span>
                                    <span class="text-sm font-medium text-primary">+{{ formatPrice(mod.price) }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Price summary -->
                        <div class="rounded-lg bg-muted/40 p-3 flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Item total</span>
                            <span class="font-bold text-primary">
                                {{ formatPrice((selectedProduct.price + modifierTotal) * productQty) }}
                            </span>
                        </div>
                    </div>
                    <div class="p-5 border-t flex gap-3">
                        <button
                            @click="selectedProduct = null"
                            class="flex-1 rounded-lg border py-2 text-sm font-medium hover:bg-muted"
                        >
                            Cancel
                        </button>
                        <button
                            @click="addToCart"
                            class="flex-1 rounded-lg bg-primary py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90"
                        >
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
