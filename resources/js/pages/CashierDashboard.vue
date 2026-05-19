<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useCartStore } from '@/stores/cartStore'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import { ShoppingCart, X, Plus, Minus, Search, CreditCard, Banknote, CheckCircle2, Printer } from 'lucide-vue-next'

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
interface Tender { id: number; name: string; is_active: boolean; display_order: number }
interface CompletedOrder {
    orderId: number; queueNumber: number | null; orderType: string
    tableNumber: string | null; customerName: string | null
    customerContact: string | null; customerAddress: string | null; notes: string | null
    items: { name: string; quantity: number; unit_price: number }[]
    subtotal: number; discount: number; total: number
    tenderName: string; amountTendered: number; change: number; paid: boolean
}

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
const cartOpen = ref(false)

// Payment modal state
const paymentOpen = ref(false)
const pendingOrder = ref<{ id: number; total_amount: number } | null>(null)
const tenders = ref<Tender[]>([])
const selectedTenderId = ref<number | null>(null)
const amountTendered = ref('')
const reference = ref('')
const paymentSubmitting = ref(false)
const paymentDone = ref(false)
const completedOrder = ref<CompletedOrder | null>(null)

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

const change = computed(() => {
    const tendered = parseFloat(amountTendered.value) || 0
    const total = pendingOrder.value?.total_amount ?? 0
    return tendered - total
})

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
    if (window.innerWidth < 1024) cartOpen.value = true
}

const loadTenders = async () => {
    if (tenders.value.length > 0) return
    try {
        const res = await api.get('/api/v1/payment-tenders')
        tenders.value = res.data
    } catch {
        // non-fatal
    }
}

const submitOrder = async () => {
    if (cartStore.items.length === 0) return
    submitting.value = true
    try {
        const payload = {
            order_type: cartStore.orderType,
            table_number: cartStore.tableNumber,
            customer_name: cartStore.customerName || null,
            customer_contact: cartStore.customerContact || null,
            customer_address: cartStore.customerAddress || null,
            discount_amount: cartStore.discount,
            notes: '',
            items: cartStore.items.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                modifiers: item.modifiers ?? [],
            })),
        }
        const res = await api.post('/api/v1/orders', payload)
        const raw = res.data.data ?? res.data
        // Normalize decimal fields that Laravel serializes as strings
        pendingOrder.value = { ...raw, total_amount: parseFloat(raw.total_amount ?? 0) }
        cartOpen.value = false
        await loadTenders()
        selectedTenderId.value = tenders.value[0]?.id ?? null
        amountTendered.value = pendingOrder.value.total_amount.toFixed(2)
        reference.value = ''
        paymentOpen.value = true
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to submit order')
    } finally {
        submitting.value = false
    }
}

const captureOrder = (paid: boolean): CompletedOrder => {
    const o = pendingOrder.value!
    const tendered = parseFloat(amountTendered.value) || o.total_amount
    return {
        orderId: o.id,
        queueNumber: (o as any).queue_number ?? null,
        orderType: (o as any).order_type ?? 'dine_in',
        tableNumber: (o as any).table_number ?? null,
        customerName: (o as any).customer_name ?? null,
        customerContact: (o as any).customer_contact ?? null,
        customerAddress: (o as any).customer_address ?? null,
        notes: (o as any).notes ?? null,
        items: cartStore.items.map(i => ({ name: i.name, quantity: i.quantity, unit_price: i.unit_price })),
        subtotal: cartStore.subtotal,
        discount: cartStore.discount,
        total: o.total_amount,
        tenderName: tenders.value.find(t => t.id === selectedTenderId.value)?.name ?? '',
        amountTendered: tendered,
        change: paid ? Math.max(0, tendered - o.total_amount) : 0,
        paid,
    }
}

const submitPayment = async () => {
    if (!pendingOrder.value || !selectedTenderId.value) return
    paymentSubmitting.value = true
    try {
        await api.post('/api/v1/payments', {
            order_id: pendingOrder.value.id,
            payment_tender_id: selectedTenderId.value,
            amount: pendingOrder.value.total_amount,
            reference: reference.value || null,
        })
        completedOrder.value = captureOrder(true)
        paymentDone.value = true
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Payment failed')
    } finally {
        paymentSubmitting.value = false
    }
}

