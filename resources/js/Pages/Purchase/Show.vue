<script setup>
import {Link} from '@inertiajs/vue3'
import {computed} from 'vue'

const props = defineProps({
    purchase: {
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
const currencyTotals = computed(() => {
    const totals = {}

    if (props.purchase.items && props.purchase.items.length) {
        props.purchase.items.forEach(item => {
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
            <h1 class="text-xl font-bold">کڕینی ژمارە #{{ purchase.id }}</h1>
            <div class="flex items-center gap-2">
                <Link :href="purchase.pay_url" class="primary-btn">
                    پارەدان
                </Link>
                <Link v-if="purchase.supplier?.purchases_url" :href="purchase.supplier.purchases_url" class="secondary-btn">
                    لیستی پسوڵەکان
                </Link>
            </div>
        </div>

        <!-- Purchase & Supplier Info -->
        <div class="grid md:grid-cols-2 gap-4">
            <!-- Purchase Details Card -->
            <div class="bg-white border rounded p-4 text-gray-800 dark:text-white dark:bg-gray-800">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="font-medium">ڕێکەوت : </span>
                        <span>{{ purchase.purchase_date }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="font-medium">تێبینی:</span>
                        <span>{{ purchase.notes || '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- Supplier Info Card -->
            <div class="bg-white border rounded p-4 text-gray-800 dark:text-white dark:bg-gray-800">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="font-medium">فرۆشیار:</span>
                        <span>{{ purchase.supplier?.name || '—' }}</span>
                    </div>
                    <div>
                        <span class="font-medium">مۆبایل:</span>
                        <span>{{ purchase.supplier?.phone || '—' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="font-medium">ناونیشان:</span>
                        <span>{{ purchase.supplier?.address || '—' }}</span>
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
                    <div v-for="(total, currency) in currencyTotals" :key="currency" class="flex items-baseline gap-2">
                        <p class="text-2xl font-extrabold">{{ formatNumber(total) }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ currency }}</p>
                    </div>
                </div>
                <p v-else class="text-2xl font-extrabold">{{ formatNumber(0) }}</p>
            </div>

            <!-- Paid Amount -->
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-sm">پارەی دراو</p>
                <p class="text-2xl font-extrabold mt-1">
                    {{ formatNumber(purchase.paid_amount) }}
                </p>
            </div>

            <!-- Due Amount -->
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-sm">بڕی قەرز</p>
                <p class="text-2xl font-extrabold mt-1" :class="getDueAmountClass(purchase.due_amount)">
                    {{ formatNumber(purchase.due_amount) }}
                </p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-auto border rounded dark:border-gray-200">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700 dark:text-white">
                <tr>
                    <th>#</th>
                    <th>ناوی کاڵا</th>
                    <th>بڕ</th>
                    <th>نرخی تاک</th>
                    <th>نرخی کۆ</th>
                    <th>تێبینی</th>
                </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 dark:text-white">
                <tr
                    v-for="(item, index) in purchase.items" :key="item.id" class="border-t dark:border-gray-700">
                    <td>{{ index + 1 }}</td>
                    <td>{{ item.product.name || '—' }}</td>
                    <td>{{ formatNumber(item.quantity) }}</td>
                    <td>{{ formatNumber(item.unit_price) }}</td>
                    <td>{{ formatNumber(item.unit_price * item.quantity) }} / <span class="text-xs text-red-500">{{ item.currency.name }}</span></td>
                    <td>{{ item.note || '' }}</td>
                </tr>
                <tr v-if="!purchase.items || purchase.items.length === 0">
                    <td colspan="6" class="p-4 text-center text-gray-500 dark:text-gray-400">
                        هیچ نەدۆزرایەوە
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Payment History -->
        <div v-if="purchase.payments && purchase.payments.length"
             class="bg-white border rounded p-4 text-gray-800 dark:text-white dark:bg-gray-800">
            <h2 class="font-semibold mb-3">مێژووی پارەدان</h2>
            <ul class="space-y-2">
                <li v-for="payment in purchase.payments" :key="payment.id" class="flex justify-between items-center text-sm p-2 bg-gray-50 dark:bg-gray-700 rounded">
                    <span>
                        <span class="font-medium">{{ payment.paid_at }}</span>
                        <span v-if="payment.note" class="text-gray-600 dark:text-gray-400"> - {{ payment.note }}</span>
                    </span>
                    <span class="font-semibold">{{ formatNumber(payment.amount) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
