<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    sales: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    customers: {
        type: Array,
        default: () => []
    }
})

const search = ref(props.filters.search || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')
const customerId = ref(props.filters.customer_id || '')

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

const applyFilters = () => {
    router.get(route('sales.index'), {
        search: search.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        customer_id: customerId.value
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

const resetFilters = () => {
    search.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    customerId.value = ''
    router.get(route('sales.index'))
}

const deleteSale = (sale) => {
    if (confirm('دڵنیای لە سڕینەوەی ئەم فرۆشتنە؟')) {
        router.delete(route('sales.destroy', sale.id))
    }
}

const getPaymentStatusClass = (sale) => {
    if (sale.due_amount === 0) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    } else if (sale.paid_amount > 0) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    } else {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    }
}

const getPaymentStatusText = (sale) => {
    if (sale.due_amount === 0) {
        return 'تەواو'
    } else if (sale.paid_amount > 0) {
        return 'بەشێک'
    } else {
        return 'نەدراو'
    }
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">فرۆشتنەکان</h1>
            <Link :href="route('sales.create')" class="primary-btn">
                <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                زیادکردنی فرۆشتن
            </Link>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 border rounded p-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">گەڕان</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="ژمارەی پسوڵە، ناوی کڕیار..."
                        class="input-field"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">کڕیار</label>
                    <select
                        v-model="customerId"
                        class="input-field"
                    >
                        <option value="">هەموو کڕیارەکان</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
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
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">کۆی فرۆشتن</p>
                <p class="text-2xl font-extrabold mt-1">
                    {{ formatNumber(sales.data?.length || 0) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">کۆی گشتی</p>
                <p class="text-2xl font-extrabold text-blue-600 mt-1">
                    {{ formatNumber(sales.meta?.total_amount || 0) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">پارەی وەرگیراو</p>
                <p class="text-2xl font-extrabold text-green-600 mt-1">
                    {{ formatNumber(sales.meta?.paid_amount || 0) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 border rounded p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">بڕی قەرز</p>
                <p class="text-2xl font-extrabold text-red-600 mt-1">
                    {{ formatNumber(sales.meta?.due_amount || 0) }}
                </p>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="bg-white dark:bg-gray-800 border rounded overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-center">#</th>
                        <th class="px-4 py-3 text-right">کڕیار</th>
                        <th class="px-4 py-3 text-center">بەروار</th>
                        <th class="px-4 py-3 text-center">کۆی گشتی</th>
                        <th class="px-4 py-3 text-center">پارەی دراو</th>
                        <th class="px-4 py-3 text-center">قەرز</th>
                        <th class="px-4 py-3 text-center">دۆخی پارەدان</th>
                        <th class="px-4 py-3 text-right">تێبینی</th>
                        <th class="px-4 py-3 text-center">کردارەکان</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                    <tr
                        v-for="sale in sales.data"
                        :key="sale.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <td class="px-4 py-3 text-center font-semibold">
                            {{ sale.id }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                v-if="sale.customer"
                                :href="route('customers.show', sale.customer.id)"
                                class="text-blue-600 hover:underline font-medium"
                            >
                                {{ sale.customer.name }}
                            </Link>
                            <span v-else class="text-gray-500">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            {{ sale.date }}
                        </td>
                        <td class="px-4 py-3 text-center font-semibold">
                            {{ formatNumber(sale.total) }}
                        </td>
                        <td class="px-4 py-3 text-center text-green-600 font-medium">
                            {{ formatNumber(sale.paid_amount) }}
                        </td>
                        <td class="px-4 py-3 text-center font-medium" :class="sale.due_amount > 0 ? 'text-red-600' : 'text-green-600'">
                            {{ formatNumber(sale.due_amount) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                                <span
                                    class="px-2 py-1 rounded text-xs font-medium"
                                    :class="getPaymentStatusClass(sale)"
                                >
                                    {{ getPaymentStatusText(sale) }}
                                </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-400">
                            {{ sale.note || '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <Link
                                    :href="route('sales.show', sale.id)"
                                    class="text-blue-600 hover:text-blue-800"
                                    title="بینین"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </Link>
                                <Link
                                    :href="route('sales.edit', sale.id)"
                                    class="text-yellow-600 hover:text-yellow-800"
                                    title="دەستکاریکردن"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </Link>
                                <button
                                    v-if="sale.due_amount > 0"
                                    @click="router.visit(route('sales.receive-payment', sale.id))"
                                    class="text-green-600 hover:text-green-800"
                                    title="وەرگرتنی پارە"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </button>
                                <button
                                    @click="deleteSale(sale)"
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
                    <tr v-if="!sales.data || sales.data.length === 0">
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                            هیچ فرۆشتنێک نەدۆزرایەوە
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="sales.last_page > 1" class="px-4 py-3 border-t dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ sales.from }} - {{ sales.to }} لە {{ sales.total }}
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-for="link in sales.links"
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
