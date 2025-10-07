<script setup>
import TextInput from "@/Pages/Components/TextInput.vue";
import {useForm} from "@inertiajs/vue3";
import Swal from 'sweetalert2'
import SearchableSelect from "@/Pages/Components/SearchableSelect.vue";

const props = defineProps({
    currencies: {
        type: Object,
        required: true
    }
})
const form = useForm({
    name: null,
    barcode: null,
    currency_id: null,
    stock_quantity: null,
    buy_price: null,
    sell_price: null,
    description: null
})
const submit = () => {
    form.post(route('products.store'), {
        onSuccess: () => {
            Swal.fire({
                position: "top-end",
                icon: "success",
                title: "بەسەرکەوتویی زیادکرا!",
                showConfirmButton: false,
                timerProgressBar: true,
                timer: 2000
            });
        }
    });
}
</script>

<template>
    <Head title="زیادکردنی کاڵا  "/>
    <div class="container mx-auto mt-4">
        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-md">
            <h1 class="text-sm lg:text-xl">زیادکردنی کاڵا</h1>
            <Link :href="route('products.index')" class="primary-btn">
                 <span>
                    گەڕانەوە
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18"/>
                </svg>

            </Link>
        </div>
        <form @submit.prevent="submit" class="border border-gray-200 rounded-md p-4 mt-2">
            <TextInput name="ناوی کاڵا" v-model="form.name" :message="form.errors.name" type="text"/>
            <TextInput name="کۆد (اختیاری)" v-model="form.barcode" type="text"/>
            <TextInput name="نرخی کڕین" v-model="form.buy_price" :message="form.errors.buy_price" type="text" :thousand-separator="true"/>
            <TextInput name="نرخی فرۆشتن" v-model="form.sell_price" :message="form.errors.sell_price" type="text" :thousand-separator="true"/>
            <TextInput name="بڕی کاڵا" v-model="form.stock_quantity" :message="form.errors.stock_quantity" type="text" :thousand-separator="true"/>
            <div class="mb-4">
                <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-2">جۆری دراو</label>
                <SearchableSelect
                    v-model="form.currency_id"
                    :options="currencies"
                    placeholder="جۆری دراو"
                    label="name"
                    value-prop="id"
                    :error="form.errors.currency_id"
                    no-options-text="هیچ نەدۆزرایەوە"
                />
            </div>
            <div class="mb-4">
                <label for="note">تێبینی :</label>
                <textarea id="note" cols="30" rows="10" v-model="form.description"></textarea>
            </div>
            <button type="submit" class="primary-btn" :disabled="form.processing">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="size-6" viewBox="0 0 16 16">
                    <path d="M11 2H9v3h2z"/>
                    <path
                        d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zM3 15h10v-4.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5z"/>
                </svg>
                <span>زیادکردن</span>
            </button>
        </form>
    </div>
</template>
