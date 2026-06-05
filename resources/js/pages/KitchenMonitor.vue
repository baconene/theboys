<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import { RefreshCw, Pencil, X, Plus, Minus, Search, ShoppingCart } from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kitchen Monitor', href: '/kitchen' },
        ],
    },
})

interface OrderItem {
    id: number; quantity: number; unit_price: number
    special_instructions: string | null
    product: { id: number; name: string; price: number }
}
interface Order {
    id: number; queue_number: number | null; order_type: string
    status: string; payment_status: string
    table_number: string | null
    customer_name: string | null; customer_contact: string | null; customer_address: string | null
    notes: string | null
    total_amount: number; created_at: string; items: OrderItem[]
}
interface Product { id: number; name: string; price: number; category: string | null }

const props = defineProps<{ initialOrders: Order[]; products: Product[] }>()

// ── State ────────────────────────────────────────────────────────
const orders = ref<Order[]>(props.initialOrders.map(normalizeOrder))
const updatingId = ref<number | null>(null)
let pollInterval: ReturnType<typeof setInterval> | null = null

// Edit modal
const editOpen = ref(false)
const editOrder = ref<Order | null>(null)
const originalTotal = ref(0)
const editItems = ref<{ product_id: number; name: string; price: number; quantity: number }[]>([])
const editNotes = ref('')
const editDiscount = ref(0)
const editSaving = ref(false)
const addSearch = ref('')

// ── Helpers ──────────────────────────────────────────────────────
function normalizeOrder(o: any): Order {
    return {
        id: o.id,
        queue_number: o.queue_number ?? null,
        order_type: o.order_type,
        status: o.status,
        payment_status: o.payment_status ?? 'pending',
        table_number: o.table_number ?? null,
        customer_name: o.customer_name ?? null,
        customer_contact: o.customer_contact ?? null,
        customer_address: o.customer_address ?? null,
        notes: o.notes ?? null,
        total_amount: parseFloat(o.total_amount ?? 0),
        created_at: o.created_at,
        items: (o.items ?? []).map((i: any) => ({
            id: i.id,
            quantity: i.quantity,
            unit_price: parseFloat(i.unit_price ?? 0),
            special_instructions: i.special_instructions ?? null,
            product: {
                id: i.product?.id,
                name: i.product?.name,
                price: parseFloat(i.product?.price ?? 0),
            },
        })),
    }
}

const formatPrice = (v: number) => '₱' + (v ?? 0).toFixed(2)

const ageMinutes = (d: string) => Math.floor((Date.now() - new Date(d).getTime()) / 60000)
const ageClass = (d: string) => {
    const m = ageMinutes(d)
    if (m >= 15) return 'bg-red-100 text-red-700 dark:bg-red-950/30'
    if (m >= 8)  return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950/30'
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800'
}

const paymentBadge = (s: string) => {
    const map: Record<string, { label: string; cls: string }> = {
        paid:     { label: 'Paid',     cls: 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400' },
        pending:  { label: 'Unpaid',   cls: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400' },
        refunded: { label: 'Refunded', cls: 'bg-purple-100 text-purple-700' },
        voided:   { label: 'Voided',   cls: 'bg-gray-100 text-gray-500 dark:bg-gray-800' },
    }
    return map[s] ?? { label: s, cls: 'bg-gray-100 text-gray-500' }
}

// ── Computed ─────────────────────────────────────────────────────
const pending   = computed(() => orders.value.filter(o => o.status === 'pending'))
const preparing = computed(() => orders.value.filter(o => o.status === 'preparing'))
const ready     = computed(() => orders.value.filter(o => o.status === 'ready'))

const editSubtotal = computed(() =>
    editItems.value.reduce((s, i) => s + i.price * i.quantity, 0)
)
const editTotal = computed(() => Math.max(0, editSubtotal.value - editDiscount.value))

// Payment delta (only meaningful when the order was already paid)
const wasPaid       = computed(() => editOrder.value?.payment_status === 'paid')
const additionalDue = computed(() => wasPaid.value ? Math.max(0, editTotal.value - originalTotal.value) : 0)
const refundDue     = computed(() => wasPaid.value ? Math.max(0, originalTotal.value - editTotal.value) : 0)

const filteredAddProducts = computed(() => {
    const q = addSearch.value.toLowerCase().trim()
    return q
        ? props.products.filter(p => p.name.toLowerCase().includes(q)).slice(0, 16)
        : props.products.slice(0, 16)
})

// ── API ──────────────────────────────────────────────────────────
const fetchOrders = async () => {
    try {
        const res = await api.get('/api/v1/orders/active')
        const list = res.data.data ?? res.data
        orders.value = (list as any[]).map(normalizeOrder)
    } catch { /* silent */ }
}

const onVisible = () => { if (document.visibilityState === 'visible') fetchOrders() }

onMounted(() => {
    fetchOrders()                                   // immediate first load — no 5s blank wait
    pollInterval = setInterval(fetchOrders, 3000)   // poll every 3s
    document.addEventListener('visibilitychange', onVisible)
})
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
    document.removeEventListener('visibilitychange', onVisible)
})

