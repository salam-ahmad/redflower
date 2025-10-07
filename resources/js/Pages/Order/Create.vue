<script setup>
import PaginationLinks from "@/Pages/Components/PaginationLinks.vue";
import SearchableSelect from "@/Pages/Components/SearchableSelect.vue";
import TextInput from "@/Pages/Components/TextInput.vue";
import {Head, Link, useForm, router} from "@inertiajs/vue3";
import {ref, computed, watch} from "vue";
import {debounce} from "lodash";
import Swal from "sweetalert2";
import {formatNumber} from "@/utils/numberUtils.js";

const props = defineProps({
    customers: {type: Object, required: true},
    products: {type: Object, required: true}, // paginated list; each item needs currency + default_sell_price
    filters: {type: Object, default: () => ({})},
});

const search = ref(props.filters?.search || "");
watch(search, debounce((value) => {
    router.get(route("orders.create"), {search: value}, {preserveState: true, replace: true});
}, 300));

// ---- FORM STATE ----
const form = useForm({
    customer_id: "",
    note: "",
    status: "cash", // will be overwritten automatically
    order_items: [],
    payments: [],   // [{currency_id, amount}]
});

// cart lines (each product once; quantity editable)
const rows = ref([]);

// helper: show toast
const showMessage = (icon, title) => {
    Swal.fire({position: "top-end", icon, title, showConfirmButton: false, timerProgressBar: true, timer: 2000});
};

// add a product line (quantity=1) honoring product currency
const addRow = (p) => {
    const idx = rows.value.findIndex(r => r.id === p.id);
    if (idx !== -1) {
        // respect stock if you want
        const next = rows.value[idx].quantity + 1;
        if (p.quantity != null && next > p.quantity) {
            showMessage("error", `تەنها ${p.quantity} دانە لەم کاڵایە هەیە`);
            return;
        }
        rows.value[idx].quantity = next;
        return;
    }
    rows.value.push({
        id: p.id,
        code: p.code,
        name: p.name,
        unit_label: p.unit?.name ?? "",
        stock: p.quantity ?? null, // remaining
        quantity: 1,
        unit_price: p.default_sell_price ?? 0, // editable
        currency_id: p.currency_id,
        currency_code: p.currency?.code ?? p.currency?.name ?? "",
    });
};

// delete a line
const deleteRow = (i) => rows.value.splice(i, 1);

// keep form.order_items synced (for debugging or previews)
watch(rows, (newRows) => {
    form.order_items = newRows.map(r => ({
        product_id: r.id,
        quantity: r.quantity,
        unit_price: r.unit_price,
        currency_id: r.currency_id,
    }));
}, {deep: true});

// clamp quantity to stock (if you enforce stock)
watch(rows, (rs) => {
    rs.forEach(r => {
        if (r.stock != null && r.quantity > r.stock) {
            r.quantity = r.stock;
            showMessage("error", `تەنها ${r.stock} دانە لەم کاڵایە هەیە`);
        }
        if (r.quantity < 0) r.quantity = 0; // ✅ now decimals like 0.5 work fine
    });
}, {deep: true});


// ---- TOTALS BY CURRENCY ----
const totalsByCurrency = computed(() => {
    const map = {};
    for (const r of rows.value) {
        const k = r.currency_id;
        const curr = map[k] ?? {code: r.currency_code, total: 0};
        curr.total += Number(r.quantity) * Number(r.unit_price || 0);
        map[k] = curr;
    }
    return map; // { [currency_id]: { code, total } }
});

// ---- PAYMENTS (PER CURRENCY) ----
// UI list the user can add to; amount per currency_id
const payments = ref([]); // [{currency_id:null, amount:null}]
const addPaymentRow = () => payments.value.push({currency_id: null, amount: null});
const removePaymentRow = (i) => payments.value.splice(i, 1);

// aggregate paid per currency (sums duplicate rows of same currency)
const paidByCurrency = computed(() => {
    const map = {};
    for (const p of payments.value) {
        if (!p.currency_id || !p.amount) continue;
        map[p.currency_id] = (map[p.currency_id] ?? 0) + Number(p.amount);
    }
    return map; // { [currency_id]: amount }
});

