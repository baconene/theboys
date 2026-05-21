<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import {
    BarChart3, DollarSign, Plus, Trash2, ChevronLeft, ChevronRight,
    TrendingUp, TrendingDown, ChevronDown, Search,
} from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Financial', href: '/financial' },
        ],
    },
})

// ── Types ─────────────────────────────────────────────────────────────────────
interface FtSummary {
    period: { start: string; end: string }
    orders: { total: number; count: number }
    payments: { total: number; count: number }
    expenses: { total: number; count: number }
    income_adjustments: { total: number; count: number }
    payroll: { total: number; count: number }
    net: number
    by_tender: { tender: string; total: number; count: number }[]
}
interface FtTransaction {
    id: number; type: string; amount: number; description: string; transacted_at: string
    running_balance: number
    user?: { name: string }; tender?: { name: string }
}

// ── State ──────────────────────────────────────────────────────────────────────
const today = new Date().toISOString().split('T')[0]
const ftStartDate = ref(today)
const ftEndDate = ref(today)
const ftTypeFilter = ref('')
const loading = ref(false)
const ftSummary = ref<FtSummary | null>(null)
const ftTransactions = ref<FtTransaction[]>([])
const ftMeta = ref<any>(null)
const ftPage = ref(1)
const showEntryForm = ref(false)
const entryForm = ref({ type: 'expense' as 'expense' | 'income_adjustment', description: '', amount: '', notes: '', transacted_at: '' })
const entrySaving = ref(false)
const ftDeleting = ref<number | null>(null)
const summaryOpen = ref(true)
const ftSearch = ref('')
const ftSortKey = ref<'transacted_at' | 'type' | 'amount' | 'description'>('transacted_at')
const ftSortDir = ref<'asc' | 'desc'>('desc')

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmt = (v: number | string | null | undefined) =>
    '₱' + parseFloat(String(v ?? 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 })

const fmtDatetime = (s: string) => {
    if (!s) return '—'
    const d = new Date(s)
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: '2-digit' }) + ' ' +
        d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}

const typeLabel = (t: string) => ({
    order: 'Order', payment: 'Payment', expense: 'Expense', income_adjustment: 'Income Adj.', payroll: 'Payroll',
}[t] ?? t)

const typeBadgeClass = (t: string) => ({
    order:             'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    payment:           'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    expense:           'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    income_adjustment: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    payroll:           'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
}[t] ?? 'bg-muted text-muted-foreground')

const isCredit = (t: string) => t === 'payment' || t === 'income_adjustment'

// ── Computed ───────────────────────────────────────────────────────────────────
const pieData = computed(() => {
    if (!ftSummary.value) return []
    const s = ftSummary.value
    const items = [
        { label: 'Orders',      value: s.orders.total,                   color: '#3b82f6' },
        { label: 'Payments',    value: s.payments.total,                 color: '#22c55e' },
        { label: 'Expenses',    value: s.expenses.total,                 color: '#ef4444' },
        { label: 'Income Adj.', value: s.income_adjustments?.total ?? 0, color: '#14b8a6' },
        { label: 'Payroll',     value: s.payroll?.total ?? 0,            color: '#a855f7' },
    ].filter(i => i.value > 0)
    const total = items.reduce((sum, i) => sum + i.value, 0)
    if (!total || items.length === 0) return []
    const R = 70, r = 42, cx = 90, cy = 90
    const arcPath = (sa: number, ea: number): string => {
        const sweep = ea - sa
        if (sweep >= Math.PI * 2 - 0.001) {
            // Full circle: split into two halves to avoid degenerate arc
            const mid = sa + Math.PI
            return [
                `M${cx + R * Math.cos(sa)} ${cy + R * Math.sin(sa)}`,
                `A${R} ${R} 0 0 1 ${cx + R * Math.cos(mid)} ${cy + R * Math.sin(mid)}`,
                `A${R} ${R} 0 0 1 ${cx + R * Math.cos(ea)} ${cy + R * Math.sin(ea)}`,
                `L${cx + r * Math.cos(ea)} ${cy + r * Math.sin(ea)}`,
                `A${r} ${r} 0 0 0 ${cx + r * Math.cos(mid)} ${cy + r * Math.sin(mid)}`,
                `A${r} ${r} 0 0 0 ${cx + r * Math.cos(sa)} ${cy + r * Math.sin(sa)}Z`,
            ].join(' ')
        }
        const large = sweep > Math.PI ? 1 : 0
        return [
            `M${cx + R * Math.cos(sa)} ${cy + R * Math.sin(sa)}`,
            `A${R} ${R} 0 ${large} 1 ${cx + R * Math.cos(ea)} ${cy + R * Math.sin(ea)}`,
            `L${cx + r * Math.cos(ea)} ${cy + r * Math.sin(ea)}`,
            `A${r} ${r} 0 ${large} 0 ${cx + r * Math.cos(sa)} ${cy + r * Math.sin(sa)}Z`,
        ].join(' ')
    }
    let angle = -Math.PI / 2
    return items.map(item => {
        const sweep = (item.value / total) * 2 * Math.PI
        const sa = angle, ea = angle + sweep
        angle = ea
        return { ...item, pct: Math.round((item.value / total) * 100), path: arcPath(sa, ea) }
    })
})

