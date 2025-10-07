<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
    customers: {
        type: Array,
        required: true
    },
    currencies: {
        type: Array,
        required: true
    },
    sales: {
        type: Array,
        default: () => []
    },
    paymentMethods: {
        type: Array,
        default: () => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە']
    },
    preselectedSale: {
        type: Object,
        default: null
    },
    preselectedCustomer: {
        type: Object,
        default: null
    }
})

const form = useForm({
    customer_id: null,
    sale_id: null,
    amount: '',
    currency_id: null,
    paid_at: new Date().toISOString().split('T')[0],
    payment_method: 'نەقد',
    note: '',
    redirect_to_sale: false
})

const selectedCustomer = ref(null)
const customerSales = ref([])

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

// Initialize with preselected data
onMounted(() => {
    if (props.preselectedCustomer) {
        form.customer_id = props.preselectedCustomer.id
        selectedCustomer.value = props.preselectedCustomer
    }

    if (props.preselectedSale) {
        form.sale_id = props.preselectedSale.id
        form.customer_id = props.preselectedSale.customer_id

        // Pre-fill amount with due amount
        if (props.preselectedSale.due_amount) {
            form.amount = props.preselectedSale.due_amount
        }

        // Pre-fill currency if sale has items with currency
        if (props.preselectedSale.items && props.preselectedSale.items.length > 0) {
            const firstItemCurrency = props.preselectedSale.items[0].currency_id
            if (firstItemCurrency) {
                form.currency_id = firstItemCurrency
            }
        }

        // Set redirect flag
        form.redirect_to_sale = true

        // Load sales for this customer
        customerSales.value = props.sales.filter(s => s.customer_id === props.preselectedSale.customer_id)
    }
})

// Watch for customer selection to load their sales
watch(() => form.customer_id, (customerId) => {
    if (customerId) {
        selectedCustomer.value = props.customers.find(c => c.id === customerId)
        // Filter sales for selected customer
        customerSales.value = props.sales.filter(s => s.customer_id === customerId)

        // Only reset sale_id if not preselected
        if (!props.preselectedSale) {
            form.sale_id = null
        }
    } else {
        selectedCustomer.value = null
        customerSales.value = []
        form.sale_id = null
    }
})

const submit = () => {
    form.post(route('customer-payments.store'), {
        preserveScroll: true
    })
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">زیادکردنی پارەدانی کڕیار</h1>
            <Link :href="route('customer-payments.index')" class="secondary-btn">
                گەڕانەوە بۆ لیست
            </Link>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 border rounded p-6 space-y-6">
            <!-- Sale Context Banner -->
            <div
                v-if="preselectedSale"
                class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded p-4"
            >
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="flex-1">
                        <h3 class="font-semibold text-green-900 dark:text-green-100 mb-1">
                            وەرگرتنی پارە بۆ پسوڵەی فرۆشتن #{{ preselectedSale.id }}
                        </h3>
                        <div class="text-sm text-green-800 dark:text-green-200 space-y-1">
                            <p>کڕیار: <span class="font-medium">{{ preselectedSale.customer?.name }}</span></p>
                            <p>کۆی گشتی: <span class="font-medium">{{ formatNumber(preselectedSale.total) }}</span></p>
                            <p>بڕی قەرز: <span class="font-semibold text-red-600">{{ formatNumber(preselectedSale.due_amount) }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Selection -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">
                        کڕیار <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.customer_id"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.customer_id }"
                        required
                    >
                        <option :value="null">کڕیارێک هەڵبژێرە</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.customer_id }}
                    </p>
                    <p v-if="selectedCustomer && selectedCustomer.due_amount" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        قەرزی کڕیار: <span class="font-semibold text-red-600">{{ Number(selectedCustomer.due_amount).toLocaleString() }}</span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">
                        پسوڵەی فرۆشتن
                    </label>
                    <select
                        v-model="form.sale_id"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.sale_id }"
                        :disabled="!form.customer_id"
                    >
                        <option :value="null">هەمووی</option>
                        <option v-for="sale in customerSales" :key="sale.id" :value="sale.id">
                            #{{ sale.id }} - {{ Number(sale.due_amount || 0).toLocaleString() }} قەرز
                        </option>
                    </select>
                    <p v-if="form.errors.sale_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.sale_id }}
                    </p>
                </div>
            </div>

            <!-- Amount and Currency -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">
                        بڕی پارە <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.amount }"
                        placeholder="بڕی پارە"
                        required
                    >
                    <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">
                        {{ form.errors.amount }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">
                        جۆری دراو <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.currency_id"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.currency_id }"
                        required
                    >
                        <option :value="null">جۆری دراو هەڵبژێرە</option>
                        <option v-for="currency in currencies" :key="currency.id" :value="currency.id">
                            {{ currency.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.currency_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.currency_id }}
                    </p>
                </div>
            </div>

            <!-- Date and Payment Method -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">
                        بەرواری پارەدان <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.paid_at"
                        type="date"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.paid_at }"
                        required
                    >
                    <p v-if="form.errors.paid_at" class="text-red-500 text-sm mt-1">
                        {{ form.errors.paid_at }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">
                        شێوازی پارەدان
                    </label>
                    <select
                        v-model="form.payment_method"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.payment_method }"
                    >
                        <option v-for="method in paymentMethods" :key="method" :value="method">
                            {{ method }}
                        </option>
                    </select>
                    <p v-if="form.errors.payment_method" class="text-red-500 text-sm mt-1">
                        {{ form.errors.payment_method }}
                    </p>
                </div>
            </div>

            <!-- Note -->
            <div>
                <label class="block text-sm font-medium mb-2">تێبینی</label>
                <textarea
                    v-model="form.note"
                    rows="3"
                    class="input-field"
                    :class="{ 'border-red-500': form.errors.note }"
                    placeholder="تێبینی زیاتر..."
                ></textarea>
                <p v-if="form.errors.note" class="text-red-500 text-sm mt-1">
                    {{ form.errors.note }}
                </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-4 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="primary-btn"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                >
                    {{ form.processing ? 'تکایە چاوەڕێ بە...' : 'پاشەکەوتکردن' }}
                </button>
                <Link :href="route('customer-payments.index')" class="secondary-btn">
                    پاشگەزبوونەوە
                </Link>
            </div>
        </form>
    </div>
</template>
