<script setup>
import TextInput from "@/Pages/Components/TextInput.vue";
import {useForm} from "@inertiajs/vue3";
import Swal from 'sweetalert2'
import SearchableSelect from "@/Pages/Components/SearchableSelect.vue";

const props = defineProps({
    product: {
        type: Object,
        required: true
    },
    currencies: {
        type: Object,
        required: true
    }
})
const form = useForm({
    name: props.product.name,
    stock_price: props.product.stock_price,
    buy_price: props.product.buy_price,
    sell_price: props.product.sell_price,
    stock_quantity: props.product.stock_quantity,
    barcode: props.product.barcode,
    currency_id: props.product.currency_id,
    description: props.product.description
})

const submit = () => {
    form.put(route('products.update', {id: props.product.id}), {
        onSuccess: () => {
            Swal.fire({
                position: "top-end",
                icon: "success",
                title: "بەسەرکەوتویی گۆڕا!",
                showConfirmButton: false,
                timerProgressBar: true,
                timer: 2000
            });
        }
    });
}
</script>

<template>
    <Head title="گۆڕینی کاڵا - "/>
    <div class="container mx-auto mt-4">
        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-md">
            <h1 class="text-sm lg:text-xl">گۆڕینی کاڵا</h1>
            <Link :href="route('products.index')" class="primary-btn">
                <span>گەڕانەوە</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18"/>
                </svg>
            </Link>
        </div>
        <form @submit.prevent="submit" class="border border-gray-200 rounded-md p-4 mt-6">
            <TextInput name="ناو" v-model="form.name" :message="form.errors.name" type="text"/>
            <TextInput name="نرخی کڕین" v-model="form.buy_price" :message="form.errors.buy_price" type="text" :thousand-separator="true"/>
            <TextInput name="نرخی فرۆشتن" v-model="form.sell_price" :message="form.errors.sell_price" type="text" :thousand-separator="true"/>
            <TextInput name="بڕی کاڵا" v-model="form.stock_quantity" :message="form.errors.stock_quantity" type="text" :thousand-separator="true"/>
            <TextInput name="کۆد" v-model="form.barcode" :message="form.errors.barcode" type="text"/>
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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                </svg>
                <span>گۆڕین</span>
            </button>
        </form>
    </div>
</template>

<style scoped>

</style>
