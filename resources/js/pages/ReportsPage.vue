<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import {
    BarChart3, Download, RefreshCw, TrendingUp, TrendingDown,
    DollarSign, Plus, X, Search, ChevronLeft, ChevronRight,
    ShoppingBag, ClipboardList, Package,
} from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Reports', href: '/reports' },
        ],
    },
})

// ── Types ─────────────────────────────────────────────────────────────────────
interface DailyReport {
    date: string; total_orders: number; total_sales: number; total_discount: number
}
interface MonthlyReport {
    month: string; total_orders: number; total_sales: number; total_discount: number
}
interface ProductSale {
    product_id: number; product_name: string; total_quantity: number; total_sales: number
}
interface FtSummary {
    period: { start: string; end: string }
    orders: { total: number; count: number }
    payments: { total: number; count: number }
    expenses: { total: number; count: number }
    net: number
    by_tender: { tender: string; total: number; count: number }[]
}
interface FtTransaction {
    id: number; type: string; amount: number; description: string; transacted_at: string
    user?: { name: string }; tender?: { name: string }
}
interface OrderRow {
    id: number; queue_number: number | null; order_type: string; status: string
    payment_status: string; table_number: string | null; notes: string | null
    total_amount: number; items: { data: any[] } | any[]; user?: { data?: any; name?: string }
    created_at: string
}
interface InvTransaction {
    id: number; type: string; quantity: number; old_quantity: number; new_quantity: number
    reference: string | null; notes: string | null; created_at: string
    ingredient?: { id: number; name: string; unit: string }
    user?: { name: string }
}
interface Ingredient { id: number; name: string; unit: string }

const props = defineProps<{
    initialDailyReport: DailyReport
    initialProductSales: ProductSale[]
}>()

// ── Active tab ─────────────────────────────────────────────────────────────────
type Tab = 'orders' | 'inventory' | 'financial' | 'daily' | 'monthly' | 'products'
const tab = ref<Tab>('orders')
const loading = ref(false)

const tabs: { key: Tab; label: string }[] = [
    { key: 'orders',    label: 'Orders' },
    { key: 'inventory', label: 'Inventory' },
    { key: 'financial', label: 'Financial' },
    { key: 'daily',     label: 'Daily Sales' },
    { key: 'monthly',   label: 'Monthly Sales' },
    { key: 'products',  label: 'Product Sales' },
]

// ── Daily / Monthly ────────────────────────────────────────────────────────────
const today = new Date().toISOString().split('T')[0]
const selectedDate = ref(today)
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const dailyReport = ref<DailyReport | null>(props.initialDailyReport)
const monthlyReport = ref<MonthlyReport | null>(null)

// ── Products ───────────────────────────────────────────────────────────────────
const productSales = ref<ProductSale[]>(props.initialProductSales)
const prodDateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const prodDateTo = ref(today)

// ── Orders ─────────────────────────────────────────────────────────────────────
const ordSearch = ref('')
const ordDateFrom = ref(today)
const ordDateTo = ref(today)
const ordStatus = ref('')
const ordPayment = ref('')
const ordersData = ref<OrderRow[]>([])
const ordersMeta = ref<any>(null)
const ordPage = ref(1)

// ── Inventory transactions ─────────────────────────────────────────────────────
const invDateFrom = ref(today)
const invDateTo = ref(today)
const invType = ref('')
const invIngredientId = ref('')
const invTransactions = ref<InvTransaction[]>([])
const invMeta = ref<any>(null)
const invPage = ref(1)
const ingredients = ref<Ingredient[]>([])

// ── Financial ─────────────────────────────────────────────────────────────────
const ftStartDate = ref(today)
const ftEndDate = ref(today)
const ftTypeFilter = ref('')
const ftSummary = ref<FtSummary | null>(null)
const ftTransactions = ref<FtTransaction[]>([])
const ftMeta = ref<any>(null)
const showExpenseForm = ref(false)
const expenseForm = ref({ description: '', amount: '', notes: '' })
const expenseSaving = ref(false)

