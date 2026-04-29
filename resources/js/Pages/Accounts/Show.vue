<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SplitTransactionModal from '@/Components/SplitTransactionModal.vue';
import TextInput from '@/Components/TextInput.vue';
import TransactionList from '@/Components/TransactionList.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    account: { type: Object, required: true },
    transactions: { type: Array, required: true },
    accounts: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const flash = computed(() => usePage().props.flash);

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);

// Use local date parts so the default doesn't shift across timezones
// (toISOString() returns UTC, which yields the wrong calendar day near midnight).
const today = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

// ----- Quick-add / edit modal -----
const quickModalOpen = ref(false);
const editingTransaction = ref(null);
const quickAmountInput = ref(null);
const form = useForm({
    type: 'expense',
    amount: '',
    date: today(),
    description: '',
    category_id: null,
    transfer_to_account_id: null,
    notes: '',
});

const filteredCategories = computed(() =>
    props.categories.filter((c) => c.type === form.type)
);

const openQuick = (type, prefill = null) => {
    editingTransaction.value = prefill?.id ? prefill : null;
    form.reset();
    form.clearErrors();
    form.type = type;
    if (prefill) {
        form.amount = Math.abs(parseFloat(prefill.amount)).toFixed(2);
        form.description = prefill.description || '';
        form.category_id = prefill.category_id || null;
        form.date = prefill.id ? (prefill.date || '').split('T')[0] : today();
        form.notes = prefill.notes || '';
    }
    quickModalOpen.value = true;
    nextTick(() => quickAmountInput.value?.focus());
};

const submitQuick = () => {
    if (editingTransaction.value) {
        form.put(route('transactions.update', editingTransaction.value.id), {
            onSuccess: () => closeQuick(),
        });
    } else {
        form.post(route('transactions.store', props.account.id), {
            onSuccess: () => closeQuick(),
        });
    }
};

const deleteEditing = () => {
    if (!editingTransaction.value) return;
    router.delete(route('transactions.destroy', editingTransaction.value.id), {
        onSuccess: () => closeQuick(),
    });
};

const closeQuick = () => {
    quickModalOpen.value = false;
    editingTransaction.value = null;
    form.reset();
};

// ----- Split modal -----
const splitModalOpen = ref(false);
const splittingTransaction = ref(null);

const openSplit = (tx) => {
    if (tx.type === 'transfer') return; // backend rejects too, but hide it here
    // Close the edit modal if it's open — splitting is a separate flow.
    if (quickModalOpen.value) closeQuick();
    splittingTransaction.value = tx;
    splitModalOpen.value = true;
};

const closeSplit = () => {
    splitModalOpen.value = false;
    splittingTransaction.value = null;
};

const handleEditTransaction = (tx) => {
    if (tx.type === 'transfer') {
        // Inbound transfers belong to a different source account; editing them
        // here from the destination's perspective would render the wrong "Van"
        // and exclude the saved destination from the dropdown. Send the user
        // to the source account's page instead.
        if (tx.transfer_to_account_id === props.account.id) {
            router.visit(route('accounts.show', tx.account_id));
            return;
        }
        openTransfer(tx);
    } else {
        openQuick(tx.type, tx);
    }
};

// ----- Transfer modal -----
const transferModalOpen = ref(false);
const transferAmountInput = ref(null);
const editingTransfer = ref(null);
const transferForm = useForm({
    type: 'transfer',
    amount: '',
    date: today(),
    description: '',
    transfer_to_account_id: null,
    notes: '',
});

const otherAccounts = computed(() => props.accounts.filter((a) => a.id !== props.account.id));

