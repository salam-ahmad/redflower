<script setup>
import PaginationLinks from "@/Pages/Components/PaginationLinks.vue";
import SearchableSelect from "@/Pages/Components/SearchableSelect.vue";
import TextInput from "@/Pages/Components/TextInput.vue";
import {Head, Link, useForm, router} from "@inertiajs/vue3";
import {ref, computed, watch} from "vue";
import {debounce} from "lodash";
import Swal from "sweetalert2";

const props = defineProps({
    customers: {type: Array, required: true},
    products: {type: Object, required: true}, // paginated
    currencies: {type: Array, required: true},
    filters: {type: Object, default: () => ({})},
});

// Form state
const form = useForm({
    customer_id: null,
    sale_date: new Date().toISOString().slice(0, 10),
    notes: "",
});

// Cart rows
const rows = ref([]);

// Payment rows (multiple payments, per currency)
const payments = ref([]);

// Search functionality
const search = ref(props.filters?.search || "");
watch(search, debounce((value) => {
    router.get(route("sales.create"), {search: value}, {
        preserveState: true,
        replace: true
    });
}, 300));

// Format number with thousand separator
const formatNumber = (number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }).format(number || 0);
};

// Add product to cart
const addRow = (product) => {
    const stock = Number(product.stock_quantity ?? 0);
    if (stock < 1) {
        showMessage('error', 'ئەم کاڵایە لە کۆگا نەماوە');
        return;
    }

    const idx = rows.value.findIndex(r => r.id === product.id);
    if (idx !== -1) {
        const currentQty = Number(rows.value[idx].quantity || 0);
        if (currentQty >= stock) {
            showMessage('error', `تەنها ${formatNumber(stock)} دانە لەم کاڵایە هەیە`);
            return;
        }
        rows.value[idx].quantity = currentQty + 1;
        return;
    }

    rows.value.push({
        id: product.id,
        barcode: product.barcode || '',
        name: product.name,
        stock: stock,                                            // ✅ keep stock on the row
        quantity: 1,
        unit_price: Number(product.sell_price ?? 0),
        currency_id: product.currency_id,
        currency_name: product.currency?.name ?? '',
    });
};

// Delete row from cart
const deleteRow = (i) => rows.value.splice(i, 1);

// Calculate totals per currency
const totalsByCurrency = computed(() => {
    const map = {};
    for (const r of rows.value) {
        const k = r.currency_id;
        if (!k) continue;
        map[k] ??= {name: r.currency_name, total: 0};
        map[k].total += Number(r.quantity) * Number(r.unit_price || 0);
    }
    return map;
});

// Add payment row
const addPaymentRow = () => {
    const firstCid = Number(Object.keys(totalsByCurrency.value)[0] ?? 0) || null;
    payments.value.push({currency_id: firstCid, amount: null, note: ''});
};

// Remove payment row
const removePaymentRow = (i) => payments.value.splice(i, 1);

// Aggregate paid amounts per currency
const paidByCurrency = computed(() => {
    const map = {};
    for (const p of payments.value) {
        if (!p.currency_id || !p.amount) continue;
        map[p.currency_id] = (map[p.currency_id] ?? 0) + Number(p.amount);
    }
    return map;
});

// Prevent overpayment - clamp amount if exceeds total
watch([payments, totalsByCurrency], () => {
    for (const [cid, paid] of Object.entries(paidByCurrency.value)) {
        const total = totalsByCurrency.value[cid]?.total ?? 0;
        if (paid > total + 0.0001) {
            const idx = payments.value.findLastIndex(r => String(r.currency_id) === String(cid));
            if (idx !== -1) {
                const current = Number(payments.value[idx].amount || 0);
                const others = paid - current;
                payments.value[idx].amount = Math.max(0, total - others);
                showMessage("error", "پارەدان لەم دراوە زیاترە لە کۆی داواکراو.");
            }
        }
    }
}, {deep: true});

// Determine payment method based on coverage
const paymentMethod = computed(() => {
    const ids = Object.keys(totalsByCurrency.value);
    if (!ids.length) return 'cash';
    let anyPaid = false, allCovered = true;
    for (const cid of ids) {
        const t = totalsByCurrency.value[cid]?.total ?? 0;
        const p = paidByCurrency.value[cid] ?? 0;
        if (p > 0) anyPaid = true;
        if (Math.abs(p - t) > 0.0001) allCovered = false;
    }
    return allCovered ? 'cash' : (anyPaid ? 'partial' : 'debt');
});

const mapPaymentStatus = (pm) => (pm === 'cash' ? 'paid' : pm === 'partial' ? 'partial' : 'unpaid');


