<script setup>
import {useForm, Link} from '@inertiajs/vue3'
import {ref, computed} from 'vue'

const props = defineProps({
    sale: {
        type: Object,
        required: true
    },
    customers: {
        type: Array,
        required: true
    },
    products: {
        type: Array,
        required: true
    },
    currencies: {
        type: Array,
        required: true
    }
})

const form = useForm({
    customer_id: props.sale.customer_id,
    date: props.sale.date,
    note: props.sale.note || '',
    items: props.sale.items.map(item => ({
        id: item.id,
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
        currency_id: item.currency_id,
        note: item.note || ''
    }))
})

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

// Calculate total for each item
const getItemTotal = (item) => {
    return (item.quantity || 0) * (item.unit_price || 0)
}

// Calculate grand total
const grandTotal = computed(() => {
    return form.items.reduce((sum, item) => sum + getItemTotal(item), 0)
})

// Group totals by currency
const currencyTotals = computed(() => {
    const totals = {}
    form.items.forEach(item => {
        const currency = props.currencies.find(c => c.id === item.currency_id)
        const currencyName = currency?.name || 'N/A'
        const itemTotal = getItemTotal(item)

        if (totals[currencyName]) {
            totals[currencyName] += itemTotal
        } else {
            totals[currencyName] = itemTotal
        }
    })
    return totals
})

const addItem = () => {
    form.items.push({
        product_id: null,
        quantity: 1,
        unit_price: 0,
        currency_id: null,
        note: ''
    })
}

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1)
    }
}

const getProduct = (productId) => {
    return props.products.find(p => p.id === productId)
}

// Auto-fill price when product is selected
const onProductChange = (item, productId) => {
    const product = getProduct(productId)
    if (product) {
        item.unit_price = product.sale_price || product.price || 0
    }
}

