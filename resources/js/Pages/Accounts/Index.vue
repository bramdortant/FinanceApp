<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    accounts: {
        type: Array,
        required: true,
    },
});

const totalBalance = computed(() =>
    props.accounts.reduce((sum, a) => sum + parseFloat(a.current_balance || 0), 0)
);

const flash = computed(() => usePage().props.flash);

const confirmingDeletion = ref(false);
const accountToDelete = ref(null);

const confirmDelete = (account) => {
    accountToDelete.value = account;
    confirmingDeletion.value = true;
};

const deleteAccount = () => {
    router.delete(route('accounts.destroy', accountToDelete.value.id), {
        onSuccess: () => {
            confirmingDeletion.value = false;
            accountToDelete.value = null;
        },
        onError: () => {
            confirmingDeletion.value = false;
            accountToDelete.value = null;
        },
    });
};

const closeModal = () => {
    confirmingDeletion.value = false;
    accountToDelete.value = null;
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount);
};

const accountTypeLabels = {
    checking: 'Betaalrekening',
    savings: 'Spaarrekening',
    cash: 'Contant',
};
</script>

<template>
    <Head title="Rekeningen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Rekeningen
                </h2>
                <Link
                    :href="route('accounts.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                >
                    Toevoegen
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="flash?.success"
                    class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700"
                    role="alert"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash?.error"
                    class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700"
                    role="alert"
                >
                    {{ flash.error }}
                </div>

                <div v-if="accounts.length === 0" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        Nog geen rekeningen.
                        <Link :href="route('accounts.create')" class="text-indigo-600 hover:text-indigo-500">
                            Maak je eerste rekening aan
                        </Link>
                    </div>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        :href="route('accounts.all')"
                        class="overflow-hidden rounded-lg bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
                    >
                        <div class="p-6">
                            <h3 class="text-lg font-semibold">Alle rekeningen</h3>
                            <p class="mt-1 text-xs text-indigo-100">Overzicht van alle transacties</p>
                            <p class="mt-4 text-2xl font-bold">{{ formatCurrency(totalBalance) }}</p>
                        </div>
                    </Link>

                    <div
                        v-for="account in accounts"
                        :key="account.id"
                        class="overflow-hidden rounded-lg bg-white shadow-sm"
                    >
                        <div class="p-6">
                            <Link :href="route('accounts.show', account.id)" class="block">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            {{ account.name }}
                                        </h3>
                                        <span class="mt-1 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                            {{ accountTypeLabels[account.type] || account.type }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold" :class="parseFloat(account.current_balance) >= 0 ? 'text-green-600' : 'text-red-600'">
                                            {{ formatCurrency(account.current_balance) }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            Beginsaldo: {{ formatCurrency(account.starting_balance) }}
                                        </p>
                                    </div>
                                </div>
                            </Link>
                            <div class="mt-4 flex justify-end gap-2">
                                <Link
                                    :href="route('accounts.edit', account.id)"
                                    class="text-sm text-indigo-600 hover:text-indigo-500"
                                >
                                    Bewerken
                                </Link>
                                <button
                                    class="text-sm text-red-600 hover:text-red-500"
                                    @click="confirmDelete(account)"
                                >
                                    Verwijderen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Rekening verwijderen
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Weet je zeker dat je "{{ accountToDelete?.name }}" wilt verwijderen?
                    Deze rekening kan alleen verwijderd worden als er geen transacties aan gekoppeld zijn.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Annuleren</SecondaryButton>
                    <DangerButton @click="deleteAccount">Verwijderen</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
