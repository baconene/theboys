<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
    LayoutTemplate, GripVertical, Eye, EyeOff, Pencil, X, Save,
    ChevronDown, ChevronRight, Image as ImageIcon, ExternalLink, RefreshCw,
} from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/profile' },
            { title: 'Page Content', href: '/settings/page-content' },
        ],
    },
})

interface Section {
    id: number
    key: string
    label: string
    content: string | null
    position: 'before_products' | 'after_products'
    is_active: boolean
    display_order: number
}

const props = defineProps<{ sections: Section[] }>()

// ── Editing state ──────────────────────────────────────────────────────────────
const editing  = ref<Section | null>(null)
const form     = ref({ label: '', content: '', position: 'before_products' as Section['position'], is_active: true, display_order: 0 })
const saving   = ref(false)
const tab      = ref<'code' | 'preview'>('code')

function openEdit(s: Section) {
    editing.value = s
    form.value    = { label: s.label, content: s.content ?? '', position: s.position, is_active: s.is_active, display_order: s.display_order }
    tab.value     = 'code'
}

function closeEdit() { editing.value = null }

function save() {
    if (!editing.value) return
    saving.value = true
    router.patch(`/settings/page-content/${editing.value.id}`, { ...form.value, is_active: form.value.is_active ? 1 : 0 }, {
        preserveScroll: true,
        onSuccess: () => { toast.success('Section saved.'); closeEdit() },
        onError: (e) => toast.error(Object.values(e)[0] as string ?? 'Save failed'),
        onFinish: () => { saving.value = false },
    })
}

