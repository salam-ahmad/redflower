<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    sale: {
        type: Object,
        required: true
    }
})

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

const getDueAmountClass = (amount) => {
    return amount > 0 ? 'text-red-600' : 'text-green-600'
}

// Calculate totals per currency
const currencyTotals = computed(() => {
    const totals = {}

    if (props.sale.items && props.sale.items.length) {
        props.sale.items.forEach(item => {
            const currencyName = item.currency?.name || 'N/A'
            const itemTotal = (item.unit_price || 0) * (item.quantity || 0)

            if (totals[currencyName]) {
                totals[currencyName] += itemTotal
            } else {
                totals[currencyName] = itemTotal
            }
        })
    }

    return totals
})
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header Section -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold">فرۆشتنی ژمارە #{{ sale.id }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    v-if="sale.due_amount > 0"
                    :href="route('sales.receive-payment', sale.id)"
                    class="primary-btn"
                >
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    وەرگرتنی پارە
                </Link>
                <Link
                    :href="route('sales.edit', sale.id)"
                    class="secondary-btn"
                >
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    دەستکاریکردن
                </Link>
                <Link
                    v-if="sale.customer?.sales_url"
                    :href="sale.customer.sales_url"
                    class="secondary-btn"
                >
                    لیستی پسوڵەکان
                </Link>
                <Link
                    :href="route('sales.index')"
                    class="secondary-btn"
                >
                    گەڕانەوە
                </Link>
            </div>
        </div>

        <!-- Sale & Customer Info -->
        <div class="grid md:grid-cols-2 gap-4">
            <!-- Sale Details Card -->
            <div class="bg-white border rounded p-4 text-gray-800 dark:text-white dark:bg-gray-800">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="font-medium">ڕێکەوت:</span>
                        <span>{{ sale.date }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="font-medium">تێبینی:</span>
                        <span>{{ sale.note || '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- Customer Info Card -->
            <div class="bg-white border rounded p-4 text-gray-800 dark:text-white dark:bg-gray-800">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="font-medium">کڕیار:</span>
                        <Link
                            v-if="sale.customer"
                            :href="route('customers.show', sale.customer.id)"
                            class="text-blue-600 hover:underline"
                        >
                            {{ sale.customer.name }}
                        </Link>
                        <span v-else>—</span>
                    </div>
                    <div>
                        <span class="font-medium">مۆبایل:</span>
                        <span>{{ sale.customer?.phone || '—' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="font-medium">ناونیشان:</span>
                        <span>{{ sale.customer?.address || '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-800 dark:text-white">
            <!-- Total Amount by Currency -->
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-sm mb-2">کۆی گشتی</p>
                <div v-if="Object.keys(currencyTotals).length > 0" class="space-y-1">
                    <div
                        v-for="(total, currency) in currencyTotals"
                        :key="currency"
                        class="flex items-baseline gap-2"
                    >
                        <p class="text-2xl font-extrabold">{{ formatNumber(total) }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ currency }}</p>
                    </div>
                </div>
                <p v-else class="text-2xl font-extrabold">{{ formatNumber(0) }}</p>
            </div>

            <!-- Paid Amount -->
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-sm">پارەی وەرگیراو</p>
                <p class="text-2xl font-extrabold mt-1 text-green-600">
                    {{ formatNumber(sale.paid_amount) }}
                </p>
            </div>

            <!-- Due Amount -->
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-sm">بڕی قەرز</p>
                <p
                    class="text-2xl font-extrabold mt-1"
                    :class="getDueAmountClass(sale.due_amount)"
                >
                    {{ formatNumber(sale.due_amount) }}
                </p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-auto border rounded dark:border-gray-200">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700 dark:text-white">
                <tr>
                    <th class="px-4 py-2 text-center">#</th>
                    <th class="px-4 py-2 text-right">ناوی کاڵا</th>
                    <th class="px-4 py-2 text-center">بڕ</th>
                    <th class="px-4 py-2 text-center">نرخی تاک</th>
                    <th class="px-4 py-2 text-center">نرخی کۆ</th>
                    <th class="px-4 py-2 text-right">تێبینی</th>
                </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 dark:text-white">
                <tr
                    v-for="(item, index) in sale.items"
                    :key="item.id"
                    class="border-t dark:border-gray-700"
                >
                    <td class="px-4 py-2 text-center">{{ index + 1 }}</td>
                    <td class="px-4 py-2 text-right">{{ item.product?.name || '—' }}</td>
                    <td class="px-4 py-2 text-center">{{ formatNumber(item.quantity) }}</td>
                    <td class="px-4 py-2 text-center">
                        {{ formatNumber(item.unit_price) }} {{ item.currency?.name || '' }}
                    </td>
                    <td class="px-4 py-2 text-center">
                        {{ formatNumber(item.unit_price * item.quantity) }} {{ item.currency?.name || '' }}
                    </td>
                    <td class="px-4 py-2 text-right">{{ item.note || '' }}</td>
                </tr>
                <tr v-if="!sale.items || sale.items.length === 0">
                    <td colspan="6" class="p-4 text-center text-gray-500 dark:text-gray-400">
                        هیچ نەدۆزرایەوە
                    </td>
                </tr>
                </tbody>
                <tfoot v-if="sale.items && sale.items.length" class="bg-gray-50 dark:bg-gray-700 dark:text-white">
                <tr v-for="(total, currency) in currencyTotals" :key="currency">
                    <td colspan="4" class="px-4 py-2 text-right font-semibold border-t dark:border-gray-600">
                        کۆی گشتی ({{ currency }})
                    </td>
                    <td colspan="1" class="px-4 py-2 text-center font-extrabold border-t dark:border-gray-600">
                        {{ formatNumber(total) }}
                    </td>
                    <td class="border-t dark:border-gray-600"></td>
                </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payment History -->
        <div
            v-if="sale.payments && sale.payments.length"
            class="bg-white border rounded p-4 text-gray-800 dark:text-white dark:bg-gray-800"
        >
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">مێژووی پارەوەرگرتن</h2>
                <Link
                    v-if="sale.due_amount > 0"
                    :href="route('sales.receive-payment', sale.id)"
                    class="text-sm text-blue-600 hover:underline"
                >
                    + زیادکردنی پارەدان
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                    <tr>
                        <th class="px-3 py-2 text-right">بەروار</th>
                        <th class="px-3 py-2 text-center">بڕی پارە</th>
                        <th class="px-3 py-2 text-center">دراو</th>
                        <th class="px-3 py-2 text-center">شێواز</th>
                        <th class="px-3 py-2 text-right">تێبینی</th>
                        <th class="px-3 py-2 text-center">کردار</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                    <tr
                        v-for="payment in sale.payments"
                        :key="payment.id"
                        class="text-sm"
                    >
                        <td class="px-3 py-2 text-right">{{ payment.paid_at }}</td>
                        <td class="px-3 py-2 text-center font-semibold text-green-600">
                            {{ formatNumber(payment.amount) }}
                        </td>
                        <td class="px-3 py-2 text-center">{{ payment.currency?.name || '—' }}</td>
                        <td class="px-3 py-2 text-center">
                                <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-600">
                                    {{ payment.payment_method || '—' }}
                                </span>
                        </td>
                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                            {{ payment.note || '—' }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <Link
                                :href="route('customer-payments.show', payment.id)"
                                class="text-blue-600 hover:text-blue-800"
                                title="بینین"
                            >
                                <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </Link>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- No Payments Yet -->
        <div
            v-else-if="sale.due_amount > 0"
            class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded p-4 text-center"
        >
            <p class="text-gray-700 dark:text-gray-300 mb-3">
                هێشتا هیچ پارەیەک وەرنەگیراوە بۆ ئەم پسوڵەیە
            </p>
            <Link
                :href="route('sales.receive-payment', sale.id)"
                class="primary-btn inline-block"
            >
                زیادکردنی یەکەم پارەوەرگرتن
            </Link>
        </div>
    </div>
</template>