const submit = () => {
    form.put(route('sales.update', props.sale.id), {
        preserveScroll: true
    })
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">دەستکاریکردنی فرۆشتن #{{ sale.id }}</h1>
            <div class="flex items-center gap-2">
                <Link :href="route('sales.show', sale.id)" class="secondary-btn">
                    بینین
                </Link>
                <Link :href="route('sales.index')" class="secondary-btn">
                    گەڕانەوە بۆ لیست
                </Link>
            </div>
        </div>

        <!-- Payment Warning -->
        <div v-if="sale.paid_amount > 0" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded p-4">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="font-semibold text-yellow-900 dark:text-yellow-100">ئاگاداری</h3>
                    <p class="text-sm text-yellow-800 dark:text-yellow-200 mt-1">
                        ئەم فرۆشتنە پارەی بەشێکی وەرگرتووە ({{ formatNumber(sale.paid_amount) }}). دەستکاری کردن دەکرێت کاریگەری لەسەر هەژماری پارەکان دابنێت.
                    </p>
                </div>
            </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-4">
            <!-- Sale Info Card -->
            <div class="bg-white dark:bg-gray-800 border rounded p-6 space-y-4">
                <h2 class="text-lg font-semibold border-b pb-2">زانیاری فرۆشتن</h2>

                <div class="grid md:grid-cols-2 gap-4">
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
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">
                            بەرواری فرۆشتن <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.date"
                            type="date"
                            class="input-field"
                            :class="{ 'border-red-500': form.errors.date }"
                            required
                        >
                        <p v-if="form.errors.date" class="text-red-500 text-sm mt-1">
                            {{ form.errors.date }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">تێبینی</label>
                    <textarea
                        v-model="form.note"
                        rows="2"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.note }"
                        placeholder="تێبینی زیاتر لەسەر فرۆشتنەکە..."
                    ></textarea>
                    <p v-if="form.errors.note" class="text-red-500 text-sm mt-1">
                        {{ form.errors.note }}
                    </p>
                </div>
            </div>

            <!-- Items Section -->
            <div class="bg-white dark:bg-gray-800 border rounded p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h2 class="text-lg font-semibold">کاڵاکان</h2>
                    <button
                        type="button"
                        @click="addItem"
                        class="text-sm bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700"
                    >
                        + زیادکردنی کاڵا
                    </button>
                </div>

                <!-- Items List -->
                <div class="space-y-4">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="border dark:border-gray-700 rounded p-4 space-y-3"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium">کاڵای {{ index + 1 }}</h3>
                            <button
                                v-if="form.items.length > 1"
                                type="button"
                                @click="removeItem(index)"
                                class="text-red-600 hover:text-red-800 text-sm"
                            >
                                سڕینەوە
                            </button>
                        </div>

                        <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-3">
                            <!-- Product -->
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium mb-1">
                                    کاڵا <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="item.product_id"
                                    @change="onProductChange(item, item.product_id)"
                                    class="input-field text-sm"
                                    :class="{ 'border-red-500': form.errors[`items.${index}.product_id`] }"
                                    required
                                >
                                    <option :value="null">کاڵایەک هەڵبژێرە</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.name }}
                                        <span v-if="product.unit">({{ product.unit.name }})</span>
                                    </option>
                                </select>
                                <p v-if="form.errors[`items.${index}.product_id`]" class="text-red-500 text-xs mt-1">
                                    {{ form.errors[`items.${index}.product_id`] }}
                                </p>
                            </div>

                            <!-- Quantity -->
                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    بڕ <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model.number="item.quantity"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="input-field text-sm"
                                    :class="{ 'border-red-500': form.errors[`items.${index}.quantity`] }"
                                    required
                                >
                                <p v-if="form.errors[`items.${index}.quantity`]" class="text-red-500 text-xs mt-1">
                                    {{ form.errors[`items.${index}.quantity`] }}
                                </p>
                            </div>

                            <!-- Unit Price -->
                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    نرخی تاک <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model.number="item.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="input-field text-sm"
                                    :class="{ 'border-red-500': form.errors[`items.${index}.unit_price`] }"
                                    required
                                >
                                <p v-if="form.errors[`items.${index}.unit_price`]" class="text-red-500 text-xs mt-1">
                                    {{ form.errors[`items.${index}.unit_price`] }}
                                </p>
                            </div>

                            <!-- Currency -->
                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    دراو <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="item.currency_id"
                                    class="input-field text-sm"
                                    :class="{ 'border-red-500': form.errors[`items.${index}.currency_id`] }"
                                    required
                                >
                                    <option :value="null">هەڵبژێرە</option>
                                    <option v-for="currency in currencies" :key="currency.id" :value="currency.id">
                                        {{ currency.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors[`items.${index}.currency_id`]" class="text-red-500 text-xs mt-1">
                                    {{ form.errors[`items.${index}.currency_id`] }}
                                </p>
                            </div>
                        </div>

                        <!-- Item Note & Total -->
                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">تێبینی</label>
                                <input
                                    v-model="item.note"
                                    type="text"
                                    class="input-field text-sm"
                                    placeholder="تێبینی بۆ ئەم کاڵایە..."
                                >
                            </div>
                            <div class="flex items-end">
                                <div class="w-full p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    <p class="text-xs text-gray-600 dark:text-gray-400">کۆی کاڵا</p>
                                    <p class="text-lg font-bold">{{ formatNumber(getItemTotal(item)) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="bg-white dark:bg-gray-800 border rounded p-6">
                <h2 class="text-lg font-semibold border-b pb-2 mb-4">کورتەی داواکاری</h2>

                <div class="space-y-2">
                    <div v-for="(total, currency) in currencyTotals" :key="currency" class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">کۆی گشتی ({{ currency }})</span>
                        <span class="text-2xl font-bold">{{ formatNumber(total) }}</span>
                    </div>
                    <div v-if="Object.keys(currencyTotals).length === 0" class="text-center text-gray-500 py-4">
                        هیچ کاڵایەک زیاد نەکراوە
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-4 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing || form.items.length === 0"
                    class="primary-btn"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing || form.items.length === 0 }"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-5 w-5 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? 'تکایە چاوەڕێ بە...' : 'نوێکردنەوە' }}
                </button>
                <Link :href="route('sales.show', sale.id)" class="secondary-btn">
                    پاشگەزبوونەوە
                </Link>
            </div>
        </form>
    </div>
</template>