function toggle(s: Section) {
    router.post(`/settings/page-content/${s.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(s.is_active ? 'Section hidden.' : 'Section shown.'),
        onError: () => toast.error('Failed to toggle.'),
    })
}

// ── Drag-to-reorder ────────────────────────────────────────────────────────────
const localSections = ref([...props.sections].sort((a, b) => {
    if (a.position !== b.position) return a.position === 'before_products' ? -1 : 1
    return a.display_order - b.display_order
}))

const dragging  = ref<number | null>(null)
const dragOver  = ref<number | null>(null)

function onDragStart(id: number) { dragging.value = id }
function onDragOver(id: number) { if (dragging.value !== id) dragOver.value = id }
function onDrop(targetId: number) {
    const from = localSections.value.findIndex(s => s.id === dragging.value)
    const to   = localSections.value.findIndex(s => s.id === targetId)
    if (from === -1 || to === -1 || from === to) return

    const arr = [...localSections.value]
    const [item] = arr.splice(from, 1)
    arr.splice(to, 0, item)
    localSections.value = arr
    dragging.value  = null
    dragOver.value  = null

    router.post('/settings/page-content/reorder', {
        order: arr.map(s => s.id),
    }, { preserveScroll: true, onSuccess: () => toast.success('Order saved.') })
}
function onDragEnd() { dragging.value = null; dragOver.value = null }

// ── Media picker ───────────────────────────────────────────────────────────────
const showMediaPicker = ref(false)

function insertMediaUrl(url: string) {
    const tag = `<img src="${url}" alt="" class="max-w-full rounded-xl" />`
    form.value.content = (form.value.content ?? '') + '\n' + tag
    showMediaPicker.value = false
    toast.success('Image tag inserted at end of content.')
}

// ── Section groups for display ─────────────────────────────────────────────────
const beforeSections = computed(() => localSections.value.filter(s => s.position === 'before_products'))
const afterSections  = computed(() => localSections.value.filter(s => s.position === 'after_products'))

// ── Media files loaded for picker ─────────────────────────────────────────────
const mediaFiles = ref<{ id: number; url: string; original_name: string; is_image: boolean }[]>([])
const loadingMedia = ref(false)

async function loadMedia() {
    loadingMedia.value = true
    try {
        const res = await fetch('/settings/media/json', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        mediaFiles.value = await res.json()
    } catch {
        toast.error('Failed to load media files.')
    } finally {
        loadingMedia.value = false
    }
}

function openMediaPicker() { showMediaPicker.value = true; loadMedia() }
</script>

<template>
    <Head title="Page Content" />

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold">Page Content</h2>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Edit the HTML content of each section on the public homepage. Drag rows to reorder.
                </p>
            </div>
            <a href="/" target="_blank"
                class="flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium hover:bg-muted shrink-0">
                <ExternalLink class="h-3.5 w-3.5" /> Preview Site
            </a>
        </div>

        <!-- ── Section: Before Products ───────────────────────────────────── -->
        <div class="space-y-2">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Before Product Grid</span>
                <div class="flex-1 h-px bg-border"></div>
            </div>
            <SectionRow
                v-for="s in beforeSections" :key="s.id"
                :section="s" :drag-over="dragOver === s.id"
                @edit="openEdit" @toggle="toggle"
                @dragstart="onDragStart(s.id)" @dragover.prevent="onDragOver(s.id)"
                @drop.prevent="onDrop(s.id)" @dragend="onDragEnd"
            />
        </div>

        <!-- ── Product Grid placeholder ───────────────────────────────────── -->
        <div class="rounded-xl border border-dashed border-orange-500/30 bg-orange-950/10 px-5 py-4 flex items-center gap-3">
            <LayoutTemplate class="h-5 w-5 text-orange-500 shrink-0" />
            <div>
                <p class="text-sm font-semibold text-orange-400">Product Grid</p>
                <p class="text-xs text-muted-foreground mt-0.5">Auto-generated from the Products database. Manage products at <a href="/products" class="underline hover:text-foreground">/products</a>.</p>
            </div>
        </div>

        <!-- ── Section: After Products ────────────────────────────────────── -->
        <div class="space-y-2">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-bold uppercase tracking-widest text-muted-foreground">After Product Grid</span>
                <div class="flex-1 h-px bg-border"></div>
            </div>
            <SectionRow
                v-for="s in afterSections" :key="s.id"
                :section="s" :drag-over="dragOver === s.id"
                @edit="openEdit" @toggle="toggle"
                @dragstart="onDragStart(s.id)" @dragover.prevent="onDragOver(s.id)"
                @drop.prevent="onDrop(s.id)" @dragend="onDragEnd"
            />
        </div>
    </div>

    <!-- ── Edit Modal ──────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="editing" class="fixed inset-0 z-50 flex flex-col bg-background">
            <!-- Modal header -->
            <div class="flex items-center justify-between border-b px-5 py-3 shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="closeEdit" class="rounded-lg p-1.5 hover:bg-muted"><X class="h-4 w-4" /></button>
                    <div>
                        <p class="font-bold text-sm">{{ editing.label }}</p>
                        <p class="text-xs text-muted-foreground font-mono">{{ editing.key }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="openMediaPicker"
                        class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-muted">
                        <ImageIcon class="h-3.5 w-3.5" /> Insert Media
                    </button>
                    <button @click="save" :disabled="saving"
                        class="flex items-center gap-1.5 rounded-lg bg-primary px-4 py-1.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-40">
                        <Save class="h-3.5 w-3.5" />
                        {{ saving ? 'Saving…' : 'Save Section' }}
                    </button>
                </div>
            </div>

            <!-- Meta bar -->
            <div class="flex flex-wrap items-center gap-4 border-b px-5 py-2.5 text-xs shrink-0 bg-muted/30">
                <div class="flex items-center gap-2">
                    <label class="font-semibold text-muted-foreground">Label</label>
                    <input v-model="form.label" type="text"
                        class="rounded-md border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-primary w-48" />
                </div>
                <div class="flex items-center gap-2">
                    <label class="font-semibold text-muted-foreground">Position</label>
                    <select v-model="form.position"
                        class="rounded-md border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="before_products">Before product grid</option>
                        <option value="after_products">After product grid</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="font-semibold text-muted-foreground">Order</label>
                    <input v-model.number="form.display_order" type="number" min="0"
                        class="rounded-md border bg-background px-2 py-1 text-xs w-16 focus:outline-none focus:ring-1 focus:ring-primary" />
                </div>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input v-model="form.is_active" type="checkbox" class="rounded" />
                    <span class="font-semibold text-muted-foreground">Visible</span>
                </label>
            </div>

            <!-- Tab bar -->
            <div class="flex border-b shrink-0">
                <button v-for="t in [{ id: 'code', label: 'HTML Code' }, { id: 'preview', label: 'Preview' }]" :key="t.id"
                    @click="tab = t.id as 'code' | 'preview'"
                    :class="['px-5 py-2.5 text-sm font-medium border-b-2 transition',
                        tab === t.id ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground']">
                    {{ t.label }}
                </button>
            </div>

            <!-- Code editor -->
            <div v-show="tab === 'code'" class="flex-1 flex flex-col min-h-0">
                <textarea
                    v-model="form.content"
                    spellcheck="false"
                    class="flex-1 w-full p-4 font-mono text-xs bg-zinc-950 text-green-300 resize-none focus:outline-none leading-relaxed"
                    placeholder="<section class=&quot;py-24 px-6&quot;>&#10;  <!-- Your HTML here -->&#10;</section>"
                ></textarea>
                <div class="px-4 py-2 border-t bg-muted/20 text-xs text-muted-foreground flex items-center justify-between">
                    <span>Use Tailwind classes and inline styles. Media URLs available at <code class="font-mono bg-muted px-1 rounded">/storage/media/filename</code>.</span>
                    <span>{{ (form.content ?? '').length.toLocaleString() }} chars</span>
                </div>
            </div>

            <!-- Preview -->
            <div v-show="tab === 'preview'" class="flex-1 overflow-y-auto bg-[#0a0602] text-white min-h-0">
                <div v-if="!form.content" class="flex items-center justify-center h-full text-muted-foreground text-sm">
                    No content to preview.
                </div>
                <div v-else v-html="form.content"></div>
            </div>
        </div>
    </Teleport>

    <!-- ── Media Picker Modal ──────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showMediaPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-3xl rounded-2xl bg-background shadow-2xl border border-border flex flex-col max-h-[80vh]">
                <div class="flex items-center justify-between border-b px-5 py-4 shrink-0">
                    <h3 class="font-bold">Media Library</h3>
                    <button @click="showMediaPicker = false" class="rounded-lg p-1.5 hover:bg-muted"><X class="h-4 w-4" /></button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div v-if="loadingMedia" class="text-center py-10 text-muted-foreground text-sm">Loading…</div>
                    <div v-else-if="!mediaFiles.length" class="text-center py-10 text-muted-foreground text-sm">
                        No media uploaded yet. Go to <a href="/settings/media" target="_blank" class="underline">Media Library</a> to upload files.
                    </div>
                    <div v-else class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-3">
                        <button
                            v-for="f in mediaFiles" :key="f.id"
                            @click="insertMediaUrl(f.url)"
                            class="group relative rounded-xl overflow-hidden border border-border hover:border-primary aspect-square bg-muted/30 transition"
                            :title="f.original_name"
                        >
                            <img v-if="f.is_image" :src="f.url" :alt="f.original_name" class="h-full w-full object-cover group-hover:scale-105 transition-transform" />
                            <div v-else class="h-full w-full flex items-center justify-center">
                                <span class="text-3xl">📄</span>
                            </div>
                            <div class="absolute inset-x-0 bottom-0 bg-black/70 px-2 py-1 opacity-0 group-hover:opacity-100 transition">
                                <p class="text-[10px] text-white truncate">{{ f.original_name }}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="border-t px-5 py-3 text-xs text-muted-foreground flex items-center justify-between shrink-0">
                    <span>Click any image to insert its &lt;img&gt; tag into the editor.</span>
                    <a href="/settings/media" target="_blank" class="flex items-center gap-1 hover:text-foreground">
                        <ExternalLink class="h-3 w-3" /> Open full media library
                    </a>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<!-- ── SectionRow sub-component ──────────────────────────────────────────────── -->
<script lang="ts">
import { defineComponent, h } from 'vue'
import { GripVertical, Pencil, Eye, EyeOff } from 'lucide-vue-next'

export default defineComponent({
    name: 'SectionRow',
    props: {
        section: { type: Object as () => any, required: true },
        dragOver: { type: Boolean, default: false },
    },
    emits: ['edit', 'toggle'],
    setup(props, { emit, attrs }) {
        return () =>
            h('div', {
                draggable: true,
                ...attrs,
                class: [
                    'flex items-center gap-3 rounded-xl border px-4 py-3 bg-card transition cursor-default',
                    props.dragOver ? 'border-primary bg-primary/5' : 'border-border',
                    !props.section.is_active ? 'opacity-50' : '',
                ],
            }, [
                h(GripVertical, { class: 'h-4 w-4 text-muted-foreground cursor-grab shrink-0' }),
                h('div', { class: 'flex-1 min-w-0' }, [
                    h('p', { class: 'text-sm font-semibold truncate' }, props.section.label),
                    h('p', { class: 'text-xs text-muted-foreground font-mono' }, props.section.key),
                ]),
                h('span', {
                    class: [
                        'text-[11px] font-semibold px-2 py-0.5 rounded-full',
                        props.section.position === 'before_products'
                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    ],
                }, props.section.position === 'before_products' ? 'Before grid' : 'After grid'),
                h('button', {
                    title: props.section.is_active ? 'Hide section' : 'Show section',
                    class: 'rounded-lg p-1.5 hover:bg-muted text-muted-foreground',
                    onClick: () => emit('toggle', props.section),
                }, [h(props.section.is_active ? Eye : EyeOff, { class: 'h-4 w-4' })]),
                h('button', {
                    title: 'Edit HTML',
                    class: 'rounded-lg p-1.5 hover:bg-muted text-muted-foreground hover:text-foreground',
                    onClick: () => emit('edit', props.section),
                }, [h(Pencil, { class: 'h-4 w-4' })]),
            ])
    },
})
</script>