// Save purchase
const saveSale = () => {
    const pm = paymentMethod.value;
    const payment_status = mapPaymentStatus(pm);
    if ((pm === 'partial' || pm === 'debt') && !form.customer_id) {
        showMessage('error', 'بۆ قەرز/نیوەقەرز، ناوی کڕیار هەڵبژێرە.');
        return;
    }
    if (rows.value.length === 0) {
        showMessage('error', 'هیچ کاڵایەک هەڵنەبژێردراوە.');
        return;
    }

    const sale_items = rows.value.map(r => ({
        product_id: r.id,
        quantity: r.quantity,
        unit_price: r.unit_price,
        currency_id: r.currency_id,
    }));

    const paymentsPayload = payments.value
        .filter(p => p.currency_id && Number(p.amount) > 0)
        .map(p => ({
            currency_id: Number(p.currency_id),
            amount: Number(p.amount),
            note: p.note || ''
        }));

    const payload = {
        customer_id: form.customer_id || null,
        sale_date: form.sale_date,
        notes: form.notes || null,
        payment_status,
        items: sale_items,
        payments: paymentsPayload,
    };

    router.post(route('sales.store'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showMessage('success', 'بەسەرکەوتویی فرۆشرا');
            rows.value = [];
            payments.value = [];
            form.reset('customer_id', 'notes');
        },
        onError: () => showMessage('error', 'هەڵەیەک ڕوویدا لە کاتی فرۆشتن.'),
    });
};

// Show message helper
const showMessage = (icon, title) =>
    Swal.fire({
        position: "top-end",
        icon,
        title,
        showConfirmButton: false,
        timerProgressBar: true,
        timer: 2000
    });

watch(rows, (newRows) => {
    for (const r of newRows) {
        const max = Number(r.stock ?? 0);
        let q = Number(r.quantity ?? 0);

        if (q > max) {
            r.quantity = max;
            showMessage('error', `تەنها ${formatNumber(max)} دانە هەیە`);
        } else if (q < 0) {
            r.quantity = 0;
        }
    }
}, {deep: true});

</script>

