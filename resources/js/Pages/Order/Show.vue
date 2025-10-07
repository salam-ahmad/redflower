<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    order: { type: Object, required: true }
})

const fmt = (n, digits = 4) => {
    // show money with up to 4 decimals; trim trailing zeros
    const num = Number(n ?? 0)
    return num.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: digits,
    })
}

// ---- Totals by currency (from items) ----
const totalsByCurrency = computed(() => {
    const map = {} // { [currency_id]: { code, total } }
    for (const it of props.order.items ?? []) {
        const cid = it.currency_id
        if (cid == null) continue
        const code = it.currency_code ?? it.currency?.code ?? it.currency?.name ?? ''
        map[cid] = map[cid] || { code, total: 0 }
        map[cid].total += Number(it.quantity) * Number(it.unit_price)
    }
    return map
})

// ---- Payments by currency ----
const paidByCurrency = computed(() => {
    const map = {} // { [currency_id]: { code, paid } }
    for (const p of props.order.payments ?? []) {
        const cid = p.currency_id
        if (cid == null) continue
        const code = p.currency_code ?? p.currency?.code ?? p.currency?.name ?? ''
        map[cid] = map[cid] || { code, paid: 0 }
        map[cid].paid += Number(p.amount)
    }
    return map
})

// ---- Balance by currency = total - paid ----
const balances = computed(() => {
    const out = {}
    const ids = new Set([
        ...Object.keys(totalsByCurrency.value),
        ...Object.keys(paidByCurrency.value),
    ])
    ids.forEach(cid => {
        const total = totalsByCurrency.value[cid]?.total ?? 0
        const paid  = paidByCurrency.value[cid]?.paid ?? 0
        const code  = totalsByCurrency.value[cid]?.code
            ?? paidByCurrency.value[cid]?.code
            ?? ''
        out[cid] = { code, total, paid, due: total - paid }
    })
    return out
})
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold">پسوڵە ژمارە # {{ order.id }}</h1>
            <div class="flex items-center gap-2">
                <Link :href="order.receive_url" class="primary-btn px-4">وەرگرتنی پارە</Link>
                <Link :href="order.customer?.orders_url" class="px-3 py-2 rounded border">لیستی پسوڵەکان</Link>
            </div>
        </div>
        <!-- Summary blocks -->
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 dark:text-white border rounded p-4">
                <div class="grid grid-cols-2 gap-2 text-base">
                    <div><span>ڕێکەوت:</span> {{ order.date }}</div>
                    <div><span>دۆخ : </span>
                        <span class="font-semibold"
                              :class="{
                    'text-green-600': order.payment_method === 'cash',
                    'text-yellow-600': order.payment_method === 'partial',
                    'text-red-600': order.payment_method === 'debt'
                  }">
              {{ order.payment_method === 'cash' ? 'نەقد' : order.payment_method === 'partial' ? 'نیوە قەرز' : 'قەرز' }}
            </span>
                    </div>
                    <div class="col-span-2"><span>تێبینی:</span> {{ order.note || '—' }}</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 dark:text-white border rounded p-4">
                <div class="grid grid-cols-2 gap-2 text-base">
                    <div><span>کڕیار:</span> {{ order.customer?.name || '—' }}</div>
                    <div><span>مۆبایل:</span> {{ order.customer?.phone || '—' }}</div>
                    <div class="col-span-2"><span>ناونیشان:</span> {{ order.customer?.address || '—' }}</div>
                </div>
            </div>
        </div>

        <!-- Totals (multi-currency) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-gray-800 dark:text-white">
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-base">کۆی گشتی بەپێی دراو</p>
                <ul class="mt-2 space-y-1">
                    <li v-for="(v, cid) in totalsByCurrency" :key="'t-'+cid" class="flex justify-between">
                        <span>{{ v.code }}</span><span class="font-extrabold">{{ fmt(v.total) }}</span>
                    </li>
                    <li v-if="Object.keys(totalsByCurrency).length===0" class="text-sm text-gray-500">—</li>
                </ul>
            </div>
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-base">پارەی دراو</p>
                <ul class="mt-2 space-y-1">
                    <li v-for="(v, cid) in paidByCurrency" :key="'p-'+cid" class="flex justify-between">
                        <span>{{ v.code }}</span><span class="font-extrabold">{{ fmt(v.paid) }}</span>
                    </li>
                    <li v-if="Object.keys(paidByCurrency).length===0" class="text-sm text-gray-500">—</li>
                </ul>
            </div>
            <div class="bg-white border rounded p-4 dark:bg-gray-800">
                <p class="text-base">ماوە (قەرز)</p>
                <ul class="mt-2 space-y-1">
                    <li v-for="(v, cid) in balances" :key="'d-'+cid" class="flex justify-between"
                        :class="v.due > 0 ? 'text-red-600' : 'text-green-600'">
                        <span>{{ v.code }}</span><span class="font-extrabold">{{ fmt(v.due) }}</span>
                    </li>
                    <li v-if="Object.keys(balances).length===0" class="text-sm text-gray-500">—</li>
                </ul>
            </div>
        </div>

        <!-- Items -->
        <div class="overflow-auto bg-white border rounded dark:bg-transparent dark:border-gray-200">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>ناوی کاڵا</th>
                    <th>دراو</th>
                    <th>بڕ</th>
                    <th>نرخی تاک</th>
                    <th>کۆی هێڵ</th>
                    <th>تێبینی</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(it, idx) in order.items" :key="it.id">
                    <td>{{ idx + 1 }}</td>
                    <td>{{ it.name || it.product?.name || '—' }}</td>
                    <td class="text-xs">{{ it.currency_code || it.currency?.code || it.currency?.name }}</td>
                    <td>{{ fmt(it.quantity, 3) }}</td>
                    <td>{{ fmt(it.unit_price) }}</td>
                    <td class="font-semibold">
                        {{ fmt(Number(it.quantity) * Number(it.unit_price)) }}
                    </td>
                    <td>{{ it.note || '' }}</td>
                </tr>
                <tr v-if="!order.items || order.items.length === 0">
                    <td colspan="7" class="p-3 text-center text-gray-800">هیچ نەدۆزرایەوە</td>
                </tr>
                </tbody>
                <tfoot v-if="order.items && order.items.length">
                <tr class="bg-gray-50 text-black">
                    <td colspan="5" class="px-2 text-right font-semibold">کۆی گشتی بەپێی دراو</td>
                    <td colspan="2" class="p-2">
                        <div class="flex flex-wrap gap-3">
                <span v-for="(v, cid) in totalsByCurrency" :key="'ft-'+cid" class="inline-flex items-center gap-1 font-extrabold">
                  <span>{{ v.code }}</span>
                  <span>{{ fmt(v.total) }}</span>
                </span>
                        </div>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payments history (multi-currency) -->
        <div v-if="order.payments && order.payments.length"
             class="bg-white border rounded p-4 dark:text-white dark:bg-gray-800">
            <h2 class="font-semibold mb-4">پارەدانەکان</h2>
            <ul class="space-y-1 text-base">
                <li v-for="p in order.payments" :key="p.id" class="flex justify-between">
          <span>
            {{ p.date || p.paid_at }} —
            <span class="text-xs text-gray-500 dark:text-white">{{ p.currency_code || p.currency?.code || p.currency?.name }}</span>
            <span class="ms-2">{{ p.note || '' }}</span>
          </span>
                    <span class="font-semibold">{{ fmt(p.amount) }}</span>
                </li>
            </ul>
        </div>

    </div>
</template>
