<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import { BarChart3, Download, RefreshCw, TrendingUp, TrendingDown, DollarSign, Plus, X } from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Reports', href: '/reports' },
        ],
    },
})

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

const props = defineProps<{
    initialDailyReport: DailyReport
    initialProductSales: ProductSale[]
}>()

const reportType = ref<'daily' | 'monthly' | 'products' | 'inventory' | 'financial'>('daily')
const selectedDate = ref(new Date().toISOString().split('T')[0])
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const loading = ref(false)
const dailyReport = ref<DailyReport | null>(props.initialDailyReport)
const monthlyReport = ref<MonthlyReport | null>(null)
const productSales = ref<ProductSale[]>(props.initialProductSales)
const inventoryReport = ref<any[]>([])

// Financial transactions state
const ftStartDate = ref(new Date().toISOString().split('T')[0])
const ftEndDate = ref(new Date().toISOString().split('T')[0])
const ftTypeFilter = ref('')
const ftSummary = ref<FtSummary | null>(null)
const ftTransactions = ref<FtTransaction[]>([])
const ftMeta = ref<any>(null)
const showExpenseForm = ref(false)
const expenseForm = ref({ description: '', amount: '', notes: '' })
const expenseSaving = ref(false)

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const monthName = (n: number) => monthNames[n - 1] ?? ''

const formatCurrency = (v: number | null | undefined) =>
    '₱' + (v ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })

const generateReport = async () => {
    loading.value = true
    try {
        if (reportType.value === 'daily') {
            const res = await api.get('/api/v1/reports/daily-sales', { params: { date: selectedDate.value } })
            dailyReport.value = res.data
        } else if (reportType.value === 'monthly') {
            const res = await api.get('/api/v1/reports/monthly-sales', { params: { year: selectedYear.value, month: selectedMonth.value } })
            monthlyReport.value = res.data
        } else if (reportType.value === 'products') {
            const res = await api.get('/api/v1/reports/product-sales')
            productSales.value = res.data
        } else if (reportType.value === 'inventory') {
            const res = await api.get('/api/v1/reports/inventory-valuation')
            inventoryReport.value = res.data
        } else if (reportType.value === 'financial') {
            await loadFinancial()
        }
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to load report')
    } finally {
        loading.value = false
    }
}

const loadFinancial = async () => {
    const [summaryRes, listRes] = await Promise.all([
        api.get('/api/v1/financial-transactions/summary', {
            params: { start_date: ftStartDate.value, end_date: ftEndDate.value },
        }),
        api.get('/api/v1/financial-transactions', {
            params: { start_date: ftStartDate.value, end_date: ftEndDate.value, type: ftTypeFilter.value || undefined },
        }),
    ])
    ftSummary.value = summaryRes.data
    ftTransactions.value = listRes.data.data ?? listRes.data
    ftMeta.value = listRes.data.meta ?? null
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

const exportCSV = () => {
    let rows: string[][] = []
    let filename = 'report'

    if (reportType.value === 'daily' && dailyReport.value) {
        filename = `daily-sales-${dailyReport.value.date}`
        rows = [
            ['Date', 'Total Orders', 'Total Sales', 'Discounts'],
            [dailyReport.value.date, String(dailyReport.value.total_orders), String(dailyReport.value.total_sales), String(dailyReport.value.total_discount)],
        ]
    } else if (reportType.value === 'products') {
        filename = `product-sales`
        rows = [['Product', 'Qty Sold', 'Total Sales'], ...productSales.value.map((p) => [p.product_name, String(p.total_quantity), String(p.total_sales)])]
    } else if (reportType.value === 'financial' && ftTransactions.value.length > 0) {
        filename = `financial-transactions-${ftStartDate.value}-to-${ftEndDate.value}`
        rows = [
            ['Date', 'Type', 'Description', 'Tender', 'Amount', 'User'],
            ...ftTransactions.value.map((t) => [
                t.transacted_at?.slice(0, 10) ?? '',
                t.type,
                t.description,
                t.tender?.name ?? '',
                String(t.amount),
                t.user?.name ?? '',
            ]),
        ]
    }

    if (rows.length === 0) { toast.info('No data to export'); return }

    const csv = rows.map((r) => r.map((c) => `"${c.replace(/"/g, '""')}"`).join(',')).join('\n')
    const a = document.createElement('a')
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv)
    a.download = `${filename}.csv`
    a.click()
    toast.success('CSV downloaded')
}

const activeReport = computed(() => {
    if (reportType.value === 'daily') return dailyReport.value
    if (reportType.value === 'monthly') return monthlyReport.value
    return null
})

const printReport = () => window.print()

