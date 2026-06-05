<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'

defineOptions({ layout: null })

interface OrderItem { name: string; quantity: number; unit_price: number; subtotal: number }
interface OrderPayment { method: string; amount: number; change: number; status: string }
interface Order {
    id: number; queue_number: number | null; order_type: string; status: string
    payment_status: string; table_number: string | null
    customer_name: string | null; customer_contact: string | null; customer_address: string | null
    notes: string | null; cashier: string | null; created_at: string
    subtotal: number; discount_amount: number; tax_amount: number; total_amount: number
    items: OrderItem[]; payment: OrderPayment | null
}

const props = defineProps<{ order: Order }>()

const fmt = (v: number) => '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const qrUrl = computed(() => window.location.href)

const orderTypeLabel = (t: string) => ({ dine_in: 'Dine In', takeout: 'Takeout', delivery: 'Delivery' }[t] ?? t)

const statusColor = (s: string) => ({
    pending:   'bg-yellow-100 text-yellow-800',
    preparing: 'bg-blue-100 text-blue-800',
    ready:     'bg-green-100 text-green-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
}[s] ?? 'bg-gray-100 text-gray-800')
</script>

<template>
    <Head :title="`Order #${order.id} — Bypass Grill`" />

    <div class="min-h-screen bg-gray-50 py-6 px-4">
        <div class="max-w-md mx-auto">

            <!-- Header -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-black tracking-tight">BYPASS GRILL</h1>
                <p class="text-sm text-gray-500 mt-0.5">Filipino Grill Restaurant</p>
            </div>

            <!-- Order Card -->
            <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">

                <!-- Order Number Banner -->
                <div class="bg-gray-900 text-white px-5 py-4 text-center">
                    <p class="text-xs uppercase tracking-widest text-gray-400 mb-0.5">
                        {{ order.queue_number ? 'Queue Number' : 'Order Number' }}
                    </p>
                    <p class="text-4xl font-black">
                        {{ order.queue_number ? '#' + order.queue_number : '#' + order.id }}
                    </p>
                    <div class="flex items-center justify-center gap-2 mt-2">
                        <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full capitalize', statusColor(order.status)]">
                            {{ order.status }}
                        </span>
                        <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full capitalize', statusColor(order.payment_status)]">
                            {{ order.payment_status === 'paid' ? 'Paid' : 'Payment Pending' }}
                        </span>
                    </div>
                </div>

                <!-- Order Info -->
                <div class="px-5 py-4 border-b space-y-2.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Order #</span>
                        <span class="font-semibold">{{ order.id }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Type</span>
                        <span class="font-semibold">{{ orderTypeLabel(order.order_type) }}</span>
                    </div>
                    <div v-if="order.table_number" class="flex justify-between text-sm">
                        <span class="text-gray-500">Table</span>
                        <span class="font-semibold">{{ order.table_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Date</span>
                        <span class="font-semibold">{{ order.created_at }}</span>
                    </div>
                    <div v-if="order.cashier" class="flex justify-between text-sm">
                        <span class="text-gray-500">Cashier</span>
                        <span class="font-semibold">{{ order.cashier }}</span>
                    </div>
                </div>

                <!-- Customer Info (delivery / named customer) -->
                <div v-if="order.customer_name || order.customer_address" class="px-5 py-4 border-b bg-gray-50 space-y-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Customer</p>
                    <div v-if="order.customer_name" class="flex justify-between text-sm">
                        <span class="text-gray-500">Name</span>
                        <span class="font-semibold">{{ order.customer_name }}</span>
                    </div>
                    <div v-if="order.customer_contact" class="flex justify-between text-sm">
                        <span class="text-gray-500">Contact</span>
                        <span class="font-semibold">{{ order.customer_contact }}</span>
                    </div>
                    <div v-if="order.customer_address" class="flex justify-between text-sm">
                        <span class="text-gray-500">Address</span>
                        <span class="font-semibold text-right max-w-[60%]">{{ order.customer_address }}</span>
                    </div>
                </div>

                <!-- Items -->
                <div class="px-5 py-4 border-b">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Items</p>
                    <div class="space-y-2">
                        <div v-for="item in order.items" :key="item.name"
                            class="flex items-start justify-between text-sm gap-2">
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold">{{ item.quantity }}×</span>
                                <span class="ml-1">{{ item.name }}</span>
                                <div class="text-xs text-gray-400">{{ fmt(item.unit_price) }} each</div>
                            </div>
                            <span class="font-semibold tabular-nums">{{ fmt(item.subtotal) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="px-5 py-4 border-b space-y-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Summary</p>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="tabular-nums">{{ fmt(order.subtotal) }}</span>
                    </div>
                    <div v-if="order.discount_amount > 0" class="flex justify-between text-sm text-red-600">
                        <span>Discount</span>
                        <span class="tabular-nums">-{{ fmt(order.discount_amount) }}</span>
                    </div>
                    <div v-if="order.tax_amount > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500">Tax</span>
                        <span class="tabular-nums">{{ fmt(order.tax_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-black pt-1 border-t">
                        <span>TOTAL</span>
                        <span class="tabular-nums">{{ fmt(order.total_amount) }}</span>
                    </div>
                </div>

                <!-- Payment Info -->
                <div v-if="order.payment" class="px-5 py-4 border-b bg-green-50 space-y-2">
                    <p class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-2">Payment</p>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Method</span>
                        <span class="font-semibold">{{ order.payment.method }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Amount Paid</span>
                        <span class="font-semibold tabular-nums">{{ fmt(order.payment.amount) }}</span>
                    </div>
                    <div v-if="order.payment.change > 0" class="flex justify-between text-sm font-bold text-green-700">
                        <span>Change</span>
                        <span class="tabular-nums">{{ fmt(order.payment.change) }}</span>
                    </div>
                </div>
                <div v-else class="px-5 py-3 bg-yellow-50 text-center">
                    <p class="text-sm font-semibold text-yellow-700">⏳ Payment Pending</p>
                </div>

                <!-- Notes -->
                <div v-if="order.notes" class="px-5 py-3 border-t bg-gray-50 text-sm text-gray-600">
                    <span class="font-semibold">Note:</span> {{ order.notes }}
                </div>

                <!-- QR Code -->
                <div class="px-5 py-5 border-t text-center">
                    <p class="text-xs text-gray-400 mb-3 uppercase tracking-wide">Scan to view this order</p>
                    <div class="flex justify-center">
                        <img
                            :src="`https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(qrUrl)}&bgcolor=ffffff&color=111111&margin=2`"
                            alt="Order QR Code"
                            class="rounded-lg border"
                            width="140"
                            height="140"
                        />
                    </div>
                    <p class="text-xs text-gray-400 mt-2 break-all font-mono">{{ qrUrl }}</p>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 bg-gray-900 text-white text-center">
                    <p class="text-sm font-semibold">Thank you for dining with us!</p>
                    <p class="text-xs text-gray-400 mt-0.5">Please come again ♥</p>
                </div>

            </div>

            <p class="text-center text-xs text-gray-400 mt-4">Bypass Grill · bypassgrill.baconologies.com</p>
        </div>
    </div>
</template>
