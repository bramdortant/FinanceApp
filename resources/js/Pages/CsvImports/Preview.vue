<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    originalFilename: { type: String, required: true },
    sections: { type: Array, required: true },
});

const activeTab = ref(0);

const form = useForm({
    token: props.token,
    original_filename: props.originalFilename,
});

const submit = () => {
    form.post(route('csv-imports.store'));
};

const totals = computed(() => {
    const t = { new: 0, transfer: 0, mirror: 0, duplicate: 0 };
    for (const s of props.sections) {
        t.new += s.summary.new;
        t.transfer += s.summary.transfer;
        t.mirror += s.summary.mirror || 0;
        t.duplicate += s.summary.duplicate;
    }
    return t;
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);

const formatDate = (date) => {
    if (!date) return '';
    const parts = date.split('-');
    if (parts.length !== 3) return date;
    const [y, m, d] = parts.map(Number);
    const parsed = new Date(y, m - 1, d);
    if (isNaN(parsed.getTime())) return date;
    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
};

const statusBadge = (status) => {
    if (status === 'duplicate') return { label: 'Duplicaat', class: 'bg-gray-200 text-gray-700' };
    if (status === 'transfer') return { label: 'Overboeking', class: 'bg-blue-100 text-blue-700' };
    if (status === 'transfer_mirror') return { label: 'Spiegel (skip)', class: 'bg-gray-200 text-gray-500' };
    return { label: 'Nieuw', class: 'bg-green-100 text-green-700' };
};
</script>

<template>
    <Head title="CSV preview" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                CSV preview
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 grid grid-cols-4 gap-4">
                    <div class="rounded-md bg-green-50 p-4 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ totals.new }}</div>
                        <div class="text-xs text-green-700">Nieuw</div>
                    </div>
                    <div class="rounded-md bg-blue-50 p-4 text-center">
                        <div class="text-2xl font-bold text-blue-700">{{ totals.transfer }}</div>
                        <div class="text-xs text-blue-700">Overboekingen</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ totals.mirror }}</div>
                        <div class="text-xs text-gray-700">Spiegelregels (skip)</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ totals.duplicate }}</div>
                        <div class="text-xs text-gray-700">Duplicaten (skip)</div>
                    </div>
                </div>

                <div class="mb-4 flex gap-2 border-b border-gray-200">
                    <button
                        v-for="(section, idx) in sections"
                        :key="section.account.id"
                        type="button"
                        class="border-b-2 px-4 py-2 text-sm font-medium transition"
                        :class="activeTab === idx
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeTab = idx"
                    >
                        {{ section.account.name }}
                        <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                            {{ section.summary.new }}
                        </span>
                    </button>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
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
                            <tr v-if="!sections.length || !sections[activeTab]">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Geen transacties om te tonen.
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="(row, idx) in sections[activeTab].rows"
                                :key="idx"
                                :class="(row.status === 'duplicate' || row.status === 'transfer_mirror') ? 'opacity-50' : ''"
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
                                    <div class="truncate max-w-xs" :title="row.description">{{ row.description || '—' }}</div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    <div v-if="row.transfer_to_account_name" class="whitespace-nowrap text-blue-700">
                                        ⇄ {{ row.transfer_to_account_name }}
                                    </div>
                                    <div v-else class="truncate max-w-[12rem]" :title="row.counterparty_name || ''">
                                        {{ row.counterparty_name || '—' }}
                                    </div>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2 pr-6 text-right text-sm font-semibold"
                                    :class="parseFloat(row.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                                >
                                    {{ formatCurrency(row.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">
                    <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="route('csv-imports.create')">
                        <SecondaryButton type="button">Annuleren</SecondaryButton>
                    </Link>
                    <form @submit.prevent="submit">
                        <PrimaryButton :disabled="form.processing || totals.new === 0">
                            Bevestig import ({{ totals.new }} nieuw)
                        </PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