const updateStatus = async (orderId: number, status: string) => {
    updatingId.value = orderId
    try {
        await api.patch(`/api/v1/orders/${orderId}/status`, { status })
        await fetchOrders()
        toast.success(`Order updated → ${status}`)
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to update')
    } finally {
        updatingId.value = null
    }
}

const cancelOrder = async (orderId: number) => {
    if (!confirm(`Cancel order #${orderId}? This cannot be undone.`)) return
    updatingId.value = orderId
    try {
        await api.post(`/api/v1/orders/${orderId}/cancel`, { reason: 'Cancelled from kitchen' })
        await fetchOrders()
        toast.success(`Order #${orderId} cancelled`)
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to cancel')
    } finally {
        updatingId.value = null
    }
}

// ── Edit modal ───────────────────────────────────────────────────
const openEdit = (order: Order) => {
    editOrder.value = order
    originalTotal.value = order.total_amount
    editItems.value = order.items.map(i => ({
        product_id: i.product.id,
        name: i.product.name,
        price: i.product.price,
        quantity: i.quantity,
    }))
    editNotes.value = order.notes ?? ''
    editDiscount.value = 0
    addSearch.value = ''
    editOpen.value = true
}

const changeQty = (idx: number, delta: number) => {
    const next = editItems.value[idx].quantity + delta
    if (next <= 0) editItems.value.splice(idx, 1)
    else editItems.value[idx].quantity = next
}

const addProduct = (product: Product) => {
    const existing = editItems.value.find(i => i.product_id === product.id)
    if (existing) { existing.quantity++; return }
    editItems.value.push({ product_id: product.id, name: product.name, price: product.price, quantity: 1 })
}

const saveEdit = async () => {
    if (!editOrder.value || editItems.value.length === 0) return
    editSaving.value = true
    try {
        const res = await api.put(`/api/v1/orders/${editOrder.value.id}`, {
            notes: editNotes.value || null,
            discount_amount: editDiscount.value,
            items: editItems.value.map(i => ({ product_id: i.product_id, quantity: i.quantity })),
        })
        const updated = normalizeOrder(res.data.data ?? res.data)
        const idx = orders.value.findIndex(o => o.id === editOrder.value!.id)
        if (idx !== -1) orders.value[idx] = updated
        toast.success('Order updated successfully')
        editOpen.value = false
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to save changes')
    } finally {
        editSaving.value = false
    }
}
</script>