// guard: don’t allow paying more than total in that currency
watch([payments, totalsByCurrency], () => {
    for (const [curId, paid] of Object.entries(paidByCurrency.value)) {
        const t = totalsByCurrency.value[curId]?.total ?? 0;
        if (paid > t) {
            // clamp the last row of that currency
            const idx = payments.value.findLastIndex(x => String(x.currency_id) === String(curId));
            if (idx !== -1) payments.value[idx].amount = t - (paid - payments.value[idx].amount);
            showMessage("error", "ناتوانیت بڕێک زیاتر لە کۆی وەکو پێوەندییەکدا بدەیت");
        }
    }
}, {deep: true});

// derive status: cash if fully covered per currency; partial if some uncovered but some paid; debt if nothing paid
const statusDerived = computed(() => {
    const curIds = Object.keys(totalsByCurrency.value);
    if (curIds.length === 0) return "cash";
    let anyPaid = false, allCovered = true;
    for (const curId of curIds) {
        const total = totalsByCurrency.value[curId]?.total ?? 0;
        const paid = paidByCurrency.value[curId] ?? 0;
        if (paid > 0) anyPaid = true;
        if (Math.abs(paid - total) > 0.0001) allCovered = false;
    }
    if (allCovered) return "cash";
    if (anyPaid) return "partial";
    return "debt";
});

// ---- SAVE ----
const saveOrder = () => {
    // require customer for loan/partial
    const st = statusDerived.value;
    if ((st === "debt" || st === "partial") && !form.customer_id) {
        showMessage("error", "مادام قەرز یان نیوەقەرزە، ناوی کڕیار هەڵبژێرە.");
        return;
    }
    // build payload
    const order_items = rows.value.map(r => ({
        product_id: r.id,
        quantity: r.quantity,
        unit_price: r.unit_price,
        currency_id: r.currency_id,
    }));
    const paymentsPayload = payments.value
        .filter(p => p.currency_id && Number(p.amount) > 0)
        .map(p => ({currency_id: p.currency_id, amount: Number(p.amount)}));

    const payload = {
        customer_id: form.customer_id || null,
        note: form.note || null,
        status: st, // let backend also recompute if you wish
        order_items: JSON.stringify(order_items),
        payments: JSON.stringify(paymentsPayload),
    };

    router.post(route("orders.store"), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showMessage("success", "بەسەرکەوتویی زیادکرا");
            form.reset();
            rows.value = [];
            payments.value = [];
        },
        onError: () => showMessage("error", "هەڵەیەک ڕوویدا لە کاتی پاشەکەوتکردن"),
    });
};
const currencyIdsInCart = computed(() => Object.keys(totalsByCurrency.value).map(Number))

</script>

