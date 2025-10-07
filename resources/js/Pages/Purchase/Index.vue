<script setup>
import {ref, watch} from 'vue';
import {router} from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import PaginationLinks from "@/Pages/Components/PaginationLinks.vue";

const props = defineProps({
    purchases: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

const search = ref(props.filters.search || '');
const paymentStatus = ref(props.filters.payment_status || '');

// Search functionality
const performSearch = () => {
    router.get(route('purchases.index'), {
        search: search.value,
        payment_status: paymentStatus.value
    }, {
        preserveState: true,
        replace: true
    });
};

// Watch for changes and search
watch([search, paymentStatus], () => {
    performSearch();
});

// Delete purchase
const deletePurchase = (id) => {
    Swal.fire({
        title: 'دڵنیای؟',
        text: "ئایا دڵنیایت لە سڕینەوەی ئەم کڕینە؟",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'بەڵێ، بیسڕەوە!',
        cancelButtonText: 'پاشگەزبوونەوە'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('purchases.destroy', id), {
                onSuccess: () => {
                    Swal.fire({
                        position: "top-end",
                        icon: "success",
                        title: "بەسەرکەوتویی سڕایەوە!",
                        showConfirmButton: false,
                        timerProgressBar: true,
                        timer: 2000
                    });
                }
            });
        }
    });
};

// Get status badge color
const getStatusColor = (status) => {
    const colors = {
        'paid': 'bg-green-100 text-green-800',
        'partial': 'bg-yellow-100 text-yellow-800',
        'unpaid': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

// Get status text in Kurdish
const getStatusText = (status) => {
    const texts = {
        'paid': 'پارەدراو',
        'partial': 'بەشێک پارەدراو',
        'unpaid': 'پارە نەدراو'
    };
    return texts[status] || status;
};

// Format number with thousand separator
const formatNumber = (number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }).format(number || 0);
};

// Reset filters
const resetFilters = () => {
    search.value = '';
    paymentStatus.value = '';
};
</script>

<template>
    <Head title="لیستی کڕین"/>
    <div class="container mx-auto mt-4">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-md">
            <h1 class="text-sm lg:text-xl">لیستی کڕین</h1>
            <Link :href="route('purchases.create')" class="primary-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                <span>کڕینی نوێ</span>
            </Link>
        </div>

        <!-- Filters -->
        <div class="border border-gray-200 rounded-md p-4 mt-2">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-white">گەڕان</label>
                    <input v-model="search" type="text" placeholder="گەڕان بە ژمارەی کڕین یان ناوی دابینکەر..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                </div>

                <!-- Payment Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-white">دۆخی پارەدان</label>
                    <select v-model="paymentStatus"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">هەموو</option>
                        <option value="paid">پارەدراو</option>
                        <option value="partial">بەشێک پارەدراو</option>
                        <option value="unpaid">پارە نەدراو</option>
                    </select>
                </div>

                <!-- Reset Button -->
                <div class="flex items-end">
                    <button @click="resetFilters" class="w-full px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                        سڕینەوەی فلتەر
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="border border-gray-200 rounded-md mt-2 overflow-x-auto p-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                <tr>
                    <th>
                        #
                    </th>
                    <th>
                        ژمارەی کڕین
                    </th>
                    <th>
                        دابینکەر
                    </th>
                    <th>
                        بەروار
                    </th>
                    <th>
                        کۆی گشتی
                    </th>
                    <th>
                        دۆخی پارەدان
                    </th>
                    <th>
                        زیادکراوە لەلایەن
                    </th>
                    <th>
                        کردارەکان
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(purchase, index) in purchases.data" :key="purchase.id">
                    <td>
                        {{ purchases.from + index }}
                    </td>
                    <td>
                        {{ purchase.purchase_number }}
                    </td>
                    <td>
                        {{ purchase.supplier?.name }}
                    </td>
                    <td>
                        {{ new Date(purchase.purchase_date).toLocaleDateString('en-GB') }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div v-for="(total, idx) in purchase.totals_by_currency" :key="idx" class="mb-1">
                            <span class="font-semibold dark:text-white">{{ formatNumber(total.total) }}</span>
                            <span class="text-gray-600 mr-2 dark:text-white text-xs">{{ total.currency_name }}</span>
                        </div>
                    </td>
                    <td>
                        <span :class="getStatusColor(purchase.payment_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
                            {{ getStatusText(purchase.payment_status) }}
                        </span>
                    </td>
                    <td>
                        {{ purchase.created_by?.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex gap-2">
                            <!-- View Button -->
                            <Link :href="route('purchases.show', purchase.id)" class="text-blue-600 hover:text-blue-900" title="بینین">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                            </Link>

                            <!-- Edit Button -->
                            <Link :href="route('purchases.edit', purchase.id)" class="text-yellow-600 hover:text-yellow-900" title="دەستکاری">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                                </svg>
                            </Link>

                            <!-- Delete Button -->
                            <button @click="deletePurchase(purchase.id)" class="text-red-600 hover:text-red-900" title="سڕینەوە">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>

                <!-- Empty State -->
                <tr v-if="purchases.data.length === 0">
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 mx-auto mb-4 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                        </svg>
                        <p class="text-lg font-medium">هیچ کڕینێک نەدۆزرایەوە</p>
                        <p class="text-sm mt-2">دەستپێبکە بە زیادکردنی یەکەمین کڕینت</p>
                        <Link :href="route('purchases.create')" class="primary-btn mt-4 inline-flex">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            <span>کڕینی نوێ</span>
                        </Link>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="mt-2">
            <PaginationLinks :paginator="purchases"/>
        </div>
    </div>
</template>

