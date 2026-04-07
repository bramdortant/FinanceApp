<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    originalFilename: { type: String, required: true },
    account: { type: Object, required: true },
    rows: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const form = useForm({
    token: props.token,
    original_filename: props.originalFilename,
});

const submit = () => {
    form.post(route('csv-imports.store'));
};

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);

const formatDate = (date) => {
    if (!date) return '';
    const [y, m, d] = date.split('-').map(Number);
    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(y, m - 1, d));
};

const statusBadge = (status) => {
    if (status === 'duplicate') return { label: 'Duplicaat', class: 'bg-gray-200 text-gray-700' };
    if (status === 'transfer') return { label: 'Overboeking', class: 'bg-blue-100 text-blue-700' };
    return { label: 'Nieuw', class: 'bg-green-100 text-green-700' };
};
</script>

<template>
    <Head title="CSV preview" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                CSV preview — {{ account.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
                <div class="mb-4 grid grid-cols-3 gap-4">
                    <div class="rounded-md bg-green-50 p-4 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ summary.new }}</div>
                        <div class="text-xs text-green-700">Nieuw</div>
                    </div>
                    <div class="rounded-md bg-blue-50 p-4 text-center">
                        <div class="text-2xl font-bold text-blue-700">{{ summary.transfer }}</div>
                        <div class="text-xs text-blue-700">Overboekingen herkend</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ summary.duplicate }}</div>
                        <div class="text-xs text-gray-700">Duplicaten (overgeslagen)</div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Datum</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Omschrijving</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tegenpartij</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Bedrag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr
                                v-for="(row, idx) in rows"
                                :key="idx"
                                :class="row.status === 'duplicate' ? 'opacity-50' : ''"
                            >
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="statusBadge(row.status).class"
                                    >
                                        {{ statusBadge(row.status).label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-700">
                                    {{ formatDate(row.date) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    <div class="truncate max-w-md">{{ row.description || '—' }}</div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    <div v-if="row.transfer_to_account_name" class="text-blue-700">
                                        ⇄ {{ row.transfer_to_account_name }}
                                    </div>
                                    <div v-else class="truncate max-w-xs">
                                        {{ row.counterparty_name || '—' }}
                                    </div>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2 text-right text-sm font-semibold"
                                    :class="parseFloat(row.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ formatCurrency(row.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="route('csv-imports.create')">
                        <SecondaryButton type="button">Annuleren</SecondaryButton>
                    </Link>
                    <form @submit.prevent="submit">
                        <PrimaryButton :disabled="form.processing || summary.new === 0">
                            Bevestig import ({{ summary.new }} nieuw)
                        </PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
