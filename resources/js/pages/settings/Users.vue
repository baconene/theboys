<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { UserPlus, Trash2, Shield, Check, X } from 'lucide-vue-next'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/profile' },
            { title: 'Users', href: '/settings/users' },
        ],
    },
})

const props = defineProps<{
    users: { id: number; name: string; email: string; roles: string[]; created_at: string }[]
    availableRoles: string[]
}>()

const page = usePage()
const currentUserId = computed(() => (page.props.auth?.user as any)?.id)

// ── Per-row role editing ────────────────────────────────────────────────────
const editingId = ref<number | null>(null)
const editRoles = ref<string[]>([])

const startEdit = (user: typeof props.users[0]) => {
    editingId.value = user.id
    editRoles.value = [...user.roles]
}
const cancelEdit = () => { editingId.value = null; editRoles.value = [] }

const toggleRole = (role: string) => {
    if (editRoles.value.includes(role)) {
        editRoles.value = editRoles.value.filter(r => r !== role)
    } else {
        editRoles.value.push(role)
    }
}

const saveRoles = (userId: number) => {
    if (editRoles.value.length === 0) {
        toast.error('User must have at least one role')
        return
    }
    router.patch(`/settings/users/${userId}`, { roles: editRoles.value }, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; toast.success('Roles updated') },
        onError: () => toast.error('Failed to update roles'),
    })
}

const deleteUser = (user: typeof props.users[0]) => {
    if (!confirm(`Delete "${user.name}"? This cannot be undone.`)) return
    router.delete(`/settings/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('User deleted'),
        onError: () => toast.error('Failed to delete user'),
    })
}

// ── Create user form ────────────────────────────────────────────────────────
const showForm = ref(false)
const form = ref({ name: '', email: '', password: '', roles: ['cashier'] as string[] })
const saving = ref(false)

const toggleFormRole = (role: string) => {
    if (form.value.roles.includes(role)) {
        form.value.roles = form.value.roles.filter(r => r !== role)
    } else {
        form.value.roles.push(role)
    }
}

const submitForm = () => {
    if (!form.value.name || !form.value.email || !form.value.password) {
        toast.error('All fields are required')
        return
    }
    if (form.value.roles.length === 0) {
        toast.error('Select at least one role')
        return
    }
    saving.value = true
    router.post('/settings/users', form.value, {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false
            form.value = { name: '', email: '', password: '', roles: ['cashier'] }
            toast.success('User created')
        },
        onError: (errors) => {
            const first = Object.values(errors)[0] as string
            toast.error(first ?? 'Failed to create user')
        },
        onFinish: () => { saving.value = false },
    })
}

const roleBadgeColor = (r: string) => ({
    admin:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    cashier: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    kitchen: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    auditor: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
}[r] ?? 'bg-muted text-muted-foreground')
</script>

<template>
    <Head title="User Management" />

    <div class="space-y-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-base font-semibold">User Management</h2>
                <p class="text-sm text-muted-foreground mt-0.5">Manage staff accounts and their access roles.</p>
            </div>
            <button
                @click="showForm = !showForm"
                class="flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90"
            >
                <UserPlus class="h-4 w-4" />
                Add User
            </button>
        </div>

        <!-- Create user form -->
        <div v-if="showForm" class="rounded-xl border bg-card shadow-sm p-5 space-y-4">
            <h3 class="font-semibold text-sm flex items-center gap-2"><UserPlus class="h-4 w-4" /> New Staff Account</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Full Name</label>
                    <input v-model="form.name" type="text" placeholder="e.g. Juan dela Cruz"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Email Address</label>
                    <input v-model="form.email" type="email" placeholder="staff@bypassgrill.com"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Password</label>
                    <input v-model="form.password" type="password" placeholder="Min. 8 characters"
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground block mb-1">Roles</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <button
                            v-for="role in availableRoles" :key="role"
                            @click="toggleFormRole(role)"
                            :class="[
                                'rounded-full px-3 py-1 text-xs font-semibold border transition capitalize',
                                form.roles.includes(role)
                                    ? roleBadgeColor(role) + ' border-transparent'
                                    : 'border-border text-muted-foreground hover:bg-muted',
                            ]"
                        >{{ role }}</button>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 pt-1">
                <button @click="submitForm" :disabled="saving"
                    class="rounded-lg bg-primary px-5 py-2 text-sm font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-50">
                    {{ saving ? 'Creating…' : 'Create User' }}
                </button>
                <button @click="showForm = false"
                    class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted">Cancel</button>
            </div>
        </div>

        <!-- Users table -->
        <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-muted-foreground text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Roles</th>
                        <th class="px-4 py-3 text-left">Joined</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-medium">{{ user.name }}</span>
                                <span v-if="user.id === currentUserId" class="text-xs text-muted-foreground">(you)</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ user.email }}</td>
                        <td class="px-4 py-3">
                            <!-- Viewing mode -->
                            <template v-if="editingId !== user.id">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in user.roles" :key="role"
                                        :class="['rounded-full px-2 py-0.5 text-xs font-semibold capitalize', roleBadgeColor(role)]">
                                        {{ role }}
                                    </span>
                                    <span v-if="user.roles.length === 0" class="text-xs text-muted-foreground">No roles</span>
                                </div>
                            </template>
                            <!-- Editing mode -->
                            <template v-else>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="role in availableRoles" :key="role"
                                        @click="toggleRole(role)"
                                        :class="[
                                            'rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize border transition',
                                            editRoles.includes(role)
                                                ? roleBadgeColor(role) + ' border-transparent'
                                                : 'border-border text-muted-foreground',
                                        ]"
                                    >{{ role }}</button>
                                </div>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground text-xs">{{ user.created_at }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <template v-if="editingId !== user.id">
                                    <button @click="startEdit(user)"
                                        class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted flex items-center gap-1">
                                        <Shield class="h-3 w-3" /> Roles
                                    </button>
                                    <button
                                        v-if="user.id !== currentUserId"
                                        @click="deleteUser(user)"
                                        class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 flex items-center gap-1">
                                        <Trash2 class="h-3 w-3" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button @click="saveRoles(user.id)"
                                        class="rounded-lg bg-primary px-2.5 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 flex items-center gap-1">
                                        <Check class="h-3 w-3" /> Save
                                    </button>
                                    <button @click="cancelEdit"
                                        class="rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-muted flex items-center gap-1">
                                        <X class="h-3 w-3" /> Cancel
                                    </button>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No users found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Role legend -->
        <div class="rounded-xl border bg-card shadow-sm p-4">
            <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Role Permissions</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="role in availableRoles" :key="role" class="rounded-lg bg-muted/40 p-3">
                    <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold capitalize', roleBadgeColor(role)]">{{ role }}</span>
                    <p class="text-xs text-muted-foreground mt-2">{{
                        role === 'admin' ? 'Full access — all features, settings, and user management.' :
                        role === 'cashier' ? 'Point of Sale access — take orders and process payments.' :
                        role === 'kitchen' ? 'Kitchen Monitor — view and update order status.' :
                        'Inventory & Reports — manage stock and view reports.'
                    }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
