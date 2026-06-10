<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import {
    PieChart, Users, Percent, History, TrendingUp, RefreshCw, Plus, Trash2, Pencil,
    Download, Save, X,
} from 'lucide-vue-next'

defineOptions({ layout: { breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }, { title: 'Profit Sharing', href: '/distribution' }] } })

const props = defineProps<{ categories: { id: number; name: string }[]; products: { id: number; name: string; category_id: number }[] }>()

// ── Shared filters ──────────────────────────────────────────────────────────
const today = new Date().toISOString().split('T')[0]
const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]
const basis = ref<'sales' | 'profit'>('sales')
const startDate = ref(monthStart)
const endDate = ref(today)
const categoryId = ref<number | ''>('')
const productId = ref<number | ''>('')
const shareholderId = ref<number | ''>('')

const subTab = ref<'distribution' | 'shareholders' | 'royalties' | 'trends' | 'history'>('distribution')

const fmt = (v: number) => '₱' + (v ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Distribution preview ────────────────────────────────────────────────────
const result = ref<any>(null)
const loading = ref(false)

const params = () => ({
    basis: basis.value, start_date: startDate.value, end_date: endDate.value,
    category_id: categoryId.value || undefined, product_id: productId.value || undefined,
    shareholder_id: shareholderId.value || undefined,
})

const loadPreview = async () => {
    loading.value = true
    try {
        const res = await api.get('/api/v1/distribution/preview', { params: params() })
        result.value = res.data
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to compute distribution')
    } finally {
        loading.value = false
    }
}

const setMonth = () => { startDate.value = monthStart; endDate.value = today; loadPreview() }
const setQuarter = () => {
    const q = Math.floor(new Date().getMonth() / 3)
    startDate.value = new Date(new Date().getFullYear(), q * 3, 1).toISOString().split('T')[0]
    endDate.value = today; loadPreview()
}
const setYear = () => { startDate.value = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]; endDate.value = today; loadPreview() }

const exportCsv = () => {
    const qs = new URLSearchParams(params() as any).toString()
    window.open(`/api/v1/distribution/export?${qs}`, '_blank')
}

const saveSnapshot = async () => {
    try {
        await api.post('/api/v1/distribution/snapshots', params())
        toast.success('Snapshot saved to history')
        if (subTab.value === 'history') loadSnapshots()
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to save snapshot')
    }
}