const topProducts = computed(() =>
    [...productSales.value].sort((a, b) => b.total_sales - a.total_sales).slice(0, 10)
)

const typeLabel = (t: string) => ({ order: 'Order', payment: 'Payment', expense: 'Expense' }[t] ?? t)
const typeBadgeClass = (t: string) => ({
    order: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    payment: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    expense: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}[t] ?? 'bg-muted text-muted-foreground')
</script>

<template>
    <Head title="Reports" />

    <div class="space-y-6">
        <!-- Filters Bar -->
        <div class="rounded-xl border bg-card shadow-sm p-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Report Type</label>
                    <select v-model="reportType" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="daily">Daily Sales</option>
                        <option value="monthly">Monthly Sales</option>
                        <option value="products">Product Sales</option>
                        <option value="inventory">Inventory Valuation</option>
                        <option value="financial">Financial Transactions</option>
                    </select>
                </div>

                <!-- Daily date picker -->
                <div v-if="reportType === 'daily'">
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Date</label>
                    <input v-model="selectedDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>

                <!-- Monthly pickers -->
                <template v-if="reportType === 'monthly'">
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Year</label>
                        <input v-model.number="selectedYear" type="number" min="2020" class="w-24 rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Month</label>
                        <select v-model.number="selectedMonth" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                        </select>
                    </div>
                </template>

                <!-- Financial date range -->
                <template v-if="reportType === 'financial'">
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
                        <input v-model="ftStartDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
                        <input v-model="ftEndDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Type</label>
                        <select v-model="ftTypeFilter" class="rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Types</option>
                            <option value="order">Orders</option>
                            <option value="payment">Payments</option>
                            <option value="expense">Expenses</option>
                        </select>
                    </div>
                </template>

                <button
                    @click="generateReport"
                    :disabled="loading"
                    class="rounded-lg bg-primary px-5 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50 flex items-center gap-1.5"
                >
                    <RefreshCw v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                    <BarChart3 v-else class="h-3.5 w-3.5" />
                    Generate
                </button>
                <button
                    @click="exportCSV"
                    class="rounded-lg border bg-background px-4 py-2 text-sm font-medium hover:bg-muted flex items-center gap-1.5"
                >
                    <Download class="h-3.5 w-3.5" />
                    Export CSV
                </button>
                <button
                    @click="printReport"
                    class="rounded-lg border bg-background px-4 py-2 text-sm font-medium hover:bg-muted"
                >
                    Print
                </button>
            </div>
        </div>

        <!-- Daily / Monthly Summary Cards -->
        <template v-if="(reportType === 'daily' || reportType === 'monthly') && activeReport">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="font-bold text-base mb-4">
                    <span v-if="reportType === 'daily'">Daily Sales — {{ (activeReport as any).date }}</span>
                    <span v-else>Monthly Sales — {{ monthName(Number((activeReport as any).month?.split('-')[1])) }} {{ (activeReport as any).month?.split('-')[0] }}</span>
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg bg-muted/40 p-4">
                        <p class="text-xs text-muted-foreground mb-1">Total Orders</p>
                        <p class="text-3xl font-black">{{ activeReport.total_orders }}</p>
                    </div>
                    <div class="rounded-lg bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1"><TrendingUp class="h-3 w-3" /> Revenue</p>
                        <p class="text-2xl font-black text-green-600">{{ formatCurrency(activeReport.total_sales) }}</p>
                    </div>
                    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950/20 p-4">
                        <p class="text-xs text-muted-foreground mb-1">Discounts</p>
                        <p class="text-2xl font-black text-yellow-600">{{ formatCurrency(activeReport.total_discount) }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- Product Sales Table -->
        <template v-if="reportType === 'products'">
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="font-bold text-sm">Product Sales — This Month</h2>
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
                                <td class="px-4 py-2 text-right font-bold text-green-600">{{ formatCurrency(item.total_sales) }}</td>
                                <td class="px-4 py-2 w-40">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                                            <div
                                                class="h-full bg-primary rounded-full"
                                                :style="{ width: topProducts[0]?.total_sales ? ((item.total_sales / topProducts[0].total_sales) * 100) + '%' : '0%' }"
                                            />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="topProducts.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No data available</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Inventory Valuation -->
        <template v-if="reportType === 'inventory' && inventoryReport.length > 0">
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="font-bold text-sm">Inventory Valuation</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Ingredient</th>
                                <th class="px-4 py-3 text-right">Stock</th>
                                <th class="px-4 py-3 text-left">Unit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in inventoryReport" :key="item.id" class="hover:bg-muted/20">
                                <td class="px-4 py-2 font-medium">{{ item.name }}</td>
                                <td class="px-4 py-2 text-right">{{ item.current_quantity }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ item.unit }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <div v-if="reportType === 'inventory' && inventoryReport.length === 0 && !loading" class="rounded-xl border bg-card p-10 text-center shadow-sm text-muted-foreground text-sm">
            Click <strong>Generate</strong> to load the inventory valuation report.
        </div>

        <!-- ── Financial Transactions ─────────────────────── -->
        <template v-if="reportType === 'financial'">
            <!-- Summary Cards -->
            <div v-if="ftSummary" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1">
                        <BarChart3 class="h-3 w-3" /> Orders
                    </p>
                    <p class="text-2xl font-black">{{ ftSummary.orders.count }}</p>
                    <p class="text-sm font-semibold text-blue-600 mt-0.5">{{ formatCurrency(ftSummary.orders.total) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1">
                        <TrendingUp class="h-3 w-3" /> Payments
                    </p>
                    <p class="text-2xl font-black">{{ ftSummary.payments.count }}</p>
                    <p class="text-sm font-semibold text-green-600 mt-0.5">{{ formatCurrency(ftSummary.payments.total) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1">
                        <TrendingDown class="h-3 w-3" /> Expenses
                    </p>
                    <p class="text-2xl font-black">{{ ftSummary.expenses.count }}</p>
                    <p class="text-sm font-semibold text-red-600 mt-0.5">{{ formatCurrency(ftSummary.expenses.total) }}</p>
                </div>
                <div :class="[
                    'rounded-xl border p-4 shadow-sm',
                    ftSummary.net >= 0 ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800'
                ]">
                    <p class="text-xs text-muted-foreground mb-1 flex items-center gap-1">
                        <DollarSign class="h-3 w-3" /> Net Cash
                    </p>
                    <p class="text-2xl font-black" :class="ftSummary.net >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600'">
                        {{ formatCurrency(ftSummary.net) }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-0.5">Payments − Expenses</p>
                </div>
            </div>

            <!-- Payment by Tender -->
            <div v-if="ftSummary && ftSummary.by_tender.length > 0" class="rounded-xl border bg-card shadow-sm p-4">
                <h3 class="font-bold text-sm mb-3">Payments by Tender</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="row in ftSummary.by_tender"
                        :key="row.tender"
                        class="flex items-center justify-between rounded-lg bg-muted/40 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-semibold">{{ row.tender }}</p>
                            <p class="text-xs text-muted-foreground">{{ row.count }} transaction{{ row.count !== 1 ? 's' : '' }}</p>
                        </div>
                        <p class="text-base font-bold text-green-600">{{ formatCurrency(row.total) }}</p>
                    </div>
                </div>
            </div>

            <!-- Add Expense button + form -->
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-sm text-muted-foreground uppercase tracking-wider">Transaction Log</h3>
                <button
                    @click="showExpenseForm = !showExpenseForm"
                    class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted transition"
                >
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
                        <input
                            v-model="expenseForm.description"
                            type="text"
                            placeholder="e.g. Charcoal supply, LPG refill"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Amount (₱)</label>
                        <input
                            v-model="expenseForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Notes (optional)</label>
                        <input
                            v-model="expenseForm.notes"
                            type="text"
                            placeholder="Additional details"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="saveExpense"
                            :disabled="expenseSaving"
                            class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {{ expenseSaving ? 'Saving…' : 'Save Expense' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transaction list -->
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
                                <td class="px-4 py-2 text-muted-foreground whitespace-nowrap">
                                    {{ tx.transacted_at?.slice(0, 10) }}
                                </td>
                                <td class="px-4 py-2">
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', typeBadgeClass(tx.type)]">
                                        {{ typeLabel(tx.type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 max-w-xs truncate">{{ tx.description }}</td>
                                <td class="px-4 py-2 text-muted-foreground">{{ tx.tender?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-bold"
                                    :class="tx.type === 'expense' ? 'text-red-600' : 'text-green-600'">
                                    {{ tx.type === 'expense' ? '-' : '' }}{{ formatCurrency(tx.amount) }}
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

            <div v-if="ftTransactions.length === 0 && !loading && !ftSummary" class="rounded-xl border bg-card p-10 text-center shadow-sm text-muted-foreground text-sm">
                Select a date range and click <strong>Generate</strong> to load financial transactions.
            </div>
            <div v-else-if="ftTransactions.length === 0 && !loading && ftSummary" class="rounded-xl border bg-card p-6 text-center shadow-sm text-muted-foreground text-sm">
                No transactions found for this period.
            </div>
        </template>
    </div>
</template>