const skipPayment = () => {
    if (!pendingOrder.value) return
    completedOrder.value = captureOrder(false)
    paymentDone.value = true
}

const closeAndClear = () => {
    const o = completedOrder.value
    const queueOrId = o?.queueNumber ?? o?.orderId
    if (o?.paid) toast.success(`Order #${queueOrId} paid! Thank you.`)
    else toast.success(`Order #${queueOrId} placed. Payment pending.`)
    cartStore.clear()
    paymentOpen.value = false
    pendingOrder.value = null
    paymentDone.value = false
    completedOrder.value = null
}

const printReceipt = () => {
    const o = completedOrder.value
    if (!o) return
    const now = new Date()
    const dateStr = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
    const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
    const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const fmt = (n: number) => '&#8369;' + n.toFixed(2)

    const itemsHTML = o.items.map(i => `
        <div class="row"><span class="flex1">${i.quantity}x ${esc(i.name)}</span><span>${fmt(i.unit_price * i.quantity)}</span></div>
        <div class="small muted">${fmt(i.unit_price)} each</div>
    `).join('')

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Courier New',Courier,monospace;font-size:11px;width:72mm;padding:4mm 3mm;}
  @media print{@page{size:72mm auto;margin:0;}body{padding:2mm;}}
  .center{text-align:center;}.bold{font-weight:bold;}.muted{color:#666;}
  .large{font-size:14px;}.xlarge{font-size:20px;font-weight:bold;}
  hr{border:none;border-top:1px dashed #000;margin:3px 0;}
  .row{display:flex;justify-content:space-between;margin:1px 0;}
  .flex1{flex:1;word-break:break-word;padding-right:4px;}
  .small{font-size:9px;}.total{font-size:13px;font-weight:bold;}
</style></head><body>
  <div class="center bold large">BYPASSGRILL</div>
  <div class="center muted" style="font-size:9px;">Filipino Grill Restaurant</div>
  <hr>
  <div class="center xlarge">${o.queueNumber ? '#' + o.queueNumber : 'Order #' + o.orderId}</div>
  <div class="center muted" style="font-size:9px;">${dateStr} &nbsp; ${timeStr}</div>
  <div class="center bold" style="margin-top:2px;">${o.orderType.replace('_',' ').toUpperCase()}</div>
  ${o.tableNumber ? `<div class="center">Table: ${esc(o.tableNumber)}</div>` : ''}
  ${o.customerName ? `<div class="center bold">${esc(o.customerName)}</div>` : ''}
  ${o.customerContact ? `<div class="center muted small">${esc(o.customerContact)}</div>` : ''}
  ${o.customerAddress ? `<div class="center small" style="word-break:break-word;">${esc(o.customerAddress)}</div>` : ''}
  <hr>
  <div class="row bold"><span>ITEM</span><span>AMT</span></div>
  <hr>
  ${itemsHTML}
  <hr>
  <div class="row"><span>Subtotal</span><span>${fmt(o.subtotal)}</span></div>
  ${o.discount > 0 ? `<div class="row"><span>Discount</span><span>-${fmt(o.discount)}</span></div>` : ''}
  <div class="row total"><span>TOTAL</span><span>${fmt(o.total)}</span></div>
  <hr>
  ${o.paid
    ? `<div class="row"><span>Method</span><span>${esc(o.tenderName)}</span></div>
       <div class="row"><span>Tendered</span><span>${fmt(o.amountTendered)}</span></div>
       ${o.change > 0 ? `<div class="row bold"><span>CHANGE</span><span>${fmt(o.change)}</span></div>` : ''}`
    : `<div class="center bold" style="letter-spacing:1px;">** PAYMENT PENDING **</div>`
  }
  ${o.notes ? `<hr><div class="small">Note: ${esc(o.notes)}</div>` : ''}
  <hr>
  <div class="center" style="margin-top:3px;">Thank you for dining with us!</div>
  <div class="center muted small">Please come again  &#9829;</div>
</body></html>`

    const iframe = document.createElement('iframe')
    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px;'
    document.body.appendChild(iframe)
    const doc = iframe.contentDocument ?? iframe.contentWindow?.document
    if (!doc) { document.body.removeChild(iframe); toast.error('Could not open print frame'); return }
    doc.open(); doc.write(html); doc.close()
    iframe.contentWindow?.focus()
    setTimeout(() => {
        iframe.contentWindow?.print()
        setTimeout(() => document.body.removeChild(iframe), 1000)
    }, 250)
}

const formatPrice = (val: number) => '₱' + val.toFixed(2)

onMounted(loadTenders)
</script>

<template>
    <Head title="Point of Sale" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- LEFT: Product Browser -->
        <div class="lg:col-span-2 space-y-4 pb-24 lg:pb-0 lg:overflow-y-auto lg:max-h-[calc(100vh-8rem)] lg:pr-1">
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

        <!-- RIGHT: Cart (desktop sidebar only) -->
        <div class="hidden lg:flex flex-col rounded-xl border bg-card shadow-sm overflow-hidden sticky top-4 h-[calc(100vh-6rem)]">
            <div class="p-4 border-b flex items-center gap-2">
                <ShoppingCart class="h-5 w-5" />
                <h2 class="font-bold text-base">Order Cart</h2>
                <span v-if="cartStore.items.length > 0" class="ml-auto text-xs bg-primary text-primary-foreground rounded-full px-2 py-0.5">
                    {{ cartStore.items.length }}
                </span>
            </div>

            <div class="p-4 border-b space-y-3">
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
                <!-- Takeout: customer name -->
                <div v-if="cartStore.orderType === 'takeout'">
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Customer Name / Alias</label>
                    <input
                        v-model="cartStore.customerName"
                        type="text"
                        placeholder="e.g. Juan, Table 2"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
                <!-- Delivery: name, contact, address -->
                <template v-if="cartStore.orderType === 'delivery'">
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Customer Name / Alias</label>
                        <input
                            v-model="cartStore.customerName"
                            type="text"
                            placeholder="Full name or alias"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Contact Number</label>
                        <input
                            v-model="cartStore.customerContact"
                            type="text"
                            placeholder="e.g. 09XX XXX XXXX"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Delivery Address</label>
                        <textarea
                            v-model="cartStore.customerAddress"
                            rows="2"
                            placeholder="Street, barangay, city…"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                        />
                    </div>
                </template>
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
                        <button @click="cartStore.updateQuantity(item.id, item.quantity - 1)" class="rounded bg-muted p-0.5 hover:bg-muted/80">
                            <Minus class="h-3 w-3" />
                        </button>
                        <span class="w-6 text-center text-sm font-bold">{{ item.quantity }}</span>
                        <button @click="cartStore.updateQuantity(item.id, item.quantity + 1)" class="rounded bg-muted p-0.5 hover:bg-muted/80">
                            <Plus class="h-3 w-3" />
                        </button>
                        <button @click="cartStore.removeItem(item.id)" class="ml-1 rounded bg-destructive/10 p-0.5 text-destructive hover:bg-destructive/20">
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

    <!-- Mobile: Floating Cart Button + Drawer -->
    <Teleport to="body">
        <!-- FAB -->
        <button
            @click="cartOpen = true"
            class="fixed bottom-6 right-6 z-30 lg:hidden flex items-center gap-2 rounded-full bg-primary px-4 py-3 text-primary-foreground shadow-lg hover:bg-primary/90 transition-all"
        >
            <ShoppingCart class="h-5 w-5" />
            <span v-if="cartStore.items.length > 0" class="text-sm font-bold">{{ cartStore.items.length }}</span>
            <span v-if="cartStore.items.length > 0" class="text-xs font-semibold">{{ formatPrice(cartStore.total) }}</span>
        </button>

        <!-- Backdrop -->
        <Transition name="fade">
            <div v-if="cartOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="cartOpen = false" />
        </Transition>

        <!-- Drawer -->
        <Transition name="drawer">
            <div v-if="cartOpen" class="fixed inset-y-0 right-0 z-50 w-80 flex flex-col bg-card shadow-2xl lg:hidden">
                <div class="p-4 border-b flex items-center gap-2">
                    <ShoppingCart class="h-5 w-5" />
                    <h2 class="font-bold text-base flex-1">Order Cart</h2>
                    <span v-if="cartStore.items.length > 0" class="text-xs bg-primary text-primary-foreground rounded-full px-2 py-0.5">
                        {{ cartStore.items.length }}
                    </span>
                    <button @click="cartOpen = false" class="ml-2 rounded-full p-1 hover:bg-muted">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="p-4 border-b space-y-3">
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
                            type="text" placeholder="e.g. Table 5"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <!-- Takeout: customer name -->
                    <div v-if="cartStore.orderType === 'takeout'">
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Customer Name / Alias</label>
                        <input
                            v-model="cartStore.customerName"
                            type="text"
                            placeholder="e.g. Juan, Table 2"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <!-- Delivery: name, contact, address -->
                    <template v-if="cartStore.orderType === 'delivery'">
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1">Customer Name / Alias</label>
                            <input
                                v-model="cartStore.customerName"
                                type="text"
                                placeholder="Full name or alias"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1">Contact Number</label>
                            <input
                                v-model="cartStore.customerContact"
                                type="text"
                                placeholder="e.g. 09XX XXX XXXX"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1">Delivery Address</label>
                            <textarea
                                v-model="cartStore.customerAddress"
                                rows="2"
                                placeholder="Street, barangay, city…"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                            />
                        </div>
                    </template>
                </div>

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
                            <button @click="cartStore.updateQuantity(item.id, item.quantity - 1)" class="rounded bg-muted p-0.5 hover:bg-muted/80">
                                <Minus class="h-3 w-3" />
                            </button>
                            <span class="w-6 text-center text-sm font-bold">{{ item.quantity }}</span>
                            <button @click="cartStore.updateQuantity(item.id, item.quantity + 1)" class="rounded bg-muted p-0.5 hover:bg-muted/80">
                                <Plus class="h-3 w-3" />
                            </button>
                            <button @click="cartStore.removeItem(item.id)" class="ml-1 rounded bg-destructive/10 p-0.5 text-destructive hover:bg-destructive/20">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>

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
                    <button @click="cartStore.clear" class="w-full rounded-lg border bg-background py-2 text-sm font-medium transition hover:bg-muted">
                        Clear Cart
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Payment Modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="paymentOpen && pendingOrder"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            >
                <div class="w-full max-w-sm rounded-2xl bg-background shadow-2xl overflow-hidden">

                    <!-- ── SUCCESS STATE ────────────────────────────────── -->
                    <template v-if="paymentDone && completedOrder">
                        <div class="p-6 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 mx-auto mb-3">
                                <CheckCircle2 class="h-8 w-8 text-green-600" />
                            </div>
                            <h3 class="text-lg font-black">{{ completedOrder.paid ? 'Payment Complete' : 'Order Placed' }}</h3>
                            <p class="text-sm text-muted-foreground mt-0.5">
                                {{ completedOrder.queueNumber ? 'Queue #' + completedOrder.queueNumber : 'Order #' + completedOrder.orderId }}
                            </p>

                            <!-- Change display -->
                            <div v-if="completedOrder.paid && completedOrder.change > 0"
                                class="mt-4 rounded-xl bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 px-5 py-3">
                                <p class="text-xs text-muted-foreground uppercase tracking-wider mb-0.5">Change</p>
                                <p class="text-4xl font-black text-green-600">{{ formatPrice(completedOrder.change) }}</p>
                            </div>
                            <div v-else-if="!completedOrder.paid"
                                class="mt-4 rounded-xl bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800 px-5 py-3">
                                <p class="text-sm font-semibold text-yellow-700 dark:text-yellow-400">Payment Pending</p>
                                <p class="text-xl font-black">{{ formatPrice(completedOrder.total) }}</p>
                            </div>

                            <!-- Mini order summary -->
                            <div class="mt-4 rounded-xl bg-muted/40 p-3 text-left space-y-0.5">
                                <p v-for="item in completedOrder.items" :key="item.name" class="text-xs">
                                    <span class="font-semibold">{{ item.quantity }}×</span> {{ item.name }}
                                    <span class="text-muted-foreground float-right">{{ formatPrice(item.unit_price * item.quantity) }}</span>
                                </p>
                                <div class="border-t mt-1 pt-1 flex justify-between text-xs font-bold">
                                    <span>TOTAL</span><span>{{ formatPrice(completedOrder.total) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 border-t space-y-2">
                            <button
                                @click="printReceipt"
                                class="w-full rounded-lg bg-primary py-3 text-sm font-bold text-primary-foreground hover:bg-primary/90 transition flex items-center justify-center gap-2"
                            >
                                <Printer class="h-4 w-4" /> Print Receipt
                            </button>
                            <button
                                @click="closeAndClear"
                                class="w-full rounded-lg border bg-background py-2 text-sm font-medium hover:bg-muted transition"
                            >
                                Done
                            </button>
                        </div>
                    </template>

                    <!-- ── PAYMENT FORM ─────────────────────────────────── -->
                    <template v-else>
                        <!-- Header -->
                        <div class="p-5 border-b flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                <CreditCard class="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <h3 class="font-bold">Collect Payment</h3>
                                <p class="text-xs text-muted-foreground">Order #{{ (pendingOrder as any).queue_number ?? pendingOrder.id }}</p>
                            </div>
                        </div>

                        <div class="p-5 space-y-5">
                            <!-- Total due -->
                            <div class="rounded-xl bg-primary/5 border border-primary/20 p-4 text-center">
                                <p class="text-xs text-muted-foreground mb-1 uppercase tracking-wider">Amount Due</p>
                                <p class="text-4xl font-black text-primary">{{ formatPrice(pendingOrder.total_amount) }}</p>
                            </div>

                            <!-- Tender selection -->
                            <div>
                                <p class="text-xs font-medium text-muted-foreground mb-2 uppercase tracking-wider">Payment Method</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="tender in tenders"
                                        :key="tender.id"
                                        @click="selectedTenderId = tender.id"
                                        :class="[
                                            'flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                            selectedTenderId === tender.id
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border bg-background hover:border-primary/50 hover:bg-muted/50',
                                        ]"
                                    >
                                        <Banknote class="h-4 w-4 shrink-0" />
                                        {{ tender.name }}
                                    </button>
                                </div>
                                <p v-if="tenders.length === 0" class="text-sm text-muted-foreground text-center py-2">
                                    No payment tenders configured.
                                </p>
                            </div>

                            <!-- Amount tendered -->
                            <div>
                                <label class="text-xs font-medium text-muted-foreground block mb-1 uppercase tracking-wider">Amount Tendered</label>
                                <input
                                    v-model="amountTendered"
                                    type="number" min="0" step="0.01"
                                    :placeholder="pendingOrder.total_amount.toFixed(2)"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>

                            <!-- Change / Short-by -->
                            <div v-if="change >= 0 && amountTendered !== ''" class="flex justify-between items-center rounded-lg bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 px-4 py-3">
                                <span class="text-sm font-medium text-green-700 dark:text-green-400">Change</span>
                                <span class="text-lg font-black text-green-600">{{ formatPrice(change) }}</span>
                            </div>
                            <div v-else-if="change < 0 && amountTendered !== ''" class="flex justify-between items-center rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 px-4 py-3">
                                <span class="text-sm font-medium text-red-600">Short by</span>
                                <span class="text-lg font-black text-red-600">{{ formatPrice(Math.abs(change)) }}</span>
                            </div>

                            <!-- Reference -->
                            <div>
                                <label class="text-xs font-medium text-muted-foreground block mb-1">Reference # (optional)</label>
                                <input
                                    v-model="reference"
                                    type="text"
                                    placeholder="e.g. GCash ref, card last 4"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>
                        </div>

                        <div class="p-5 border-t space-y-2">
                            <button
                                @click="submitPayment"
                                :disabled="paymentSubmitting || !selectedTenderId"
                                class="w-full rounded-lg bg-green-600 py-3 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            >
                                {{ paymentSubmitting ? 'Processing…' : 'Confirm Payment' }}
                            </button>
                            <button
                                @click="skipPayment"
                                class="w-full rounded-lg border bg-background py-2 text-sm font-medium hover:bg-muted transition"
                            >
                                Skip — Pay Later
                            </button>
                        </div>
                    </template>

                </div>
            </div>
        </Transition>
    </Teleport>

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

                        <div class="rounded-lg bg-muted/40 p-3 flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Item total</span>
                            <span class="font-bold text-primary">
                                {{ formatPrice((selectedProduct.price + modifierTotal) * productQty) }}
                            </span>
                        </div>
                    </div>
                    <div class="p-5 border-t flex gap-3">
                        <button @click="selectedProduct = null" class="flex-1 rounded-lg border py-2 text-sm font-medium hover:bg-muted">
                            Cancel
                        </button>
                        <button @click="addToCart" class="flex-1 rounded-lg bg-primary py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90">
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
.drawer-enter-active, .drawer-leave-active { transition: transform 0.28s ease; }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); }
</style>
