<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import api from '@/utils/api'
import {
    Archive, Plus, Pencil, Trash2, ChevronDown, Search, X,
    PackageCheck, PackageOpen, Package, CheckCircle2,
} from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Parcels', href: '/parcels' },
        ],
    },
})

// ── Types ──────────────────────────────────────────────────────────────────────
interface ParcelItem {
    id: number
    item_name: string
    quantity: number
    status: 'in' | 'out'
    status_updated_at: string | null
}
interface Parcel {
    id: number
    parcel_number: string
    name: string
    assigned_personnel: string | null
    status: 'in' | 'out' | 'complete'
    notes: string | null
    updated_at: string | null
    items: ParcelItem[]
    items_count: number
    items_in: number
    items_out: number
}
interface Stats { total: number; in: number; out: number; complete: number }

// ── State ──────────────────────────────────────────────────────────────────────
const loading = ref(false)
const parcels = ref<Parcel[]>([])
const stats   = ref<Stats>({ total: 0, in: 0, out: 0, complete: 0 })
const search  = ref('')
const statusFilter = ref('')
const expandedIds  = ref<Set<number>>(new Set())

// Parcel modal
const showParcelModal = ref(false)
const parcelSaving    = ref(false)
const editingParcel   = ref<Parcel | null>(null)
const parcelForm      = ref({ name: '', parcel_number: '', assigned_personnel: '', notes: '' })

// Item modal
const showItemModal = ref(false)
const itemSaving    = ref(false)
const editingItem   = ref<ParcelItem | null>(null)
const targetParcelId = ref<number | null>(null)
const itemForm      = ref({ item_name: '', quantity: '1', status: 'in' as 'in' | 'out' })

// In-flight toggles
const toggling = ref<Set<number>>(new Set())
const deleting = ref<Set<number>>(new Set())

// ── Helpers ────────────────────────────────────────────────────────────────────
const statusLabel = (s: string) => ({ in: 'IN', out: 'OUT', complete: 'COMPLETE' }[s] ?? s.toUpperCase())

const statusBadgeClass = (s: string) => ({
    in:       'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-800',
    out:      'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border border-orange-200 dark:border-orange-800',
    complete: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800',
}[s] ?? 'bg-muted text-muted-foreground')

const cardBorderClass = (s: string) => ({
    in:       'border-l-4 border-l-blue-500',
    out:      'border-l-4 border-l-orange-500',
    complete: 'border-l-4 border-l-green-500',
}[s] ?? '')

const itemStatusClass = (s: string) => ({
    in:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    out: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
}[s] ?? '')

const fmtTime = (s: string | null) => {
    if (!s) return '—'
    return new Date(s).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true })
}

// ── Computed ───────────────────────────────────────────────────────────────────
const filtered = computed(() => {
    let list = parcels.value
    if (statusFilter.value) list = list.filter(p => p.status === statusFilter.value)
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.parcel_number.toLowerCase().includes(q) ||
            (p.assigned_personnel ?? '').toLowerCase().includes(q)
        )
    }
    return list
})

// ── Data loading ───────────────────────────────────────────────────────────────
const load = async () => {
    loading.value = true
    try {
        const res = await api.get('/api/v1/parcels')
        parcels.value = res.data.data ?? []
        stats.value   = res.data.stats ?? { total: 0, in: 0, out: 0, complete: 0 }
    } catch {
        toast.error('Failed to load parcels.')
    } finally {
        loading.value = false
    }
}

const applyParcelUpdate = (updated: Parcel) => {
    const idx = parcels.value.findIndex(p => p.id === updated.id)
    if (idx !== -1) parcels.value[idx] = updated
    // Refresh stats
    stats.value = {
        total:    parcels.value.length,
        in:       parcels.value.filter(p => p.status === 'in').length,
        out:      parcels.value.filter(p => p.status === 'out').length,
        complete: parcels.value.filter(p => p.status === 'complete').length,
    }
}

