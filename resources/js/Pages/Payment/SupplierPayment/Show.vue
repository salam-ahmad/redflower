<script setup>
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    payment: {
        type: Object,
        required: true
    }
})

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

const deletePayment = () => {
    if (confirm('دڵنیای لە سڕینەوەی ئەم پارەدانە؟')) {
        router.delete(route('supplier-payments.destroy', props.payment.id), {
            onSuccess: () => {
                router.visit(route('supplier-payments.index'))
            }
        })
    }
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">پارەدانی ژمارە #{{ payment.id }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('supplier-payments.edit', payment.id)"
                    class="primary-btn"
                >
                    دەستکاریکردن
                </Link>
                <button @click="deletePayment" class="danger-btn">
                    سڕینەوە
                </button>
                <Link :href="route('supplier-payments.index')" class="secondary-btn">
                    گەڕانەوە
                </Link>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="grid md:grid-cols-2 gap-4">
            <!-- Main Info Card -->
            <div class="bg-white dark:bg-gray-800 border rounded p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold mb-4 border-b pb-2">زانیاری پارەدان</h2>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">بڕی پارە</p>
                        <p class="text-2xl font-bold text-red-600 mt-1">
                            {{ formatNumber(payment.amount) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">جۆری دراو</p>
                        <p class="text-xl font-semibold mt-1">
                            {{ payment.currency?.name || '—' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">بەرواری پارەدان</p>
                        <p class="font-medium mt-1">{{ payment.paid_at }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">شێوازی پارەدان</p>
                        <p class="font-medium mt-1">
                            <span class="px-3 py-1 rounded bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                {{ payment.payment_method || '—' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="payment.note">
                    <p class="text-sm text-gray-600 dark:text-gray-400">تێبینی</p>
                    <p class="mt-1 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                        {{ payment.note }}
                    </p>
                </div>
            </div>

            <!-- Supplier Info Card -->
            <div class="bg-white dark:bg-gray-800 border rounded p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold mb-4 border-b pb-2">زانیاری فرۆشیار</h2>
                </div>

                <div v-if="payment.supplier">
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">ناوی فرۆشیار</p>
                            <Link
                                :href="route('suppliers.show', payment.supplier.id)"
                                class="text-lg font-semibold text-blue-600 hover:underline mt-1 inline-block"
                            >
                                {{ payment.supplier.name }}
                            </Link>
                        </div>

                        <div v-if="payment.supplier.phone">
                            <p class="text-sm text-gray-600 dark:text-gray-400">ژمارەی مۆبایل</p>
                            <p class="font-medium mt-1">{{ payment.supplier.phone }}</p>
                        </div>

                        <div v-if="payment.supplier.email">
                            <p class="text-sm text-gray-600 dark:text-gray-400">ئیمەیڵ</p>
                            <p class="font-medium mt-1">{{ payment.supplier.email }}</p>
                        </div>

                        <div v-if="payment.supplier.address">
                            <p class="text-sm text-gray-600 dark:text-gray-400">ناونیشان</p>
                            <p class="font-medium mt-1">{{ payment.supplier.address }}</p>
                        </div>

                        <div v-if="payment.supplier.due_amount !== undefined" class="pt-3 border-t">
                            <p class="text-sm text-gray-600 dark:text-gray-400">قەرزی فرۆشیار</p>
                            <p
                                class="text-xl font-bold mt-1"
                                :class="payment.supplier.due_amount > 0 ? 'text-red-600' : 'text-green-600'"
                            >
                                {{ formatNumber(payment.supplier.due_amount) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-gray-500">
                    زانیاری فرۆشیار بەردەست نییە
                </div>
            </div>
        </div>

        <!-- Purchase Reference (if linked to a specific purchase) -->
        <div v-if="payment.purchase" class="bg-white dark:bg-gray-800 border rounded p-6">
            <h2 class="text-lg font-semibold mb-4 border-b pb-2">پسوڵەی کڕین</h2>
            <div class="grid md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">ژمارەی پسوڵە</p>
                    <Link
                        :href="route('purchases.show', payment.purchase.id)"
                        class="text-lg font-semibold text-blue-600 hover:underline mt-1 inline-block"
                    >
                        #{{ payment.purchase.id }}
                    </Link>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">بەروار</p>
                    <p class="font-medium mt-1">{{ payment.purchase.date }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">کۆی گشتی</p>
                    <p class="font-semibold mt-1">{{ formatNumber(payment.purchase.total) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">قەرز</p>
                    <p
                        class="font-semibold mt-1"
                        :class="payment.purchase.due_amount > 0 ? 'text-red-600' : 'text-green-600'"
                    >
                        {{ formatNumber(payment.purchase.due_amount) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Metadata -->
        <div class="bg-white dark:bg-gray-800 border rounded p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 dark:text-gray-400">
                <div v-if="payment.created_by">
                    <p>دروستکراوە لەلایەن</p>
                    <p class="font-medium text-gray-800 dark:text-white">{{ payment.created_by.name }}</p>
                </div>
                <div v-if="payment.created_at">
                    <p>کاتی دروستکردن</p>
                    <p class="font-medium text-gray-800 dark:text-white">{{ payment.created_at }}</p>
                </div>
                <div v-if="payment.updated_at">
                    <p>دوایین نوێکردنەوە</p>
                    <p class="font-medium text-gray-800 dark:text-white">{{ payment.updated_at }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
