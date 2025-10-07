<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
    suppliers: {
        type: Array,
        required: true
    },
    currencies: {
        type: Array,
        required: true
    },
    purchases: {
        type: Array,
        default: () => []
    },
    paymentMethods: {
        type: Array,
        default: () => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە']
    },
    preselectedPurchase: {
        type: Object,
        default: null
    },
    preselectedSupplier: {
        type: Object,
        default: null
    }
})

const form = useForm({
    supplier_id: null,
    purchase_id: null,
    amount: '',
    currency_id: null,
    paid_at: new Date().toISOString().split('T')[0],
    payment_method: 'نەقد',
    note: '',
    redirect_to_purchase: false
})

const selectedSupplier = ref(null)
const supplierPurchases = ref([])

const formatNumber = (number) => {
    return Number(number || 0).toLocaleString()
}

// Initialize with preselected data
onMounted(() => {
    if (props.preselectedSupplier) {
        form.supplier_id = props.preselectedSupplier.id
        selectedSupplier.value = props.preselectedSupplier
    }

    if (props.preselectedPurchase) {
        form.purchase_id = props.preselectedPurchase.id
        form.supplier_id = props.preselectedPurchase.supplier_id

        // Pre-fill amount with due amount
        if (props.preselectedPurchase.due_amount) {
            form.amount = props.preselectedPurchase.due_amount
        }

        // Pre-fill currency if purchase has items with currency
        if (props.preselectedPurchase.items && props.preselectedPurchase.items.length > 0) {
            const firstItemCurrency = props.preselectedPurchase.items[0].currency_id
            if (firstItemCurrency) {
                form.currency_id = firstItemCurrency
            }
        }

        // Set redirect flag
        form.redirect_to_purchase = true

        // Load purchases for this supplier
        supplierPurchases.value = props.purchases.filter(p => p.supplier_id === props.preselectedPurchase.supplier_id)
    }
})

// Watch for supplier selection to load their purchases
watch(() => form.supplier_id, (supplierId) => {
    if (supplierId) {
        selectedSupplier.value = props.suppliers.find(s => s.id === supplierId)
        // Filter purchases for selected supplier
        supplierPurchases.value = props.purchases.filter(p => p.supplier_id === supplierId)

        // Only reset purchase_id if not preselected
        if (!props.preselectedPurchase) {
            form.purchase_id = null
        }
    } else {
        selectedSupplier.value = null
        supplierPurchases.value = []
        form.purchase_id = null
    }
})

const submit = () => {
    form.post(route('supplier-payments.store'), {
        preserveScroll: true
    })
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">زیادکردنی پارەدان بە فرۆشیار</h1>
            <Link :href="route('supplier-payments.index')" class="secondary-btn">
                گەڕانەوە بۆ لیست
            </Link>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 border rounded p-6 space-y-6">
            <!-- Purchase Context Banner -->
            <div
                v-if="preselectedPurchase"
                class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded p-4"
            >
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="flex-1">
                        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">
                            پارەدان بۆ پسوڵەی کڕین #{{ preselectedPurchase.id }}
                        </h3>
                        <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                            <p>فرۆشیار: <span class="font-medium">{{ preselectedPurchase.supplier?.name }}</span></p>
                            <p>کۆی گشتی: <span class="font-medium">{{ formatNumber(preselectedPurchase.total) }}</span></p>
                            <p>بڕی قەرز: <span class="font-semibold text-red-600">{{ formatNumber(preselectedPurchase.due_amount) }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supplier Selection -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-2">
                        فرۆشیار <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.supplier_id"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.supplier_id }"
                        required
                    >
                        <option :value="null">فرۆشیارێک هەڵبژێرە</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                            {{ supplier.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.supplier_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.supplier_id }}
                    </p>
                    <p v-if="selectedSupplier && selectedSupplier.due_amount" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        قەرزی فرۆشیار: <span class="font-semibold text-red-600">{{ Number(selectedSupplier.due_amount).toLocaleString() }}</span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">
                        پسوڵەی کڕین
                    </label>
                    <select
                        v-model="form.purchase_id"
                        class="input-field"
                        :class="{ 'border-red-500': form.errors.purchase_id }"
                        :disabled="!form.supplier_id"
                    >
                        <option :value="null">هەمووی</option>
                        <option v-for="purchase in supplierPurchases" :key="purchase.id" :value="purchase.id">
                            #{{ purchase.id }} - {{ Number(purchase.due_amount || 0).toLocaleString() }} قەرز
                        </option>
                    </select>
                    <p v-if="form.errors.purchase_id" class="text-red-500 text-sm mt-1">
                        {{ form.errors.purchase_id }}
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
                <Link :href="route('supplier-payments.index')" class="secondary-btn">
                    پاشگەزبوونەوە
                </Link>
            </div>
        </form>
    </div>
</template>