// ── Parcel CRUD ────────────────────────────────────────────────────────────────
const openAddParcel = () => {
    editingParcel.value = null
    parcelForm.value = { name: '', parcel_number: '', assigned_personnel: '', notes: '' }
    showParcelModal.value = true
}
const openEditParcel = (p: Parcel) => {
    editingParcel.value = p
    parcelForm.value = {
        name: p.name,
        parcel_number: p.parcel_number,
        assigned_personnel: p.assigned_personnel ?? '',
        notes: p.notes ?? '',
    }
    showParcelModal.value = true
}
const closeParcelModal = () => { showParcelModal.value = false }

const saveParcel = async () => {
    if (!parcelForm.value.name.trim()) return
    parcelSaving.value = true
    try {
        const payload = {
            name: parcelForm.value.name,
            parcel_number: parcelForm.value.parcel_number || undefined,
            assigned_personnel: parcelForm.value.assigned_personnel || null,
            notes: parcelForm.value.notes || null,
        }
        if (editingParcel.value) {
            const res = await api.put(`/api/v1/parcels/${editingParcel.value.id}`, payload)
            applyParcelUpdate(res.data.data)
            toast.success('Parcel updated.')
        } else {
            await api.post('/api/v1/parcels', payload)
            toast.success('Parcel created.')
            await load()
        }
        closeParcelModal()
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to save parcel.')
    } finally {
        parcelSaving.value = false
    }
}

const deleteParcel = async (p: Parcel) => {
    if (!confirm(`Delete parcel "${p.name}"?\nThis will also delete all items inside.`)) return
    deleting.value.add(p.id)
    try {
        await api.delete(`/api/v1/parcels/${p.id}`)
        parcels.value = parcels.value.filter(x => x.id !== p.id)
        stats.value.total--
        stats.value[p.status]--
        toast.success('Parcel deleted.')
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to delete parcel.')
    } finally {
        deleting.value.delete(p.id)
    }
}

// ── Item CRUD ─────────────────────────────────────────────────────────────────
const openAddItem = (parcelId: number) => {
    editingItem.value = null
    targetParcelId.value = parcelId
    itemForm.value = { item_name: '', quantity: '1', status: 'in' }
    showItemModal.value = true
}
const openEditItem = (item: ParcelItem, parcelId: number) => {
    editingItem.value = item
    targetParcelId.value = parcelId
    itemForm.value = { item_name: item.item_name, quantity: String(item.quantity), status: item.status }
    showItemModal.value = true
}
const closeItemModal = () => { showItemModal.value = false }

const saveItem = async () => {
    if (!itemForm.value.item_name.trim() || !itemForm.value.quantity) return
    const pid = targetParcelId.value!
    itemSaving.value = true
    try {
        if (editingItem.value) {
            const res = await api.put(`/api/v1/parcels/${pid}/items/${editingItem.value.id}`, {
                item_name: itemForm.value.item_name,
                quantity: parseInt(itemForm.value.quantity),
            })
            applyParcelUpdate(res.data.data)
            toast.success('Item updated.')
        } else {
            const res = await api.post(`/api/v1/parcels/${pid}/items`, {
                item_name: itemForm.value.item_name,
                quantity: parseInt(itemForm.value.quantity),
                status: itemForm.value.status,
            })
            applyParcelUpdate(res.data.data)
            toast.success('Item added.')
        }
        closeItemModal()
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to save item.')
    } finally {
        itemSaving.value = false
    }
}

const deleteItem = async (item: ParcelItem, parcelId: number) => {
    if (!confirm(`Remove "${item.item_name}" from parcel?`)) return
    deleting.value.add(item.id)
    try {
        const res = await api.delete(`/api/v1/parcels/${parcelId}/items/${item.id}`)
        applyParcelUpdate(res.data.data)
        toast.success('Item removed.')
    } catch (err: any) {
        toast.error(err.response?.data?.message ?? 'Failed to remove item.')
    } finally {
        deleting.value.delete(item.id)
    }
}

