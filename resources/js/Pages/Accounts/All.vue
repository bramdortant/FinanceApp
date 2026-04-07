<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TransactionList from '@/Components/TransactionList.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    totalBalance: { type: [String, Number], required: true },
    transactions: { type: Array, required: true },
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);
</script>

<template>
    <Head title="Alle rekeningen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('accounts.index')" class="text-xs text-gray-500 hover:text-gray-700">
                        ← Rekeningen
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Alle rekeningen
                    </h2>
                </div>
                <p class="text-2xl font-bold" :class="parseFloat(totalBalance) >= 0 ? 'text-green-600' : 'text-red-600'">
                    {{ formatCurrency(totalBalance) }}
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <TransactionList :transactions="transactions" :show-account="true" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