<template>
    <Head title="فرۆشتنی نوێ"/>
    <div class="container mx-auto mt-4">
        <!-- Search + Back -->
        <div class="flex gap-4 items-center justify-between p-4 border border-gray-200 rounded-md mb-4">
            <input type="search" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="گەڕان بەپێی ناو یان کۆد ..." v-model="search"
            />
            <Link :href="route('sales.index')" class="primary-btn">
                <span>گەڕانەوە</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18"/>
                </svg>
            </Link>
        </div>

        <!-- Product Grid -->
        <div class="mt-4 p-4 border border-gray-200 rounded-md">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                <div v-for="product in props.products.data" :key="product.id"
                     class="flex flex-col h-full p-4 border border-gray-200 rounded-md text-center hover:shadow-md transition-shadow">
                    <div class="space-y-2 flex-grow">
                        <h5 class="text-xl dark:text-white">
                            نرخی فرۆشتن /
                            <span class="text-red-500">{{ formatNumber(product.sell_price ?? 0) }}</span>
                            <span class="text-xs ms-1 ">{{ product.currency?.name }}</span>
                        </h5>
                        <p class="font-medium dark:text-white">{{ product.name }}</p>
                        <p v-if="product.barcode" class="text-sm text-gray-600 dark:text-white">کۆد / {{ product.barcode }}</p>
                        <p class="dark:text-white">
                            ماوە /
                            <span class="text-red-500">{{ formatNumber(product.stock_quantity ?? 0) }}</span>
                        </p>
                    </div>
                    <button
                        class="primary-btn mt-auto flex items-center justify-center gap-2"
                        @click="addRow(product)"
                        :disabled="(product.stock_quantity ?? 0) < 1"
                        :class="{ 'opacity-50 cursor-not-allowed': (product.stock_quantity ?? 0) < 1 }">
                        <span>زیادکردن</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/>
                            <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-3">
            <PaginationLinks :paginator="props.products"/>
        </div>
        <hr class="my-5 dark:text-white"/>

        <!-- Cart Section -->
        <div v-if="rows.length > 0" class="space-y-2 border border-gray-200 p-4 rounded-md">
            <!-- Header Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-start dark:text-white">
                <!-- Supplier -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 mb-2 dark:text-white">ناوی کڕیار</label>
                    <SearchableSelect v-model="form.customer_id" :options="customers" placeholder="کڕیار هەڵبژێرە" label="name"
                                      value-prop="id" :error="form.errors?.customer_id" no-options-text="هیچ کڕیارێک نەدۆزرایەوە"/>
                </div>

                <!-- Date -->
                <TextInput v-model="form.sale_date" name="ڕێکەوت" type="date"/>

                <!-- Notes -->
                <TextInput v-model="form.notes" name="تێبینی" class="xl:col-span-2"/>

                <!-- Totals by Currency -->
                <div class="xl:col-span-2 ">
                    <label class="block text-sm font-medium text-slate-900 mb-2 dark:text-white">کۆی گشتی بەپێی دراو</label>
                    <div class="rounded-lg border border-indigo-500 p-3 space-y-2">
                        <div v-for="(v, cid) in totalsByCurrency" :key="cid" class="flex items-center justify-between">
                            <span class="text-sm">{{ v.name }}</span>
                            <span class="font-semibold">{{ formatNumber(v.total) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 border-t pt-2 dark:text-white">
                            دۆخ:
                            <span class="font-medium">
                                {{ paymentMethod === 'cash' ? 'نەقد' : (paymentMethod === 'partial' ? 'نیوە قەرز' : 'قەرز') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="flex items-end">
                    <button class="primary-btn w-full" @click="saveSale">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="size-5" viewBox="0 0 16 16">
                            <path d="M11 2H9v3h2z"/>
                            <path
                                d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zM3 15h10v-4.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5z"/>
                        </svg>
                        <span>فرۆشتن</span>
                    </button>
                </div>
            </div>

            <!-- Payments Section -->
            <div class="rounded-lg border border-gray-200 p-3 dark:text-white">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold">پارەوەرگرتن لە کڕیار</h4>
                    <button type="button" class="primary-btn" @click="addPaymentRow">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span>زیادکردنی پارەدان</span>
                    </button>
                </div>

                <div class="space-y-2" v-if="payments.length">
                    <div v-for="(p, i) in payments" :key="i"
                         class="grid grid-cols-1 lg:grid-cols-[1fr_2fr_1fr_auto] gap-2 items-center bg-gray-50 p-2 rounded">
                        <!-- Currency Select -->
                        <select v-model.number="p.currency_id" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option :value="null" disabled>دراو هەڵبژێرە</option>
                            <option v-for="(v, cid) in totalsByCurrency" :key="cid" :value="Number(cid)">
                                {{ v.name }}
                            </option>
                        </select>

                        <!-- Amount + Remaining -->
                        <div class="flex gap-2 items-center">
                            <label class="text-sm whitespace-nowrap">بڕی پارە:</label>
                            <input v-model.number="p.amount" type="number" min="0" step="0.1"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   :max="p.currency_id ? (totalsByCurrency[p.currency_id]?.total ?? 0) : undefined" placeholder="0.0"
                            />
                            <span v-if="p.currency_id" class="text-xs text-gray-600 whitespace-nowrap">
                                ماوە: {{ formatNumber((totalsByCurrency[p.currency_id]?.total ?? 0) - (paidByCurrency[p.currency_id] ?? 0)) }}
                            </span>
                        </div>

                        <!-- Note -->
                        <TextInput v-model="p.note" name="تێبینی (ئارەزوومەندانە)" class="xl:col-span-1"/>
                        <!-- Delete Button -->
                        <button type="button" class="px-3 py-2 rounded bg-red-500 text-white hover:bg-red-600 transition-colors cursor-pointer" @click="removePaymentRow(i)">
                            سڕینەوە
                        </button>
                    </div>
                </div>

                <div v-else class="text-sm text-gray-500 text-center py-2">
                    پارە وەنەگیراوە (ئەگەر هیچ بڕە پارەیەک وەرنەگیرێت فرۆشتنەکە دەبێتە قەرز).
                </div>
            </div>

            <!-- Cart Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-center">
                    <thead>
                    <tr>
                        <th>کۆد</th>
                        <th>ناوی کاڵا</th>
                        <th class="text-center">نرخی فرۆشتن</th>
                        <th>بڕ</th>
                        <th>کۆ</th>
                        <th>سڕینەوە</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, index) in rows" :key="index">
                        <td>{{ item.barcode || '-' }}</td>
                        <td>
                            {{ item.name }}
                            <span class="text-red-400 text-xs">({{ item.currency_name }})</span>
                        </td>
                        <td class="text-center">
                            <input type="number" min="0" step="0.1"
                                   class="w-36 p-2 text-center border border-gray-300 rounded" v-model.number="item.unit_price"/>
                        </td>
                        <td class="text-center">
                            <input type="number" min="0.1" step="0.1" :max="item.stock"
                                   class="w-24 p-2 text-center border border-gray-300 rounded" v-model.number="item.quantity" @input="item.quantity = Math.min(item.stock, Math.max(0, Number(item.quantity) || 0))"/>
                        </td>
                        <td class="font-semibold">
                            {{ item.quantity && item.unit_price ? formatNumber(item.quantity * item.unit_price) : 0 }}
                        </td>
                        <td>
                            <button class="px-3 py-1.5 rounded bg-red-500 text-white hover:bg-red-600 transition-colors cursor-pointer" @click="deleteRow(index)">
                                سڕینەوە
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12 border border-gray-200 rounded-md dark:border-white">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 mx-auto mb-4 text-gray-400 dark:text-white">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
            <h3 class="text-2xl text-gray-600 dark:text-white">کاڵایەک هەڵبژێرە تا بیفرۆشیت</h3>
        </div>
    </div>
</template>