const toggleItem = async (item: ParcelItem, parcel: Parcel) => {
    if (toggling.value.has(item.id)) return
    toggling.value.add(item.id)
    try {
        const res = await api.patch(`/api/v1/parcels/${parcel.id}/items/${item.id}/toggle`)
        applyParcelUpdate(res.data.data)
    } catch (err: any) {
        toast.error('Failed to update item status.')
    } finally {
        toggling.value.delete(item.id)
    }
}

const toggleExpand = (id: number) => {
    if (expandedIds.value.has(id)) expandedIds.value.delete(id)
    else expandedIds.value.add(id)
}

onMounted(load)
</script>

<template>
    <Head title="Parcel Tracking" />

    <div class="space-y-5 p-4 md:p-6">

        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <Archive class="h-5 w-5 text-primary" />
                <h1 class="text-lg font-bold">Parcel Tracking</h1>
            </div>
            <button @click="openAddParcel"
                class="flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 transition-colors">
                <Plus class="h-4 w-4" /> New Parcel
            </button>
        </div>

        <!-- ── Stats cards ────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <button @click="statusFilter = ''"
                :class="['rounded-xl border p-4 text-left transition-all', !statusFilter ? 'ring-2 ring-primary border-primary' : 'bg-card hover:bg-muted/30']">
                <Package class="h-5 w-5 text-muted-foreground mb-2" />
                <p class="text-2xl font-black tabular-nums">{{ stats.total }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-0.5">Total Parcels</p>
            </button>

            <button @click="statusFilter = statusFilter === 'in' ? '' : 'in'"
                :class="['rounded-xl border p-4 text-left transition-all', statusFilter === 'in' ? 'ring-2 ring-blue-500 border-blue-500' : 'bg-card hover:bg-muted/30']">
                <PackageOpen class="h-5 w-5 text-blue-500 mb-2" />
                <p class="text-2xl font-black tabular-nums text-blue-600 dark:text-blue-400">{{ stats.in }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-0.5">Active (IN)</p>
            </button>

            <button @click="statusFilter = statusFilter === 'out' ? '' : 'out'"
                :class="['rounded-xl border p-4 text-left transition-all', statusFilter === 'out' ? 'ring-2 ring-orange-500 border-orange-500' : 'bg-card hover:bg-muted/30']">
                <Archive class="h-5 w-5 text-orange-500 mb-2" />
                <p class="text-2xl font-black tabular-nums text-orange-600 dark:text-orange-400">{{ stats.out }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-0.5">Packing (OUT)</p>
            </button>

            <button @click="statusFilter = statusFilter === 'complete' ? '' : 'complete'"
                :class="['rounded-xl border p-4 text-left transition-all', statusFilter === 'complete' ? 'ring-2 ring-green-500 border-green-500' : 'bg-card hover:bg-muted/30']">
                <CheckCircle2 class="h-5 w-5 text-green-500 mb-2" />
                <p class="text-2xl font-black tabular-nums text-green-600 dark:text-green-400">{{ stats.complete }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-0.5">Complete</p>
            </button>
        </div>

        <!-- ── Search bar ─────────────────────────────────────────────────── -->
        <div class="relative">
            <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground pointer-events-none" />
            <input v-model="search" type="text" placeholder="Search parcels by name, number or personnel…"
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border bg-card text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
        </div>

        <!-- ── Loading ────────────────────────────────────────────────────── -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        </div>

        <!-- ── Empty state ────────────────────────────────────────────────── -->
        <div v-else-if="filtered.length === 0" class="rounded-xl border bg-card p-12 text-center">
            <Archive class="h-12 w-12 text-muted-foreground/30 mx-auto mb-3" />
            <p class="font-semibold text-muted-foreground">{{ search || statusFilter ? 'No parcels match your filter.' : 'No parcels yet.' }}</p>
            <p v-if="!search && !statusFilter" class="text-sm text-muted-foreground mt-1">Create your first parcel to start tracking items.</p>
        </div>

        <!-- ── Parcel cards ───────────────────────────────────────────────── -->
        <div v-else class="space-y-3">
            <div v-for="parcel in filtered" :key="parcel.id"
                :class="['rounded-xl border bg-card shadow-sm overflow-hidden transition-shadow hover:shadow-md', cardBorderClass(parcel.status)]">

                <!-- Card header -->
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-mono font-bold text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                    {{ parcel.parcel_number }}
                                </span>
                                <span :class="['text-[11px] font-bold px-2 py-0.5 rounded-full', statusBadgeClass(parcel.status)]">
                                    {{ statusLabel(parcel.status) }}
                                </span>
                            </div>
                            <p class="font-bold text-base mt-1 leading-tight">{{ parcel.name }}</p>
                            <p v-if="parcel.assigned_personnel" class="text-xs text-muted-foreground mt-0.5">
                                👤 {{ parcel.assigned_personnel }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1 shrink-0">
                            <button @click="openEditParcel(parcel)"
                                class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                <Pencil class="h-3.5 w-3.5" />
                            </button>
                            <button @click="deleteParcel(parcel)" :disabled="deleting.has(parcel.id)"
                                class="p-1.5 rounded-lg text-muted-foreground hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors disabled:opacity-50">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Item summary bar -->
                    <div class="mt-3 flex items-center gap-3">
                        <button @click="toggleExpand(parcel.id)"
                            class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors">
                            <ChevronDown class="h-3.5 w-3.5 transition-transform duration-200"
                                :class="expandedIds.has(parcel.id) ? 'rotate-180' : ''" />
                            {{ parcel.items_count }} item{{ parcel.items_count !== 1 ? 's' : '' }}
                        </button>
                        <div class="flex items-center gap-2 text-[10px]">
                            <span v-if="parcel.items_in > 0" class="flex items-center gap-1 font-semibold text-blue-600 dark:text-blue-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                                {{ parcel.items_in }} deployed
                            </span>
                            <span v-if="parcel.items_out > 0" class="flex items-center gap-1 font-semibold text-orange-600 dark:text-orange-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0"></span>
                                {{ parcel.items_out }} returned
                            </span>
                        </div>

                        <!-- Progress bar -->
                        <div v-if="parcel.items_count > 0" class="flex-1 h-1.5 rounded-full bg-muted overflow-hidden max-w-24">
                            <div class="h-full rounded-full transition-all duration-500"
                                :class="parcel.status === 'complete' ? 'bg-green-500' : 'bg-blue-500'"
                                :style="`width:${parcel.status === 'complete' ? 100 : Math.round((parcel.items_in / parcel.items_count) * 100)}%`" />
                        </div>
                    </div>
                </div>

                <!-- Expanded items list -->
                <div v-show="expandedIds.has(parcel.id)" class="border-t">
                    <!-- Items -->
                    <div v-if="parcel.items.length > 0" class="divide-y">
                        <div v-for="item in parcel.items" :key="item.id"
                            :class="['flex items-center gap-3 px-4 py-3 transition-colors', item.status === 'out' ? 'bg-muted/20' : '']">

                            <!-- Status dot -->
                            <span :class="['w-2 h-2 rounded-full shrink-0', item.status === 'in' ? 'bg-blue-500' : 'bg-orange-400']" />

                            <!-- Item info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold leading-tight" :class="item.status === 'out' ? 'text-muted-foreground' : ''">
                                    {{ item.item_name }}
                                </p>
                                <p class="text-[10px] text-muted-foreground mt-0.5">
                                    Qty: {{ item.quantity }}
                                    <span v-if="item.status_updated_at"> · {{ fmtTime(item.status_updated_at) }}</span>
                                </p>
                            </div>

                            <!-- Status badge -->
                            <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0', itemStatusClass(item.status)]">
                                {{ item.status.toUpperCase() }}
                            </span>

                            <!-- Toggle button -->
                            <button @click="toggleItem(item, parcel)"
                                :disabled="toggling.has(item.id)"
                                :class="['shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold transition-colors disabled:opacity-50',
                                    item.status === 'in'
                                        ? 'bg-orange-100 text-orange-700 hover:bg-orange-200 dark:bg-orange-900/30 dark:text-orange-400'
                                        : 'bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400']">
                                {{ toggling.has(item.id) ? '…' : (item.status === 'in' ? '→ Return' : '→ Deploy') }}
                            </button>

                            <!-- Item actions -->
                            <div class="flex gap-0.5 shrink-0">
                                <button @click="openEditItem(item, parcel.id)"
                                    class="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <button @click="deleteItem(item, parcel.id)" :disabled="deleting.has(item.id)"
                                    class="p-1 rounded text-muted-foreground hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors disabled:opacity-50">
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-4 py-4 text-xs text-muted-foreground">
                        No items yet. Add items to this parcel.
                    </div>

                    <!-- Add item row -->
                    <div class="px-4 py-3 border-t bg-muted/20">
                        <button @click="openAddItem(parcel.id)"
                            class="flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary/80 transition-colors">
                            <Plus class="h-3.5 w-3.5" /> Add Item
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Parcel Modal ────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showParcelModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="closeParcelModal">
                <div class="w-full max-w-md rounded-2xl border bg-card shadow-2xl">
                    <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b">
                        <h2 class="font-bold text-base">{{ editingParcel ? 'Edit Parcel' : 'New Parcel' }}</h2>
                        <button @click="closeParcelModal" class="text-muted-foreground hover:text-foreground">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">Parcel Name *</label>
                            <input v-model="parcelForm.name" type="text" placeholder="e.g. Kitchen Box A"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                @keydown.enter="saveParcel" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">
                                Parcel Number
                                <span class="font-normal opacity-60">(auto if blank)</span>
                            </label>
                            <input v-model="parcelForm.parcel_number" type="text" placeholder="P-001"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">Assigned Personnel</label>
                            <input v-model="parcelForm.assigned_personnel" type="text" placeholder="Staff name"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">Notes</label>
                            <textarea v-model="parcelForm.notes" rows="2" placeholder="Optional notes…"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none" />
                        </div>
                    </div>
                    <div class="flex gap-2 px-5 pb-5">
                        <button @click="saveParcel" :disabled="parcelSaving || !parcelForm.name.trim()"
                            class="flex-1 rounded-lg bg-primary py-2.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors">
                            {{ parcelSaving ? 'Saving…' : (editingParcel ? 'Save Changes' : 'Create Parcel') }}
                        </button>
                        <button @click="closeParcelModal"
                            class="rounded-lg border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Item Modal ──────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showItemModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="closeItemModal">
                <div class="w-full max-w-sm rounded-2xl border bg-card shadow-2xl">
                    <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b">
                        <h2 class="font-bold text-base">{{ editingItem ? 'Edit Item' : 'Add Item' }}</h2>
                        <button @click="closeItemModal" class="text-muted-foreground hover:text-foreground">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">Item Name *</label>
                            <input v-model="itemForm.item_name" type="text" placeholder="e.g. Spoon, Tongs"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                @keydown.enter="saveItem" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">Quantity *</label>
                            <input v-model="itemForm.quantity" type="number" min="1" placeholder="1"
                                class="w-full rounded-lg border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                        </div>
                        <div v-if="!editingItem">
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">Initial Status</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button @click="itemForm.status = 'in'"
                                    :class="['rounded-lg border py-2 text-xs font-bold transition-colors',
                                        itemForm.status === 'in' ? 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900/30 dark:border-blue-700' : 'hover:bg-muted']">
                                    IN (Deployed)
                                </button>
                                <button @click="itemForm.status = 'out'"
                                    :class="['rounded-lg border py-2 text-xs font-bold transition-colors',
                                        itemForm.status === 'out' ? 'bg-orange-100 text-orange-700 border-orange-300 dark:bg-orange-900/30 dark:border-orange-700' : 'hover:bg-muted']">
                                    OUT (In Box)
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 px-5 pb-5">
                        <button @click="saveItem" :disabled="itemSaving || !itemForm.item_name.trim() || !itemForm.quantity"
                            class="flex-1 rounded-lg bg-primary py-2.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors">
                            {{ itemSaving ? 'Saving…' : (editingItem ? 'Save Changes' : 'Add Item') }}
                        </button>
                        <button @click="closeItemModal"
                            class="rounded-lg border px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>
