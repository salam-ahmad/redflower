<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    payments: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    }
})

const search = ref(props.filters.search || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

const applyFilters = () => {
    router.get(route('customer-payments.index'), {
        search: search.value,
        date_from: dateFrom.value,
        date_to: dateTo.value
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    search.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    router.get(route('customer-payments.index'))
}

const deletePayment = (payment) => {
    if (confirm('دڵنیای لە سڕینەوەی ئەم پارەدانە؟')) {
        router.delete(route('customer-payments.destroy', payment.id))
    }
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">پارەی وەرگیراو لە کڕیاران</h1>
            <Link :href="route('customer-payments.create')" class="primary-btn">
                + زیادکردنی پارەدان
            </Link>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 border rounded p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">گەڕان</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="گەڕان بە ناوی کڕیار، ژمارە..."
                        class="input-field"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">لە بەرواری</label>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="input-field"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">بۆ بەرواری</label>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="input-field"
                    >
                </div>
                <div class="flex items-end gap-2">
                    <button @click="applyFilters" class="primary-btn flex-1">
                        گەڕان
                    </button>
                    <button @click="resetFilters" class="secondary-btn">
                        سڕینەوە
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">کۆی پارەی وەرگیراو</p>
                <p class="text-2xl font-extrabold text-green-600 mt-1">
                    {{ formatNumber(payments.meta?.total_amount || 0) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">ژمارەی پارەدانەکان</p>
                <p class="text-2xl font-extrabold mt-1">
                    {{ formatNumber(payments.total || 0) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">مانگی ئەم مانگە</p>
                <p class="text-2xl font-extrabold text-blue-600 mt-1">
                    {{ formatNumber(payments.meta?.current_month_amount || 0) }}
                </p>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="bg-white dark:bg-gray-800 border rounded overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-center">#</th>
                        <th class="px-4 py-3 text-right">کڕیار</th>
                        <th class="px-4 py-3 text-center">بڕی پارە</th>
                        <th class="px-4 py-3 text-center">دراوی پارە</th>
                        <th class="px-4 py-3 text-center">بەروار</th>
                        <th class="px-4 py-3 text-center">شێوازی پارەدان</th>
                        <th class="px-4 py-3 text-right">تێبینی</th>
                        <th class="px-4 py-3 text-center">کردارەکان</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                    <tr
                        v-for="(payment, index) in payments.data"
                        :key="payment.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <td class="px-4 py-3 text-center">
                            {{ (payments.current_page - 1) * payments.per_page + index + 1 }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                v-if="payment.customer"
                                :href="route('customers.show', payment.customer.id)"
                                class="text-blue-600 hover:underline"
                            >
                                {{ payment.customer.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold">
                            {{ formatNumber(payment.amount) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            {{ payment.currency?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            {{ payment.paid_at }}
                        </td>
                        <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded text-xs bg-gray-100 dark:bg-gray-600">
                                    {{ payment.payment_method || '—' }}
                                </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-400">
                            {{ payment.note || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <Link
                                    :href="route('customer-payments.show', payment.id)"
                                    class="text-blue-600 hover:text-blue-800"
                                    title="بینین"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </Link>
                                <Link
                                    :href="route('customer-payments.edit', payment.id)"
                                    class="text-yellow-600 hover:text-yellow-800"
                                    title="دەستکاریکردن"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </Link>
                                <button
                                    @click="deletePayment(payment)"
                                    class="text-red-600 hover:text-red-800"
                                    title="سڕینەوە"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!payments.data || payments.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            هیچ پارەدانێک نەدۆزرایەوە
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="payments.last_page > 1" class="px-4 py-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ payments.from }} - {{ payments.to }} لە {{ payments.total }}
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-for="link in payments.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-3 py-1 rounded text-sm',
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600',
                                !link.url && 'opacity-50 cursor-not-allowed'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
