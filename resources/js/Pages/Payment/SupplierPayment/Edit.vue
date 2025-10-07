<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    payment: {
        type: Object,
        required: true
    },
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
    }
})

const form = useForm({
    supplier_id: props.payment.supplier_id,
    purchase_id: props.payment.purchase_id,
    amount: props.payment.amount,
    currency_id: props.payment.currency_id,
    paid_at: props.payment.paid_at,
    payment_method: props.payment.payment_method || 'نەقد',
    note: props.payment.note || ''
})

const selectedSupplier = ref(null)
const supplierPurchases = ref([])

// Initialize supplier data
if (form.supplier_id) {
    selectedSupplier.value = props.suppliers.find(s => s.id === form.supplier_id)
    supplierPurchases.value = props.purchases.filter(p => p.supplier_id === form.supplier_id)
}

// Watch for supplier selection to load their purchases
watch(() => form.supplier_id, (supplierId) => {
    if (supplierId) {
        selectedSupplier.value = props.suppliers.find(s => s.id === supplierId)
        supplierPurchases.value = props.purchases.filter(p => p.supplier_id === supplierId)

        // Reset purchase_id if it doesn't belong to the new supplier
        if (form.purchase_id && !supplierPurchases.value.find(p => p.id === form.purchase_id)) {
            form.purchase_id = null
        }
    } else {
        selectedSupplier.value = null
        supplierPurchases.value = []
        form.purchase_id = null
    }
})

const submit = () => {
    form.put(route('supplier-payments.update', props.payment.id), {
        preserveScroll: true
    })
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">دەستکاریکردنی پارەدان #{{ payment.id }}</h1>
            <div class="flex items-center gap-2">
                <Link :href="route('supplier-payments.show', payment.id)" class="secondary-btn">
                    بینین
                </Link>
                <Link :href="route('supplier-payments.index')" class="secondary-btn">
                    گەڕانەوە بۆ لیست
                </Link>
            </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 border rounded p-6 space-y-6">
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
                    {{ form.processing ? 'تکایە چاوەڕێ بە...' : 'نوێکردنەوە' }}
                </button>
                <Link :href="route('supplier-payments.show', payment.id)" class="secondary-btn">
                    پاشگەزبوونەوە
                </Link>
            </div>
        </form>
    </div>
</template>