<template>
    <Head title="فرۆشتن"/>
    <div class="container mx-auto mt-4">

        <!-- Search + Back -->
        <div class="flex gap-4 items-center justify-between p-4 border border-gray-200 rounded-md mb-4">
            <input type="search" class="flex-1" placeholder="گەڕان بەپێی ناو یان کۆد ..." v-model="search">
            <Link :href="route('orders.index')" class="primary-btn">
                <span>گەڕانەوە</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18"/>
                </svg>
            </Link>
        </div>
        <hr class="dark:text-gray-100">
        <!-- Product grid -->
        <div class="mt-4 p-4 border border-gray-200 rounded-md">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                <div v-for="item in props.products.data" :key="item.id" class="flex flex-col p-4 border border-gray-200 rounded-md text-center">
                    <div class="space-y-2 flex-grow mb-4 dark:text-white">
                        <h5 class="text-xl">
                            نرخ / <span class="text-red-500">{{ formatNumber(item.default_sell_price) }}</span>
                            <span class="text-xs ms-1">{{ item.currency?.code ?? item.currency?.name }}</span>
                        </h5>
                        <p>{{ item.name }}</p>
                        <p v-if="item.code">کۆد / {{ item.code }}</p>
                        <p>بڕی ماوە / <span class="text-red-500">{{ formatNumber(item.quantity) }}</span> <span class="text-xs">{{ item.unit?.name }}</span></p>
                    </div>
                    <button class="primary-btn mt-auto" @click="addRow(item)" :disabled="(item.quantity ?? 0) < 1">
                        <span>زیادکردن</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart-plus" viewBox="0 0 16 16">
                            <path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9z"/>
                            <path
                                d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <PaginationLinks :paginator="products"/>
        </div>

        <hr class="my-5 border border-gray-200">

        <div v-if="rows.length > 0" class="space-y-4 border border-gray-200 p-4">

            <!-- header form + totals by currency -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-2 items-start">
                <div>
                    <label class="block text-sm font-medium">ناوی کڕیار</label>
                    <SearchableSelect
                        v-model="form.customer_id"
                        :options="customers"
                        placeholder="ناوی کڕیار"
                        label="name"
                        value-prop="id"
                        no-options-text="هیچ کڕیارێک نەدۆزرایەوە"
                    />
                </div>

                <TextInput v-model="form.note" name="تێبینی" class="xl:col-span-2"/>
                <!-- Totals by currency -->
                <div class="xl:col-span-2">
                    <label class="block text-sm font-medium">کۆی گشتی بەپێی دراو</label>
                    <div class="rounded-lg border border-indigo-500 focus:border-indigo-600 p-3 space-y-2 dark:border-gray-200">
                        <div class="flex items-center justify-between dark:text-white" v-for="(v, cid) in totalsByCurrency" :key="cid">
                            <span class="text-sm">{{ v.code }}</span>
                            <span class="font-semibold">{{ formatNumber(v.total) }}</span>
                        </div>
                        <div class=" text-xs text-gray-500 dark:text-white">
                            دۆخ: <span class="font-medium">{{ statusDerived === 'cash' ? 'نەقد' : (statusDerived === 'partial' ? 'نیوە قەرز' : 'قەرز') }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <button class="primary-btn md:px-4" @click="saveOrder">فرۆشتن</button>
                </div>
            </div>

            <!-- payments per currency -->
            <div class="rounded-lg border p-3 border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold dark:text-white">پارەدان</h4>
                    <button type="button" class="secondary-btn px-3 py-1 rounded border" @click="addPaymentRow">زیادکردنی پارەدان</button>
                </div>
                <div class="grid gap-2 md:grid-cols-2" v-if="payments.length">
                    <div v-for="(p, i) in payments" :key="i" class="flex items-center gap-2">
                        <select v-model.number="p.currency_id" class="border  rounded px-2 py-1">
                            <option :value="null" disabled>دراو</option>
                            <option
                                v-for="(v, cid) in totalsByCurrency"
                                :key="cid"
                                :value="Number(cid)">
                                {{ v.code }}
                            </option>
                        </select>
                        <input v-model.number="p.amount" type="number" min="0" step="1" class="border rounded px-2 py-1 w-40" placeholder="بڕی پارەی وەرگیراو"/>
                        <button type="button" class="p-2 rounded bg-red-500 text-white cursor-pointer" @click="removePaymentRow(i)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                <path
                                    d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                <path
                                    d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                            </svg>
                        </button>
                        <div class="text-xs text-gray-500 ms-2 dark:text-white" v-if="p.currency_id">
                            {{ formatNumber((totalsByCurrency[p.currency_id]?.total ?? 0) - (paidByCurrency[p.currency_id] ?? 0)) }} ماوە
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-500 dark:text-white">هیچ پارەدانێک نەکراوە (ئەگەر پارەدان نییە کڕینەکە دەکاتە قەرز).</div>
            </div>

            <!-- cart table -->
            <div class="overflow-x-auto">
                <table>
                    <thead>
                    <tr>
                        <th>کۆد</th>
                        <th>ناوی کاڵا</th>
                        <th>نرخ</th>
                        <th>بڕی کاڵا</th>
                        <th>کۆ</th>
                        <th>سڕینەوە</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, index) in rows" :key="index">
                        <td>{{ item.code }}</td>
                        <td>{{ item.name }} <span class="text-red-400 text-xs">({{ item.currency_code }})</span></td>
                        <td><input type="number" min="1" step="1" class="text-center" v-model.number="item.unit_price"/></td>
                        <td>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                :max="item.stock ?? undefined"
                                class="text-center"
                                v-model.number="item.quantity"
                            />
                        </td>
                        <td>{{ item.quantity && item.unit_price ? formatNumber(item.quantity * item.unit_price) : 0 }}</td>
                        <td>
                            <button class="text-white rounded bg-red-500 hover:bg-red-600 px-3 py-1.5 mx-auto cursor-pointer" @click="deleteRow(index)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                    <path
                                        d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                    <path
                                        d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div v-else>
            <h3 class="text-center text-2xl dark:text-white">کاڵایەک هەڵبژێرە تا بیفرۆشیت.</h3>
        </div>
    </div>
</template>
