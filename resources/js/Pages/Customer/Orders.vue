<script setup>
import {Link, router} from '@inertiajs/vue3'

const props = defineProps({
    customer: {type: Object, required: true},
    orders: {type: Object, required: true}, // paginated
    filters: {type: Object, default: () => ({})}
})

const submit = (e) => {
    e.preventDefault()
    const f = new FormData(e.target)
    router.get(route('customers.orders', props.customer.id), {
        from: f.get('from') || null,
        to: f.get('to') || null,
        status: f.get('status') || null, // cash|partial|debt
        search_note: f.get('search_note') || null,
    }, {preserveScroll: true})
}

const fmt = (n, digits = 4) =>
    Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: digits})

// ---- Helpers to render per-currency rows ----

// If backend sent o.totals_by_currency (array like [{code,total,paid,due}]),
// use it. Otherwise compute from o.items + o.payments.
const summarizeOrder = (o) => {
    if (o?.totals_by_currency?.length) return o.totals_by_currency

    const totals = {} // { cid: { code, total } }
    for (const it of o.items ?? []) {
        const cid = it.currency_id
        if (cid == null) continue
        const code = it.currency_code ?? it.currency?.code ?? it.currency?.name ?? ''
        totals[cid] = totals[cid] || {code, total: 0}
        totals[cid].total += Number(it.quantity) * Number(it.unit_price)
    }

    const paid = {} // { cid: { code, paid } }
    for (const p of o.payments ?? []) {
        const cid = p.currency_id
        if (cid == null) continue
        const code = p.currency_code ?? p.currency?.code ?? p.currency?.name ?? ''
        paid[cid] = paid[cid] || {code, paid: 0}
        paid[cid].paid += Number(p.amount)
    }

    const ids = new Set([...Object.keys(totals), ...Object.keys(paid)])
    const out = []
    ids.forEach(cid => {
        const code = totals[cid]?.code ?? paid[cid]?.code ?? ''
        const total = totals[cid]?.total ?? 0
        const got = paid[cid]?.paid ?? 0
        out.push({code, total, paid: got, due: total - got})
    })
    return out
}
</script>

<template>
    <div class="p-4 container mx-auto space-y-4">
        <h1 class="text-xl font-bold">فرۆشتن — {{ customer.name }}</h1>

        <form @submit="submit"
              class="grid grid-cols-2 md:grid-cols-6 gap-2 items-end bg-white p-3 border rounded dark:bg-gray-800 dark:border-white">
            <div>
                <label class="dark:text-white block text-base">لە ڕێکەوتی</label>
                <input type="date" name="from" :value="filters.from" class="border rounded p-2 w-full">
            </div>
            <div>
                <label class="dark:text-white block text-base">بۆ ڕێکەوتی</label>
                <input type="date" name="to" :value="filters.to" class="border rounded p-2 w-full">
            </div>
            <div>
                <label class="dark:text-white block text-base">دۆخی پارەدان</label>
                <select name="status" :value="filters.status || ''" class="border rounded p-2 w-full">
                    <option value="">هەموی</option>
                    <option value="cash">نەقد</option>
                    <option value="partial">نیوە قەرز</option>
                    <option value="debt">قەرز</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="dark:text-white block text-base">گەڕان لە تێبینی</label>
                <input type="text" name="search_note" :value="filters.search_note" class="border rounded p-2 w-full">
            </div>
            <div class="md:col-span-1">
                <button class="primary-btn flex items-center gap-2">
                    <span>گەڕان</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                </button>
            </div>
        </form>

        <div class="overflow-auto bg-white border rounded dark:bg-transparent dark:border-gray-200">
            <table class="min-w-full">
                <thead>
                <tr>
                    <th>#</th>
                    <th>ڕێکەوت</th>
                    <th>دۆخ</th>
                    <th class="text-center">کۆ / پارەی دراو / ماوە</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="o in orders.data" :key="o.id">
                    <td>#{{ o.id }}</td>
                    <td>{{ o.date }}</td>
                    <td>
                        <span :class="{
                          'text-green-600': o.payment_method === 'cash',
                          'text-yellow-600': o.payment_method === 'partial',
                          'text-red-600': o.payment_method === 'debt'
                        }">
                          {{ o.payment_method === 'cash' ? 'نەقد' : o.payment_method === 'partial' ? 'نیوە قەرز' : 'قەرز' }}
                        </span>
                    </td>
                    <td>
                        <!-- Per-currency badges -->
                        <div class="flex flex-wrap gap-2 justify-center">
                              <span v-for="row in summarizeOrder(o)" :key="row.code"
                                    class="inline-flex items-center gap-2 rounded px-2 py-0.5 border text-sm">
                                <span class="font-semibold">{{ row.code }}</span>
                                <span>{{ fmt(row.total) }}</span> /
                                <span>{{ fmt(row.paid) }}</span> /
                                <span :class="row.due > 0 ? 'text-red-600 font-semibold' : 'text-green-600'">
                                  {{ fmt(row.due) }}
                                </span>
                              </span>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="flex items-center justify-center gap-2">
                            <Link v-if="o.receive_url" :href="o.receive_url" class="primary-btn">وەرگرتنی پارە</Link>
                            <Link v-if="o.show_url" :href="o.show_url" class="px-4 py-2 rounded border">بینین</Link>
                        </div>
                    </td>
                </tr>

                <tr v-if="orders.data.length === 0">
                    <td colspan="5" class="p-3 text-center text-gray-500">هیچ نەدۆزرایەوە</td>
                </tr>
                </tbody>
            </table>
        </div>
        <!-- your pagination component can go here -->
    </div>
</template>