// ── Helpers ───────────────────────────────────────────────────────────────────
const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December']
const monthName = (n: number) => monthNames[n - 1] ?? ''

const fmt = (v: number | string | null | undefined) =>
    '₱' + parseFloat(String(v ?? 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 })

const itemCount = (items: any) =>
    Array.isArray(items) ? items.length : (items?.data?.length ?? 0)

const fmtDatetime = (s: string) => {
    if (!s) return '—'
    const d = new Date(s)
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) + ' ' +
        d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}

const statusBadge = (s: string) => ({
    pending:   'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    preparing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    ready:     'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}[s] ?? 'bg-muted text-muted-foreground')

const payBadge = (s: string) => ({
    paid:     'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    pending:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    refunded: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    voided:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}[s] ?? 'bg-muted text-muted-foreground')

const invTypeBadge = (t: string) => ({
    stock_in:   'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    stock_out:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    adjustment: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    waste:      'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    usage:      'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    purchase:   'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
}[t] ?? 'bg-muted text-muted-foreground')

const typeLabel = (t: string) => ({ order: 'Order', payment: 'Payment', expense: 'Expense' }[t] ?? t)
const typeBadgeClass = (t: string) => ({
    order:   'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    payment: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    expense: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}[t] ?? 'bg-muted text-muted-foreground')

const orderTypeBadge = (t: string) => ({ dine_in: 'Dine-In', takeout: 'Takeout', delivery: 'Delivery' }[t] ?? t)

const topProducts = computed(() =>
    [...productSales.value].sort((a, b) => b.total_sales - a.total_sales).slice(0, 10)
)

// ── Data loading ──────────────────────────────────────────────────────────────
const loadOrders = async (page = 1) => {
    ordPage.value = page
    const res = await api.get('/api/v1/orders', {
        params: {
            page,
            search: ordSearch.value || undefined,
            date_from: ordDateFrom.value || undefined,
            date_to: ordDateTo.value || undefined,
            status: ordStatus.value || undefined,
            payment_status: ordPayment.value || undefined,
        },
    })
    ordersData.value = (res.data.data ?? []).map((o: any) => ({
        ...o,
        total_amount: parseFloat(o.total_amount ?? 0),
    }))
    ordersMeta.value = res.data.meta ?? null
}

const loadInventory = async (page = 1) => {
    invPage.value = page
    const res = await api.get('/api/v1/reports/inventory-transactions', {
        params: {
            page,
            date_from: invDateFrom.value || undefined,
            date_to: invDateTo.value || undefined,
            type: invType.value || undefined,
            ingredient_id: invIngredientId.value || undefined,
        },
    })
    invTransactions.value = res.data.data ?? []
    invMeta.value = res.data.meta ?? null
}

const loadFinancial = async () => {
    const [summaryRes, listRes] = await Promise.all([
        api.get('/api/v1/financial-transactions/summary', {
            params: { start_date: ftStartDate.value, end_date: ftEndDate.value },
        }),
        api.get('/api/v1/financial-transactions', {
            params: {
                start_date: ftStartDate.value,
                end_date: ftEndDate.value,
                type: ftTypeFilter.value || undefined,
            },
        }),
    ])
    ftSummary.value = summaryRes.data
    ftTransactions.value = listRes.data.data ?? listRes.data
    ftMeta.value = listRes.data.meta ?? null
}

const generateReport = async () => {
    loading.value = true
    try {
        if (tab.value === 'orders') {
            await loadOrders(1)
        } else if (tab.value === 'daily') {
            const res = await api.get('/api/v1/reports/daily-sales', { params: { date: selectedDate.value } })
            dailyReport.value = res.data
        } else if (tab.value === 'monthly') {
            const res = await api.get('/api/v1/reports/monthly-sales', { params: { year: selectedYear.value, month: selectedMonth.value } })
            monthlyReport.value = res.data
        } else if (tab.value === 'products') {
            const res = await api.get('/api/v1/reports/product-sales', {
                params: { start_date: prodDateFrom.value, end_date: prodDateTo.value },
            })
            productSales.value = res.data
        } else if (tab.value === 'inventory') {
            await loadInventory(1)
        } else if (tab.value === 'financial') {
            await loadFinancial()
        }
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to load report')
    } finally {
        loading.value = false
    }
}

const saveExpense = async () => {
    if (!expenseForm.value.description.trim() || !expenseForm.value.amount) return
    expenseSaving.value = true
    try {
        await api.post('/api/v1/financial-transactions', {
            type: 'expense',
            amount: parseFloat(expenseForm.value.amount),
            description: expenseForm.value.description,
            notes: expenseForm.value.notes || null,
        })
        toast.success('Expense recorded')
        expenseForm.value = { description: '', amount: '', notes: '' }
        showExpenseForm.value = false
        await loadFinancial()
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to save expense')
    } finally {
        expenseSaving.value = false
    }
}

// ── Export ────────────────────────────────────────────────────────────────────
const exportCSV = () => {
    let rows: string[][] = []
    let filename = 'report'

    if (tab.value === 'orders' && ordersData.value.length > 0) {
        filename = `orders-${ordDateFrom.value}-to-${ordDateTo.value}`
        rows = [
            ['ID', 'Queue#', 'Date', 'Type', 'Table', 'Status', 'Payment', 'Items', 'Total'],
            ...ordersData.value.map((o) => [
                String(o.id), String(o.queue_number ?? ''), o.created_at?.slice(0, 16) ?? '',
                o.order_type, o.table_number ?? '', o.status, o.payment_status,
                String(itemCount(o.items)), String(o.total_amount),
            ]),
        ]
    } else if (tab.value === 'inventory' && invTransactions.value.length > 0) {
        filename = `inventory-transactions-${invDateFrom.value}-to-${invDateTo.value}`
        rows = [
            ['Date', 'Ingredient', 'Type', 'Quantity', 'Old Stock', 'New Stock', 'Unit', 'Reference', 'Notes', 'By'],
            ...invTransactions.value.map((t) => [
                t.created_at?.slice(0, 10) ?? '', t.ingredient?.name ?? '',
                t.type, String(t.quantity), String(t.old_quantity), String(t.new_quantity),
                t.ingredient?.unit ?? '', t.reference ?? '', t.notes ?? '', t.user?.name ?? '',
            ]),
        ]
    } else if (tab.value === 'products') {
        filename = `product-sales`
        rows = [['Product', 'Qty Sold', 'Total Sales'], ...productSales.value.map((p) => [p.product_name, String(p.total_quantity), String(p.total_sales)])]
    } else if (tab.value === 'financial' && ftTransactions.value.length > 0) {
        filename = `financial-transactions-${ftStartDate.value}-to-${ftEndDate.value}`
        rows = [
            ['Date', 'Type', 'Description', 'Tender', 'Amount', 'User'],
            ...ftTransactions.value.map((t) => [
                t.transacted_at?.slice(0, 10) ?? '', t.type, t.description,
                t.tender?.name ?? '', String(t.amount), t.user?.name ?? '',
            ]),
        ]
    }

    if (rows.length === 0) { toast.info('No data to export'); return }

    const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
    const a = document.createElement('a')
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv)
    a.download = `${filename}.csv`
    a.click()
    toast.success('CSV downloaded')
}

const printReport = () => window.print()

const switchTab = (t: Tab) => {
    tab.value = t
    generateReport()
}

onMounted(async () => {
    // Pre-load ingredient list for the inventory filter dropdown
    try {
        const res = await api.get('/api/v1/inventory')
        ingredients.value = (res.data.data ?? res.data).map((i: any) => ({ id: i.id, name: i.name, unit: i.unit }))
    } catch { /* non-critical */ }
    await generateReport()
})
</script>

<template>
    <Head title="Reports" />

    <div class="space-y-5">
        <!-- Tab bar -->
        <div class="flex flex-wrap gap-1 rounded-xl border bg-card p-1.5 shadow-sm">
            <button
                v-for="t in tabs" :key="t.key"
                @click="switchTab(t.key)"
                :class="[
                    'rounded-lg px-4 py-2 text-sm font-medium transition',
                    tab === t.key
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                ]"
            >{{ t.label }}</button>
        </div>

        <!-- Filters bar -->
        <div class="rounded-xl border bg-card shadow-sm p-4">
            <div class="flex flex-wrap gap-3 items-end">

                <!-- Orders filters -->
                <template v-if="tab === 'orders'">
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
                        <input v-model="ordDateFrom" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
                        <input v-model="ordDateTo" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Order Status</label>
                        <select v-model="ordStatus" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="preparing">Preparing</option>
                            <option value="ready">Ready</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Payment</label>
                        <select v-model="ordPayment" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Unpaid</option>
                            <option value="refunded">Refunded</option>
                            <option value="voided">Voided</option>
                        </select></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Search</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                            <input v-model="ordSearch" type="text" placeholder="Order #, table, notes…"
                                class="rounded-lg border bg-background pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary w-48"
                                @keydown.enter="generateReport" />
                        </div></div>
                </template>

                <!-- Inventory filters -->
                <template v-if="tab === 'inventory'">
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
                        <input v-model="invDateFrom" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
                        <input v-model="invDateTo" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Transaction Type</label>
                        <select v-model="invType" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Types</option>
                            <option value="stock_in">Stock In</option>
                            <option value="stock_out">Stock Out</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="waste">Waste</option>
                            <option value="usage">Usage</option>
                            <option value="purchase">Purchase</option>
                        </select></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Ingredient</label>
                        <select v-model="invIngredientId" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Ingredients</option>
                            <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">{{ ing.name }}</option>
                        </select></div>
                </template>

                <!-- Daily date -->
                <div v-if="tab === 'daily'">
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Date</label>
                    <input v-model="selectedDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>

                <!-- Monthly pickers -->
                <template v-if="tab === 'monthly'">
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Year</label>
                        <input v-model.number="selectedYear" type="number" min="2020" class="w-24 rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Month</label>
                        <select v-model.number="selectedMonth" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                        </select></div>
                </template>

                <!-- Product sales date range -->
                <template v-if="tab === 'products'">
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
                        <input v-model="prodDateFrom" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
                        <input v-model="prodDateTo" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                </template>

                <!-- Financial date range -->
                <template v-if="tab === 'financial'">
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
                        <input v-model="ftStartDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
                        <input v-model="ftEndDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Type</label>
                        <select v-model="ftTypeFilter" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Types</option>
                            <option value="order">Orders</option>
                            <option value="payment">Payments</option>
                            <option value="expense">Expenses</option>
                        </select></div>
                </template>

                <button @click="generateReport" :disabled="loading"
                    class="rounded-lg bg-primary px-5 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50 flex items-center gap-1.5">
                    <RefreshCw v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                    <BarChart3 v-else class="h-3.5 w-3.5" />
                    Generate
                </button>
                <button @click="exportCSV" class="rounded-lg border bg-background px-4 py-2 text-sm font-medium hover:bg-muted flex items-center gap-1.5">
                    <Download class="h-3.5 w-3.5" /> Export CSV
                </button>
                <button @click="printReport" class="rounded-lg border bg-background px-4 py-2 text-sm font-medium hover:bg-muted">Print</button>
            </div>
        </div>

        <!-- ── Orders ─────────────────────────────────────────────────────────── -->
        <template v-if="tab === 'orders'">
            <div v-if="ordersMeta" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><ClipboardList class="h-3 w-3" /> Total Orders</p>
                    <p class="text-3xl font-black">{{ ordersMeta.total }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1">Paid (this page)</p>
                    <p class="text-3xl font-black text-green-600">{{ ordersData.filter(o => o.payment_status === 'paid').length }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1">Unpaid (this page)</p>
                    <p class="text-3xl font-black text-yellow-600">{{ ordersData.filter(o => o.payment_status === 'pending').length }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3" /> Revenue (page)</p>
                    <p class="text-xl font-black text-green-600">{{ fmt(ordersData.filter(o => o.payment_status === 'paid').reduce((s, o) => s + o.total_amount, 0)) }}</p>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h2 class="font-bold text-sm flex items-center gap-2"><ShoppingBag class="h-4 w-4" /> Orders</h2>
                    <span v-if="ordersMeta" class="text-xs text-muted-foreground">
                        Page {{ ordersMeta.current_page }} of {{ ordersMeta.last_page }} &nbsp;·&nbsp; {{ ordersMeta.total }} total
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Date & Time</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Table</th>
                                <th class="px-4 py-3 text-center">Items</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Payment</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="order in ordersData" :key="order.id" class="hover:bg-muted/20">
                                <td class="px-4 py-3">
                                    <p class="font-bold">#{{ order.id }}</p>
                                    <p v-if="order.queue_number" class="text-xs text-muted-foreground">Q{{ order.queue_number }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-muted-foreground text-xs">{{ fmtDatetime(order.created_at) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">{{ orderTypeBadge(order.order_type) }}</span></td>
                                <td class="px-4 py-3 text-muted-foreground">{{ order.table_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-center font-medium">{{ itemCount(order.items) }}</td>
                                <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-semibold capitalize', statusBadge(order.status)]">{{ order.status }}</span></td>
                                <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-semibold capitalize', payBadge(order.payment_status)]">{{ order.payment_status }}</span></td>
                                <td class="px-4 py-3 text-right font-bold">{{ fmt(order.total_amount) }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground max-w-[140px] truncate">{{ order.notes ?? '—' }}</td>
                            </tr>
                            <tr v-if="ordersData.length === 0 && !loading">
                                <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">No orders found. Adjust filters and click Generate.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="ordersMeta && ordersMeta.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t">
                    <button @click="loadOrders(ordPage - 1)" :disabled="ordPage <= 1 || loading"
                        class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40">
                        <ChevronLeft class="h-3.5 w-3.5" /> Prev
                    </button>
                    <span class="text-xs text-muted-foreground">Showing {{ ordersMeta.from }}–{{ ordersMeta.to }} of {{ ordersMeta.total }}</span>
                    <button @click="loadOrders(ordPage + 1)" :disabled="ordPage >= ordersMeta.last_page || loading"
                        class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40">
                        Next <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </template>

        <!-- ── Inventory Transactions ──────────────────────────────────────────── -->
        <template v-if="tab === 'inventory'">
            <div v-if="invMeta" class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><Package class="h-3 w-3" /> Total Transactions</p>
                    <p class="text-3xl font-black">{{ invMeta.total }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1">Stock In (page)</p>
                    <p class="text-3xl font-black text-green-600">{{ invTransactions.filter(t => t.type === 'stock_in' || t.type === 'purchase').length }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1">Stock Out / Usage (page)</p>
                    <p class="text-3xl font-black text-red-600">{{ invTransactions.filter(t => ['stock_out','waste','usage'].includes(t.type)).length }}</p>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h2 class="font-bold text-sm flex items-center gap-2"><Package class="h-4 w-4" /> Inventory Transactions</h2>
                    <span v-if="invMeta" class="text-xs text-muted-foreground">
                        Page {{ invMeta.current_page }} of {{ invMeta.last_page }} &nbsp;·&nbsp; {{ invMeta.total }} total
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Ingredient</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Before</th>
                                <th class="px-4 py-3 text-right">After</th>
                                <th class="px-4 py-3 text-left">Reference</th>
                                <th class="px-4 py-3 text-left">Notes</th>
                                <th class="px-4 py-3 text-left">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="tx in invTransactions" :key="tx.id" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-muted-foreground whitespace-nowrap text-xs">{{ tx.created_at?.slice(0, 10) }}</td>
                                <td class="px-4 py-2 font-medium">
                                    {{ tx.ingredient?.name ?? '—' }}
                                    <span class="text-xs text-muted-foreground ml-1">{{ tx.ingredient?.unit }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold capitalize', invTypeBadge(tx.type)]">
                                        {{ tx.type.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-bold" :class="['stock_in','purchase'].includes(tx.type) ? 'text-green-600' : 'text-red-600'">
                                    {{ ['stock_in','purchase'].includes(tx.type) ? '+' : '-' }}{{ tx.quantity }}
                                </td>
                                <td class="px-4 py-2 text-right text-muted-foreground">{{ tx.old_quantity }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ tx.new_quantity }}</td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">{{ tx.reference ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-muted-foreground max-w-[140px] truncate">{{ tx.notes ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">{{ tx.user?.name ?? '—' }}</td>
                            </tr>
                            <tr v-if="invTransactions.length === 0 && !loading">
                                <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">No transactions found. Adjust filters and click Generate.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="invMeta && invMeta.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t">
                    <button @click="loadInventory(invPage - 1)" :disabled="invPage <= 1 || loading"
                        class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40">
                        <ChevronLeft class="h-3.5 w-3.5" /> Prev
                    </button>
                    <span class="text-xs text-muted-foreground">Showing {{ invMeta.from }}–{{ invMeta.to }} of {{ invMeta.total }}</span>
                    <button @click="loadInventory(invPage + 1)" :disabled="invPage >= invMeta.last_page || loading"
                        class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40">
                        Next <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </template>

        <!-- ── Financial ──────────────────────────────────────────────────────── -->
        <template v-if="tab === 'financial'">
            <div v-if="ftSummary" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><BarChart3 class="h-3 w-3" /> Orders</p>
                    <p class="text-2xl font-black">{{ ftSummary.orders.count }}</p>
                    <p class="text-sm font-semibold text-blue-600 mt-0.5">{{ fmt(ftSummary.orders.total) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3" /> Payments</p>
                    <p class="text-2xl font-black">{{ ftSummary.payments.count }}</p>
                    <p class="text-sm font-semibold text-green-600 mt-0.5">{{ fmt(ftSummary.payments.total) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingDown class="h-3 w-3" /> Expenses</p>
                    <p class="text-2xl font-black">{{ ftSummary.expenses.count }}</p>
                    <p class="text-sm font-semibold text-red-600 mt-0.5">{{ fmt(ftSummary.expenses.total) }}</p>
                </div>
                <div :class="['rounded-xl border p-4 shadow-sm', ftSummary.net >= 0 ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800']">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><DollarSign class="h-3 w-3" /> Net Cash</p>
                    <p class="text-2xl font-black" :class="ftSummary.net >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600'">{{ fmt(ftSummary.net) }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Payments − Expenses</p>
                </div>
            </div>

            <div v-if="ftSummary && ftSummary.by_tender.length > 0" class="rounded-xl border bg-card shadow-sm p-4">
                <h3 class="font-bold text-sm mb-3">Payments by Tender</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="row in ftSummary.by_tender" :key="row.tender" class="flex items-center justify-between rounded-lg bg-muted/40 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">{{ row.tender }}</p>
                            <p class="text-xs text-muted-foreground">{{ row.count }} transaction{{ row.count !== 1 ? 's' : '' }}</p>
                        </div>
                        <p class="text-base font-bold text-green-600">{{ fmt(row.total) }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h3 class="font-bold text-sm text-muted-foreground uppercase tracking-wider">Transaction Log</h3>
                <button @click="showExpenseForm = !showExpenseForm" class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted transition">
                    <Plus v-if="!showExpenseForm" class="h-3.5 w-3.5" />
                    <X v-else class="h-3.5 w-3.5" />
                    {{ showExpenseForm ? 'Cancel' : 'Record Expense' }}
                </button>
            </div>

            <div v-if="showExpenseForm" class="rounded-xl border bg-card shadow-sm p-4">
                <h4 class="font-semibold text-sm mb-3">New Expense</h4>
                <div class="grid sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Description</label>
                        <input v-model="expenseForm.description" type="text" placeholder="e.g. Charcoal supply, LPG refill" class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Amount (₱)</label>
                        <input v-model="expenseForm.amount" type="number" min="0.01" step="0.01" placeholder="0.00" class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Notes (optional)</label>
                        <input v-model="expenseForm.notes" type="text" placeholder="Additional details" class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div class="flex items-end">
                        <button @click="saveExpense" :disabled="expenseSaving" class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50">
                            {{ expenseSaving ? 'Saving…' : 'Save Expense' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="ftTransactions.length > 0" class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-left">Tender</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-left">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="tx in ftTransactions" :key="tx.id" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-muted-foreground whitespace-nowrap">{{ tx.transacted_at?.slice(0, 10) }}</td>
                                <td class="px-4 py-2"><span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', typeBadgeClass(tx.type)]">{{ typeLabel(tx.type) }}</span></td>
                                <td class="px-4 py-2 max-w-xs truncate">{{ tx.description }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ tx.tender?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-bold" :class="tx.type === 'expense' ? 'text-red-600' : 'text-green-600'">
                                    {{ tx.type === 'expense' ? '-' : '' }}{{ fmt(tx.amount) }}
                                </td>
                                <td class="px-4 py-2 text-muted-foreground text-xs">{{ tx.user?.name ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="ftMeta" class="px-4 py-3 border-t text-xs text-muted-foreground">
                    Showing {{ ftTransactions.length }} of {{ ftMeta.total }} transactions
                </div>
            </div>
            <div v-else-if="!loading" class="rounded-xl border bg-card p-10 text-center shadow-sm text-muted-foreground text-sm">
                Select a date range and click <strong>Generate</strong> to load financial records.
            </div>
        </template>

        <!-- ── Daily Sales ────────────────────────────────────────────────────── -->
        <template v-if="tab === 'daily' && dailyReport">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="font-bold text-base mb-4">Daily Sales — {{ dailyReport.date }}</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg bg-muted/40 p-4">
                        <p class="text-xs text-muted-foreground mb-1">Total Orders</p>
                        <p class="text-3xl font-black">{{ dailyReport.total_orders }}</p>
                    </div>
                    <div class="rounded-lg bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3" /> Revenue</p>
                        <p class="text-2xl font-black text-green-600">{{ fmt(dailyReport.total_sales) }}</p>
                    </div>
                    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950/20 p-4">
                        <p class="text-xs text-muted-foreground mb-1">Discounts</p>
                        <p class="text-2xl font-black text-yellow-600">{{ fmt(dailyReport.total_discount) }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Monthly Sales ──────────────────────────────────────────────────── -->
        <template v-if="tab === 'monthly' && monthlyReport">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="font-bold text-base mb-4">
                    Monthly Sales — {{ monthName(Number(monthlyReport.month?.split('-')[1])) }} {{ monthlyReport.month?.split('-')[0] }}
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg bg-muted/40 p-4">
                        <p class="text-xs text-muted-foreground mb-1">Total Orders</p>
                        <p class="text-3xl font-black">{{ monthlyReport.total_orders }}</p>
                    </div>
                    <div class="rounded-lg bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3" /> Revenue</p>
                        <p class="text-2xl font-black text-green-600">{{ fmt(monthlyReport.total_sales) }}</p>
                    </div>
                    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950/20 p-4">
                        <p class="text-xs text-muted-foreground mb-1">Discounts</p>
                        <p class="text-2xl font-black text-yellow-600">{{ fmt(monthlyReport.total_discount) }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Product Sales ──────────────────────────────────────────────────── -->
        <template v-if="tab === 'products'">
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="font-bold text-sm">Product Sales — {{ prodDateFrom }} to {{ prodDateTo }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Product</th>
                                <th class="px-4 py-3 text-right">Qty Sold</th>
                                <th class="px-4 py-3 text-right">Revenue</th>
                                <th class="px-4 py-3 text-left">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(item, i) in topProducts" :key="item.product_id" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="px-4 py-2 font-medium">{{ item.product_name }}</td>
                                <td class="px-4 py-2 text-right">{{ item.total_quantity }}</td>
                                <td class="px-4 py-2 text-right font-bold text-green-600">{{ fmt(item.total_sales) }}</td>
                                <td class="px-4 py-2 w-40">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-primary rounded-full"
                                                :style="{ width: topProducts[0]?.total_sales ? ((Number(item.total_sales) / Number(topProducts[0].total_sales)) * 100) + '%' : '0%' }" />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="topProducts.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No data available for this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