const sortedTx = computed(() => {
    let list = ftTransactions.value
    const q = ftSearch.value.trim().toLowerCase()
    if (q) {
        list = list.filter(tx =>
            tx.description.toLowerCase().includes(q) ||
            tx.type.toLowerCase().includes(q) ||
            (tx.tender?.name ?? '').toLowerCase().includes(q) ||
            (tx.user?.name ?? '').toLowerCase().includes(q)
        )
    }
    const dir = ftSortDir.value === 'asc' ? 1 : -1
    return [...list].sort((a, b) => {
        switch (ftSortKey.value) {
            case 'transacted_at': return dir * (new Date(a.transacted_at).getTime() - new Date(b.transacted_at).getTime())
            case 'amount':        return dir * (a.amount - b.amount)
            case 'type':          return dir * a.type.localeCompare(b.type)
            case 'description':   return dir * a.description.localeCompare(b.description)
        }
        return 0
    })
})

const toggleSort = (key: typeof ftSortKey.value) => {
    if (ftSortKey.value === key) ftSortDir.value = ftSortDir.value === 'asc' ? 'desc' : 'asc'
    else { ftSortKey.value = key; ftSortDir.value = 'desc' }
}

// ── Data loading ───────────────────────────────────────────────────────────────
const loadFinancial = async (page = 1) => {
    ftPage.value = page
    try {
        const [summaryRes, listRes] = await Promise.all([
            api.get('/api/v1/financial-transactions/summary', {
                params: { start_date: ftStartDate.value || undefined, end_date: ftEndDate.value || undefined },
            }),
            api.get('/api/v1/financial-transactions', {
                params: {
                    page,
                    start_date: ftStartDate.value || undefined,
                    end_date: ftEndDate.value || undefined,
                    type: ftTypeFilter.value || undefined,
                },
            }),
        ])
        ftSummary.value = summaryRes.data
        ftTransactions.value = listRes.data.data ?? []
        ftMeta.value = listRes.data.meta ?? null
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to load transactions.')
    }
}

// ── Actions ────────────────────────────────────────────────────────────────────
const saveEntry = async () => {
    if (!entryForm.value.description.trim() || !entryForm.value.amount) return
    entrySaving.value = true
    try {
        await api.post('/api/v1/financial-transactions', {
            type: entryForm.value.type,
            amount: parseFloat(entryForm.value.amount),
            description: entryForm.value.description,
            notes: entryForm.value.notes || null,
            transacted_at: entryForm.value.transacted_at || null,
        })
        const label = entryForm.value.type === 'income_adjustment' ? 'Income adjustment' : 'Expense'
        toast.success(`${label} recorded.`)
        entryForm.value = { type: 'expense', description: '', amount: '', notes: '', transacted_at: '' }
        showEntryForm.value = false
        await loadFinancial()
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to save entry.')
    } finally {
        entrySaving.value = false
    }
}