const openTransfer = (prefill = null) => {
    editingTransfer.value = prefill?.id ? prefill : null;
    transferForm.reset();
    transferForm.clearErrors();
    transferForm.type = 'transfer';
    if (prefill) {
        transferForm.amount = Math.abs(parseFloat(prefill.amount)).toFixed(2);
        transferForm.description = prefill.description || '';
        transferForm.date = (prefill.date || '').split('T')[0] || today();
        transferForm.transfer_to_account_id = prefill.transfer_to_account_id;
        transferForm.notes = prefill.notes || '';
    } else if (otherAccounts.value.length > 0) {
        transferForm.transfer_to_account_id = otherAccounts.value[0].id;
    }
    transferModalOpen.value = true;
    nextTick(() => transferAmountInput.value?.focus());
};

const submitTransfer = () => {
    if (editingTransfer.value) {
        transferForm.put(route('transactions.update', editingTransfer.value.id), {
            onSuccess: () => closeTransfer(),
        });
    } else {
        transferForm.post(route('transactions.store', props.account.id), {
            onSuccess: () => closeTransfer(),
        });
    }
};

const deleteEditingTransfer = () => {
    if (!editingTransfer.value) return;
    router.delete(route('transactions.destroy', editingTransfer.value.id), {
        onSuccess: () => closeTransfer(),
    });
};

const closeTransfer = () => {
    transferModalOpen.value = false;
    editingTransfer.value = null;
    transferForm.reset();
};

// ----- Clone modal -----
const cloneModalOpen = ref(false);
const cloneSearch = ref('');
const cloneSearchInput = ref(null);

const cloneCandidates = computed(() => {
    const q = cloneSearch.value.trim().toLowerCase();
    const list = props.transactions.filter((t) => t.type !== 'transfer');
    if (!q) return list;
    return list.filter(
        (t) =>
            (t.description || '').toLowerCase().includes(q) ||
            (t.category?.name || '').toLowerCase().includes(q)
    );
});

const openClone = () => {
    cloneSearch.value = '';
    cloneModalOpen.value = true;
    nextTick(() => cloneSearchInput.value?.focus());
};

const pickClone = (tx) => {
    cloneModalOpen.value = false;
    openQuick(tx.type, { ...tx, id: null });
};

watch(quickModalOpen, (open) => {
    if (!open) form.clearErrors();
});
</script>