<template>
    <Head title="Kitchen Monitor" />

    <div class="space-y-6">
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-xl border bg-yellow-50 dark:bg-yellow-950/20 p-4 text-center">
                <p class="text-xs font-medium text-yellow-700 dark:text-yellow-400 uppercase tracking-wide">Pending</p>
                <p class="text-4xl font-black text-yellow-600 mt-1">{{ pending.length }}</p>
            </div>
            <div class="rounded-xl border bg-blue-50 dark:bg-blue-950/20 p-4 text-center">
                <p class="text-xs font-medium text-blue-700 dark:text-blue-400 uppercase tracking-wide">Preparing</p>
                <p class="text-4xl font-black text-blue-600 mt-1">{{ preparing.length }}</p>
            </div>
            <div class="rounded-xl border bg-green-50 dark:bg-green-950/20 p-4 text-center">
                <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wide">Ready</p>
                <p class="text-4xl font-black text-green-600 mt-1">{{ ready.length }}</p>
            </div>
        </div>

        <!-- Columns -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Pending -->
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-yellow-600 mb-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-yellow-400 inline-block" /> Pending
                </h2>
                <div class="space-y-3">
                    <div v-if="pending.length === 0" class="rounded-xl border-2 border-dashed p-6 text-center text-sm text-muted-foreground">No pending orders</div>
                    <div v-for="order in pending" :key="order.id"
                         class="rounded-xl border-l-4 border-yellow-500 bg-card shadow-sm">
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-1">
                                <span class="text-2xl font-black">{{ order.queue_number ? '#' + order.queue_number : 'Order #' + order.id }}</span>
                                <div class="flex items-center gap-1.5">
                                    <span :class="['text-xs rounded-full px-2 py-0.5 font-medium', ageClass(order.created_at)]">{{ ageMinutes(order.created_at) }}m</span>
                                    <button @click="openEdit(order)" class="rounded-full p-1 hover:bg-muted text-muted-foreground" title="Edit"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button @click="cancelOrder(order.id)" :disabled="updatingId === order.id" class="rounded-full p-1 hover:bg-red-50 dark:hover:bg-red-950/30 text-muted-foreground hover:text-red-600 disabled:opacity-40" title="Cancel order"><X class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                <span :class="['text-xs rounded-full px-2 py-0.5 font-semibold', paymentBadge(order.payment_status).cls]">{{ paymentBadge(order.payment_status).label }}</span>
                                <span class="text-xs text-muted-foreground capitalize">{{ order.order_type.replace('_', ' ') }}<span v-if="order.table_number"> · {{ order.table_number }}</span></span>
                                <span v-if="order.customer_name" class="text-xs font-semibold text-foreground">{{ order.customer_name }}</span>
                                <span v-if="order.customer_contact" class="text-xs text-muted-foreground">📞 {{ order.customer_contact }}</span>
                            </div>
                            <ul class="text-sm space-y-0.5 mb-2">
                                <li v-for="item in order.items" :key="item.id">
                                    <span class="font-bold">{{ item.quantity }}×</span> {{ item.product.name }}
                                </li>
                            </ul>
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 mr-2 space-y-0.5">
                                    <p v-if="order.notes" class="text-xs text-muted-foreground italic">{{ order.notes }}</p>
                                    <p v-if="order.customer_address" class="text-xs text-orange-600 dark:text-orange-400 font-medium">📍 {{ order.customer_address }}</p>
                                </div>
                                <span class="text-sm font-bold text-primary shrink-0">{{ formatPrice(order.total_amount) }}</span>
                            </div>
                            <button @click="updateStatus(order.id, 'preparing')" :disabled="updatingId === order.id"
                                class="w-full rounded-lg bg-blue-600 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                                <RefreshCw v-if="updatingId === order.id" class="inline h-3 w-3 animate-spin mr-1" />Start Preparing
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preparing -->
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-blue-600 mb-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-blue-400 inline-block" /> Preparing
                </h2>
                <div class="space-y-3">
                    <div v-if="preparing.length === 0" class="rounded-xl border-2 border-dashed p-6 text-center text-sm text-muted-foreground">Nothing being prepared</div>
                    <div v-for="order in preparing" :key="order.id"
                         class="rounded-xl border-l-4 border-blue-500 bg-card shadow-sm">
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-1">
                                <span class="text-2xl font-black">{{ order.queue_number ? '#' + order.queue_number : 'Order #' + order.id }}</span>
                                <div class="flex items-center gap-1.5">
                                    <span :class="['text-xs rounded-full px-2 py-0.5 font-medium', ageClass(order.created_at)]">{{ ageMinutes(order.created_at) }}m</span>
                                    <button @click="openEdit(order)" class="rounded-full p-1 hover:bg-muted text-muted-foreground" title="Edit"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button @click="cancelOrder(order.id)" :disabled="updatingId === order.id" class="rounded-full p-1 hover:bg-red-50 dark:hover:bg-red-950/30 text-muted-foreground hover:text-red-600 disabled:opacity-40" title="Cancel order"><X class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                <span :class="['text-xs rounded-full px-2 py-0.5 font-semibold', paymentBadge(order.payment_status).cls]">{{ paymentBadge(order.payment_status).label }}</span>
                                <span class="text-xs text-muted-foreground capitalize">{{ order.order_type.replace('_', ' ') }}<span v-if="order.table_number"> · {{ order.table_number }}</span></span>
                                <span v-if="order.customer_name" class="text-xs font-semibold text-foreground">{{ order.customer_name }}</span>
                                <span v-if="order.customer_contact" class="text-xs text-muted-foreground">📞 {{ order.customer_contact }}</span>
                            </div>
                            <ul class="text-sm space-y-0.5 mb-2">
                                <li v-for="item in order.items" :key="item.id">
                                    <span class="font-bold">{{ item.quantity }}×</span> {{ item.product.name }}
                                </li>
                            </ul>
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 mr-2 space-y-0.5">
                                    <p v-if="order.notes" class="text-xs text-muted-foreground italic">{{ order.notes }}</p>
                                    <p v-if="order.customer_address" class="text-xs text-orange-600 dark:text-orange-400 font-medium">📍 {{ order.customer_address }}</p>
                                </div>
                                <span class="text-sm font-bold text-primary shrink-0">{{ formatPrice(order.total_amount) }}</span>
                            </div>
                            <button @click="updateStatus(order.id, 'ready')" :disabled="updatingId === order.id"
                                class="w-full rounded-lg bg-green-600 py-2 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-50">
                                <RefreshCw v-if="updatingId === order.id" class="inline h-3 w-3 animate-spin mr-1" />Mark Ready
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ready -->
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-green-600 mb-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-green-400 inline-block" /> Ready for Pickup
                </h2>
                <div class="space-y-3">
                    <div v-if="ready.length === 0" class="rounded-xl border-2 border-dashed p-6 text-center text-sm text-muted-foreground">No orders ready</div>
                    <div v-for="order in ready" :key="order.id"
                         class="rounded-xl border-l-4 border-green-500 bg-card shadow-sm">
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-1">
                                <span class="text-2xl font-black">{{ order.queue_number ? '#' + order.queue_number : 'Order #' + order.id }}</span>
                                <div class="flex items-center gap-1.5">
                                    <span :class="['text-xs rounded-full px-2 py-0.5 font-medium', ageClass(order.created_at)]">{{ ageMinutes(order.created_at) }}m</span>
                                    <button @click="openEdit(order)" class="rounded-full p-1 hover:bg-muted text-muted-foreground" title="Edit"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button @click="cancelOrder(order.id)" :disabled="updatingId === order.id" class="rounded-full p-1 hover:bg-red-50 dark:hover:bg-red-950/30 text-muted-foreground hover:text-red-600 disabled:opacity-40" title="Cancel order"><X class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                <span :class="['text-xs rounded-full px-2 py-0.5 font-semibold', paymentBadge(order.payment_status).cls]">{{ paymentBadge(order.payment_status).label }}</span>
                                <span class="text-xs text-muted-foreground capitalize">{{ order.order_type.replace('_', ' ') }}<span v-if="order.table_number"> · {{ order.table_number }}</span></span>
                                <span v-if="order.customer_name" class="text-xs font-semibold text-foreground">{{ order.customer_name }}</span>
                                <span v-if="order.customer_contact" class="text-xs text-muted-foreground">📞 {{ order.customer_contact }}</span>
                            </div>
                            <ul class="text-sm space-y-0.5 mb-2">
                                <li v-for="item in order.items" :key="item.id">
                                    <span class="font-bold">{{ item.quantity }}×</span> {{ item.product.name }}
                                </li>
                            </ul>
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1 mr-2 space-y-0.5">
                                    <p v-if="order.notes" class="text-xs text-muted-foreground italic">{{ order.notes }}</p>
                                    <p v-if="order.customer_address" class="text-xs text-orange-600 dark:text-orange-400 font-medium">📍 {{ order.customer_address }}</p>
                                </div>
                                <span class="text-sm font-bold text-primary shrink-0">{{ formatPrice(order.total_amount) }}</span>
                            </div>
                            <button @click="updateStatus(order.id, 'completed')" :disabled="updatingId === order.id"
                                class="w-full rounded-lg bg-gray-700 py-2 text-sm font-bold text-white hover:bg-gray-800 disabled:opacity-50">
                                <RefreshCw v-if="updatingId === order.id" class="inline h-3 w-3 animate-spin mr-1" />Mark Completed
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Edit Order Modal ───────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="editOpen && editOrder"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                 @click.self="editOpen = false">

                <!-- Wide 2-panel modal -->
                <div class="w-full max-w-3xl rounded-2xl bg-background shadow-2xl flex flex-col max-h-[92vh]">

                    <!-- Header -->
                    <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
                        <div>
                            <h3 class="font-bold text-lg">
                                Edit {{ editOrder.queue_number ? 'Queue #' + editOrder.queue_number : 'Order #' + editOrder.id }}
                            </h3>
                            <p class="text-xs text-muted-foreground capitalize mt-0.5">
                                {{ editOrder.order_type.replace('_', ' ') }}
                                <span v-if="editOrder.table_number"> · {{ editOrder.table_number }}</span>
                                &nbsp;·&nbsp;
                                <span :class="['rounded-full px-2 py-0.5 font-semibold', paymentBadge(editOrder.payment_status).cls]">
                                    {{ paymentBadge(editOrder.payment_status).label }}
                                </span>
                            </p>
                        </div>
                        <button @click="editOpen = false" class="rounded-full p-1.5 hover:bg-muted">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Body: 2 columns -->
                    <div class="flex flex-col md:flex-row flex-1 min-h-0 overflow-hidden">

                        <!-- LEFT: Add products -->
                        <div class="md:w-72 border-b md:border-b-0 md:border-r flex flex-col shrink-0">
                            <div class="p-4 border-b">
                                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Add Items</p>
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                    <input v-model="addSearch" type="text" placeholder="Search products…"
                                        class="w-full rounded-lg border bg-background pl-8 pr-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto p-3 space-y-1.5">
                                <button v-for="p in filteredAddProducts" :key="p.id"
                                    @click="addProduct(p)"
                                    class="w-full flex items-center justify-between rounded-lg border bg-card px-3 py-2.5 text-left hover:border-primary hover:bg-primary/5 transition group">
                                    <div>
                                        <p class="text-sm font-medium leading-tight">{{ p.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ p.category }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="text-sm font-bold text-primary">{{ formatPrice(p.price) }}</span>
                                        <Plus class="h-4 w-4 text-primary opacity-0 group-hover:opacity-100 transition-opacity" />
                                    </div>
                                </button>
                                <p v-if="filteredAddProducts.length === 0" class="text-center text-xs text-muted-foreground py-6">No products found</p>
                            </div>
                        </div>

                        <!-- RIGHT: Cart -->
                        <div class="flex-1 flex flex-col min-h-0 min-w-0">

                            <!-- Cart items -->
                            <div class="flex-1 overflow-y-auto p-4 space-y-2 min-h-0">
                                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Order Items</p>
                                <div v-if="editItems.length === 0" class="rounded-xl border-2 border-dashed p-8 text-center text-sm text-muted-foreground flex flex-col items-center gap-2">
                                    <ShoppingCart class="h-8 w-8 opacity-30" />
                                    <span>No items — add from the left panel</span>
                                </div>
                                <div v-for="(item, idx) in editItems" :key="item.product_id"
                                     class="flex gap-2 items-center rounded-lg border bg-muted/20 px-3 py-2.5">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold truncate">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatPrice(item.price) }} each</p>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button @click="changeQty(idx, -1)" class="rounded bg-muted p-0.5 hover:bg-muted/80"><Minus class="h-3 w-3" /></button>
                                        <span class="w-6 text-center text-sm font-bold">{{ item.quantity }}</span>
                                        <button @click="changeQty(idx, 1)"  class="rounded bg-muted p-0.5 hover:bg-muted/80"><Plus  class="h-3 w-3" /></button>
                                        <button @click="editItems.splice(idx, 1)" class="ml-1 rounded bg-destructive/10 p-0.5 text-destructive hover:bg-destructive/20"><X class="h-3 w-3" /></button>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes + discount -->
                            <div class="px-4 pb-2 grid grid-cols-2 gap-3 shrink-0">
                                <div>
                                    <label class="text-xs font-medium text-muted-foreground block mb-1">Notes</label>
                                    <input v-model="editNotes" type="text" placeholder="Special instructions…"
                                        class="w-full rounded-lg border bg-background px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-muted-foreground block mb-1">Discount (₱)</label>
                                    <input v-model.number="editDiscount" type="number" min="0" step="0.01"
                                        class="w-full rounded-lg border bg-background px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                                </div>
                            </div>

                            <!-- Totals (same style as POS cart) -->
                            <div class="px-4 py-3 border-t space-y-1 bg-muted/30 shrink-0">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Subtotal</span>
                                    <span>{{ formatPrice(editSubtotal) }}</span>
                                </div>
                                <div v-if="editDiscount > 0" class="flex justify-between text-sm text-red-600">
                                    <span>Discount</span>
                                    <span>-{{ formatPrice(editDiscount) }}</span>
                                </div>
                                <div class="flex justify-between font-bold text-base border-t pt-1 mt-1">
                                    <span>NEW TOTAL</span>
                                    <span class="text-primary">{{ formatPrice(editTotal) }}</span>
                                </div>

                                <!-- Payment delta (shown when order was already paid) -->
                                <template v-if="wasPaid">
                                    <div class="border-t pt-2 mt-1 space-y-1">
                                        <div class="flex justify-between text-xs text-muted-foreground">
                                            <span>Previously paid</span>
                                            <span>{{ formatPrice(originalTotal) }}</span>
                                        </div>
                                        <div v-if="additionalDue > 0" class="flex justify-between text-sm font-bold text-orange-600 bg-orange-50 dark:bg-orange-950/30 rounded-lg px-3 py-2 mt-1">
                                            <span>Collect additional</span>
                                            <span>{{ formatPrice(additionalDue) }}</span>
                                        </div>
                                        <div v-else-if="refundDue > 0" class="flex justify-between text-sm font-bold text-purple-600 bg-purple-50 dark:bg-purple-950/30 rounded-lg px-3 py-2 mt-1">
                                            <span>Refund to customer</span>
                                            <span>{{ formatPrice(refundDue) }}</span>
                                        </div>
                                        <div v-else class="text-xs text-center text-muted-foreground pt-1">
                                            Total unchanged — no adjustment needed
                                        </div>
                                    </div>
                                </template>
                                <!-- Unpaid: just show what to collect -->
                                <template v-else>
                                    <div class="flex justify-between text-sm font-bold text-green-600 bg-green-50 dark:bg-green-950/20 rounded-lg px-3 py-2 mt-2">
                                        <span>Amount to collect</span>
                                        <span>{{ formatPrice(editTotal) }}</span>
                                    </div>
                                </template>
                            </div>

                            <!-- Actions -->
                            <div class="p-4 flex gap-3 shrink-0 border-t">
                                <button @click="editOpen = false" class="flex-1 rounded-lg border py-2.5 text-sm font-medium hover:bg-muted">Cancel</button>
                                <button @click="saveEdit" :disabled="editSaving || editItems.length === 0"
                                    class="flex-1 rounded-lg bg-primary py-2.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ editSaving ? 'Saving…' : 'Save Changes' }}
                                </button>
                            </div>
                        </div>
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