// ── Pie chart ───────────────────────────────────────────────────────────────
const pieSeries = computed(() => (result.value?.chart ?? []).map((c: any) => c.value))
const pieOptions = computed(() => ({
    chart: { type: 'pie' },
    labels: (result.value?.chart ?? []).map((c: any) => c.label),
    legend: { position: 'bottom' },
    colors: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899', '#14b8a6', '#6b7280'],
    dataLabels: { formatter: (val: number) => val.toFixed(1) + '%' },
    tooltip: { y: { formatter: (val: number) => '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2 }) } },
}))

// ── Shareholders CRUD ───────────────────────────────────────────────────────
const shareholders = ref<any[]>([])
const totalOwnership = ref(0)
const companyPct = ref(100)
const shForm = ref<any>({ id: null, name: '', email: '', ownership_percentage: '', status: 'active', notes: '' })
const shSaving = ref(false)

const loadShareholders = async () => {
    const res = await api.get('/api/v1/shareholders')
    shareholders.value = res.data.shareholders
    totalOwnership.value = res.data.total_ownership
    companyPct.value = res.data.company_percentage
}
const editSh = (s: any) => { shForm.value = { ...s } }
const resetShForm = () => { shForm.value = { id: null, name: '', email: '', ownership_percentage: '', status: 'active', notes: '' } }
const saveSh = async () => {
    shSaving.value = true
    try {
        const payload = { ...shForm.value, ownership_percentage: parseFloat(shForm.value.ownership_percentage) || 0 }
        if (shForm.value.id) await api.put(`/api/v1/shareholders/${shForm.value.id}`, payload)
        else await api.post('/api/v1/shareholders', payload)
        toast.success('Shareholder saved')
        resetShForm(); await loadShareholders()
    } catch (err: any) {
        toast.error(Object.values(err.response?.data?.errors ?? {}).flat().join(' ') || err.response?.data?.message || 'Failed to save')
    } finally { shSaving.value = false }
}
const deleteSh = async (s: any) => {
    if (!confirm(`Remove shareholder ${s.name}?`)) return
    await api.delete(`/api/v1/shareholders/${s.id}`); toast.success('Removed'); await loadShareholders()
}

// ── Royalty rules CRUD ──────────────────────────────────────────────────────
const rules = ref<any[]>([])
const rForm = ref<any>({ id: null, scope: 'product', product_id: '', category_id: '', recipient_name: '', shareholder_id: '', royalty_percentage: '', effective_date: today, expiration_date: '', is_active: true })
const rSaving = ref(false)

const loadRules = async () => { rules.value = (await api.get('/api/v1/royalty-rules')).data }
const editRule = (r: any) => { rForm.value = { ...r, product_id: r.product_id ?? '', category_id: r.category_id ?? '', shareholder_id: r.shareholder_id ?? '', expiration_date: r.expiration_date ?? '' } }
const resetRForm = () => { rForm.value = { id: null, scope: 'product', product_id: '', category_id: '', recipient_name: '', shareholder_id: '', royalty_percentage: '', effective_date: today, expiration_date: '', is_active: true } }
const saveRule = async () => {
    rSaving.value = true
    try {
        const payload = { ...rForm.value, royalty_percentage: parseFloat(rForm.value.royalty_percentage) || 0, product_id: rForm.value.product_id || null, category_id: rForm.value.category_id || null, shareholder_id: rForm.value.shareholder_id || null, expiration_date: rForm.value.expiration_date || null }
        if (rForm.value.id) await api.put(`/api/v1/royalty-rules/${rForm.value.id}`, payload)
        else await api.post('/api/v1/royalty-rules', payload)
        toast.success('Royalty rule saved'); resetRForm(); await loadRules()
    } catch (err: any) {
        toast.error(Object.values(err.response?.data?.errors ?? {}).flat().join(' ') || err.response?.data?.message || 'Failed to save')
    } finally { rSaving.value = false }
}
const deleteRule = async (r: any) => {
    if (!confirm(`Delete royalty rule for ${r.recipient_name}?`)) return
    await api.delete(`/api/v1/royalty-rules/${r.id}`); toast.success('Deleted'); await loadRules()
}

// ── Trends + royalty analytics ──────────────────────────────────────────────
const trend = ref<any[]>([])
const royaltyAnalytics = ref<any>(null)

const loadTrends = async () => {
    const yearStart = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]
    const [t, r] = await Promise.all([
        api.get('/api/v1/distribution/trend', { params: { basis: basis.value, start_date: yearStart, end_date: today } }),
        api.get('/api/v1/distribution/royalty-analytics', { params: params() }),
    ])
    trend.value = t.data; royaltyAnalytics.value = r.data
}
const trendSeries = computed(() => ([
    { name: 'Members', data: trend.value.map(t => t.members) },
    { name: 'Company', data: trend.value.map(t => t.company) },
    { name: 'Royalties', data: trend.value.map(t => t.royalty) },
]))
const trendOptions = computed(() => ({
    chart: { type: 'line', toolbar: { show: false } },
    stroke: { width: 2, curve: 'smooth' },
    xaxis: { categories: trend.value.map(t => t.month) },
    colors: ['#3b82f6', '#10b981', '#f59e0b'],
    yaxis: { labels: { formatter: (v: number) => '₱' + (v / 1000).toFixed(0) + 'K' } },
    legend: { position: 'top' },
}))

// ── Snapshots history ───────────────────────────────────────────────────────
const snapshots = ref<any[]>([])
const loadSnapshots = async () => { snapshots.value = (await api.get('/api/v1/distribution/snapshots')).data }

// ── Tab activation ──────────────────────────────────────────────────────────
watch(subTab, (t) => {
    if (t === 'shareholders') loadShareholders()
    else if (t === 'royalties') { loadRules(); loadShareholders() }
    else if (t === 'trends') loadTrends()
    else if (t === 'history') loadSnapshots()
})

onMounted(loadPreview)

const tabs = [
    { key: 'distribution', label: 'Distribution', icon: PieChart },
    { key: 'shareholders', label: 'Shareholders', icon: Users },
    { key: 'royalties', label: 'Royalties', icon: Percent },
    { key: 'trends', label: 'Trends', icon: TrendingUp },
    { key: 'history', label: 'History', icon: History },
] as const
</script>

<template>
    <Head title="Profit Sharing" />

    <div class="max-w-6xl mx-auto space-y-4">
        <div class="flex items-center gap-2">
            <PieChart class="h-6 w-6 text-primary" />
            <h1 class="text-xl font-black">Profit Distribution</h1>
        </div>

        <!-- Sub-tabs -->
        <div class="flex gap-1 overflow-x-auto border-b">
            <button v-for="t in tabs" :key="t.key" @click="subTab = t.key"
                :class="['flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 whitespace-nowrap transition',
                    subTab === t.key ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground']">
                <component :is="t.icon" class="h-4 w-4" /> {{ t.label }}
            </button>
        </div>

        <!-- ── DISTRIBUTION ─────────────────────────────────────────────── -->
        <template v-if="subTab === 'distribution'">
            <!-- Filters -->
            <div class="rounded-xl border bg-card shadow-sm p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="text-xs font-medium text-muted-foreground block mb-1">Basis</label>
                        <div class="flex rounded-lg border overflow-hidden">
                            <button @click="basis = 'sales'; loadPreview()" :class="['px-3 py-2 text-sm font-semibold', basis === 'sales' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted']">Sales</button>
                            <button @click="basis = 'profit'; loadPreview()" :class="['px-3 py-2 text-sm font-semibold', basis === 'profit' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted']">Profit</button>
                        </div>
                    </div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">From</label><input v-model="startDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">To</label><input v-model="endDate" type="date" class="rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Category</label>
                        <select v-model="categoryId" class="rounded-lg border bg-background px-3 py-2 text-sm"><option value="">All</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                    <div><label class="text-xs font-medium text-muted-foreground block mb-1">Product</label>
                        <select v-model="productId" class="rounded-lg border bg-background px-3 py-2 text-sm"><option value="">All</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
                    <button @click="loadPreview" :disabled="loading" class="rounded-lg bg-primary px-5 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50 flex items-center gap-1.5">
                        <RefreshCw v-if="loading" class="h-3.5 w-3.5 animate-spin" /><PieChart v-else class="h-3.5 w-3.5" /> Compute
                    </button>
                    <div class="flex items-center gap-1 ml-auto">
                        <button @click="setMonth" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted">Month</button>
                        <button @click="setQuarter" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted">Quarter</button>
                        <button @click="setYear" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted">Year</button>
                        <button @click="exportCsv" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted flex items-center gap-1"><Download class="h-3 w-3" /> CSV</button>
                        <button @click="saveSnapshot" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted flex items-center gap-1"><Save class="h-3 w-3" /> Snapshot</button>
                    </div>
                </div>
            </div>

            <template v-if="result">
                <!-- Flow KPIs -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border bg-card p-4 shadow-sm"><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ result.base_label }}</p><p class="text-xl font-black mt-1">{{ fmt(result.base_amount) }}</p></div>
                    <div class="rounded-xl border bg-card p-4 shadow-sm"><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Royalties</p><p class="text-xl font-black mt-1 text-amber-600">−{{ fmt(result.royalty.total) }}</p></div>
                    <div class="rounded-xl border bg-card p-4 shadow-sm"><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Distributable</p><p class="text-xl font-black mt-1 text-primary">{{ fmt(result.distributable) }}</p></div>
                    <div class="rounded-xl border bg-card p-4 shadow-sm"><p class="text-[10px] uppercase tracking-wide text-muted-foreground">Company ({{ result.company_percentage }}%)</p><p class="text-xl font-black mt-1 text-emerald-600">{{ fmt(result.company_amount) }}</p></div>
                </div>

                <div class="grid lg:grid-cols-2 gap-4">
                    <!-- Pie -->
                    <div class="rounded-xl border bg-card shadow-sm p-4">
                        <h3 class="font-bold text-sm mb-2">Distribution Breakdown</h3>
                        <apexchart v-if="pieSeries.length" type="pie" height="300" :options="pieOptions" :series="pieSeries" />
                        <p v-else class="text-sm text-muted-foreground text-center py-10">No distributable amount in range.</p>
                    </div>
                    <!-- Member table -->
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="p-4 border-b"><h3 class="font-bold text-sm">Member Shares</h3></div>
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-muted-foreground text-xs uppercase"><tr><th class="px-4 py-2 text-left">Member</th><th class="px-4 py-2 text-right">%</th><th class="px-4 py-2 text-right">Amount</th></tr></thead>
                            <tbody class="divide-y">
                                <tr v-for="m in result.members" :key="m.shareholder_id" class="hover:bg-muted/20"><td class="px-4 py-2 font-medium">{{ m.name }}</td><td class="px-4 py-2 text-right">{{ m.percentage }}%</td><td class="px-4 py-2 text-right font-bold">{{ fmt(m.amount) }}</td></tr>
                                <tr class="bg-muted/30 font-bold"><td class="px-4 py-2">Members total</td><td class="px-4 py-2 text-right">{{ result.members_percentage }}%</td><td class="px-4 py-2 text-right">{{ fmt(result.members_total) }}</td></tr>
                                <tr class="bg-emerald-50 dark:bg-emerald-950/20 font-bold text-emerald-700 dark:text-emerald-400"><td class="px-4 py-2">Company retained</td><td class="px-4 py-2 text-right">{{ result.company_percentage }}%</td><td class="px-4 py-2 text-right">{{ fmt(result.company_amount) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Royalty recipients -->
                <div v-if="result.royalty.by_recipient.length" class="rounded-xl border bg-card shadow-sm p-4">
                    <h3 class="font-bold text-sm mb-2">Royalty Recipients</h3>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="r in result.royalty.by_recipient" :key="r.recipient_name" class="rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-3 py-1 text-sm font-medium">{{ r.recipient_name }}: {{ fmt(r.amount) }}</span>
                    </div>
                </div>
            </template>
        </template>

        <!-- ── SHAREHOLDERS ─────────────────────────────────────────────── -->
        <template v-if="subTab === 'shareholders'">
            <div class="rounded-xl border bg-card shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm">Ownership ({{ totalOwnership }}% allocated · Company keeps {{ companyPct }}%)</h3>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden mb-4 flex">
                    <div class="bg-primary h-full" :style="{ width: totalOwnership + '%' }"></div>
                    <div class="bg-emerald-400 h-full" :style="{ width: companyPct + '%' }"></div>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                    <div><label class="text-xs text-muted-foreground block mb-1">Name *</label><input v-model="shForm.name" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Email</label><input v-model="shForm.email" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Ownership %</label><input v-model="shForm.ownership_percentage" type="number" step="0.01" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Status</label><select v-model="shForm.status" class="w-full rounded-lg border bg-background px-3 py-2 text-sm"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    <div class="flex gap-2">
                        <button @click="saveSh" :disabled="shSaving || !shForm.name" class="flex-1 rounded-lg bg-primary px-3 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50">{{ shForm.id ? 'Update' : 'Add' }}</button>
                        <button v-if="shForm.id" @click="resetShForm" class="rounded-lg border px-3 py-2"><X class="h-4 w-4" /></button>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-muted-foreground text-xs uppercase"><tr><th class="px-4 py-2 text-left">Name</th><th class="px-4 py-2 text-left">Email</th><th class="px-4 py-2 text-right">Ownership</th><th class="px-4 py-2 text-center">Status</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="s in shareholders" :key="s.id" class="hover:bg-muted/20">
                            <td class="px-4 py-2 font-medium">{{ s.name }}</td><td class="px-4 py-2 text-muted-foreground">{{ s.email ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-bold">{{ s.ownership_percentage }}%</td>
                            <td class="px-4 py-2 text-center"><span :class="['rounded-full px-2 py-0.5 text-xs', s.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ s.status }}</span></td>
                            <td class="px-4 py-2 text-right"><button @click="editSh(s)" class="p-1 text-muted-foreground hover:text-blue-600"><Pencil class="h-4 w-4" /></button><button @click="deleteSh(s)" class="p-1 text-muted-foreground hover:text-red-600"><Trash2 class="h-4 w-4" /></button></td>
                        </tr>
                        <tr v-if="!shareholders.length"><td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No shareholders yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── ROYALTIES ────────────────────────────────────────────────── -->
        <template v-if="subTab === 'royalties'">
            <div class="rounded-xl border bg-card shadow-sm p-4">
                <h3 class="font-bold text-sm mb-3">{{ rForm.id ? 'Edit' : 'Add' }} Royalty Rule</h3>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div><label class="text-xs text-muted-foreground block mb-1">Scope</label><select v-model="rForm.scope" class="w-full rounded-lg border bg-background px-3 py-2 text-sm"><option value="product">Product</option><option value="category">Category</option></select></div>
                    <div v-if="rForm.scope === 'product'"><label class="text-xs text-muted-foreground block mb-1">Product *</label><select v-model="rForm.product_id" class="w-full rounded-lg border bg-background px-3 py-2 text-sm"><option value="">Select…</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
                    <div v-else><label class="text-xs text-muted-foreground block mb-1">Category *</label><select v-model="rForm.category_id" class="w-full rounded-lg border bg-background px-3 py-2 text-sm"><option value="">Select…</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Recipient *</label><input v-model="rForm.recipient_name" placeholder="e.g. Brand Owner" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Link to member</label><select v-model="rForm.shareholder_id" class="w-full rounded-lg border bg-background px-3 py-2 text-sm"><option value="">None</option><option v-for="s in shareholders" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Royalty %</label><input v-model="rForm.royalty_percentage" type="number" step="0.01" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Effective</label><input v-model="rForm.effective_date" type="date" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div><label class="text-xs text-muted-foreground block mb-1">Expires (optional)</label><input v-model="rForm.expiration_date" type="date" class="w-full rounded-lg border bg-background px-3 py-2 text-sm" /></div>
                    <div class="flex items-end gap-2">
                        <button @click="saveRule" :disabled="rSaving || !rForm.recipient_name" class="flex-1 rounded-lg bg-primary px-3 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50">{{ rForm.id ? 'Update' : 'Add Rule' }}</button>
                        <button v-if="rForm.id" @click="resetRForm" class="rounded-lg border px-3 py-2"><X class="h-4 w-4" /></button>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-muted-foreground text-xs uppercase"><tr><th class="px-4 py-2 text-left">Scope</th><th class="px-4 py-2 text-left">Target</th><th class="px-4 py-2 text-left">Recipient</th><th class="px-4 py-2 text-right">%</th><th class="px-4 py-2 text-left">Window</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="r in rules" :key="r.id" :class="['hover:bg-muted/20', !r.is_active && 'opacity-50']">
                            <td class="px-4 py-2 capitalize">{{ r.scope }}</td>
                            <td class="px-4 py-2">{{ r.product?.name ?? r.category?.name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ r.recipient_name }}<span v-if="r.shareholder" class="text-xs text-muted-foreground"> ({{ r.shareholder.name }})</span></td>
                            <td class="px-4 py-2 text-right font-bold">{{ r.royalty_percentage }}%</td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ r.effective_date?.slice(0,10) }} → {{ r.expiration_date?.slice(0,10) ?? '∞' }}</td>
                            <td class="px-4 py-2 text-right"><button @click="editRule(r)" class="p-1 text-muted-foreground hover:text-blue-600"><Pencil class="h-4 w-4" /></button><button @click="deleteRule(r)" class="p-1 text-muted-foreground hover:text-red-600"><Trash2 class="h-4 w-4" /></button></td>
                        </tr>
                        <tr v-if="!rules.length"><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No royalty rules yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── TRENDS ───────────────────────────────────────────────────── -->
        <template v-if="subTab === 'trends'">
            <div class="rounded-xl border bg-card shadow-sm p-4">
                <h3 class="font-bold text-sm mb-2 flex items-center gap-2"><TrendingUp class="h-4 w-4 text-primary" /> Monthly Distribution Trend (this year, {{ basis }} basis)</h3>
                <apexchart v-if="trend.length" type="line" height="320" :options="trendOptions" :series="trendSeries" />
                <p v-else class="text-sm text-muted-foreground text-center py-10">No data.</p>
            </div>
            <div v-if="royaltyAnalytics" class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between"><h3 class="font-bold text-sm">Top Royalty Products</h3><span class="text-sm font-bold text-amber-600">Total: {{ fmt(royaltyAnalytics.total) }}</span></div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-muted-foreground text-xs uppercase"><tr><th class="px-4 py-2 text-left">Product</th><th class="px-4 py-2 text-right">Net Sales</th><th class="px-4 py-2 text-right">Rate</th><th class="px-4 py-2 text-right">Royalty</th></tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="p in royaltyAnalytics.by_product" :key="p.name" class="hover:bg-muted/20"><td class="px-4 py-2 font-medium">{{ p.name }}</td><td class="px-4 py-2 text-right">{{ fmt(p.net_sales) }}</td><td class="px-4 py-2 text-right">{{ p.rate }}%</td><td class="px-4 py-2 text-right font-bold text-amber-600">{{ fmt(p.royalty) }}</td></tr>
                        <tr v-if="!royaltyAnalytics.by_product.length"><td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No royalties in range.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── HISTORY ──────────────────────────────────────────────────── -->
        <template v-if="subTab === 'history'">
            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="p-4 border-b"><h3 class="font-bold text-sm">Distribution Snapshots</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-muted-foreground text-xs uppercase"><tr><th class="px-4 py-2 text-left">Period</th><th class="px-4 py-2 text-left">Basis</th><th class="px-4 py-2 text-right">Distributable</th><th class="px-4 py-2 text-right">Members</th><th class="px-4 py-2 text-right">Company</th><th class="px-4 py-2 text-left">By</th></tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="s in snapshots" :key="s.id" class="hover:bg-muted/20">
                            <td class="px-4 py-2 whitespace-nowrap">{{ s.period_start?.slice(0,10) }} → {{ s.period_end?.slice(0,10) }}</td>
                            <td class="px-4 py-2 capitalize">{{ s.distribution_basis }}</td>
                            <td class="px-4 py-2 text-right font-bold">{{ fmt(s.distributable_amount) }}</td>
                            <td class="px-4 py-2 text-right">{{ fmt(s.members_amount) }}</td>
                            <td class="px-4 py-2 text-right text-emerald-600">{{ fmt(s.company_amount) }}</td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ s.creator?.name ?? '—' }}</td>
                        </tr>
                        <tr v-if="!snapshots.length"><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No snapshots saved yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