<template>
    <Head :title="account.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('accounts.index')" class="text-xs text-gray-500 hover:text-gray-700">
                        ← Rekeningen
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ account.name }}
                    </h2>
                </div>
                <div class="text-right">
                    <p
                        class="text-2xl font-bold"
                        :class="parseFloat(account.current_balance) >= 0 ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ formatCurrency(account.current_balance) }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div v-if="flash?.success" class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700" role="alert">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700" role="alert">
                    {{ flash.error }}
                </div>

                <!-- Action row -->
                <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button
                        type="button"
                        class="rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        @click="openQuick('income')"
                    >
                        + Inkomsten
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        @click="openQuick('expense')"
                    >
                        − Uitgaven
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                        :disabled="otherAccounts.length === 0"
                        @click="openTransfer()"
                    >
                        ⇄ Overboeken
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-gray-700 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50"
                        :disabled="transactions.length === 0"
                        @click="openClone()"
                    >
                        📋 Klonen
                    </button>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <TransactionList
                        :transactions="transactions"
                        :current-account-id="account.id"
                        @edit="handleEditTransaction"
                        @split="openSplit"
                    />
                </div>
            </div>
        </div>

        <!-- Quick add/edit modal -->
        <Modal :show="quickModalOpen" @close="closeQuick">
            <form class="p-6" @submit.prevent="submitQuick">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingTransaction ? 'Transactie bewerken' : (form.type === 'income' ? 'Inkomsten toevoegen' : 'Uitgaven toevoegen') }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="amount" value="Bedrag" />
                    <TextInput
                        id="amount"
                        ref="quickAmountInput"
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.amount" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="description" value="Omschrijving" />
                    <TextInput
                        id="description"
                        v-model="form.description"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.description" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="category_id" value="Categorie" />
                    <select
                        id="category_id"
                        v-model="form.category_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option :value="null" disabled>Kies een categorie…</option>
                        <option v-for="c in filteredCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError :message="form.errors.category_id" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="date" value="Datum" />
                    <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.date" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-between gap-3">
                    <DangerButton v-if="editingTransaction" type="button" @click="deleteEditing">Verwijderen</DangerButton>
                    <span v-else></span>
                    <div class="flex gap-3">
                        <SecondaryButton
                            v-if="editingTransaction"
                            type="button"
                            @click="openSplit(editingTransaction)"
                        >
                            Splitsen…
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="closeQuick">Annuleren</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">Opslaan</PrimaryButton>
                    </div>
                </div>
            </form>
        </Modal>

        <!-- Transfer modal -->
        <Modal :show="transferModalOpen" @close="closeTransfer">
            <form class="p-6" @submit.prevent="submitTransfer">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingTransfer ? 'Overboeking bewerken' : 'Overboeken' }}
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
                    <div>
                        <InputLabel value="Van" />
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ account.name }}
                        </div>
                    </div>
                    <div class="hidden sm:block sm:pb-2 sm:text-center">⇄</div>
                    <div>
                        <InputLabel for="transfer_to" value="Naar" />
                        <select
                            id="transfer_to"
                            v-model="transferForm.transfer_to_account_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option v-for="a in otherAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                </div>
                <InputError :message="transferForm.errors.transfer_to_account_id" class="mt-2" />

                <div class="mt-4">
                    <InputLabel for="t_amount" value="Bedrag" />
                    <TextInput
                        id="t_amount"
                        ref="transferAmountInput"
                        v-model="transferForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="transferForm.errors.amount" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="t_date" value="Datum" />
                    <TextInput id="t_date" v-model="transferForm.date" type="date" class="mt-1 block w-full" required />
                    <InputError :message="transferForm.errors.date" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="t_notes" value="Notities" />
                    <TextInput id="t_notes" v-model="transferForm.notes" type="text" class="mt-1 block w-full" />
                </div>

                <div class="mt-6 flex justify-between gap-3">
                    <DangerButton v-if="editingTransfer" type="button" @click="deleteEditingTransfer">Verwijderen</DangerButton>
                    <span v-else></span>
                    <div class="flex gap-3">
                        <SecondaryButton type="button" @click="closeTransfer">Annuleren</SecondaryButton>
                        <PrimaryButton :disabled="transferForm.processing">Opslaan</PrimaryButton>
                    </div>
                </div>
            </form>
        </Modal>

        <!-- Clone modal -->
        <Modal :show="cloneModalOpen" @close="cloneModalOpen = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Transactie klonen</h2>
                <TextInput
                    ref="cloneSearchInput"
                    v-model="cloneSearch"
                    type="text"
                    class="mt-3 block w-full"
                    placeholder="Zoeken op omschrijving of categorie…"
                />
                <div class="mt-3 max-h-80 overflow-y-auto rounded border border-gray-100">
                    <button
                        v-for="tx in cloneCandidates"
                        :key="tx.id"
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50"
                        @click="pickClone(tx)"
                    >
                        <span class="truncate">{{ tx.description || '—' }} <span class="text-xs text-gray-500">· {{ tx.category?.name || 'Geen' }}</span></span>
                        <span class="ml-2 flex-shrink-0 font-medium" :class="parseFloat(tx.amount) >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ formatCurrency(tx.amount) }}
                        </span>
                    </button>
                    <div v-if="cloneCandidates.length === 0" class="px-3 py-4 text-center text-sm text-gray-500">
                        Geen resultaten.
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <SecondaryButton @click="cloneModalOpen = false">Sluiten</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Split modal -->
        <SplitTransactionModal
            :show="splitModalOpen"
            :transaction="splittingTransaction"
            :categories="categories"
            @close="closeSplit"
        />
    </AuthenticatedLayout>
</template>