const deleteTransaction = async (tx: FtTransaction) => {
    if (!confirm(`Delete transaction?\n${tx.description}\nAmount: ${fmt(tx.amount)}`)) return
    ftDeleting.value = tx.id
    try {
        await api.delete(`/api/v1/financial-transactions/${tx.id}`)
        toast.success('Transaction deleted.')
        await loadFinancial(ftPage.value)
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to delete transaction.')
    } finally {
        ftDeleting.value = null
    }
}

onMounted(async () => {
    loading.value = true
    try {
        await loadFinancial()
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <Head title="Financial Transactions" />

    <div class="space-y-5 p-6">
        <!-- Collapsible summary section -->
        <div v-if="ftSummary" class="rounded-xl border bg-card shadow-sm overflow-hidden">
            <button @click="summaryOpen = !summaryOpen"
                class="w-full px-4 py-3 flex items-center justify-between hover:bg-muted/30 transition-colors">
                <h2 class="font-bold text-sm flex items-center gap-2">
                    <BarChart3 class="h-4 w-4 text-primary" />
                    Financial Summary
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ ftSummary.period?.start }} – {{ ftSummary.period?.end }}
                    </span>
                </h2>
                <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform duration-200"
                    :class="summaryOpen ? 'rotate-180' : ''" />
            </button>

            <div v-show="summaryOpen" class="border-t p-4 space-y-4">
                <!-- 6 metric cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="rounded-xl border bg-background p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><BarChart3 class="h-3 w-3 text-blue-500" /> Orders</p>
                        <p class="text-2xl font-black">{{ ftSummary.orders.count }}</p>
                        <p class="text-sm font-semibold text-blue-600 mt-0.5">{{ fmt(ftSummary.orders.total) }}</p>
                    </div>
                    <div class="rounded-xl border bg-background p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3 text-green-500" /> Payments</p>
                        <p class="text-2xl font-black">{{ ftSummary.payments.count }}</p>
                        <p class="text-sm font-semibold text-green-600 mt-0.5">{{ fmt(ftSummary.payments.total) }}</p>
                    </div>
                    <div class="rounded-xl border bg-background p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingDown class="h-3 w-3 text-red-500" /> Expenses</p>
                        <p class="text-2xl font-black">{{ ftSummary.expenses.count }}</p>
                        <p class="text-sm font-semibold text-red-600 mt-0.5">{{ fmt(ftSummary.expenses.total) }}</p>
                    </div>
                    <div class="rounded-xl border bg-background p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3 text-teal-500" /> Income Adj.</p>
                        <p class="text-2xl font-black">{{ ftSummary.income_adjustments?.count ?? 0 }}</p>
                        <p class="text-sm font-semibold text-teal-600 mt-0.5">{{ fmt(ftSummary.income_adjustments?.total ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border bg-background p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingDown class="h-3 w-3 text-purple-500" /> Payroll</p>
                        <p class="text-2xl font-black">{{ ftSummary.payroll?.count ?? 0 }}</p>
                        <p class="text-sm font-semibold text-purple-600 mt-0.5">{{ fmt(ftSummary.payroll?.total ?? 0) }}</p>
                    </div>
                    <div :class="['rounded-xl border p-4', ftSummary.net >= 0 ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800']">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><DollarSign class="h-3 w-3" /> Net Cash</p>
                        <p class="text-2xl font-black" :class="ftSummary.net >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600'">{{ fmt(ftSummary.net) }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ ftSummary.net >= 0 ? 'Surplus' : 'Deficit' }}</p>
                    </div>
                </div>

                <!-- Pie chart + Tender breakdown -->
                <div class="grid lg:grid-cols-2 gap-4">
                    <!-- Donut allocation chart -->
                    <div v-if="pieData.length > 0" class="rounded-xl border bg-background p-4">
                        <h3 class="font-bold text-sm mb-3">Allocation by Type</h3>
                        <div class="flex items-center gap-6">
                            <svg viewBox="0 0 180 180" style="width:140px;height:140px;flex-shrink:0">
                                <path v-for="(slice, i) in pieData" :key="i"
                                    :d="slice.path" :fill="slice.color"
                                    class="transition-opacity hover:opacity-75" />
                                <text x="90" y="84" text-anchor="middle" fill="currentColor" fill-opacity="0.45" font-size="10">Total</text>
                                <text x="90" y="100" text-anchor="middle" fill="currentColor" font-size="13" font-weight="bold">
                                    {{ pieData.length }}
                                </text>
                                <text x="90" y="113" text-anchor="middle" fill="currentColor" fill-opacity="0.45" font-size="9">types</text>
                            </svg>
                            <div class="space-y-2 text-xs flex-1 min-w-0">
                                <div v-for="slice in pieData" :key="slice.label" class="flex items-center gap-2">
                                    <span class="shrink-0 w-2.5 h-2.5 rounded-sm" :style="`background:${slice.color}`"></span>
                                    <span class="text-muted-foreground">{{ slice.label }}</span>
                                    <span class="ml-auto font-bold tabular-nums shrink-0">{{ slice.pct }}%</span>
                                    <span class="text-muted-foreground tabular-nums shrink-0 text-[11px]">{{ fmt(slice.value) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments by Tender -->
                    <div v-if="ftSummary.by_tender.length > 0" class="rounded-xl border bg-background p-4">
                        <h3 class="font-bold text-sm mb-3">Payments by Tender</h3>
                        <div class="space-y-2">
                            <div v-for="row in ftSummary.by_tender" :key="row.tender"
                                class="flex items-center justify-between rounded-lg bg-muted/40 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold">{{ row.tender }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.count }} transaction{{ row.count !== 1 ? 's' : '' }}</p>
                                </div>
                                <p class="text-base font-bold text-green-600">{{ fmt(row.total) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and actions bar -->
        <div class="rounded-xl border bg-card shadow-sm p-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
                    <input v-model="ftStartDate" type="date" @change="loadFinancial()"
                        class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
                    <input v-model="ftEndDate" type="date" @change="loadFinancial()"
                        class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Type</label>
                    <select v-model="ftTypeFilter" @change="loadFinancial()" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">All Types</option>
                        <option value="order">Order</option>
                        <option value="payment">Payment</option>
                        <option value="expense">Expense</option>
                        <option value="income_adjustment">Income Adjustment</option>
                        <option value="payroll">Payroll</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Search</label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                        <input v-model="ftSearch" type="text" placeholder="Filter by description, type…"
                            class="pl-8 rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary w-56" />
                    </div>
                </div>
                <button @click="showEntryForm = !showEntryForm" class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90">
                    <Plus class="h-3.5 w-3.5" /> Record Entry
                </button>
            </div>
        </div>

        <!-- Entry form -->
        <div v-if="showEntryForm" class="rounded-xl border bg-card shadow-sm p-4">
            <p class="text-sm font-bold mb-3">Record Expense or Income Adjustment</p>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Type *</label>
                    <select v-model="entryForm.type" class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="expense">Expense</option>
                        <option value="income_adjustment">Income Adjustment</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Amount (₱) *</label>
                    <input v-model="entryForm.amount" type="number" min="0.01" step="0.01" placeholder="0.00"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Description *</label>
                    <input v-model="entryForm.description" type="text" placeholder="e.g. Office supplies, Customer refund"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Date/Time</label>
                    <input v-model="entryForm.transacted_at" type="datetime-local"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Notes</label>
                    <input v-model="entryForm.notes" type="text" placeholder="Optional reference"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button @click="saveEntry" :disabled="entrySaving || !entryForm.description.trim() || !entryForm.amount"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50">
                    {{ entrySaving ? 'Saving…' : 'Record Entry' }}
                </button>
                <button @click="showEntryForm = false" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted">Cancel</button>
            </div>
        </div>

        <!-- Transactions table -->
        <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between">
                <h2 class="font-bold text-sm flex items-center gap-2"><DollarSign class="h-4 w-4" /> Transactions</h2>
                <p v-if="ftSearch" class="text-xs text-muted-foreground">{{ sortedTx.length }} result{{ sortedTx.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left cursor-pointer select-none hover:text-foreground whitespace-nowrap" @click="toggleSort('transacted_at')">
                                <span class="flex items-center gap-1">Date
                                    <span v-if="ftSortKey === 'transacted_at'" class="text-primary">{{ ftSortDir === 'asc' ? '↑' : '↓' }}</span>
                                    <span v-else class="opacity-30">↕</span>
                                </span>
                            </th>
                            <th class="px-4 py-3 text-left cursor-pointer select-none hover:text-foreground" @click="toggleSort('type')">
                                <span class="flex items-center gap-1">Type
                                    <span v-if="ftSortKey === 'type'" class="text-primary">{{ ftSortDir === 'asc' ? '↑' : '↓' }}</span>
                                    <span v-else class="opacity-30">↕</span>
                                </span>
                            </th>
                            <th class="px-4 py-3 text-left cursor-pointer select-none hover:text-foreground" @click="toggleSort('description')">
                                <span class="flex items-center gap-1">Description
                                    <span v-if="ftSortKey === 'description'" class="text-primary">{{ ftSortDir === 'asc' ? '↑' : '↓' }}</span>
                                    <span v-else class="opacity-30">↕</span>
                                </span>
                            </th>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-right cursor-pointer select-none hover:text-foreground whitespace-nowrap" @click="toggleSort('amount')">
                                <span class="flex items-center justify-end gap-1">Amount
                                    <span v-if="ftSortKey === 'amount'" class="text-primary">{{ ftSortDir === 'asc' ? '↑' : '↓' }}</span>
                                    <span v-else class="opacity-30">↕</span>
                                </span>
                            </th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Running Balance</th>
                            <th class="px-4 py-3 text-left">By</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tx in sortedTx" :key="tx.id" class="border-t hover:bg-muted/20 transition-colors">
                            <td class="px-4 py-3 text-sm tabular-nums whitespace-nowrap">{{ fmtDatetime(tx.transacted_at) }}</td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap', typeBadgeClass(tx.type)]">
                                    {{ typeLabel(tx.type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium">{{ tx.description }}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ tx.tender?.name ?? '—' }}</td>
                            <td :class="['px-4 py-3 text-right font-semibold tabular-nums', isCredit(tx.type) ? 'text-green-600' : 'text-red-600']">
                                {{ isCredit(tx.type) ? '+' : '-' }}{{ fmt(tx.amount) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums">{{ fmt(tx.running_balance) }}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ tx.user?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <button @click="deleteTransaction(tx)" :disabled="ftDeleting === tx.id"
                                    class="text-red-600 hover:text-red-700 disabled:opacity-50">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="sortedTx.length === 0" class="p-8 text-center text-muted-foreground">
                <p>{{ ftSearch ? 'No transactions match the search.' : 'No transactions for the selected period.' }}</p>
            </div>

            <!-- Pagination -->
            <div v-if="ftMeta && ftMeta.last_page > 1" class="p-4 border-t flex items-center justify-between">
                <button @click="loadFinancial(ftPage - 1)" :disabled="ftPage === 1" class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-50">
                    <ChevronLeft class="h-4 w-4" /> Previous
                </button>
                <p class="text-xs text-muted-foreground">Page {{ ftPage }} of {{ ftMeta.last_page }}</p>
                <button @click="loadFinancial(ftPage + 1)" :disabled="ftPage === ftMeta.last_page" class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-50">
                    Next <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
